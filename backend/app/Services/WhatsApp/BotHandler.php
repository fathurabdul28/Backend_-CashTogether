<?php

namespace App\Services\WhatsApp;

use App\Models\Account;
use App\Models\Category;
use App\Models\SavingEntry;
use App\Models\SavingGoal;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Period;
use App\Support\Wallet;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Otak bot: pesan masuk (nomor + teks) -> teks balasan. Tidak tahu apa-apa soal HTTP. */
class BotHandler
{
    private const MAX_ITEMS = 10;

    public function __construct(private MessageParser $parser) {}

    public static function normalizePhone(string $raw): string
    {
        $d = preg_replace('/\D/', '', $raw);
        if (str_starts_with($d, '0')) return '62'.substr($d, 1);
        if (str_starts_with($d, '8')) return '62'.$d;
        return $d;
    }

    public function handle(string $sender, string $text): string
    {
        $phone = self::normalizePhone($sender);
        $text = trim($text);
        $cmd = mb_strtolower($text);

        if (preg_match('/^hubungkan\s+(\d{6})$/i', $text, $m)) return $this->link($phone, $m[1]);

        $user = User::where('phone', $phone)->first();
        if (! $user) {
            return "Nomor ini belum terhubung ke CashTogether.\nBuka app CashTogether → Pengaturan → Hubungkan WhatsApp, lalu kirim kode yang muncul ke sini (contoh: HUBUNGKAN 123456).";
        }
        $hid = $user->householdId();
        if (! $hid) return 'Kamu belum punya dompet bersama. Buka app CashTogether dulu untuk membuatnya atau bergabung.';

        if (in_array($cmd, ['bantuan', 'help', 'menu', '?'], true)) return $this->help();
        if (in_array($cmd, ['saldo', 'cek saldo'], true)) return 'Saldo kamu saat ini '.$this->idr(Wallet::balance($user->id, $hid)).'.';
        if (in_array($cmd, ['rekap', 'hari ini', 'laporan'], true)) return $this->recap($user, $hid);
        if (in_array($cmd, ['batal', 'undo', 'hapus'], true)) return $this->undo($user, $hid);

        if ($sections = (new SavingListParser)->parse($text, SavingGoal::where('household_id', $hid)->pluck('name')->all())) {
            return $this->recordSavingList($user, $hid, $sections);
        }

        $accounts = Account::where('household_id', $hid)->where('owner_id', $user->id)->where('archived', false)->get();
        [$account, $text] = $this->pickAccount($accounts, $text);
        if (! $account) return 'Kamu belum punya akun di CashTogether. Buka app dulu ya.';

        $items = $this->parser->parseMany($text);
        if (! $items) {
            return "Aku belum bisa membaca nominalnya.\nCoba tulis seperti ini:\n• pengeluaran 34 ribu untuk makan\n• beli bensin 50rb\n• gaji 10 juta\n• makan 25rb dan kopi 15rb (beberapa sekaligus)\nKetik BANTUAN untuk daftar perintah.";
        }
        if (count($items) > self::MAX_ITEMS) {
            return 'Maksimal '.self::MAX_ITEMS.' transaksi per pesan. Kirim dalam beberapa pesan ya.';
        }

        // Cari semua kategori dulu; kalau ada yang gagal, tidak ada yang tercatat
        $rows = [];
        $goals = SavingGoal::where('household_id', $hid)->orderBy('id')->get();
        foreach ($items as $p) {
            if ($p['saving'] === 'withdraw') {
                return 'Penarikan tabungan lewat app ya: menu Tabung → pilih tabungannya. Belum ada yang dicatat.';
            }
            if ($p['saving'] === 'deposit') {
                if ($goals->isEmpty()) {
                    return 'Belum ada tabungan. Buat dulu di app (menu Tabung), lalu kirim lagi. Belum ada yang dicatat.';
                }
                $goal = $this->pickGoal($goals, (string) $p['note']);
                if (! $goal) {
                    return "Mau nabung ke yang mana? Tulis namanya, contoh: nabung 1 juta {$goals[0]->name}\nPilihan: ".$goals->pluck('name')->implode(', ').".\nBelum ada yang dicatat.";
                }
                $category = Category::firstOrCreate(
                    ['household_id' => $hid, 'kind' => 'expense', 'name' => 'Tabungan'],
                    ['icon' => 'PiggyBank', 'color' => 'sage', 'sort_order' => 90],
                );
                $rows[] = [$p, $category, $goal];
                continue;
            }
            $category = Category::where('household_id', $hid)->where('kind', $p['type'])->where('name', $p['category'])->first()
                ?? Category::where('household_id', $hid)->where('kind', $p['type'])->where('name', 'Lainnya')->first();
            if (! $category) return 'Kategori tidak ditemukan. Coba lagi sebentar ya.';
            $rows[] = [$p, $category, null];
        }

        // Satu pesan = satu batch_id, supaya BATAL bisa membatalkan semua barang dari pesan itu sekaligus
        $batchId = (string) Str::ulid();
        DB::transaction(function () use ($rows, $hid, $account, $user, $batchId) {
            foreach ($rows as [$p, $category, $goal]) {
                Transaction::create([
                    'saving_goal_id' => $goal?->id,
                    'household_id' => $hid, 'type' => $p['type'], 'amount' => $p['amount'], 'category_id' => $category->id,
                    'account_id' => $account->id, 'occurred_at' => $p['date']->copy()->utc(), 'note' => $p['note'],
                    'source' => 'whatsapp', 'batch_id' => $batchId, 'created_by' => $user->id, 'paid_by' => $user->id,
                ]);
            }
        });

        $dateLabel = $this->dateLabel($rows[0][0]['date']);
        $balance = $this->idr(Wallet::balance($user->id, $hid));

        if (count($rows) === 1) {
            [$p, $category, $goal] = $rows[0];
            if ($goal) {
                return "✅ Nabung {$this->idr($p['amount'])} ke {$goal->name} dicatat pada {$dateLabel} (masuk pengeluaran & tabungan).\n"
                    .$this->progress($goal)."\n"
                    ."Akun: {$account->name} · Saldo kamu {$balance}.\n"
                    .'Salah? Balas BATAL.';
            }
            $label = $p['type'] === 'income' ? 'Pemasukan' : 'Pengeluaran';
            return "✅ {$label} {$this->idr($p['amount'])} untuk {$category->name} dicatat pada {$dateLabel}.\n"
                ."Akun: {$account->name} · Saldo kamu {$balance}.\n"
                .'Salah? Balas BATAL.';
        }

        $lines = [];
        foreach ($rows as $i => [$p, $category, $goal]) {
            if ($goal) {
                $lines[] = ($i + 1).". Nabung {$this->idr($p['amount'])} → {$goal->name}";
                continue;
            }
            $label = $p['type'] === 'income' ? 'Pemasukan' : 'Pengeluaran';
            $lines[] = ($i + 1).". {$label} {$this->idr($p['amount'])} · {$category->name}".($p['note'] ? " ({$p['note']})" : '');
        }
        foreach (collect($rows)->pluck(2)->filter()->unique('id') as $goal) $lines[] = $this->progress($goal);
        return '✅ Dicatat '.count($rows)." transaksi pada {$dateLabel}:\n".implode("\n", $lines)."\n"
            ."Akun: {$account->name} · Saldo kamu {$balance}.\n"
            .'Salah? Balas BATAL untuk membatalkan semuanya.';
    }

    /** Daftar tabungan (mis. seserahan): hanya menambah tabungan; saldo dan pengeluaran tidak berubah. */
    private function recordSavingList(User $user, int $hid, array $sections): string
    {
        if (collect($sections)->sum(fn ($s) => count($s['items'])) > 50) return 'Maksimal 50 baris per pesan. Kirim dalam beberapa pesan ya.';

        $batchId = (string) Str::ulid();
        $summary = DB::transaction(function () use ($user, $hid, $sections, $batchId) {
            $out = [];
            foreach ($sections as $s) {
                $key = SavingListParser::key($s['name']);
                $goal = SavingGoal::where('household_id', $hid)->get()->first(fn ($g) => SavingListParser::key($g->name) === $key);
                $new = ! $goal;
                $goal ??= SavingGoal::create([
                    'household_id' => $hid, 'created_by' => $user->id, 'name' => $s['name'], 'target_amount' => 0, // tanpa target
                    'icon' => str_contains($key, 'rumah') ? 'House' : (str_contains($key, 'seserahan') ? 'Heart' : 'PiggyBank'),
                    'color' => 'sage',
                ]);
                $added = 0; $skipped = 0;
                foreach ($s['items'] as $i) {
                    // pesan yang dikirim ulang tidak menggandakan baris yang sudah ada
                    $exists = SavingEntry::where('goal_id', $goal->id)->where('from_balance', false)
                        ->where('note', $i['name'])->where('amount', $i['amount'])->where('batch_id', '!=', $batchId)->exists();
                    if ($exists) { $skipped++; continue; }
                    SavingEntry::create([
                        'goal_id' => $goal->id, 'amount' => $i['amount'], 'from_balance' => false, 'gold_grams' => $i['grams'],
                        'batch_id' => $batchId, 'contributed_by' => $user->id, 'note' => $i['name'],
                        'occurred_at' => ($i['date'] ?? Carbon::now())->copy()->utc(),
                    ]);
                    $added++;
                }
                $out[] = [$goal, $new, $added, $skipped];
            }
            return $out;
        });

        $lines = [];
        foreach ($summary as [$goal, $new, $added, $skipped]) {
            $q = SavingEntry::where('goal_id', $goal->id);
            $grams = (float) (clone $q)->sum('gold_grams');
            $lines[] = "• {$goal->name}".($new ? ' (baru)' : '').": +{$added} rincian".($skipped ? ", {$skipped} sudah ada (dilewati)" : '')
                .'. Total '.$this->idr((int) $q->sum('amount')).($grams > 0 ? ' + '.$this->grams($grams).' emas' : '').'.';
        }
        return "✅ Tabungan diperbarui:\n".implode("\n", $lines)."\nSaldo dan pengeluaran tidak berubah. Salah? Balas BATAL.";
    }

    private function grams(float $g): string
    {
        return rtrim(rtrim(number_format($g, 2, ',', '.'), '0'), ',').' gram';
    }

    /** Tabungan yang namanya disebut; kalau cuma ada satu tabungan, pakai itu. Null = perlu ditanyakan. */
    private function pickGoal($goals, string $note): ?SavingGoal
    {
        $words = fn (string $x) => array_diff(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($x), -1, PREG_SPLIT_NO_EMPTY), ['tabungan', 'nabung', 'menabung', 'dana']);
        $said = $words($note);
        $scores = $goals->mapWithKeys(fn ($g) => [$g->id => count(array_intersect($words($g->name), $said))]);
        $best = $scores->max();
        if ($best > 0 && $scores->filter(fn ($s) => $s === $best)->count() === 1) return $goals->firstWhere('id', $scores->search($best));
        return $goals->count() === 1 ? $goals->first() : null;
    }

    private function progress(SavingGoal $g): string
    {
        $saved = (int) SavingEntry::where('goal_id', $g->id)->sum('amount');
        if ($g->target_amount <= 0) return "{$g->name}: terkumpul {$this->idr($saved)}.";
        $pct = $g->target_amount > 0 ? (int) floor($saved * 100 / $g->target_amount) : 0;
        return "{$g->name}: terkumpul {$this->idr($saved)} dari {$this->idr((int) $g->target_amount)} ({$pct}%).";
    }

    private function link(string $phone, string $code): string
    {
        $user = User::where('wa_link_code', $code)->where('wa_link_expires_at', '>', now())->first();
        if (! $user) return 'Kode tidak valid atau sudah kedaluwarsa. Buat kode baru di app CashTogether → Pengaturan → Hubungkan WhatsApp.';

        $other = User::where('phone', $phone)->where('id', '!=', $user->id)->first();
        if ($other) return 'Nomor ini sudah terhubung ke akun lain.';

        $user->forceFill(['phone' => $phone, 'wa_link_code' => null, 'wa_link_expires_at' => null])->save();
        return "Terhubung! Halo {$user->name}.\nSekarang kamu bisa mencatat lewat chat, contoh: pengeluaran 34 ribu untuk makan.\nKetik BANTUAN untuk daftar perintah.";
    }

    private function help(): string
    {
        return "Cara mencatat:\n• pengeluaran 34 ribu untuk makan\n• beli bensin 50rb kemarin\n• gaji 10 juta\n• nabung 1 juta (masuk pengeluaran & tabungan)\n• daftar tabungan: baris judul \"Seserahan:\" lalu \"1. Handuk : Rp 200.000\" (saldo tidak dipotong)\n• makan siang 25k pakai BCA\n• makan 25rb dan kopi 15rb (beberapa sekaligus)\n\nPerintah:\n• SALDO — cek saldo\n• REKAP — ringkasan hari ini & bulan ini\n• BATAL — hapus catatan terakhir dari WhatsApp";
    }

    private function recap(User $user, int $hid): string
    {
        \App\Services\RecurringIncomes::apply($user->id, $hid);
        $tz = Period::tz();
        $today = Carbon::now($tz);
        $cycle = Period::clampDay($user->cycle_start_day);
        $q = fn (Carbon $from, Carbon $to) => Transaction::where('household_id', $hid)->where('created_by', $user->id)
            ->whereBetween('occurred_at', [$from->copy()->utc(), $to->copy()->utc()])->get(['type', 'amount']);
        $d = $q($today->copy()->startOfDay(), $today->copy()->endOfDay());
        $key = Period::currentKey($cycle);
        $m = $q(...Period::local($key, $cycle));
        $name = $cycle <= 1 ? 'Bulan ini' : 'Periode ini ('.Period::label($key, $cycle).')';
        return "Hari ini: masuk {$this->idr((int) $d->where('type', 'income')->sum('amount'))}, keluar {$this->idr((int) $d->where('type', 'expense')->sum('amount'))}.\n"
            ."{$name}: masuk {$this->idr((int) $m->where('type', 'income')->sum('amount'))}, keluar {$this->idr((int) $m->where('type', 'expense')->sum('amount'))}.\n"
            ."Saldo kamu {$this->idr(Wallet::balance($user->id, $hid))}.";
    }

    private function undo(User $user, int $hid): string
    {
        $last = Transaction::where('household_id', $hid)->where('created_by', $user->id)->where('source', 'whatsapp')
            ->where('created_at', '>=', now()->subDay())->orderByDesc('id')->first();

        // daftar tabungan yang dikirim setelah transaksi terakhir dibatalkan lebih dulu (ULID urut waktu)
        $list = SavingEntry::whereIn('goal_id', SavingGoal::where('household_id', $hid)->pluck('id'))->where('contributed_by', $user->id)
            ->where('from_balance', false)->whereNotNull('batch_id')->where('created_at', '>=', now()->subDay())->orderByDesc('batch_id')->first();
        if ($list && (! $last || ($last->batch_id ? $list->batch_id > $last->batch_id : $list->created_at >= $last->created_at))) {
            return $this->undoSavingList($list->batch_id);
        }

        if (! $last) return 'Tidak ada catatan dari WhatsApp (24 jam terakhir) yang bisa dibatalkan.';

        // semua barang dari pesan yang sama berbagi batch_id (catatan lama tanpa batch_id dibatalkan satu per satu)
        $batch = $last->batch_id
            ? Transaction::where('household_id', $hid)->where('created_by', $user->id)->where('batch_id', $last->batch_id)->get()
            : collect([$last]);
        $batch->each->delete();
        $balance = $this->idr(Wallet::balance($user->id, $hid));

        if ($batch->count() === 1) {
            $kind = $last->type === 'income' ? 'Pemasukan' : 'Pengeluaran';
            return "Dibatalkan: {$kind} {$this->idr($last->amount)}. Saldo kamu {$balance}.";
        }
        $lines = $batch->sortBy('id')->map(fn ($t) => '• '.($t->type === 'income' ? 'Pemasukan' : 'Pengeluaran').' '.$this->idr($t->amount))->implode("\n");
        return "Dibatalkan {$batch->count()} transaksi dari pesan terakhir:\n{$lines}\nSaldo kamu {$balance}.";
    }

    private function undoSavingList(string $batchId): string
    {
        $entries = SavingEntry::where('batch_id', $batchId)->get();
        $goalIds = $entries->pluck('goal_id')->unique();
        $entries->each->delete();
        // tabungan tanpa target yang jadi kosong (dibuat oleh pesan itu) ikut dihapus
        SavingGoal::whereIn('id', $goalIds)->where('target_amount', 0)->get()
            ->reject(fn ($g) => SavingEntry::where('goal_id', $g->id)->exists())->each->delete();
        return "Dibatalkan {$entries->count()} rincian tabungan dari pesan terakhir. Saldo tidak berubah.";
    }

    /** Cari nama akun milik user di pesan ("pakai bca", "via gopay"), lalu buang dari teks. */
    private function pickAccount($accounts, string $text): array
    {
        foreach ($accounts as $a) {
            $re = '/(?:\b(?:pakai|pake|via|dari|dengan|dgn|lewat|ke)\s+)?(?<![\p{L}\p{N}])'.preg_quote($a->name, '/').'(?![\p{L}\p{N}])/iu';
            if (preg_match($re, $text) && mb_strtolower($a->name) !== 'tunai') {
                return [$a, trim(preg_replace($re, ' ', $text))];
            }
        }
        $cash = $accounts->firstWhere('type', 'cash') ?? $accounts->first();
        return [$cash, $text];
    }

    private function dateLabel(Carbon $d): string
    {
        $tz = config('services.fonnte.timezone', 'Asia/Jakarta');
        $today = Carbon::now($tz)->startOfDay();
        $full = $d->copy()->locale('id')->translatedFormat('j F Y');
        $diff = (int) $today->diffInDays($d->copy()->startOfDay(), false);
        return match (true) { $diff === 0 => "{$full} (hari ini)", $diff === -1 => "{$full} (kemarin)", default => $full };
    }

    private function idr(int $n): string
    {
        return ($n < 0 ? '-' : '').'Rp'.number_format(abs($n), 0, ',', '.');
    }
}
