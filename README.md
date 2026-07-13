# Car Rental Backend

Sistem backend untuk aplikasi penyewaan kendaraan yang modern dan scalable. Dibangun dengan Laravel dan Filament Admin Panel.

## Deskripsi Proyek

Car Rental Backend adalah sistem manajemen komprehensif untuk bisnis penyewaan kendaraan. Aplikasi ini menyediakan:

- **Admin Panel** dengan Filament untuk manajemen data
- **RESTful API** untuk aplikasi frontend
- **Manajemen Inventori Kendaraan** yang lengkap
- **Sistem Kategori & Produk** yang terstruktur
- **Manajemen Pengguna & Autentikasi**
- **Testimonial & Rating Pelanggan**
- **Banner & Promosi Dinamis**
- **Manajemen Kontak & Inquiry Pelanggan**

## Fitur Utama

- ✅ Admin Panel berbasis Filament dengan UI yang intuitif
- ✅ Soft Delete untuk data management yang aman
- ✅ API RESTful yang well-documented
- ✅ Sistem autentikasi dengan middleware
- ✅ Database migrations yang terstruktur
- ✅ Model relationships yang tepat
- ✅ Seed data untuk development
- ✅ Lightweight dan performant

## Tech Stack

- **Backend Framework**: [Laravel 11](https://laravel.com)
- **Admin Panel**: [Filament](https://filamentphp.com)
- **ORM**: [Eloquent](https://laravel.com/docs/eloquent)
- **Database**: MySQL/PostgreSQL
- **Frontend Build Tool**: [Vite](https://vitejs.dev)
- **Real-time**: [Livewire](https://livewire.laravel.com)
- **Testing**: PHPUnit

## Instalasi & Setup

### Prasyarat

- PHP 8.2 atau lebih tinggi
- Composer
- Node.js & npm (untuk Vite)
- MySQL/PostgreSQL

### Langkah Instalasi

1. **Clone repository**
   ```bash
   git clone <repository-url>
   cd car-rental-backend
   ```

2. **Install dependencies PHP**
   ```bash
   composer install
   ```

3. **Setup environment**
   ```bash
   cp .env.example .env
   ```

4. **Generate application key**
   ```bash
   php artisan key:generate
   ```

5. **Konfigurasi database di file `.env`**
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=car_rental
   DB_USERNAME=root
   DB_PASSWORD=
   ```

6. **Jalankan migrations**
   ```bash
   php artisan migrate
   ```

7. **Seed database (optional)**
   ```bash
   php artisan db:seed --class=RentalCmsSeeder
   ```

8. **Install dependencies Node.js**
   ```bash
   npm install
   ```

9. **Build assets**
   ```bash
   npm run build
   ```

10. **Jalankan development server**
    ```bash
    php artisan serve
    ```

Akses aplikasi di `http://localhost:8000`

## Struktur Database

### Model Utama

- **User** - Pengguna sistem (admin, customer)
- **Category** - Kategori kendaraan
- **Product** - Data kendaraan/produk penyewaan
- **Banner** - Banner promosi
- **Testimonial** - Review & testimonial pelanggan
- **Setting** - Pengaturan sistem
- **Contact** - Data inquiry pelanggan

## Dokumentasi API

API endpoint tersedia di `/api/` dengan format JSON.

### Contoh Endpoint

```
GET    /api/products              - Dapatkan semua produk
GET    /api/products/{id}         - Dapatkan detail produk
GET    /api/categories            - Dapatkan semua kategori
GET    /api/testimonials          - Dapatkan testimonial
POST   /api/contacts              - Submit kontak inquiry
```

Untuk dokumentasi lengkap, lihat file `routes/api.php`

## Panduan Penggunaan

### Admin Panel

Akses admin panel di `/admin` setelah login. Filament menyediakan interface untuk:

- Manajemen Produk/Kendaraan
- Manajemen Kategori
- Manajemen Pengguna
- Manajemen Konten (Banner, Testimonial)
- Manajemen Inquiry Kontak

### Menggunakan Artisan Commands

```bash
# Lihat semua command
php artisan

# Run tests
php artisan test

# Clear cache
php artisan cache:clear

# Jalankan migration tertentu
php artisan migrate --path=database/migrations/2025_01_01_000003_create_categories_table.php
```

## Kontribusi

Kami menerima kontribusi dari siapa saja. Berikut langkah-langkahnya:

1. Fork repository ini
2. Buat branch fitur (`git checkout -b feature/AmazingFeature`)
3. Commit changes (`git commit -m 'Add some AmazingFeature'`)
4. Push ke branch (`git push origin feature/AmazingFeature`)
5. Buat Pull Request

Pastikan kode Anda mengikuti standar project dan lolos semua test.

## Lisensi

Proyek ini menggunakan lisensi MIT. Lihat file [LICENSE](LICENSE) untuk detail lebih lanjut.
