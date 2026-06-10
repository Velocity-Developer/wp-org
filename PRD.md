# PRD — Plugin WordPress Manajemen Organisasi

## 1. Ringkasan Produk

Plugin WordPress ini dibuat untuk membantu organisasi mengelola proses pendaftaran anggota, autentikasi pengguna, data anggota, dan administrasi organisasi dari dalam dashboard WordPress.

Plugin ditujukan untuk organisasi seperti komunitas, yayasan, himpunan, paguyuban, atau perkumpulan yang membutuhkan sistem anggota sederhana namun terstruktur.

## 2. Latar Belakang

Banyak organisasi masih mengelola data pendaftaran anggota secara manual melalui formulir terpisah, spreadsheet, dan komunikasi chat. Hal ini menyebabkan:

- data anggota tidak terpusat,
- proses verifikasi lambat,
- sulit melacak status pendaftaran,
- kesulitan menampilkan daftar anggota,
- dan administrasi menjadi tidak efisien.

Plugin ini bertujuan menyediakan solusi terintegrasi langsung di WordPress.

## 3. Tujuan Produk

### Tujuan Utama

- Menyediakan sistem registrasi dan login anggota.
- Menyimpan dan mengelola data anggota dalam satu tempat.
- Memudahkan admin mengatur field/formulir pendaftaran.
- Menampilkan daftar anggota yang sudah terdaftar.
- Mendukung alur verifikasi atau approval anggota oleh admin.

### Tujuan Bisnis

- Mengurangi proses administrasi manual.
- Meningkatkan akurasi dan kelengkapan data anggota.
- Mempercepat proses onboarding anggota baru.
- Menyediakan fondasi untuk fitur organisasi lanjutan di masa depan.

## 4. Ruang Lingkup Versi Awal (MVP)

Fitur yang wajib ada pada versi awal:

1. Register anggota baru.
2. Login anggota.
3. Logout anggota.
4. Form pendaftaran dengan field yang dapat dikelola admin.
5. Penyimpanan data profil anggota.
6. Halaman daftar anggota.
7. Panel admin untuk melihat, meninjau, dan mengubah data anggota.
8. Status pendaftaran anggota (pending, approved, rejected).
9. Role/capability dasar untuk admin organisasi dan anggota.

## 5. Persona Pengguna

### 5.1 Admin Organisasi

Pengguna yang mengelola data anggota, menyetujui pendaftaran, mengatur field pendaftaran, dan melihat seluruh data anggota.

### 5.2 Calon Anggota

Pengguna yang melakukan registrasi dan mengisi data yang dibutuhkan untuk mendaftar.

### 5.3 Anggota

Pengguna yang sudah disetujui dan dapat login untuk melihat atau memperbarui data profilnya sesuai izin.

## 6. Fitur Utama

### 6.1 Registrasi Anggota

**Deskripsi:**
Pengguna dapat mendaftar melalui form frontend.

**Kebutuhan fungsional:**

- Form registrasi tersedia melalui shortcode atau page template WordPress.
- User mengisi data dasar seperti nama, email, username, password.
- User mengisi data tambahan sesuai field yang diatur admin.
- Sistem menyimpan data pendaftaran.
- Sistem mengirim notifikasi bahwa pendaftaran berhasil dikirim.
- Status awal pendaftaran adalah `pending`.

**Contoh field default:**

- Nama lengkap
- Email
- Nomor HP
- Alamat
- Tanggal lahir
- Jenis kelamin
- Pekerjaan
- Foto profil / dokumen pendukung

### 6.2 Login Anggota

**Deskripsi:**
Pengguna yang sudah punya akun dapat login dari halaman frontend.

**Kebutuhan fungsional:**

- Form login frontend.
- Validasi username/email dan password.
- Redirect setelah login.
- Pesan error jika login gagal.
- Opsi pembatasan login untuk akun yang belum disetujui, jika dibutuhkan.

### 6.3 Manajemen Data Pendaftaran

**Deskripsi:**
Admin dapat menentukan data apa saja yang wajib diisi saat pendaftaran.

**Kebutuhan fungsional:**

- Admin dapat menambah field formulir.
- Admin dapat mengubah label field.
- Admin dapat menentukan tipe field: text, textarea, select, radio, checkbox, date, file.
- Admin dapat menandai field sebagai wajib atau opsional.
- Admin dapat menentukan urutan field.
- Admin dapat menonaktifkan field tertentu.

### 6.4 Daftar Anggota

**Deskripsi:**
Sistem menampilkan daftar anggota yang sudah terdaftar.

**Kebutuhan fungsional:**

- Admin melihat seluruh daftar anggota di dashboard.
- Frontend dapat menampilkan daftar anggota bila diaktifkan.
- Filter berdasarkan status, nama, atau kategori tertentu.
- Pencarian anggota.
- Pagination daftar anggota.

### 6.5 Approval Pendaftaran

**Deskripsi:**
Admin meninjau pendaftaran baru sebelum anggota aktif penuh.

**Kebutuhan fungsional:**

- Admin melihat daftar pendaftar baru.
- Admin mengubah status menjadi approved atau rejected.
- Admin dapat menambahkan catatan internal.
- Sistem dapat mengirim notifikasi status ke pendaftar.

### 6.6 Profil Anggota

**Deskripsi:**
Anggota dapat melihat data profilnya.

**Kebutuhan fungsional:**

- Halaman profil anggota.
- Menampilkan data yang sudah diisi.
- Edit data tertentu sesuai kebijakan admin.
- Upload/update dokumen jika diizinkan.

## 7. Fitur Admin Dashboard

Admin membutuhkan halaman pengelolaan di WordPress dashboard.

### Menu admin yang dibutuhkan:

- Dashboard ringkas organisasi
- Data anggota
- Pendaftaran baru
- Field formulir
- Pengaturan plugin
- Laporan sederhana

### Widget/summary dashboard:

- Total anggota
- Total pendaftaran pending
- Total anggota approved
- Total anggota rejected

## 8. Kebutuhan Fungsional Detail

### 8.1 Akun dan Role

- Menggunakan sistem user WordPress.
- Menambahkan role khusus, misalnya `org_member`.
- Admin WordPress atau role tertentu dapat mengelola anggota.
- Capability harus dipisahkan untuk melihat, mengedit, approve, dan mengatur field.

### 8.2 Penyimpanan Data

- Data akun menggunakan tabel user WordPress.
- Data tambahan anggota dapat disimpan di `user_meta` pada MVP.
- Definisi field dinamis dapat disimpan di `options` atau custom table.
- Jika skala data besar, custom table dapat dipertimbangkan pada versi lanjut.

### 8.3 Shortcode / Integrasi Frontend

Minimal menyediakan shortcode:

- `[org_register]` untuk form register
- `[org_login]` untuk form login
- `[org_members]` untuk daftar anggota
- `[org_profile]` untuk profil anggota

### 8.4 Validasi

- Email harus unik.
- Username harus unik.
- Password mengikuti kebijakan minimum.
- Field wajib tidak boleh kosong.
- File upload harus dibatasi tipe dan ukuran.
- Field wilayah harus konsisten secara hierarki: kecamatan harus berasal dari kota/kabupaten yang dipilih, dan kota/kabupaten harus berasal dari provinsi yang dipilih.

### 8.5 Data Wilayah Indonesia

- Plugin mendukung data wilayah administratif Indonesia minimal level provinsi, kota/kabupaten, dan kecamatan.
- Dataset wilayah dapat mengacu ke repositori `https://github.com/cahyadsn/wilayah`.
- Pada MVP, data wilayah sebaiknya disimpan lokal di database/plugin agar form tetap cepat dan tidak tergantung API pihak ketiga.
- Struktur data wilayah sebaiknya dipisahkan dari `user_meta`, karena dipakai ulang oleh banyak anggota.
- Field yang disimpan pada data anggota minimal mencakup: kode provinsi, kode kota/kabupaten, kode kecamatan, serta label/nama wilayah untuk kebutuhan tampilan.

## 9. Kebutuhan Non-Fungsional

- Mengikuti standar coding WordPress.
- Aman terhadap CSRF, XSS, dan SQL Injection.
- Menggunakan nonce untuk form.
- Sanitasi dan validasi semua input.
- Mendukung responsive layout pada halaman frontend.
- Mudah dikembangkan untuk fitur berikutnya.
- Mendukung bahasa Indonesia pada UI awal.

## 10. User Flow

### 10.1 Flow Registrasi

1. Pengunjung membuka halaman pendaftaran.
2. Pengunjung mengisi form.
3. Sistem memvalidasi data.
4. Sistem membuat akun/user.
5. Sistem menyimpan data tambahan.
6. Status user menjadi `pending`.
7. Admin meninjau pendaftaran.
8. Admin approve atau reject.
9. User menerima informasi status.

### 10.2 Flow Login

1. User membuka halaman login.
2. User memasukkan email/username dan password.
3. Sistem memvalidasi kredensial.
4. Jika valid, user diarahkan ke halaman profil/dashboard.

### 10.3 Flow Admin Kelola Field

1. Admin membuka menu field formulir.
2. Admin menambah/mengubah field.
3. Admin menyimpan konfigurasi.
4. Form frontend otomatis mengikuti konfigurasi terbaru.

## 11. Halaman yang Dibutuhkan

### Frontend

- Halaman Register
- Halaman Login
- Halaman Profil Anggota
- Halaman Daftar Anggota

### Backend WordPress

- Halaman daftar anggota
- Halaman detail anggota
- Halaman approval pendaftaran
- Halaman pengaturan field
- Halaman pengaturan umum plugin

## 12. Struktur Data Awal

Berikut contoh data inti anggota:

- ID user
- Nama lengkap
- Username
- Email
- Nomor HP
- Alamat
- Tanggal lahir
- Jenis kelamin
- Pekerjaan
- Foto profil
- Dokumen pendukung
- Status pendaftaran
- Tanggal daftar
- Catatan admin

## 13. Batasan MVP

Versi awal belum mencakup:

- Pembayaran iuran anggota
- Kartu anggota digital
- Integrasi WhatsApp
- Integrasi email marketing
- Multi-level organisasi/cabang
- Event management
- Absensi kegiatan
- Export/import data lanjutan

## 14. Risiko dan Pertimbangan

- Jika field formulir terlalu dinamis, implementasi bisa lebih kompleks.
- Penyimpanan file perlu perhatian pada keamanan dan ukuran server.
- Approval manual dapat menambah beban admin jika pendaftar banyak.
- Jika daftar anggota ditampilkan publik, perlu aturan privasi yang jelas.

## 15. Metrik Keberhasilan

- Jumlah pendaftaran berhasil.
- Persentase pendaftaran yang disetujui.
- Waktu rata-rata approval admin.
- Kelengkapan data anggota.
- Pengurangan proses administrasi manual.

## 16. Rekomendasi Teknis Awal

- Gunakan custom admin menu WordPress.
- Gunakan shortcode untuk halaman frontend.
- Gunakan `wp_insert_user()` untuk registrasi akun.
- Gunakan `user_meta` untuk data tambahan pada MVP.
- Gunakan nonce dan capability checks di semua form/admin action.
- Pertimbangkan custom table untuk data field dinamis jika kebutuhan berkembang.

## 17. Acceptance Criteria MVP

Plugin dianggap memenuhi MVP jika:

- Pengunjung dapat register dari frontend.
- Pengguna dapat login dari frontend.
- Admin dapat melihat daftar anggota.
- Admin dapat approve/reject pendaftaran.
- Admin dapat menentukan field pendaftaran yang diperlukan.
- Data anggota tersimpan dan bisa dilihat kembali.
- Daftar anggota dapat ditampilkan dengan aman sesuai hak akses.

## 18. Pengembangan Lanjutan

Fitur fase berikutnya yang bisa ditambahkan:

- Iuran dan pembayaran anggota
- Kategori/divisi anggota
- Kartu anggota digital
- QR code anggota
- Export Excel/PDF
- Integrasi notifikasi email/WhatsApp
- Riwayat aktivitas anggota
- Dashboard statistik yang lebih lengkap
