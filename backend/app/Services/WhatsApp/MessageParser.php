<?php

namespace App\Services\WhatsApp;

use Carbon\Carbon;

/**
 * Parser kalimat bebas -> transaksi, murni aturan (tanpa AI).
 * "pengeluaran 34 ribu untuk makan" => expense, 34000, Makan & Minum.
 */
class MessageParser
{
    private const INCOME_WORDS = ['pemasukan', 'masuk', 'terima', 'diterima', 'dapat', 'dapet', 'income', 'pendapatan', 'gaji', 'bonus', 'thr', 'jualan', 'omzet', 'dividen'];
    private const EXPENSE_WORDS = ['pengeluaran', 'keluar', 'beli', 'bayar', 'jajan', 'bayarin', 'traktir', 'belanja', 'habis'];
    private const FILLERS = ['untuk', 'buat', 'utk', 'dari', 'di', 'ke', 'rp', 'sebesar', 'senilai', 'tadi', 'hari ini', 'barusan'];

    private const EXPENSE_CATEGORIES = [
        'Makan & Minum' => ['makan', 'minum', 'sarapan', 'kopi', 'bakso', 'nasi', 'ayam', 'mie', 'mi', 'jajan', 'resto', 'restoran', 'warteg', 'es teh', 'snack', 'cemilan', 'gofood', 'grabfood', 'kafe', 'cafe'],
        'Belanja Harian' => ['belanja harian', 'sayur', 'beras', 'pasar', 'sembako', 'indomaret', 'alfamart', 'minyak', 'telur', 'gula', 'belanja bulanan', 'belanja dapur', 'belanja'],
        'Transportasi' => ['bensin', 'bbm', 'pertalite', 'ojek', 'ojol', 'grab', 'gojek', 'parkir', 'tol', 'transport', 'angkot', 'busway', 'krl', 'kereta', 'taksi'],
        'Tagihan & Listrik' => ['listrik', 'pln', 'token listrik', 'air', 'pdam', 'tagihan', 'gas'],
        'Rumah' => ['kontrakan', 'sewa', 'kos', 'kost', 'rumah', 'perabot', 'renovasi', 'ipl'],
        'Kesehatan' => ['obat', 'dokter', 'klinik', 'rumah sakit', 'vitamin', 'apotek', 'bpjs', 'periksa'],
        'Hiburan' => ['nonton', 'bioskop', 'game', 'hiburan', 'netflix', 'spotify', 'liburan', 'wisata', 'karaoke'],
        'Pendidikan' => ['sekolah', 'kursus', 'buku', 'spp', 'les', 'kuliah', 'pendidikan'],
        'Pulsa & Internet' => ['pulsa', 'internet', 'wifi', 'paket data', 'kuota'],
        'Belanja' => ['baju', 'sepatu', 'tas', 'fashion', 'online shop', 'shopee', 'tokopedia', 'lazada', 'elektronik'],
        'Hadiah & Sosial' => ['kado', 'hadiah', 'sumbangan', 'donasi', 'zakat', 'sedekah', 'infaq', 'arisan', 'amplop'],
        'Cicilan' => ['cicilan', 'kredit', 'angsuran', 'paylater', 'kartu kredit'],
    ];
    private const INCOME_CATEGORIES = [
        'Gaji' => ['gaji', 'salary', 'upah'],
        'Bonus' => ['bonus', 'thr', 'insentif'],
        'Usaha' => ['usaha', 'jualan', 'dagang', 'omzet', 'untung', 'laba', 'jasa', 'freelance'],
        'Investasi' => ['investasi', 'dividen', 'saham', 'bunga', 'reksadana', 'crypto'],
        'Hadiah' => ['hadiah', 'kado', 'angpao'],
    ];

    /** @return array{type:string, amount:int, category:string, note:?string, date:Carbon}|null */
    public function parse(string $text, ?Carbon $now = null): ?array
    {
        $now ??= Carbon::now(config('services.fonnte.timezone', 'Asia/Jakarta'));
        $t = ' '.mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text))).' ';

        [$amount, $t] = $this->extractAmount($t);
        if (! $amount || $amount > 999_999_999_999) return null;

        [$date, $t] = $this->extractDate($t, $now);

        // Jenis: kata eksplisit dulu, kalau tidak ada tebak dari kata kunci
        $type = null;
        foreach (['pemasukan' => 'income', 'pengeluaran' => 'expense'] as $w => $ty) {
            if ($this->has($t, $w)) { $type = $ty; break; }
        }
        $type ??= $this->hasAny($t, self::INCOME_WORDS) && ! $this->hasAny($t, ['keluar']) ? 'income' : null;
        $type ??= 'expense';

        $category = $this->category($t, $type) ?? 'Lainnya';

        // Catatan: sisa kalimat tanpa kata jenis & kata sambung
        $note = $t;
        foreach (array_merge(['pemasukan', 'pengeluaran', 'masuk', 'keluar'], self::FILLERS) as $w) {
            $note = preg_replace('/(?<![\p{L}\p{N}])'.preg_quote($w, '/').'(?![\p{L}\p{N}])/u', ' ', $note);
        }
        $note = trim(preg_replace('/\s+/u', ' ', $note));
        $note = $note !== '' ? mb_substr($note, 0, 200) : null;

        return ['type' => $type, 'amount' => (int) $amount, 'category' => $category, 'note' => $note, 'date' => $date];
    }

    /** @return array{?int, string} nominal dan teks tanpa bagian nominal */
    private function extractAmount(string $t): array
    {
        $mult = ['juta' => 1_000_000, 'jt' => 1_000_000, 'ribu' => 1_000, 'rb' => 1_000, 'k' => 1_000];

        // 34 ribu | 34rb | 34k | 1,5 juta | rp 2jt
        if (preg_match('/(?:rp\.?\s*)?(\d+(?:[.,]\d+)?)\s*(juta|jt|ribu|rb|k)(?![\p{L}\p{N}])/u', $t, $m)) {
            $n = (float) str_replace(',', '.', $m[1]);
            return [(int) round($n * $mult[$m[2]]), str_replace($m[0], ' ', $t)];
        }
        // rp 34.000 | rp34000
        if (preg_match('/rp\.?\s*(\d{1,3}(?:[.,]\d{3})+|\d+)/u', $t, $m)) {
            return [(int) preg_replace('/\D/', '', $m[1]), str_replace($m[0], ' ', $t)];
        }
        // 34.000 | 1.250.000
        if (preg_match('/(?<![\d.,])(\d{1,3}(?:\.\d{3})+)(?![\d.,])/u', $t, $m)) {
            return [(int) str_replace('.', '', $m[1]), str_replace($m[0], ' ', $t)];
        }
        // 34000 (minimal 4 digit supaya "2 kopi" tidak dibaca sebagai nominal)
        if (preg_match('/(?<![\d.,])(\d{4,})(?![\d.,])/u', $t, $m)) {
            return [(int) $m[1], str_replace($m[0], ' ', $t)];
        }
        return [null, $t];
    }

    /** @return array{Carbon, string} */
    private function extractDate(string $t, Carbon $now): array
    {
        if (preg_match('/(?<![\p{L}\p{N}])(\d{1,2}) hari (?:yang )?lalu(?![\p{L}\p{N}])/u', $t, $m)) {
            return [$now->copy()->subDays(min((int) $m[1], 60))->setTime(12, 0), str_replace($m[0], ' ', $t)];
        }
        if (preg_match('/(?<![\p{L}\p{N}])kemarin(?![\p{L}\p{N}])/u', $t, $m)) {
            return [$now->copy()->subDay()->setTime(12, 0), str_replace($m[0], ' ', $t)];
        }
        return [$now->copy(), $t];
    }

    private function category(string $t, string $type): ?string
    {
        // Frasa yang lebih panjang dicoba lebih dulu supaya "belanja harian" menang atas "belanja"
        $map = $type === 'income' ? self::INCOME_CATEGORIES : self::EXPENSE_CATEGORIES;
        $best = null; $bestLen = 0;
        foreach ($map as $name => $words) {
            foreach ($words as $w) {
                if ($this->has($t, $w) && mb_strlen($w) > $bestLen) { $best = $name; $bestLen = mb_strlen($w); }
            }
        }
        return $best;
    }

    private function has(string $t, string $word): bool
    {
        return (bool) preg_match('/(?<![\p{L}\p{N}])'.preg_quote($word, '/').'(?![\p{L}\p{N}])/u', $t);
    }

    private function hasAny(string $t, array $words): bool
    {
        foreach ($words as $w) if ($this->has($t, $w)) return true;
        return false;
    }
}
