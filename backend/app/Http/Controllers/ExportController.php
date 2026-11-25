<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\HouseholdMember;
use App\Models\SavingEntry;
use App\Models\SavingGoal;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Period;
use Carbon\Carbon;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Ekspor ke Excel (.xlsx; bisa dibuka juga di Google Sheets, Numbers, LibreOffice).
 * Alur: app meminta tautan sementara bertanda tangan (5 menit) lalu membukanya di browser/sistem,
 * sehingga unduhan jalan di web, iPhone, dan Android tanpa mengirim token lewat header.
 * Hanya data milik pemohon: transaksi pribadi + tabungan bersama.
 */
class ExportController extends Controller
{
    private const MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function link(Request $r)
    {
        $r->validate(['scope' => 'nullable|in:period,all']);
        $all = $r->query('scope') === 'all';
        $key = $all ? null : $this->periodKey($r);
        $url = URL::temporarySignedRoute('export.download', now()->addMinutes(5),
            ['u' => $r->user()->id] + ($all ? ['scope' => 'all'] : ['month' => $key]));
        return ['url' => $url, 'filename' => $this->filename($key, $this->cycleDay($r)), 'expires_in' => 300];
    }

    /** Rute bertanda tangan: tidak memakai login, keabsahannya dari signature. */
    public function download(Request $r)
    {
        $user = User::findOrFail((int) $r->query('u'));
        $hid = $user->householdId();
        abort_if(! $hid, 404);
        $cycle = Period::clampDay($user->cycle_start_day);
        $key = $r->query('scope') === 'all' ? null : (string) $r->query('month');
        abort_if($key !== null && ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $key), 422);

        $path = tempnam(sys_get_temp_dir(), 'ct_').'.xlsx';
        $this->build($user, $hid, $key, $cycle, $path);
        return response()->download($path, $this->filename($key, $cycle), ['Content-Type' => self::MIME])->deleteFileAfterSend(true);
    }

    private function filename(?string $key, int $cycle): string
    {
        if ($key === null) return 'CashTogether_semua-data.xlsx';
        [$s, $e] = Period::local($key, $cycle);
        return 'CashTogether_'.$s->format('Y-m-d').'_sd_'.$e->format('Y-m-d').'.xlsx';
    }

    private function build(User $user, int $hid, ?string $key, int $cycle, string $path): void
    {
        $tz = Period::tz();
        $money = '#,##0';
        $head = new Style(fontBold: true, fontColor: Color::WHITE, backgroundColor: Color::rgb(31, 78, 140));
        $bold = new Style(fontBold: true);
        $num = new Style(format: $money);
        $numBold = new Style(fontBold: true, format: $money);
        $date = new Style(format: 'dd/mm/yyyy', cellAlignment: CellAlignment::LEFT);
        $pct = new Style(format: '0%');
        $row = fn (array $cells) => new Row(array_map(fn ($c) => $c instanceof Cell ? $c : Cell::fromValue($c), $cells));
        $headerRow = fn (array $labels) => new Row(array_map(fn ($l) => Cell::fromValue($l, $head), $labels));

        $q = Transaction::where('household_id', $hid)->where('created_by', $user->id);
        if ($key !== null) $q->whereBetween('occurred_at', Period::utc($key, $cycle));
        $txs = $q->orderBy('occurred_at')->orderBy('id')->get();
        $cats = Category::where('household_id', $hid)->get()->keyBy('id');
        $accs = Account::where('household_id', $hid)->where('owner_id', $user->id)->get()->keyBy('id');

        $writer = new Writer(new Options());
        $writer->openToFile($path);

        // ---- Sheet 1: Transaksi ----
        $sheet = $writer->getCurrentSheet()->setName('Transaksi');
        $sheet->setColumnWidth(13, 1);
        $sheet->setColumnWidth(8, 2);
        $sheet->setColumnWidth(13, 3);
        $sheet->setColumnWidth(22, 4);
        $sheet->setColumnWidth(16, 5);
        $sheet->setColumnWidth(16, 6);
        $sheet->setColumnWidth(38, 7);
        $sheet->setColumnWidth(11, 8);
        $writer->addRow($headerRow(['Tanggal', 'Jam', 'Jenis', 'Kategori', 'Akun', 'Nominal (Rp)', 'Catatan', 'Sumber']));
        foreach ($txs as $t) {
            $local = $t->occurred_at->copy()->setTimezone($tz);
            $writer->addRow($row([
                Cell::fromValue(new DateTimeImmutable($local->format('Y-m-d')), $date),
                $local->format('H:i'),
                $t->type === 'income' ? 'Pemasukan' : 'Pengeluaran',
                $cats[$t->category_id]->name ?? 'Lainnya',
                $accs[$t->account_id]->name ?? '-',
                Cell::fromValue((int) $t->amount, $num),
                $t->note ?? '',
                $t->source === 'whatsapp' ? 'WhatsApp' : 'App',
            ]));
        }

        // ---- Sheet 2: Ringkasan ----
        $inc = (int) $txs->where('type', 'income')->sum('amount');
        $exp = (int) $txs->where('type', 'expense')->sum('amount');
        $writer->addNewSheetAndMakeItCurrent()->setName('Ringkasan');
        $writer->getCurrentSheet()->setColumnWidth(26, 1);
        $writer->getCurrentSheet()->setColumnWidth(20, 2);
        $writer->getCurrentSheet()->setColumnWidth(12, 3);
        $writer->addRow($row([Cell::fromValue('Periode', $bold), $key === null ? 'Semua data' : Period::label($key, $cycle)]));
        $writer->addRow($row([Cell::fromValue('Dibuat', $bold), Carbon::now($tz)->locale('id')->translatedFormat('j F Y H:i')]));
        $writer->addRow($row([Cell::fromValue('Pemilik', $bold), $user->name]));
        $writer->addRow($row(['']));
        $writer->addRow($row([Cell::fromValue('Total pemasukan', $bold), Cell::fromValue($inc, $numBold)]));
        $writer->addRow($row([Cell::fromValue('Total pengeluaran', $bold), Cell::fromValue($exp, $numBold)]));
        $writer->addRow($row([Cell::fromValue('Selisih', $bold), Cell::fromValue($inc - $exp, $numBold)]));
        $writer->addRow($row(['']));
        $writer->addRow($headerRow(['Pengeluaran per kategori', 'Total (Rp)', 'Porsi']));
        foreach ($txs->where('type', 'expense')->groupBy('category_id')->map(fn ($g) => (int) $g->sum('amount'))->sortDesc() as $cid => $sum) {
            $writer->addRow($row([$cats[$cid]->name ?? 'Lainnya', Cell::fromValue($sum, $num), Cell::fromValue($exp ? $sum / $exp : 0, $pct)]));
        }

        // ---- Sheet 3: Tabungan (bersama) ----
        $writer->addNewSheetAndMakeItCurrent()->setName('Tabungan');
        foreach ([1 => 24, 2 => 16, 3 => 18, 4 => 18, 5 => 11, 6 => 18, 7 => 18, 8 => 14] as $col => $w) $writer->getCurrentSheet()->setColumnWidth($w, $col);
        $partner = $this->partner($hid, $user->id);
        $writer->addRow($headerRow(['Target', 'Tanggal target', 'Nominal target (Rp)', 'Terkumpul (Rp)', 'Progres', 'Setoran kamu (Rp)', 'Setoran '.($partner->name ?? 'pasangan').' (Rp)', 'Emas (gram)']));
        $goals = SavingGoal::where('household_id', $hid)->get();
        $entries = SavingEntry::whereIn('goal_id', $goals->pluck('id'))->get()->groupBy('goal_id');
        foreach ($goals as $g) {
            $e = $entries->get($g->id, collect());
            $saved = (int) $e->sum('amount');
            $own = $e->reject->is_conversion;
            $writer->addRow($row([
                $g->name,
                $g->target_date ? Cell::fromValue(new DateTimeImmutable($g->target_date->format('Y-m-d')), $date) : '',
                Cell::fromValue((int) $g->target_amount, $num),
                Cell::fromValue($saved, $num),
                Cell::fromValue($g->target_amount ? $saved / $g->target_amount : 0, $pct),
                Cell::fromValue((int) $own->where('contributed_by', $user->id)->sum('amount'), $num),
                Cell::fromValue($partner ? (int) $own->where('contributed_by', $partner->id)->sum('amount') : 0, $num),
                (float) $e->sum('gold_grams') ?: '',
            ]));
        }
        $writer->close();
    }

    /** Anggota lain di dompet yang sama (atau null). */
    private function partner(int $hid, int $userId): ?User
    {
        $id = HouseholdMember::where('household_id', $hid)->where('user_id', '!=', $userId)->value('user_id');
        return $id ? User::find($id) : null;
    }
}
