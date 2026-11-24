# BUSINESS RULES
## Sistem Informasi Manajemen Perkebunan Durian

- Versi: 1.0 (draft)
- Aturan harus divalidasi dengan pemilik proses perkebunan sebelum menjadi validasi produksi.

## 1. Struktur Organisasi dan Wilayah

BR-001. Data operasional dimiliki oleh satu organisasi/tenant.
BR-002. Pengguna hanya boleh mengakses organisasi yang menjadi scope kewenangannya.
BR-003. Struktur wilayah mengikuti Desa → Blok → Kebun → Pohon.
BR-004. Satu blok berada di bawah satu desa.
BR-005. Satu kebun berada di bawah satu blok.
BR-006. Satu pohon berada langsung di bawah satu kebun.
BR-007. Pohon tidak memiliki relasi langsung ke blok; blok diturunkan melalui kebun.

## 2. Pemilik dan Petani

BR-008. Satu kebun dapat memiliki lebih dari satu pemilik.
BR-009. Satu pemilik dapat memiliki lebih dari satu kebun.
BR-010. Relasi pemilik-kebun harus menyimpan atribut tambahan (misalnya persentase kepemilikan) hanya jika disetujui dan didefinisikan.
BR-011. Petani dapat ditugaskan ke satu atau lebih blok dan/atau kebun.
BR-012. Penugasan petani menentukan cakupan pekerjaan/data lapangan, bukan otomatis memberi role administrator.
BR-013. Perubahan penugasan harus tercatat dan tidak boleh menghapus riwayat aktivitas lama.

## 3. Pohon Durian

BR-014. Setiap pohon memiliki UUID unik.
BR-015. Setiap pohon terhubung ke satu kebun aktif yang valid.
BR-016. Varietas harus berasal dari master varietas bila master digunakan.
BR-017. Tanggal tanam tidak boleh berada di masa depan kecuali proses bisnis mengizinkan pencatatan rencana tanam.
BR-018. Koordinat harus berada dalam rentang geografis valid.
BR-019. Perubahan status pohon harus mempertahankan riwayat jika status historis dibutuhkan.
BR-020. QR Code mengidentifikasi pohon, tetapi tidak boleh menjadi pengganti autentikasi untuk data privat.

## 4. Pengukuran

BR-021. Pengukuran disimpan sebagai catatan historis.
BR-022. Setiap pengukuran memiliki tanggal dan petugas/pencatat jika tersedia.
BR-023. Nilai pengukuran harus lebih besar dari nol dan menggunakan satuan yang jelas.
BR-024. Koreksi pengukuran harus tercatat; jangan menimpa histori tanpa audit.
BR-025. Sistem dapat menampilkan pengukuran terakhir sebagai ringkasan, tetapi sumber data tetap histori.

## 5. Pemupukan dan Penyemprotan

BR-026. Satu catatan pemupukan dapat memiliki beberapa item produk.
BR-027. Satu catatan penyemprotan dapat memiliki beberapa item produk.
BR-028. Produk harus berasal dari master produk yang sesuai.
BR-029. Dosis dan satuan harus disimpan secara konsisten.
BR-030. Catatan harus memiliki tanggal dan pelaksana sesuai aturan.
BR-031. Sistem tidak boleh mengasumsikan dosis agronomis yang benar tanpa standar dari ahli/pengelola kebun.
BR-032. Aktivitas harus dikaitkan ke pohon atau area dengan aturan cakupan yang jelas.

## 6. Pembungaan dan Perkembangan Buah

BR-033. Catatan pembungaan disimpan sebagai histori.
BR-034. Catatan perkembangan buah disimpan sebagai histori.
BR-035. Tanggal pengamatan dan objek pengamatan harus jelas.
BR-036. Status/tahapan perkembangan harus menggunakan pilihan nilai yang konsisten setelah disepakati.

## 7. Panen

BR-037. Musim panen memiliki periode yang ditetapkan.
BR-038. Sesi panen terkait dengan satu kebun dan musim yang valid.
BR-039. Catatan panen harus terkait dengan sesi dan pohon.
BR-040. Kebun pada catatan panen harus sama dengan kebun sesi dan kebun pohon.
BR-041. Jumlah buah harus bilangan bulat tidak negatif.
BR-042. Berat total harus angka tidak negatif dengan satuan yang ditetapkan (misalnya kg).
BR-043. Detail grade/mutu bersifat opsional sampai standar grading disepakati.
BR-044. Koreksi hasil panen harus tercatat dan dapat ditelusuri.
BR-045. Laporan panen harus menghindari penghitungan ganda pada detail dan total.

## 8. Foto dan Dokumen

BR-046. Foto harus terkait dengan objek yang valid.
BR-047. File upload harus melewati validasi MIME, ukuran, dan isi.
BR-048. Foto privat hanya dapat diakses pengguna yang berhak.
BR-049. File disimpan pada storage; database menyimpan metadata/path secara default.
BR-050. Penghapusan metadata dan file harus konsisten; kegagalan penghapusan file ditangani melalui proses pembersihan.

## 9. QR Code dan Informasi Publik

BR-051. Satu pohon memiliki satu identitas QR aktif sesuai rancangan.
BR-052. Halaman QR hanya menampilkan data yang ditandai publik.
BR-053. Informasi sensitif seperti data pemilik, kontak, catatan internal, atau data organisasi privat tidak boleh ditampilkan tanpa persetujuan.

## 10. Sinkronisasi Offline

BR-054. Data yang dibuat offline memiliki ID client-generated yang stabil.
BR-055. Pengiriman ulang operasi yang sama tidak boleh membuat duplikasi.
BR-056. Server tetap menjadi otoritas akhir untuk validasi dan otorisasi.
BR-057. Status lokal harus membedakan draft, pending, synced, failed, dan conflict.
BR-058. Konflik diselesaikan berdasarkan jenis entitas; jangan menerapkan last-write-wins secara universal.
BR-059. Catatan historis aktivitas dan pengukuran sebaiknya append-only; koreksi dibuat sebagai perubahan yang dapat diaudit.
BR-060. Data yang tidak lagi termasuk penugasan pengguna tidak boleh terus dapat diakses melalui sync berikutnya.

## 11. Audit dan Keamanan

BR-061. Aksi penting mencatat siapa, kapan, apa, dan objek yang diubah.
BR-062. Password, token, secret, dan kredensial tidak boleh masuk audit log.
BR-063. Permission sistem dan penugasan kebun/blok adalah dua konsep berbeda.
BR-064. Semua validasi keamanan dilakukan di backend.
BR-065. Cache dan file storage harus mengikuti batas tenant dan permission.

## 12. Aturan yang Harus Dikonfirmasi

- Apakah petani boleh mengedit aktivitas setelah tersinkron?
- Apakah hasil panen dapat direvisi, dan siapa yang berhak?
- Apakah satu pohon dapat dipindahkan kebun?
- Apakah kepemilikan kebun memakai persentase?
- Apakah kegiatan pemupukan/penyemprotan dicatat per pohon, per kebun, atau keduanya?
- Standar satuan pengukuran dan grading panen.
- Definisi status pohon dan tahapan perkembangan buah.
- Kebijakan penghapusan/arsip data.
