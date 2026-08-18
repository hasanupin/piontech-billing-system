# Piontech Billing System — Docker Setup

Piontech Billing System adalah aplikasi billing berbasis web yang dibangun dengan Laravel dan Filament admin panel.

## Technology Stack

- **Language & Runtime:** PHP 8.4
- **Framework:** Laravel 13
- **Admin Panel:** Filament 5
- **Database:** MySQL 8.0
- **Caching & Session:** Redis
- **Web Server:** Apache 2.4
- **Runtime Environment:** Docker & Docker Compose

## Local Development Setup (Docker)

### 1. Prerequisites

- Docker Desktop / Docker Engine 24+
- Docker Compose V2

### 2. Environment Configuration

```bash
cp .env.example .env
```

`.env.example` sudah berisi koneksi ke MySQL & Redis milik `docker-compose` (diakses lewat host-mapped port, karena `php artisan serve` biasanya dijalankan di host, bukan di dalam container):

| Key | Value |
| --- | --- |
| DB_CONNECTION | mysql |
| DB_HOST | 127.0.0.1 |
| DB_PORT | 3309 |
| DB_DATABASE | piontech_billing |
| DB_USERNAME | laravel_user |
| DB_PASSWORD | laravel_password |

> Catatan: container `app` (dijalankan via `docker-compose up`) menimpa nilai ini lewat `environment:` di `docker-compose.yml` (`DB_HOST=db`, `DB_PORT=3306`) agar terhubung ke `db` lewat jaringan internal Docker — `.env` tidak perlu diubah untuk kasus ini.

### 3. Build and Start Containers

```bash
docker-compose up -d --build
```

Services launched:

| Service | Container | Description | Host Port |
| --- | --- | --- | --- |
| app | piontech_billing_app | Laravel + Apache | 8002 |
| db | piontech_billing_db | MySQL 8.0 | 3309 |
| redis | piontech_billing_redis | Redis cache | 6382 |

### 4. Install Dependencies & Generate Key

```bash
docker-compose exec app composer install
docker-compose exec app php artisan key:generate
```

### 5. Run Migrations & Seeders

```bash
docker-compose exec app php artisan migrate --seed
```

### 6. Set Writable Permissions

```bash
docker-compose exec app chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
docker-compose exec app chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
```

### 7. Access the Application

Visit `http://localhost:8002` in your browser.

### Useful Docker Commands

```bash
docker-compose logs -f            # Tail service logs
docker-compose exec app bash      # Shell into the PHP container
docker-compose exec app php artisan migrate:fresh --seed
docker-compose down               # Stop and remove containers
```

### Database & Cache

- MySQL DSN: `mysql://laravel_user:laravel_password@127.0.0.1:3309/piontech_billing`
- Redis: `redis://127.0.0.1:6382`

## Running the App Locally (MySQL/Redis Tetap di Docker)

> Butuh PHP 8.4 dan Composer di host; MySQL & Redis cukup jalan lewat `docker-compose up -d db redis`.

```bash
docker-compose up -d db redis
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

`.env.example` sudah menunjuk ke `127.0.0.1:3309` (MySQL) dan `127.0.0.1:6382` (Redis), jadi tidak perlu instalasi database lokal terpisah.

## Email & Queue Worker (wajib untuk fitur Lupa Password)

Email reset password dikirim sebagai queued notification (`QUEUE_CONNECTION=database`),
jadi **tanpa worker email tidak akan pernah terkirim**.

1. Isi SMTP asli di `.env` produksi — `MAIL_MAILER=smtp` beserta `MAIL_HOST`, `MAIL_PORT`,
   `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`.
2. Pastikan **`APP_URL`** berisi domain produksi — tautan reset dibangun dari nilai ini;
   kalau masih `http://localhost`, link di email mati.
3. Pasang worker sekali di server (`/etc/supervisor/conf.d/piontech-queue.conf`):

	```ini
	[program:piontech-queue]
	command=php /var/www/html/piontech-billing-system/artisan queue:work --tries=3 --sleep=3 --max-time=3600
	directory=/var/www/html/piontech-billing-system
	user=www-data
	autostart=true
	autorestart=true
	redirect_stderr=true
	stdout_logfile=/var/www/html/piontech-billing-system/storage/logs/queue.log
	```

	```bash
	sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start piontech-queue
	```

	Deploy sudah menjalankan `php artisan queue:restart` supaya worker memuat kode baru.

Uji lokal tanpa SMTP: `MAIL_MAILER=log` + `QUEUE_CONNECTION=sync`, link reset muncul di
`storage/logs/laravel.log`.

## Cron Scheduler (wajib untuk retensi Audit Log)

Audit Log menyimpan jejak aktivitas 6 bulan (`AuditLog::RETENTION_MONTHS`) lalu dibersihkan
`model:prune` yang dijadwalkan harian di `routes/console.php`. **Tanpa cron tabelnya tidak
pernah dipangkas.** Pasang sekali di server:

```bash
sudo crontab -u www-data -e
```

```cron
* * * * * cd /var/www/html/piontech-billing-system && php artisan schedule:run >> /dev/null 2>&1
```

Cek manual tanpa menunggu cron: `php artisan schedule:run` atau langsung
`php artisan model:prune --model="App\Models\AuditLog"`.

## Troubleshooting

- Rebuild containers:
	```bash
	docker-compose down
	docker-compose up -d --build
	```
- Reset database:
	```bash
	docker-compose exec app php artisan migrate:fresh --seed
	```
- Permission issues:
	```bash
	docker-compose exec app chown -R www-data:www-data /var/www/html
	docker-compose exec app chmod -R 775 /var/www/html/storage
	```
