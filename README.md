# WegoPresence

Sistem manajemen kehadiran (attendance) karyawan berbasis web.

## Stack

- Laravel 13 / PHP 8.4
- Inertia.js + React + TypeScript
- Vite
- Tailwind CSS
- Laravel Fortify (authentication)
- Laravel Wayfinder (typed routes untuk frontend)
- PostgreSQL (dev), SQLite :memory: (test)

## Authentication

- Login menggunakan **NIP + password** (bukan email)
- Logout (POST)
- Forgot / Reset password
- Fortify sebagai authentication engine
- Public registration, email verification, 2FA, dan passkey **tidak aktif**

## Roles

- `super_admin` — akses penuh (dashboard 8 kartu statistik, manajemen semua user)
- `admin` — dashboard ringkas, manajemen user dengan role `user` saja
- `user` — dashboard personal: status kehadiran hari ini, check-in/out, riwayat 5 terakhir

## Fitur

### User Management

- List, search, sorting, pagination
- Create / edit / soft delete user
- Assign role + office
- Super admin boleh mengubah/menghapus siapa saja; admin dibatasi (tidak bisa sentuh super_admin)

### Attendance

- Check In / Check Out (sekali per hari)
- Status `late` / `on_time` berdasarkan jam mulai kantor
- Riwayat attendance
- GPS wajib saat **check-in**: koordinat browser → reverse geocoding (Nominatim) → validasi kota terhadap `Office.city` via `CityNormalizer`
- Check-out **tidak** meminta GPS
- Latitude/longitude tersimpan di record attendance

### Dashboard

- Statistik attendance (harian, mingguan, 7 hari trend)
- Recent activity (attendance, leave, permission, sick leave, complaint)
- Variasi konten per role

### Permission (Izin)

- Workflow pengajuan izin: `pending` → `approved` / `rejected` / `cancelled`
- User (employee): membuat pengajuan (type `personal`/`official`, tanggal, alasan), melihat daftar miliknya, melihat detail, membatalkan hanya jika masih `pending`
- Admin & Super Admin: melihat semua pengajuan, filter status, search (alasan/nama), approve/reject dengan catatan opsional
- Semua data sensitif ditentukan server: `user_id` dari session, `status` selalu `pending` saat create
- Validasi: tanggal wajib valid (end >= start, start tidak boleh di masa lalu), alasan wajib, type wajib
- Authorization via `PermissionPolicy` (owner-only untuk cancel, admin/super untuk approve/reject, hanya status pending yang bisa diubah)
- Business logic di `PermissionService`; controller tipis
- Dashboard terintegrasi: kartu `permission_today` (approved yang mencakup hari ini) dan `pending_approval` (jumlah pending) + activity feed permission

### Lainnya

- Leave / Permission / Sick Leave / Attendance Complaint (model + migration + tampil di aktivitas dashboard)
- Office management (kota, jam kerja `start_time`/`end_time`)

## Development

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
php artisan migrate --seed

php artisan dev        # server + vite
npm run dev            # atau jalankan vite saja
```

## Quality Checks

```bash
php artisan test
vendor/bin/pint --test
npm run types:check
npm run lint:check
npm run build
```

## Catatan Schema

- `users` memakai schema kustom: `role_id`, `office_id`, `nip`, `name`, `position`, `email`, `phone`, `join_date`, `city`, `status`, `password`, soft deletes
- Kolom starter-kit (`email_verified_at`, `two_factor_*`) tidak ada
- Migration `2026_08_11_000001_align_users_and_password_resets_schema` menambahkan kolom `password`/`phone`/`status` dan tabel `password_reset_tokens` secara kondisional (aman di database lama maupun baru)
