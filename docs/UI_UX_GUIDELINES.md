# UI/UX GUIDELINES
## Sistem Informasi Manajemen Perkebunan Durian

- Versi: 1.0
- Sasaran: Web admin, website publik, dan Flutter mobile

## 1. Prinsip UX

1. Sederhana: tampilkan informasi sesuai tugas pengguna.
2. Konsisten: pola tombol, form, tabel, status, dan navigasi seragam.
3. Bertahap: tampilkan detail saat dibutuhkan, bukan semuanya sekaligus.
4. Jelas: label dan pesan menggunakan bahasa Indonesia yang mudah dipahami.
5. Aman: tindakan penting memerlukan konfirmasi yang sesuai.
6. Responsif: web nyaman di desktop/tablet; mobile nyaman digunakan di lapangan.
7. Toleran terhadap jaringan buruk: mobile menunjukkan status offline/sync secara jelas.

## 2. Segmentasi Pengalaman

### Admin web
Fokus pada pengelolaan data, monitoring, konfigurasi, dan laporan. Gunakan sidebar terstruktur, breadcrumb, filter, pagination, tabel, dan halaman detail.

### Petani mobile
Fokus pada tugas lapangan. Prioritaskan daftar tugas/kebun yang ditugaskan, pencarian pohon, input aktivitas, kamera, QR scanner, dan status sinkronisasi.

### Pengunjung publik
Halaman QR hanya menampilkan informasi pohon yang telah disetujui untuk publik. Jangan tampilkan menu internal.

## 3. Struktur Navigasi yang Disarankan

### Web admin
- Dashboard
- Data Wilayah
  - Desa
  - Blok
  - Kebun
- Data Pelaku
  - Pemilik
  - Petani
  - Penugasan
- Data Pohon
  - Varietas
  - Pohon
  - Pengukuran
  - QR Code
- Aktivitas Perkebunan
  - Pemupukan
  - Penyemprotan
  - Pembungaan
  - Perkembangan Buah
- Panen
  - Musim Panen
  - Sesi Panen
  - Catatan Panen
  - Laporan
- Pengguna dan Hak Akses
- Pengaturan

Menu harus ditampilkan berdasarkan permission. Struktur final mengikuti hasil validasi pengguna dan fitur yang benar-benar tersedia.

### Mobile
- Beranda/Tugas
- Kebun dan Blok Saya
- Pohon
- Catat Aktivitas
- Panen
- Sinkronisasi
- Profil

Hindari menampilkan menu admin yang tidak diperlukan petani.

## 4. Dashboard dan Information Overload

- Tampilkan 4–6 indikator utama yang paling relevan.
- Gunakan kartu ringkasan untuk angka penting.
- Tampilkan aktivitas terbaru dalam jumlah terbatas dengan tautan “Lihat semua”.
- Grafik harus menjawab pertanyaan operasional, bukan sekadar dekorasi.
- Sediakan filter periode/kebun jika relevan.
- Jangan menampilkan semua tabel dan grafik pada satu layar.
- Gunakan empty state yang menjelaskan langkah berikutnya.

## 5. Formulir

- Kelompokkan field berdasarkan konteks.
- Tandai field wajib secara jelas.
- Gunakan dropdown/search-select untuk data master.
- Gunakan input angka dengan satuan terlihat.
- Gunakan date picker untuk tanggal.
- Berikan validasi dekat dengan field.
- Pertahankan input pengguna ketika validasi gagal.
- Untuk form panjang, gunakan beberapa langkah atau section.
- Tampilkan ringkasan sebelum submit untuk transaksi penting.
- Hindari field yang tidak digunakan oleh proses bisnis.

## 6. Tabel dan Daftar

- Gunakan pagination server-side.
- Sediakan pencarian dan filter yang relevan.
- Tampilkan kolom penting terlebih dahulu.
- Sediakan halaman detail untuk informasi panjang.
- Jangan memuat ribuan record sekaligus.
- Tampilkan loading, empty, error, dan retry state.

## 7. Status dan Feedback

Gunakan label konsisten:
- Draft
- Menunggu sinkronisasi
- Tersinkron
- Gagal
- Konflik
- Aktif
- Nonaktif

Setiap aksi harus memberikan feedback. Hindari notifikasi sukses sebelum server mengonfirmasi penyimpanan.

## 8. UX Offline Mobile

- Tampilkan indikator koneksi.
- Tampilkan jumlah data yang menunggu sinkronisasi.
- Beri tahu pengguna jika foto masih dalam antrean upload.
- Sediakan retry untuk kegagalan yang dapat dicoba ulang.
- Jangan menghapus draft ketika upload gagal.
- Tampilkan konflik dengan instruksi penyelesaian yang jelas.
- Hindari proses sync yang mengunci seluruh aplikasi.

## 9. Foto dan Kamera

- Tampilkan preview foto sebelum upload.
- Berikan progress upload.
- Kompres foto dengan tetap menjaga keterbacaan.
- Berikan opsi mengambil ulang foto.
- Tampilkan status file lokal dan server secara berbeda.

## 10. Aksesibilitas dan Konsistensi

- Kontras teks memadai.
- Ukuran target sentuh mobile nyaman.
- Jangan mengandalkan warna saja untuk menyampaikan status.
- Label tombol menggunakan kata kerja yang jelas.
- Format tanggal, angka, berat, dan satuan konsisten.
- Bahasa utama: Bahasa Indonesia.

## 11. Alur Bisnis Utama

### Registrasi pohon
Pilih organisasi/kebun → input identitas dan varietas → tanggal tanam/koordinat → foto opsional → validasi → simpan → QR tersedia.

### Pengukuran
Pilih kebun → pilih pohon → isi pengukuran dan tanggal → foto/catatan opsional → validasi → simpan sebagai histori.

### Aktivitas
Pilih jenis aktivitas → pilih cakupan pohon/kebun → isi tanggal dan rincian → pilih produk jika relevan → lampirkan foto → simpan → tampilkan status sinkronisasi.

### Panen
Pilih musim → pilih sesi/kebun → pilih pohon → input jumlah buah dan berat → detail grade opsional → validasi konsistensi → simpan → laporan diperbarui.

### Sinkronisasi mobile
Perubahan lokal → antrean → koneksi tersedia → push → validasi server → hasil per item → pull perubahan terbaru → perbarui status.

## 12. Design System

Tetapkan token desain untuk:
- Warna primer, sekunder, sukses, peringatan, bahaya, dan netral.
- Tipografi dan hierarki heading.
- Spacing.
- Radius dan elevation.
- Komponen button, input, select, card, table, modal, badge/status, toast, dan empty state.

Jangan membuat warna atau komponen berbeda untuk halaman yang memiliki fungsi sama tanpa alasan.
