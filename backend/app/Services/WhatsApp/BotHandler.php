<?php

namespace App\Services\WhatsApp;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Wallet;
use Carbon\Carbon;

/** Otak bot: pesan masuk (nomor + teks) -> teks balasan. Tidak tahu apa-apa soal HTTP. */
class BotHandler
{
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

        $accounts = Account::where('household_id', $hid)->where('owner_id', $user->id)->where('archived', false)->get();
        [$account, $text] = $this->pickAccount($accounts, $text);
        if (! $account) return 'Kamu belum punya akun di CashTogether. Buka app dulu ya.';

        $p = $this->parser->parse($text);
        if (! $p) {
            return "Aku belum bisa membaca nominalnya.\nCoba tulis seperti ini:\n• pengeluaran 34 ribu untuk makan\n• beli bensin 50rb\n• gaji 10 juta\nKetik BANTUAN untuk daftar perintah.";
        }

        $category = Category::where('household_id', $hid)->where('kind', $p['type'])->where('name', $p['category'])->first()
            ?? Category::where('household_id', $hid)->where('kind', $p['type'])->where('name', 'Lainnya')->first();
        if (! $category) return 'Kategori tidak ditemukan. Coba lagi sebentar ya.';

        Transaction::create([
            'household_id' => $hid, 'type' => $p['type'], 'amount' => $p['amount'], 'category_id' => $category->id,
            'account_id' => $account->id, 'occurred_at' => $p['date']->copy()->utc(), 'note' => $p['note'],
            'source' => 'whatsapp', 'created_by' => $user->id, 'paid_by' => $user->id,
        ]);

        $label = $p['type'] === 'income' ? 'Pemasukan' : 'Pengeluaran';
        $dateLabel = $this->dateLabel($p['date']);
        return "✅ {$label} {$this->idr($p['amount'])} untuk {$category->name} dicatat pada {$dateLabel}.\n"
            ."Akun: {$account->name} · Saldo kamu {$this->idr(Wallet::balance($user->id, $hid))}.\n"
            .'Salah? Balas BATAL.';
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
        return "Cara mencatat:\n• pengeluaran 34 ribu untuk makan\n• beli bensin 50rb kemarin\n• gaji 10 juta\n• makan siang 25k pakai BCA\n\nPerintah:\n• SALDO — cek saldo\n• REKAP — ringkasan hari ini & bulan ini\n• BATAL — hapus catatan terakhir dari WhatsApp";
    }

    private function recap(User $user, int $hid): string
    {
        $tz = config('services.fonnte.timezone', 'Asia/Jakarta');
        $today = Carbon::now($tz);
        $q = fn (Carbon $from, Carbon $to) => Transaction::where('household_id', $hid)->where('created_by', $user->id)
            ->whereBetween('occurred_at', [$from->copy()->utc(), $to->copy()->utc()])->get(['type', 'amount']);
        $d = $q($today->copy()->startOfDay(), $today->copy()->endOfDay());
        $m = $q($today->copy()->startOfMonth(), $today->copy()->endOfMonth());
        return "Hari ini: masuk {$this->idr((int) $d->where('type', 'income')->sum('amount'))}, keluar {$this->idr((int) $d->where('type', 'expense')->sum('amount'))}.\n"
            ."Bulan ini: masuk {$this->idr((int) $m->where('type', 'income')->sum('amount'))}, keluar {$this->idr((int) $m->where('type', 'expense')->sum('amount'))}.\n"
            ."Saldo kamu {$this->idr(Wallet::balance($user->id, $hid))}.";
    }

    private function undo(User $user, int $hid): string
    {
        $tx = Transaction::where('household_id', $hid)->where('created_by', $user->id)->where('source', 'whatsapp')
            ->where('created_at', '>=', now()->subDay())->orderByDesc('id')->first();
        if (! $tx) return 'Tidak ada catatan dari WhatsApp (24 jam terakhir) yang bisa dibatalkan.';
        $tx->delete();
        $kind = $tx->type === 'income' ? 'Pemasukan' : 'Pengeluaran';
        return "Dibatalkan: {$kind} {$this->idr($tx->amount)}. Saldo kamu {$this->idr(Wallet::balance($user->id, $hid))}.";
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
