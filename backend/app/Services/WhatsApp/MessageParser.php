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
    private const FILLERS = ['untuk', 'buat', 'utk', 'dari', 'di', 'ke', 'rp', 'sebesar', 'senilai', 'tadi', 'hari ini', 'barusan', 'saya', 'aku', 'gue', 'gua', 'gw', 'udah', 'sudah'];
    private const MAX = 999_999_999_999;

    /** Pola nominal berurutan menurut posisi: 34 ribu | 1,5 juta | rp 2jt | rp 34.000 | 34.000 | 34000 */
    private const AMOUNT_RE = '/(?:rp\.?\s*)?\d+(?:[.,]\d+)?\s*(?:juta|jt|ribu|rb|k)(?![\p{L}\p{N}])|rp\.?\s*(?:\d{1,3}(?:[.,]\d{3})+|\d+)|(?<![\d.]|\d,)\d{1,3}(?:\.\d{3})+(?![\d.]|,\d)|(?<![\d.]|\d,)\d{4,}(?![\d.]|,\d)/u';

    private const EXPENSE_CATEGORIES = [
        'Makan & Minum' => ['makan', 'minum', 'sarapan', 'kopi', 'bakso', 'nasi', 'ayam', 'mie', 'mi', 'jajan', 'resto', 'restoran', 'warteg', 'es teh', 'snack', 'cemilan', 'gofood', 'grabfood', 'kafe', 'cafe', 'roti', 'martabak', 'gorengan', 'bubur', 'soto', 'sate', 'pizza', 'burger', 'kebab', 'seblak', 'siomay', 'batagor', 'geprek', 'makanan', 'minuman', 'sushi', 'steak', 'boba', 'teh', 'susu', 'jus'],
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

    /** @return array{type:string, amount:int, category:string, note:?string, date:Carbon, saving:?string}|null */
    public function parse(string $text, ?Carbon $now = null): ?array
    {
        $now ??= $this->now();
        $t = $this->normalize($text);

        [$amount, $t] = $this->extractAmount($t);
        if (! $amount || $amount > self::MAX) return null;

        [$date, $t] = $this->extractDate($t, $now);
        return $this->item($t, (int) $amount, $date, null);
    }

    /**
     * Satu pesan boleh memuat beberapa barang: "makan ayam geprek 25 ribu dan minum kopi 80 ribu".
     * Tanggal (kemarin, 3 hari lalu) dan kata "pemasukan"/"pengeluaran" berlaku untuk semua barang.
     * Pesan dengan satu nominal diproses persis seperti parse().
     *
     * @return list<array{type:string, amount:int, category:string, note:?string, date:Carbon}>
     */
    public function parseMany(string $text, ?Carbon $now = null): array
    {
        $now ??= $this->now();
        $t = $this->normalize($text);

        [$date, $t] = $this->extractDate($t, $now);
        $forced = null;
        foreach (['pemasukan' => 'income', 'pengeluaran' => 'expense'] as $w => $ty) {
            if ($this->has($t, $w)) { $forced = $ty; $t = $this->removeWord($t, $w); break; }
        }

        $segments = $this->segments($t);
        if (count($segments) < 2) {
            $one = $this->parse($text, $now);
            return $one ? [$one] : [];
        }

        $items = [];
        foreach ($segments as $seg) {
            [$amount, $rest] = $this->extractAmount(' '.$seg.' ');
            if (! $amount || $amount > self::MAX) continue;
            $items[] = $this->item($rest, (int) $amount, $date, $forced);
        }
        return $items;
    }

    /** "nabung 1 juta" → 'deposit'; "ambil tabungan 500rb" → 'withdraw'; selain itu null. "tabung gas" bukan tabungan. */
    private function saving(string $t): ?string
    {
        if (preg_match('/(?<![\p{L}])(?:ambil|tarik|cairkan|pakai|pake)\s+(?:(?:uang|duit|dari)\s+)*tabungan(?![\p{L}])/u', $t)) return 'withdraw';
        if ($this->has($t, 'bunga')) return null;
        $deposit = '/(?<![\p{L}])(?:nabung|menabung|ditabung|nabungin|tabungin|(?:setor|isi|ke|masuk|buat|untuk)\s+tabungan)(?![\p{L}])|^\s*tabungan(?![\p{L}])/u';
        return preg_match($deposit, $t) ? 'deposit' : null;
    }

    /** @return array{type:string, amount:int, category:string, note:?string, date:Carbon, saving:?string} */
    private function item(string $t, int $amount, Carbon $date, ?string $forcedType): array
    {
        // Jenis: kata eksplisit dulu, kalau tidak ada tebak dari kata kunci
        $type = $forcedType;
        if ($type === null) {
            foreach (['pemasukan' => 'income', 'pengeluaran' => 'expense'] as $w => $ty) {
                if ($this->has($t, $w)) { $type = $ty; break; }
            }
        }
        $type ??= $this->hasAny($t, self::INCOME_WORDS) && ! $this->hasAny($t, ['keluar']) ? 'income' : null;
        $type ??= 'expense';

        $category = $this->category($t, $type) ?? 'Lainnya';

        $saving = $this->saving($t);
        if ($saving === 'deposit') { $type = 'expense'; $category = 'Tabungan'; }

        // Catatan: sisa kalimat tanpa kata jenis & kata sambung
        $note = $t;
        foreach (array_merge(['pemasukan', 'pengeluaran', 'masuk', 'keluar'], self::FILLERS) as $w) {
            $note = $this->removeWord($note, $w);
        }
        $note = trim(preg_replace('/\s+/u', ' ', $note));
        $note = $note !== '' ? mb_substr($note, 0, 200) : null;

        return ['type' => $type, 'amount' => $amount, 'category' => $category, 'note' => $note, 'date' => $date, 'saving' => $saving];
    }

    /**
     * Memecah teks menjadi satu potongan per nominal. Pemisah: koma (bukan koma desimal), titik koma, baris baru,
     * "dan", "&", "lalu", "terus", "serta", "plus". Tanpa pemisah, dipotong tepat setelah tiap nominal.
     *
     * @return list<string> satu elemen bila nominal kurang dari dua
     */
    private function segments(string $t): array
    {
        $amounts = $this->findAmounts($t);
        if (count($amounts) < 2) return [$t];

        $clean = fn (string $x) => trim(preg_replace('/^(?:[\s,;:+&-]+|(?:dan|lalu|terus|serta|plus)\s+)+/u', '', trim($x)));

        $parts = preg_split('/\s*(?:;|\n|(?<!\d),|,(?!\d)|\s&\s|\s(?:dan|lalu|terus|serta|plus)\s)\s*/u', $t) ?: [$t];
        $parts = array_values(array_filter(array_map($clean, $parts), fn ($x) => $x !== ''));
        if (count($parts) >= 2 && count(array_filter($parts, fn ($x) => count($this->findAmounts($x)) === 1)) === count($parts)) {
            return $parts;
        }

        // cadangan: potong tepat setelah tiap nominal; sisa kalimat ikut potongan terakhir
        $out = [];
        $cursor = 0;
        $n = count($amounts);
        foreach ($amounts as $i => [$off, $len]) {
            $end = $i === $n - 1 ? strlen($t) : $off + $len;
            $out[] = $clean(substr($t, $cursor, $end - $cursor));
            $cursor = $off + $len;
        }
        return array_values(array_filter($out, fn ($x) => $x !== ''));
    }

    /** @return list<array{int,int}> [posisi byte, panjang] tiap nominal; angka polos mirip tahun (1990–2100) diabaikan */
    private function findAmounts(string $t): array
    {
        preg_match_all(self::AMOUNT_RE, $t, $m, PREG_OFFSET_CAPTURE);
        $out = [];
        foreach ($m[0] as [$text, $off]) {
            if (preg_match('/^\d{4}$/', $text) && (int) $text >= 1990 && (int) $text <= 2100) continue;
            $out[] = [$off, strlen($text)];
        }
        return $out;
    }

    private function normalize(string $text): string
    {
        return ' '.mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text))).' ';
    }

    private function now(): Carbon
    {
        return Carbon::now(config('services.fonnte.timezone', 'Asia/Jakarta'));
    }

    private function removeWord(string $t, string $w): string
    {
        return preg_replace('/(?<![\p{L}\p{N}])'.preg_quote($w, '/').'(?![\p{L}\p{N}])/u', ' ', $t);
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
        if (preg_match('/(?<![\d.]|\d,)(\d{1,3}(?:\.\d{3})+)(?![\d.]|,\d)/u', $t, $m)) {
            return [(int) str_replace('.', '', $m[1]), str_replace($m[0], ' ', $t)];
        }
        // 34000 (minimal 4 digit supaya "2 kopi" tidak dibaca sebagai nominal)
        if (preg_match('/(?<![\d.]|\d,)(\d{4,})(?![\d.]|,\d)/u', $t, $m)) {
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
