BMKG Scraper API (Prakiraan Cuaca & Nowcast)
===========================================

Ringkas: Scraper untuk data prakiraan cuaca publik BMKG dan nowcast, dengan pemetaan desa ke kode admin4 (adm4).

Konteks lokasi: Desa Pujon Kidul Malang.

Paket inti
- Endpoints API BMKG
  - /index.php?action=bmkg_prakiraan&adm4=<adm4_code>
    - Mengembalikan data prakiraan cuaca publik BMKG untuk kode admin4 tertentu.
  - /index.php?action=bmkg_nowcast
    - Mengembalikan nowcast/peringatan dini BMKG secara umum.
  - /index.php?action=bmkg_prakiraan_desa&desa=<nama_desa>
    - Mengambil adm4 lewat lookup lokal (config/admin4_lookup.json) dan mengembalikan gabungan: prakiraan cuaca publik untuk adm4, serta nowcast.
  - /index.php?action=bmkg_latest&adm4=<adm4_code>
    - Mengembalikan prakiraan cuaca latest dan nowcast latest untuk adm4 tertentu.

- Mapping admin4
  - File: `config/admin4_lookup.json` berisi mapping desa → adm4. Contoh:
    { "Desa Pujon Kidul Malang": "35.07.26.2003" }

- Penyimpanan data
  - Tabel DB: prakiraan_cuaca, nowcast_alerts.
  - Scheduler menjalankan scraping berkala via `scheduler_fetch_data.php` untuk menyimpan data ke DB.

- Caching
  - BMKG data prakiraan cuaca publik dicache menggunakan file di `storage/cache/bmkg` (TTL default 30 menit).

Cara penggunaan (contoh praktis)
- Prakiraan cuaca berdasarkan adm4:
  - curl -s "http://localhost/API-Desaverse/index.php?action=bmkg_prakiraan&adm4=35.07.26.2003"
- Nowcast (umum):
  - curl -s "http://localhost/API-Desaverse/index.php?action=bmkg_nowcast"
- Prakiraan dan Nowcast untuk desa tertentu:
  - curl -s "http://localhost/API-Desaverse/index.php?action=bmkg_prakiraan_desa&desa=Desa%20Pujon%20Kidul%20Malang"
  - Output mencakup prakiraan cuaca dan nowcast untuk desa tersebut.
- Prakiraan dan Nowcast latest per adm4:
  - curl -s "http://localhost/API-Desaverse/index.php?action=bmkg_latest&adm4=35.07.26.2003"
- Catatan keamanan
  - Autentikasi untuk endpoint BMKG saat ini belum diterapkan; tambahkan mekanisme auth jika diperlukan untuk produksi.
- Konfigurasi penting
  - Admin4 lookup berada di `config/admin4_lookup.json`.
  - Jalankan scheduler:
    - php scheduler_fetch_data.php
  - Scheduler akan menyimpan prakiraan cuaca dan nowcast ke DB melalui dua model baru.
- Dokumentasi tambahan
  - Dokumentasi penggunaan tersedia di docs/usage_bmkg_api.md.
