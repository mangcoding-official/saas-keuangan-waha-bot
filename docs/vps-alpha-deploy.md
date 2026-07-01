# Deploy Alpha ke VPS

Dokumen ini menyiapkan app Laravel di domain `macau.mcdigits.com` untuk closed alpha sesuai kontrak `public app, private onboarding`.

## Asumsi

- VPS memakai Ubuntu 24.04.
- Domain `macau.mcdigits.com` sudah mengarah ke IP VPS.
- App source akan ditempatkan di `/var/www/saas-keuangan-waha-bot/current`.
- Database memakai MySQL atau MariaDB lokal.
- PHP-FPM yang dipakai adalah `php8.3-fpm`.
- WAHA dijalankan sebagai service terpisah dan diakses dari app lewat `http://127.0.0.1:3000`.

## Komponen minimum

- Nginx
- PHP 8.3 FPM + extension `mysql`, `mbstring`, `xml`, `curl`, `zip`, `bcmath`, `intl`, `gd`, `sqlite3`
- Composer
- Node.js 20 LTS
- MySQL atau MariaDB
- Git
- Certbot

## Struktur target

```text
/var/www/saas-keuangan-waha-bot/
  current/
```

## 1. Install dependency server

```bash
sudo apt update
sudo apt install -y nginx git unzip curl software-properties-common
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl php8.3-gd php8.3-sqlite3
sudo apt install -y mariadb-server
sudo apt install -y certbot python3-certbot-nginx
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
cd /tmp
curl -sS https://getcomposer.org/installer -o composer-setup.php
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
```

## 2. Siapkan database

```bash
sudo mysql
```

```sql
CREATE DATABASE saas_waha_bot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'saas_waha_bot'@'127.0.0.1' IDENTIFIED BY 'GANTI_PASSWORD_DB';
GRANT ALL PRIVILEGES ON saas_waha_bot.* TO 'saas_waha_bot'@'127.0.0.1';
FLUSH PRIVILEGES;
EXIT;
```

Jika koneksi memakai `localhost` dan bukan `127.0.0.1`, buat user untuk host `localhost` juga.

## 3. Upload code

```bash
sudo mkdir -p /var/www/saas-keuangan-waha-bot
sudo chown -R $USER:$USER /var/www/saas-keuangan-waha-bot
cd /var/www/saas-keuangan-waha-bot
git clone <URL_REPO_GIT> current
cd current
composer install --no-dev --optimize-autoloader
npm ci
npm run build
cp .env.production.example .env
php artisan key:generate
```

## 4. Isi environment production

Edit file `.env` lalu isi minimal:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://macau.mcdigits.com

DB_DATABASE=saas_waha_bot
DB_USERNAME=saas_waha_bot
DB_PASSWORD=GANTI_PASSWORD_DB

SESSION_DOMAIN=macau.mcdigits.com
SESSION_SECURE_COOKIE=true

QUEUE_CONNECTION=database
CACHE_STORE=database

WAHA_BASE_URL=http://127.0.0.1:3000
WAHA_API_KEY=GANTI_API_KEY_WAHA
WAHA_DEFAULT_SESSION=default

SUPPORT_WHATSAPP_NUMBER=628xxxxxxxxxx
SUPPORT_FEEDBACK_FORM_URL=
```

Catatan:

- `APP_URL` harus `https://macau.mcdigits.com`.
- Karena alpha ini punya register, login, dan internal dashboard, cookie secure harus aktif.
- Bila belum memakai Redis, queue dan cache database tetap valid untuk VPS 2 GB.

## 5. Permission storage

```bash
sudo chown -R www-data:www-data /var/www/saas-keuangan-waha-bot/current
sudo find /var/www/saas-keuangan-waha-bot/current -type f -exec chmod 644 {} \;
sudo find /var/www/saas-keuangan-waha-bot/current -type d -exec chmod 755 {} \;
sudo chmod -R 775 /var/www/saas-keuangan-waha-bot/current/storage
sudo chmod -R 775 /var/www/saas-keuangan-waha-bot/current/bootstrap/cache
php artisan storage:link
```

## 6. Migration, cache, dan seed awal

```bash
cd /var/www/saas-keuangan-waha-bot/current
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Seeder default akan membuat akun demo. Untuk alpha real, segera ganti password akun production atau buat akun admin baru lalu hapus data demo bila tidak dibutuhkan.

## 7. Nginx

Copy contoh config dari repo:

```bash
sudo cp deploy/nginx/macau.mcdigits.com.conf /etc/nginx/sites-available/macau.mcdigits.com
sudo ln -s /etc/nginx/sites-available/macau.mcdigits.com /etc/nginx/sites-enabled/macau.mcdigits.com
sudo nginx -t
sudo systemctl reload nginx
```

## 8. SSL

```bash
sudo certbot --nginx -d macau.mcdigits.com
```

Jika domain Cloudflare masih diproxy dan challenge gagal, nonaktifkan proxy sementara atau pakai DNS challenge Cloudflare.

## 9. Queue worker dan scheduler

Copy unit dari repo:

```bash
sudo cp deploy/systemd/saas-keuangan-waha-bot-queue.service /etc/systemd/system/
sudo cp deploy/systemd/saas-keuangan-waha-bot-scheduler.service /etc/systemd/system/
sudo cp deploy/systemd/saas-keuangan-waha-bot-scheduler.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now saas-keuangan-waha-bot-queue.service
sudo systemctl enable --now saas-keuangan-waha-bot-scheduler.timer
```

Verifikasi:

```bash
systemctl status saas-keuangan-waha-bot-queue.service
systemctl status saas-keuangan-waha-bot-scheduler.timer
```

## 10. WAHA integration

App ini mengirim invite WhatsApp dan aktivasi lewat service WAHA, jadi sebelum smoke test pastikan:

- WAHA service hidup di VPS atau host private yang dapat diakses VPS.
- `WAHA_BASE_URL` dan `WAHA_API_KEY` valid.
- session `default` memang ada di WAHA.
- webhook WAHA diarahkan ke `https://macau.mcdigits.com/webhooks/waha`.
- ada `bot_instances` default aktif di database.

Jika belum ada bot aktif, seeder membuat bot `default` dasar, tetapi koneksi WAHA tetap harus benar-benar dihubungkan dari sisi service WAHA.

## 11. Smoke test alpha

Urutan cek minimum:

1. Buka `https://macau.mcdigits.com`.
2. Buka `https://macau.mcdigits.com/register`.
3. Login internal di `https://macau.mcdigits.com/internal/login`.
4. Buat invite owner dari dashboard internal.
5. Uji kirim ulang invite WhatsApp.
6. Selesaikan satu registrasi owner.
7. Login tenant owner.
8. Buka profile dan support page.
9. Pastikan queue worker dan scheduler tetap hidup.

## 12. Post-deploy setiap update

```bash
cd /var/www/saas-keuangan-waha-bot/current
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo systemctl restart php8.3-fpm
sudo systemctl restart saas-keuangan-waha-bot-queue.service
```

## Risiko yang perlu dijaga

- Jangan biarkan akun demo dari seeder tetap dipakai di production.
- Jangan aktifkan open signup; alpha ini masih invite-gated.
- Jangan anggap WAHA sehat hanya karena app hidup; webhook dan session WAHA harus benar-benar aktif.
- Jangan lupa backup database sebelum update schema berikutnya.
