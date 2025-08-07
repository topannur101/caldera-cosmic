# Caldera Cosmic

Sistem manajemen manufaktur komprehensif yang dibangun oleh Digitalization Team di Manufacturing Modernization Department PT. TKG Taekwang Indonesia. Aplikasi berbasis Laravel ini mengelola proses manufaktur sepatu dengan kemampuan pelacakan inventaris terintegrasi dan pemantauan real-time.

## 🏭 Gambaran Sistem

Caldera Cosmic terdiri dari dua modul utama:

### Bagian Wawasan (Insight)
Platform analitik manufaktur untuk kontrol dan pemantauan proses:

- **OMV (Open Mill Validation)**: Pemantauan real-time proses pencampuran karet dengan pelacakan timing otomatis, tracking ampere, pengambilan gambar, dan manajemen resep
- **CTC (Calendar Thickness Control)**: Pemantauan dan kontrol ketebalan otomatis untuk proses kalender karet
- **STC (Stabilization Temperature Control)**: Pemantauan suhu dan kelembaban ruang untuk stabilisasi area IP
- **RDC (Rheometer Data Collection)**: Pengumpulan dan analisis data uji rheometer otomatis
- **LDC (Leather Data Collection)**: Sistem kontrol kualitas untuk inspeksi dan penilaian kulit/hide
- **CLM (Climate Monitoring)**: Pemantauan lingkungan untuk suhu dan kelembaban di area produksi

### Manajemen Inventaris
Sistem inventaris komprehensif untuk consumable manufaktur:

- **Manajemen Item**: Katalog lengkap consumable dengan foto, deskripsi, lokasi, dan spesifikasi
- **Pelacakan Stok**: Inventaris multi-unit dengan kalkulasi qty otomatis dan konversi mata uang
- **Kontrol Sirkulasi**: Pelacakan deposit/penarikan dengan alur kerja persetujuan dan delegasi pengguna
- **Otorisasi Berbasis Area**: Kontrol akses berbasis peran per departemen/area
- **Analitik Lanjutan**: Aging stok, pola penggunaan, dan pemesanan ulang prediktif

## 🛠 Stack Teknologi

- **Framework**: Laravel 12
- **Versi PHP**: 8.x
- **Database**: MySQL
- **Frontend**: Livewire Volt
- **Charts**: Chart.js
- **Package Manager**: Composer + NPM
- **Layanan Tambahan**: Sistem Queue, Task Scheduler
- **Komunikasi Industri**: Integrasi klien Modbus TCP

## 📋 Persyaratan

- PHP >= 8.0
- Composer
- Node.js & NPM
- MySQL 5.7+ atau 8.0+
- Redis (untuk queue dan caching)

## 🚀 Instalasi

1. **Clone repository**
   ```bash
   git clone [repository-url]
   cd caldera-cosmic
   ```

2. **Install dependensi PHP**
   ```bash
   composer install
   ```

3. **Install dependensi Node.js**
   ```bash
   npm install
   ```

4. **Setup environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Konfigurasi file `.env`**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=caldera_cosmic
   DB_USERNAME=your_username
   DB_PASSWORD=your_password

   QUEUE_CONNECTION=redis
   CACHE_DRIVER=redis
   ```

6. **Setup database**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

7. **Build frontend assets**
   ```bash
   npm run build
   ```

8. **Buat symlink storage**
   ```bash
   php artisan storage:link
   ```

9. **Jalankan development server**
   ```bash
   php artisan serve
   ```

10. **Jalankan queue worker**
    ```bash
    php artisan queue:work
    ```

## 📁 Fitur Utama

### OMV (Open Mill Validation)
- **Operasi Berbasis Resep**: Resep pencampuran yang dapat dikonfigurasi dengan timing langkah otomatis dan titik tangkap
- **Pemantauan Real-time**: Pembacaan ampere langsung dengan evaluasi otomatis (too_soon, on_time, too_late, on_time_manual)
- **Dokumentasi Otomatis**: Pengambilan gambar pada interval resep tertentu dengan encoding base64
- **Kalkulasi Energi**: Konsumsi kWh presisi menggunakan √3 × Arus × Tegangan × Waktu × Faktor Daya × Faktor Kalibrasi
- **Dukungan Multi-tim**: Penugasan tim (A, B, C) dengan pelacakan shift dan delegasi pengguna
- **Integrasi Batch**: Menghubungkan ke komposisi dan spesifikasi batch karet

### CTC (Calendar Thickness Control)
- **Analitik Performa Mesin**: Melacak performa dan metrik efisiensi line kalender
- **Manajemen Resep**: Sistem ekspor resep HMI dan rekomendasi
- **Analitik Batch**: Analisis run produksi dengan metrik kualitas
- **Analitik Koreksi**: Memantau efisiensi koreksi dan penyesuaian
- **Data Real-time**: Pengukuran ketebalan langsung dan feedback kontrol

### STC (Stabilization Temperature Control)
- **Pemantauan Chamber**: Pelacakan suhu dan kelembaban chamber stabilisasi area IP
- **Manajemen Device**: Integrasi device pengukuran HOBO/T&D
- **Analisis Historis**: Analisis tren lingkungan jangka panjang
- **Pelacakan Deviasi**: Memantau dan memberikan alert pada pelanggaran spesifikasi lingkungan
- **Laporan Performa**: Analitik performa mesin dan operator

### RDC (Rheometer Data Collection)
- **Testing Otomatis**: Manajemen uji rheometer berbasis antrian
- **Analisis TC10/TC90**: Analisis waktu cure dengan charting tren
- **Integrasi Batch**: Menghubungkan hasil uji ke kode batch karet
- **Analitik Kualitas**: Analisis statistik properti cure karet

### LDC (Leather Data Collection)
- **Inspeksi Hide**: Sistem penilaian kualitas untuk bahan kulit/hide
- **Integrasi Mesin**: Manajemen kode mesin NT/NEK
- **Analitik Produksi**: Pelacakan performa pekerja dan mesin
- **Kontrol Kualitas**: Distribusi grade dan analisis tren kualitas

### Sistem Inventaris
- **Pencarian Cerdas**: Pencarian multi-field dengan autocomplete tag dan filter lokasi
- **Alur Kerja Sirkulasi**: Sistem deposit/penarikan berbasis persetujuan dengan penugasan evaluator
- **Dukungan Multi-mata Uang**: Konversi mata uang otomatis dan kalkulasi jumlah
- **Manajemen Lokasi**: Sistem lokasi hierarkis (struktur parent-bin)
- **Otorisasi Pengguna**: Izin berbasis area dengan dukungan delegasi
- **Analitik Lanjutan**: Laporan aging, frekuensi penggunaan, dan analitik prediktif

## 🔌 Endpoint API

### Manajemen Produksi
- `GET /api/ins-rubber-batches/recents` - Mendapatkan 19 batch karet terbaru
- `GET /api/ins-rubber-batches/{code}` - Mendapatkan batch spesifik berdasarkan kode
- `GET /api/omv-recipes` - Mendapatkan semua resep OMV dengan langkah dan titik tangkap
- `POST /api/omv-metric` - Submit metrik produksi OMV dengan gambar dan data sensor

### Inventaris & Utilitas
- `GET /api/inv-tags` - Pencarian tag inventaris dengan autocomplete (mendukung parameter query `q`)
- `GET /api/time` - Mendapatkan timestamp sistem saat ini dalam UTC dengan formatting ISO8601

## 🏭 Integrasi Manufaktur

### Komunikasi Modbus TCP
Sistem terintegrasi dengan peralatan industri menggunakan package `aldas/modbus-tcp-client` untuk:
- Pengumpulan data real-time dari mesin produksi
- Pembacaan sensor otomatis
- Pemantauan status peralatan

### Pemantauan Energi
Kalkulasi kWh otomatis menggunakan:
- **Tegangan**: Standar 380V
- **Faktor Daya**: 0.85
- **Faktor Kalibrasi**: 0.8
- **Rumus**: √3 × Arus × Tegangan × Waktu × Faktor Daya × Faktor Kalibrasi / 1000

## 📊 Manajemen Data

### Pemrosesan Gambar
- Encoding/decoding gambar base64
- Penamaan file otomatis dengan identifier unik
- Dukungan untuk berbagai format gambar (PNG, JPEG)
- Penyimpanan aman di `/storage/app/public/omv-captures/`

### Pelacakan Komposisi
- Array komposisi 7-elemen untuk batch karet
- Rentang validasi: 0-5000 untuk setiap komponen
- Penyimpanan JSON untuk struktur data fleksibel

## 🔧 Layanan Background

### Sistem Queue
Konfigurasi queue worker untuk:
- Tugas pemrosesan gambar
- Operasi ekspor data
- Notifikasi email
- Generasi laporan

### Task Scheduler
Tugas otomatis meliputi:
- Pembersihan dan pengarsipan data
- Update aging inventaris
- Pemantauan kesehatan sistem
- Generasi laporan

## 🚦 Variabel Environment

Variabel konfigurasi kunci:
```env
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=caldera_cosmic

# Queue & Cache
QUEUE_CONNECTION=redis
CACHE_DRIVER=redis

# File Storage
FILESYSTEM_DISK=local

# Application
APP_NAME="Caldera Cosmic"
APP_ENV=production
APP_DEBUG=false
```

## 📈 Pemantauan & Analitik

- Dashboard metrik produksi real-time
- Analitik konsumsi energi
- Laporan efisiensi produksi
- Analisis turnover inventaris
- Pelacakan utilisasi peralatan

## 🏢 Tim Pengembang

**Digitalization Team**  
Manufacturing Modernization Department  
PT. TKG Taekwang Indonesia

## 📄 Lisensi

Software ini adalah properti PT. TKG Taekwang Indonesia dan ditujukan khusus untuk penggunaan internal.

## 🆘 Dukungan

Untuk dukungan teknis dan pertanyaan, silakan hubungi Digitalization Team melalui saluran internal.

---

**Catatan**: Sistem ini dirancang khusus untuk proses manufaktur sepatu dan terintegrasi dengan peralatan industri khusus. Pastikan semua dependensi hardware telah dikonfigurasi dengan benar sebelum deployment.
