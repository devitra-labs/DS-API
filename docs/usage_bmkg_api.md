BMKG Scraper API – Panduan Penggunaan
====================================
Dokumentasi singkat untuk QA cepat, mencakup endpoint BMKG prakiraan cuaca publik dan nowcast.

1) Prakiraan cuaca berdasarkan adm4
- Endpoint:
  - GET /index.php?action=bmkg_prakiraan&adm4=<adm4_code>
- Contoh:
  - curl -s "http://localhost/API-Desaverse/index.php?action=bmkg_prakiraan&adm4=35.07.26.2003"

2) Nowcast secara umum
- Endpoint:
  - GET /index.php?action=bmkg_nowcast
- Contoh:
  - curl -s "http://localhost/API-Desaverse/index.php?action=bmkg_nowcast"

3) Prakiraan + Nowcast untuk desa tertentu
- Endpoint:
  - GET /index.php?action=bmkg_prakiraan_desa&desa=<nama_desa>
- Contoh:
  - curl -s "http://localhost/API-Desaverse/index.php?action=bmkg_prakiraan_desa&desa=Desa%20Pujon%20Kidul%20Malang"
- Respons mencakup:
  - desa, adm4, prakiraan (data dari BMKG publik), nowcast_alerts (data nowcast)

4) Prakiraan dan Nowcast latest per adm4 (endpoint baru)
- Endpoint:
  - GET /index.php?action=bmkg_latest&adm4=<adm4_code>
- Contoh:
  - curl -s "http://localhost/API-Desaverse/index.php?action=bmkg_latest&adm4=35.07.26.2003"
- Respons mencakup:
  - adm4, prakiraan_latest, nowcast_latest

5) Contoh skenario QA
- Periksa semua endpoint secara beriringan untuk konfirmasi integritas data.

6) Catatan
- Autentikasi untuk endpoint BMKG saat ini belum diterapkan; untuk produksi, tambahkan mekanisme auth jika diperlukan.
- Perubahan ini mengandalkan admin4 lookup di config/admin4_lookup.json untuk konversi desa ke adm4.
- Pastikan database sudah di-migrate untuk tabel prakiraan_cuaca dan nowcast_alerts sebelum menjalankan endpoint baru.
