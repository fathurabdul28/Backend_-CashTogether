# DECISIONS

Perubahan terhadap DESIGN_SPEC.md (spec awal memakai Supabase; diganti Laravel atas permintaan).

| Topik | Spec | Keputusan | Alasan |
|---|---|---|---|
| Backend | Supabase | **Laravel 13 + Sanctum** (token Bearer) | Permintaan pemilik |
| Database | Postgres Supabase | **TiDB Cloud Starter (MySQL-compatible, gratis)** di produksi; MySQL lokal untuk dev; SQLite in-memory untuk tes | Render tidak punya MySQL terkelola; TiDB cocok dengan driver MySQL. Koneksi wajib TLS (`MYSQL_ATTR_SSL_CA`). Belum diuji langsung ke TiDB |
| RLS | Policy Postgres | Middleware `household` + semua query diberi `where household_id`; ID referensi (akun, kategori) divalidasi milik dompet yang sama. Dites di `tests/Feature/CashTogetherTest.php` | Laravel tidak memakai RLS |
| Realtime | Supabase Realtime + Presence | **Polling** `GET /api/pulse` tiap ±3 dtk saat app aktif; klien invalidate cache bila stempel berubah | Dipilih pemilik; hosting gratis tidur saat idle sehingga WebSocket tidak cocok. Presence (titik online) ditunda |
| ID | UUID | bigint auto-increment | Lebih sederhana; klien tak butuh ID offline |
| Tabel `profiles` | terpisah | kolom `gender`, `identity_override` ditambah ke `users` | Lebih sederhana |
| Satu dompet per user | tidak disebut | `household_members.user_id` unik | Mencegah ambiguitas dompet aktif |
| Saldo | view `account_balances` | dihitung di PHP (`AccountController`, `ReportController`) | Portabel SQLite/Postgres; data 2 orang kecil |
| Saldo | satu dompet bersama | **Pribadi per orang**: akun, transaksi, anggaran, ringkasan, dan laporan hanya terlihat oleh pemiliknya. Hanya **tabungan** (dan kategori) yang bersama | Permintaan pemilik (19 Nov 2026). Dibatasi di controller lewat `created_by` / `owner_id` / `user_id` |
| Setoran tabungan | transfer ke tabungan | Setoran mengurangi saldo pribadi si penyetor, penarikan menambahnya | Uang tabungan datang dari saldo orang yang menyetor |
| Transfer | ada | **Dihapus** (tipe transaksi hanya `income`/`expense`; baris transfer lama dihapus lewat migrasi). Kolom `to_account_id` dibiarkan tak terpakai | Tidak ada lagi uang pindah antar akun bersama |
| Anggaran | per dompet | per orang (`budgets.user_id`) | Mengikuti saldo pribadi |
| Akun | satu akun Tunai bersama | tiap anggota dapat akun "Tunai" sendiri saat membuat/bergabung dompet | |
| Frontend | Expo | Expo (React Native) di `app/` | Sesuai spec |

## Deploy gratis
- API: Render free web service (Docker, `render.yaml`). Tidur setelah ±15 mnt idle; request pertama ±30–60 dtk. Alternatif tanpa tidur: Koyeb/Fly (butuh kartu).
- DB: TiDB Cloud Starter. Isi `DB_HOST`/`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` di Render.
- Android: build APK gratis lewat EAS (`eas build -p android --profile preview`).
- Web: `npx expo export -p web` → Cloudflare Pages / Vercel.
