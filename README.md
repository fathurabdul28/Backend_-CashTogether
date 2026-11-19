# CashTogether

Pencatatan keuangan berdua. `backend/` = Laravel API, `app/` = Expo (React Native, Android/iOS/Web). Keputusan teknis: `DECISIONS.md`.

## Jalankan lokal
```bash
# 1) API
cd backend && touch database/database.sqlite && php artisan migrate && php artisan serve   # http://localhost:8000
# 2) App
cd app && npm install && cp .env.example .env && npx expo start      # tekan w (web) / a (Android) / i (iOS)
```
HP fisik: ganti `EXPO_PUBLIC_API_URL` ke IP laptop (mis. `http://192.168.1.5:8000/api`) lalu `php artisan serve --host=0.0.0.0`.
Android emulator: `http://10.0.2.2:8000/api`.

Tes: `cd backend && php artisan test` · Typecheck: `cd app && npm run typecheck`.

## Deploy gratis
Alur produksi: Fonnte → `https://<app>.onrender.com/api/whatsapp/fonnte/<rahasia>` → Laravel di Render → TiDB Cloud. Laptop dan cloudflared tidak dipakai.
1. **Database**: tidbcloud.com → buat cluster **Starter** (gratis) → *Connect* → catat host, port (4000), user, password, nama database. Kuota gratis: 5 GiB baris dan 50 juta RU per bulan.
2. **API**: render.com → New → Blueprint → pilih repo (`render.yaml`). Isi variabel `sync: false`: `APP_KEY` (`php artisan key:generate --show`), `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `FONNTE_*`. Migrasi berjalan otomatis saat start. Cek `https://<app>.onrender.com/up`.
3. **Agar tidak tidur**: UptimeRobot / cron-job.org → ping `https://<app>.onrender.com/up` tiap 5 menit.
4. **Fonnte**: ganti URL webhook ke alamat Render.
5. **Web (PWA)**: Render → New → Static Site → repo ini. Root Directory `app`, Build Command `npm ci && npx expo export -p web`, Publish Directory `dist`, env `EXPO_PUBLIC_API_URL=https://<app>.onrender.com/api` dan `NODE_VERSION=22`. Tab Redirects/Rewrites: `/*` → `/index.html` (Rewrite). Di iPhone: buka alamat situs di Safari → Bagikan → Tambah ke Layar Utama.
6. **Android**: isi URL di `app/eas.json` lalu `npx eas-cli build -p android --profile preview`.
Catatan: Render gratis tidur setelah 15 menit tanpa lalu lintas; penyimpanan filenya tidak permanen (semua data ada di TiDB).

## WhatsApp (Fonnte)
Catat lewat chat: kirim "pengeluaran 34 ribu untuk makan" ke nomor bot, lalu bot membalas konfirmasi.

1. **Fonnte** → Device → tambah device → scan QR dengan **nomor cadangan** (bukan nomor utama). Di pengaturan device, nyalakan **Auto Read** dan matikan **Autoreply**.
2. Isi `backend/.env`: `FONNTE_TOKEN` (token device), `FONNTE_BOT_NUMBER` (nomor bot, format `62812...`). `FONNTE_WEBHOOK_SECRET` berisi string acak yang sudah dibuatkan.
3. Webhook butuh URL publik HTTPS. Lokal: `cloudflared tunnel --url http://localhost:8000`. Produksi: URL Render.
4. Fonnte → Device → **Webhook** → isi `https://<url-publik>/api/whatsapp/fonnte/<FONNTE_WEBHOOK_SECRET>`.
5. Di app: **Pengaturan → Hubungkan WhatsApp**, lalu kirim `HUBUNGKAN <kode>` ke bot.

Perintah: `SALDO`, `REKAP`, `BATAL` (hapus catatan WhatsApp terakhir), `BANTUAN`. Tambahkan `pakai bca` untuk memilih akun, dan `kemarin` / `3 hari lalu` untuk tanggal.
Pengujian lokal API ke database terpisah: jangan pakai `php artisan serve` dengan variabel `DB_*` dari shell (tidak diteruskan); pakai `APP_ENV=nama` + file `.env.nama`.
