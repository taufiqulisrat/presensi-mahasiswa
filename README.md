# Sistem Presensi Mahasiswa Berbasis Face Recognition dan Liveness Detection

Dokumen ini adalah rencana awal pengembangan aplikasi tugas akhir: **Implementasi FaceNet dan Liveness Detection pada Sistem Presensi Mahasiswa Berbasis Web untuk Mencegah Pemalsuan Wajah**.

## Tujuan

Membangun sistem presensi yang berjalan secara lokal di komputer ruang kelas. Sistem mengenali mahasiswa yang hadir melalui wajah, kemudian memverifikasi bahwa wajah tersebut berasal dari orang nyata melalui *challenge-response liveness detection*.

Sistem ditujukan untuk mengurangi titip absen menggunakan foto cetak, foto pada layar, dan video rekaman.

## Keputusan Produk

| Area | Keputusan |
| --- | --- |
| Pengguna mahasiswa | Tidak memiliki akun dan tidak perlu login. |
| Pengguna admin | Login untuk mengelola data, mendaftarkan wajah, membuka sesi, dan melihat laporan. |
| Halaman awal | Menyediakan tombol utama **Mulai Absen** dan tautan **Login Admin**. |
| Lingkungan | Berjalan lokal pada komputer ruang kelas; tidak membutuhkan hosting atau internet ketika aplikasi sudah dipasang. |
| Backend aplikasi | Laravel/PHP di Laragon. |
| Database | MySQL dari Laragon. |
| Layanan AI | Python + FastAPI, berjalan sebagai layanan lokal terpisah. |

Mahasiswa hanya dapat melakukan presensi ketika admin telah membuka sesi presensi yang aktif untuk kelas dan mata kuliah tertentu.

## Peran Pengguna

### Mahasiswa

1. Membuka halaman awal lalu memilih **Mulai Absen**.
2. Mengizinkan akses webcam.
3. Mengikuti instruksi gerakan acak di layar.
4. Setelah lolos liveness detection, sistem mengenali wajahnya.
5. Sistem menyimpan presensi atau menunjukkan alasan kegagalan tanpa mengungkap data mahasiswa lain.

### Admin

1. Login ke dashboard.
2. Mengelola data mahasiswa, kelas, mata kuliah, dan jadwal/sesi presensi.
3. Mendaftarkan wajah mahasiswa dalam lima pose: depan, kiri, kanan, atas, dan bawah.
4. Membuka atau menutup sesi presensi.
5. Melihat, mencari, dan mengekspor laporan presensi.

## Alur Presensi

```text
Admin membuka sesi presensi
           |
Mahasiswa memilih "Mulai Absen"
           |
Browser meminta akses webcam
           |
FastAPI membuat challenge acak
           |
Mahasiswa merespons: kiri/kanan/atas/bawah/berkedip
           |
Liveness lolos?
     | tidak                       | ya
Tolak dan tampilkan pesan      MTCNN mendeteksi + align wajah
                                    |
                         FaceNet menghasilkan embedding
                                    |
                   Cocok dengan embedding mahasiswa?
                         | tidak             | ya
                       Tolak           Simpan presensi ke MySQL
```

Urutan challenge dibuat secara acak untuk setiap presensi. Implementasi awal mengikuti lima jenis perintah dalam proposal: menoleh kiri, kanan, atas, bawah, dan berkedip.

## Arsitektur

```text
Browser di komputer ruang kelas
  ├─ Laravel (Apache Laragon)       : halaman, admin, sesi, laporan
  ├─ MySQL (Laragon)                : data aplikasi dan embedding wajah
  └─ FastAPI (Python, localhost)    : MTCNN, FaceNet, liveness, API AI
       ├─ MTCNN                     : deteksi dan alignment wajah
       ├─ FaceNet pra-latih          : pembuatan embedding dan pencocokan
       └─ MediaPipe Face Mesh        : landmark mata dan estimasi arah kepala
```

Laravel menjadi pemilik data bisnis dan autentikasi admin. FastAPI hanya memproses frame kamera dan mengembalikan hasil liveness/pengenalan. Komunikasi dilakukan melalui REST API pada `localhost`.

> MTCNN memiliki lima landmark wajah, sehingga tidak cukup untuk menghitung Eye Aspect Ratio (EAR). Karena itu, MediaPipe Face Mesh dipakai khusus untuk landmark mata, EAR, dan arah kepala; MTCNN tetap dipakai untuk alignment masukan FaceNet.

## Modul Aplikasi

| Modul | Fungsi |
| --- | --- |
| Halaman awal | Tombol **Mulai Absen**, informasi sesi aktif, dan akses Login Admin. |
| Autentikasi admin | Login, logout, proteksi dashboard. |
| Data master | Mahasiswa, kelas, mata kuliah, dan relasi mahasiswa-kelas. |
| Pendaftaran wajah | Pengambilan wajah lima pose, pemeriksaan kualitas, pembuatan dan penyimpanan embedding. |
| Sesi presensi | Admin memilih kelas/mata kuliah, waktu aktif, lalu membuka/menutup sesi. |
| Liveness detection | Challenge acak, deteksi arah kepala, kedipan berbasis EAR, batas waktu, dan hasil validasi. |
| Face recognition | Deteksi/alignment, ekstraksi embedding FaceNet, pencarian identitas dan threshold. |
| Riwayat/laporan | Daftar kehadiran, filter, rekap, serta ekspor CSV/Excel. |
| Audit dasar | Menyimpan hasil proses, waktu, jarak kemiripan, dan alasan penolakan tanpa menyimpan video mentah secara default. |

## Model Data Awal

| Tabel | Data inti |
| --- | --- |
| `admins` | nama, email/username, kata sandi ter-hash |
| `students` | NIM, nama, email opsional, status pendaftaran wajah |
| `classes` | nama/angkatan/semester |
| `courses` | kode dan nama mata kuliah |
| `class_students` | relasi mahasiswa dan kelas |
| `attendance_sessions` | kelas, mata kuliah, waktu mulai/selesai, status aktif |
| `face_embeddings` | mahasiswa, pose, embedding terenkripsi/terserialisasi, waktu dibuat |
| `attendances` | sesi, mahasiswa, waktu presensi, status, nilai kemiripan |
| `liveness_attempts` | sesi, waktu, hasil, challenge, alasan gagal |

`attendances` perlu memiliki aturan unik `attendance_session_id + student_id` agar mahasiswa tidak bisa tercatat dua kali dalam satu sesi.

## Rencana API

### Laravel

| Metode | Endpoint | Fungsi |
| --- | --- | --- |
| `POST` | `/admin/login` | Login admin. |
| `GET/POST` | `/admin/students` | Kelola mahasiswa. |
| `POST` | `/admin/students/{id}/enroll` | Mengirim data pendaftaran wajah ke layanan AI. |
| `GET/POST` | `/admin/sessions` | Membuat dan mengelola sesi presensi. |
| `POST` | `/attendance/complete` | Menyimpan hasil presensi yang telah tervalidasi. |
| `GET` | `/admin/reports` | Laporan dan ekspor presensi. |

### FastAPI

| Metode | Endpoint | Fungsi |
| --- | --- | --- |
| `POST` | `/api/enrollment/start` | Membuka proses pendaftaran wajah. |
| `POST` | `/api/enrollment/frame` | Memvalidasi pose dan membuat embedding pendaftaran. |
| `POST` | `/api/liveness/start` | Membuat sesi serta urutan challenge acak. |
| `POST` | `/api/liveness/frame` | Menerima frame dan memvalidasi respons challenge. |
| `POST` | `/api/recognition/identify` | Mengenali wajah setelah liveness sukses. |
| `GET` | `/api/health` | Pemeriksaan ketersediaan layanan AI. |

Endpoint sensitif di FastAPI hanya boleh menerima permintaan dari Laravel/halaman lokal yang memiliki token sesi singkat. Hasil liveness dan identifikasi juga harus terikat pada `attendance_session` yang sedang aktif.

## Aturan Teknis Utama

- Gunakan FaceNet **pra-latih**: model tidak dilatih ulang untuk setiap mahasiswa; sistem hanya menyimpan embedding hasil pendaftaran.
- Simpan embedding beberapa pose, bukan video atau foto mentah, kecuali penyimpanan foto penelitian memang disetujui secara eksplisit.
- Tentukan threshold FaceNet dari data pengujian lokal. Jangan langsung memakai angka dari penelitian lain.
- EAR kedipan menggunakan baseline mata terbuka dan ambang yang dikalibrasi; nilai `0,17` hanya dijadikan titik awal eksperimen.
- Respons arah kepala diukur relatif terhadap pose netral pengguna, bukan hanya koordinat piksel absolut.
- Setiap challenge memiliki batas waktu, misalnya 5--8 detik, serta perlu mendeteksi perubahan gerak yang benar-benar terjadi setelah instruksi ditampilkan.
- Batasi satu presensi sukses per mahasiswa per sesi.
- Jangan menampilkan nama mahasiswa kandidat ketika pengenalan gagal.

## Keamanan dan Privasi

- Kata sandi admin disimpan menggunakan hashing bawaan Laravel.
- Akses dashboard selalu membutuhkan login admin.
- Data wajah adalah data biometrik; mahasiswa perlu diberi pemberitahuan dan persetujuan yang jelas sebelum pendaftaran.
- Raw frame/video tidak disimpan secara default. Data yang diperlukan untuk audit dibatasi pada hasil proses dan metrik teknis.
- Database serta folder model hanya dapat diakses oleh operator komputer lokal.
- Layanan FastAPI dibatasi untuk alamat lokal dan memiliki CORS yang hanya mengizinkan aplikasi Laravel.

## Pengujian yang Direncanakan

### Fungsional

- Login/logout admin dan pembatasan akses dashboard.
- Pendaftaran tiap pose berhasil maupun gagal karena wajah tidak jelas.
- Sesi presensi aktif, belum aktif, telah ditutup, dan melewati waktu.
- Presensi satu kali dalam satu sesi.
- Filter dan ekspor laporan.

### Face recognition dan liveness

- Wajah mahasiswa terdaftar dan tidak terdaftar.
- Wajah asli dalam kondisi normal serta variasi cahaya/jarak/pose.
- Foto cetak, foto pada layar, dan video replay.
- Semua jenis challenge: kiri, kanan, atas, bawah, serta kedip.

### Metrik penelitian

- Akurasi, precision, recall, dan F1-score.
- FAR, FRR, dan bila memungkinkan EER untuk pengenalan wajah.
- APCER, BPCER, dan ACER untuk liveness detection.
- Pengujian black box untuk seluruh fungsi aplikasi.

## Tahapan Pengerjaan

1. Menyiapkan struktur Laravel, MySQL, autentikasi admin, dan halaman awal.
2. Membuat data master mahasiswa/kelas/mata kuliah dan manajemen sesi.
3. Menyiapkan FastAPI serta endpoint pemeriksaan kesehatan.
4. Mengimplementasikan pendaftaran wajah lima pose dan penyimpanan embedding.
5. Mengimplementasikan liveness challenge-response dengan Face Mesh, EAR, dan head pose.
6. Mengintegrasikan FaceNet untuk identifikasi dan pencatatan presensi.
7. Membuat dashboard, laporan, ekspor, serta audit proses.
8. Melakukan kalibrasi threshold, pengujian serangan, dan dokumentasi hasil penelitian.

## Kriteria Selesai Versi Awal

- Admin dapat login dan membuka sesi presensi.
- Mahasiswa dapat presensi tanpa login dari halaman **Mulai Absen**.
- Sistem mengharuskan liveness challenge sebelum identifikasi wajah.
- Sistem hanya menerima mahasiswa terdaftar yang lolos threshold pengenalan.
- Presensi sukses tersimpan pada sesi yang benar dan dapat dilihat admin.
- Foto serta video replay diuji dan hasilnya tercatat untuk evaluasi penelitian.

## Status Implementasi

Fondasi aplikasi sudah dibuat di workspace ini:

- `web/` berisi Laravel untuk halaman mahasiswa, login admin, data master, sesi, pendaftaran wajah, dan laporan.
- `ai-service/` berisi FastAPI untuk MTCNN, FaceNet pra-latih, MediaPipe Face Mesh, challenge-response, dan pencocokan embedding.
- Database Laragon MySQL bernama `presensi_aman` sudah disiapkan dan migrasi Laravel telah dijalankan.

### Menjalankan Aplikasi Lokal

1. Pastikan Apache dan MySQL Laragon aktif.
2. Jalankan layanan AI dari folder `ai-service`:

   ```powershell
   .\.venv\Scripts\python.exe -m uvicorn main:app --host 127.0.0.1 --port 8000
   ```

3. Jalankan Laravel dari folder `web`:

   ```powershell
   php artisan serve --port=8080
   ```

4. Buka `http://127.0.0.1:8080`. Untuk penggunaan melalui Apache Laragon, arahkan document root virtual host ke folder `web/public`.

Admin awal hasil seed:

```text
Email: admin@presensi.test
Kata sandi: admin12345
```

Ganti kata sandi admin awal sebelum dipakai di luar lingkungan pengembangan.
