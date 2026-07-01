# Deploy Alpha ke VPS

Dokumen ini adalah panduan deploy yang sudah disesuaikan dengan setup real alpha yang berhasil dijalankan di:

- app Laravel di `/var/www/macau`
- WAHA di `/opt/waha`
- domain `macau.mcdigits.com`
- Ubuntu 24.04

Target deploy ini adalah `closed alpha` dengan model `public app, private onboarding`.

## Ringkasan arsitektur

Komponen yang dipakai:

- Nginx sebagai web server
- PHP 8.3 FPM untuk Laravel
- MariaDB lokal untuk database app
- WAHA via Docker di server yang sama
- Certbot untuk SSL
- queue worker + scheduler via `systemd`

Port yang dipakai:

- `80` untuk HTTP
- `443` untuk HTTPS
- `3000` untuk WAHA internal, bind ke `127.0.0.1`

## Struktur final server

```text
/var/www/macau
/opt/waha
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
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER
sudo apt install -y docker-compose-plugin
cd /tmp
curl -sS https://getcomposer.org/installer -o composer-setup.php
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
```

Setelah menambahkan user ke grup `docker`, logout lalu login lagi.

## 2. Siapkan database MariaDB

Masuk ke MariaDB:

```bash
sudo mysql
```

Buat database dan user app. Contoh final yang dipakai:

```sql
CREATE DATABASE wamaca CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'FrostyVanguard'@'127.0.0.1' IDENTIFIED BY 'TempPass12345';
CREATE USER 'FrostyVanguard'@'localhost' IDENTIFIED BY 'TempPass12345';
GRANT ALL PRIVILEGES ON wamaca.* TO 'FrostyVanguard'@'127.0.0.1';
GRANT ALL PRIVILEGES ON wamaca.* TO 'FrostyVanguard'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Catatan penting:

- buat user untuk `127.0.0.1` dan `localhost` sekaligus
- jika password mengandung karakter spesial, `.env` Laravel harus memakai tanda kutip
- jika sedang debugging koneksi, lebih aman pakai password sementara alfanumerik dulu

## 3. Upload code app

```bash
sudo mkdir -p /var/www/macau
sudo chown -R $USER:$USER /var/www/macau
cd /var/www
git clone <URL_REPO_GIT> macau
cd /var/www/macau
composer install --no-dev --optimize-autoloader
npm ci
npm run build
cp .env.production.example .env
```

Jika repo kamu tidak di-clone langsung ke `/var/www/macau`, sesuaikan path Nginx dan service `systemd` agar konsisten.

## 4. Install dan jalankan WAHA di `/opt/waha`

```bash
sudo mkdir -p /opt/waha
sudo chown -R $USER:$USER /opt/waha
cd /opt/waha
mkdir -p data
```

Buat file `docker-compose.yml`:

```yaml
services:
  waha:
    image: devlikeapro/waha:latest
    container_name: waha
    restart: unless-stopped
    ports:
      - "127.0.0.1:3000:3000"
    environment:
      WAHA_API_KEY: "GANTI_API_KEY_WAHA"
      WAHA_DASHBOARD_USERNAME: "admin"
      WAHA_DASHBOARD_PASSWORD: "GANTI_PASSWORD_DASHBOARD_WAHA"
      WAHA_PRINT_QR: "true"
      WAHA_SESSION_STORE: "FILE"
    volumes:
      - ./data:/app/.sessions
```

Jalankan WAHA:

```bash
cd /opt/waha
docker compose up -d
docker compose logs -f waha
```

Verifikasi:

```bash
curl http://127.0.0.1:3000
```

Catatan penting:

- kredensial dashboard WAHA bisa digenerate otomatis, tapi `WAHA_API_KEY` harus kamu tentukan sendiri
- samakan `WAHA_API_KEY` antara container WAHA dan `.env` Laravel
- port `3000` sengaja bind ke `127.0.0.1` agar tidak terbuka ke publik

## 5. Isi `.env` production

Template dasar sudah ada di [.env.production.example](/D:/Mangcoding%20Project/SaaS%20System%20Keuangan%20By%20WAHA%20BOT/saas-keuangan-waha-bot/.env.production.example:1), tapi untuk server final ini isi minimalnya harus mengikuti pola berikut:

```dotenv
APP_NAME="SaaS Keuangan WAHA"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://macau.mcdigits.com
APP_TIMEZONE=Asia/Jakarta

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=wamaca
DB_USERNAME=FrostyVanguard
DB_PASSWORD="TempPass12345"

SESSION_DRIVER=database
SESSION_DOMAIN=macau.mcdigits.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

QUEUE_CONNECTION=database
CACHE_STORE=database

WAHA_BASE_URL=http://127.0.0.1:3000
WAHA_API_KEY="GANTI_API_KEY_WAHA"
WAHA_DASHBOARD_USERNAME="admin"
WAHA_DASHBOARD_PASSWORD="GANTI_PASSWORD_DASHBOARD_WAHA"
WAHA_DEFAULT_SESSION=default

SUPPORT_WHATSAPP_NUMBER=628xxxxxxxxxx
SUPPORT_FEEDBACK_FORM_URL=
```

Catatan penting:

- `APP_URL` harus persis `https://macau.mcdigits.com`
- `DB_HOST=127.0.0.1` lebih aman untuk setup ini
- jika password DB atau WAHA ada karakter spesial, bungkus dengan tanda kutip
- jangan biarkan ada duplikasi `DB_*` atau `WAHA_*` di `.env`

Verifikasi cepat:

```bash
grep -n "^DB_" .env
grep -n "^WAHA_" .env
grep -n "^APP_KEY" .env
```

## 6. Generate `APP_KEY`

Ini wajib. Tanpa `APP_KEY`, Laravel akan error meskipun web server dan database sudah benar.

Jalankan:

```bash
cd /var/www/macau
php artisan key:generate
```

Jika `artisan` gagal karena cache lama atau konfigurasi lama, hapus dulu cache bootstrap:

```bash
rm -f bootstrap/cache/*.php
php artisan key:generate
```

## 7. Permission storage dan bootstrap cache

```bash
cd /var/www/macau
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
php artisan storage:link
```

Kalau mau ketat tapi aman:

```bash
sudo find /var/www/macau -type f -exec chmod 644 {} \;
sudo find /var/www/macau -type d -exec chmod 755 {} \;
sudo chmod -R 775 /var/www/macau/storage
sudo chmod -R 775 /var/www/macau/bootstrap/cache
```

## 8. Bersihkan cache Laravel sebelum migrate

Saat setup real, cache config lama bisa tetap terbaca walaupun `.env` sudah benar. Karena itu, sebelum migrate lakukan:

```bash
cd /var/www/macau
rm -f bootstrap/cache/*.php
php artisan optimize:clear
```

Jika `optimize:clear` gagal saat app belum sehat, minimal:

```bash
rm -f bootstrap/cache/*.php
```

## 9. Verifikasi koneksi database dari PHP

Sebelum migrate, cek dulu koneksi DB langsung dari PHP:

```bash
cd /var/www/macau
php -r "new PDO('mysql:host=127.0.0.1;port=3306;dbname=wamaca','FrostyVanguard','TempPass12345'); echo 'ok'.PHP_EOL;"
```

Kalau hasilnya `ok`, berarti user DB, password, grant, dan extension PHP MySQL sudah benar.

## 10. Jalankan migration dan seed awal

```bash
cd /var/www/macau
php artisan migrate --force
php artisan db:seed --force
```

Setelah itu cache ulang:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Seeder default membuat data demo. Untuk alpha real:

- ganti password akun demo atau buat akun production baru
- hapus data demo jika tidak ingin tercampur dengan user alpha

## 11. Siapkan Nginx

Copy file contoh dari repo:

```bash
sudo cp deploy/nginx/macau.mcdigits.com.conf /etc/nginx/sites-available/macau.mcdigits.com
sudo ln -s /etc/nginx/sites-available/macau.mcdigits.com /etc/nginx/sites-enabled/macau.mcdigits.com
```

Untuk setup final `/var/www/macau`, pastikan `root` di config Nginx menunjuk ke:

```nginx
root /var/www/macau/public;
```

Nonaktifkan default site agar request ke domain tidak jatuh ke halaman default Nginx:

```bash
sudo rm -f /etc/nginx/sites-enabled/default
```

Lalu verifikasi:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

Tes cepat:

```bash
curl -I http://macau.mcdigits.com
```

## 12. Pasang SSL

Pastikan record DNS `A` untuk `macau.mcdigits.com` sudah mengarah ke IP VPS.

Kalau Cloudflare dipakai:

- sementara set `DNS only` dulu saat menjalankan Certbot
- pastikan domain yang dipakai benar-benar `macau.mcdigits.com`
- jangan typo menjadi `macau.digits.com` atau `macau.mcdigit.com`

Jalankan:

```bash
sudo certbot --nginx -d macau.mcdigits.com
```

Tes cepat:

```bash
curl -I https://macau.mcdigits.com
```

## 13. Pasang queue worker dan scheduler

Copy unit file dari repo:

```bash
sudo cp deploy/systemd/saas-keuangan-waha-bot-queue.service /etc/systemd/system/
sudo cp deploy/systemd/saas-keuangan-waha-bot-scheduler.service /etc/systemd/system/
sudo cp deploy/systemd/saas-keuangan-waha-bot-scheduler.timer /etc/systemd/system/
```

Karena app final ada di `/var/www/macau`, ubah `WorkingDirectory` di file service agar menunjuk ke:

```text
/var/www/macau
```

Lalu:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now saas-keuangan-waha-bot-queue.service
sudo systemctl enable --now saas-keuangan-waha-bot-scheduler.timer
```

Verifikasi:

```bash
systemctl status saas-keuangan-waha-bot-queue.service
systemctl status saas-keuangan-waha-bot-scheduler.timer
```

## 14. Hubungkan session dan webhook WAHA

Setelah WAHA hidup:

1. pastikan `WAHA_API_KEY` di Laravel sama dengan container WAHA
2. buat atau pakai session `default`
3. login WhatsApp pada session itu sampai `connected`
4. arahkan webhook ke `https://macau.mcdigits.com/webhooks/waha`
5. pastikan `bot_instances.waha_instance_key` cocok dengan session yang dipakai

Kalau ingin verifikasi dashboard WAHA dari browser, gunakan kredensial dashboard WAHA, bukan `WAHA_API_KEY`.

## 15. Checklist troubleshooting yang terbukti penting

### Jika `php artisan migrate --force` gagal karena `Access denied`

Urutan cek:

```bash
grep -n "^DB_" .env
rm -f bootstrap/cache/*.php
php -r "require 'vendor/autoload.php'; \$app=require 'bootstrap/app.php'; \$kernel=\$app->make(Illuminate\Contracts\Console\Kernel::class); \$kernel->bootstrap(); var_export(config('database.connections.mysql'));"
php -r "new PDO('mysql:host=127.0.0.1;port=3306;dbname=wamaca','FrostyVanguard','TempPass12345'); echo 'ok'.PHP_EOL;"
```

Kalau `PDO` langsung `ok` tetapi Laravel gagal, hampir pasti cache config lama belum dibersihkan atau `.env` masih menyimpan nilai lama.

### Jika domain malah menampilkan halaman default Nginx

Biasanya:

- site domain belum aktif
- default site belum dimatikan
- `root` masih salah

### Jika browser menunjukkan `500 Server Error`

Fokus ke log Laravel:

```bash
tail -n 100 storage/logs/laravel.log
```

### Jika browser timeout

Fokus ke listener dan firewall:

```bash
sudo ss -tulpn | grep -E ':80|:443'
sudo ufw status
```

## 16. Smoke test alpha

Urutan cek minimum:

1. buka `https://macau.mcdigits.com`
2. buka `https://macau.mcdigits.com/register`
3. login internal di `https://macau.mcdigits.com/internal/login`
4. buat invite owner dari dashboard internal
5. uji kirim ulang invite WhatsApp
6. selesaikan satu registrasi owner
7. login tenant owner
8. buka profile dan support page
9. cek queue worker dan scheduler tetap hidup
10. cek WAHA session tetap connected

## 17. Post-deploy untuk update berikutnya

```bash
cd /var/www/macau
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci
npm run build
rm -f bootstrap/cache/*.php
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo systemctl restart php8.3-fpm
sudo systemctl restart saas-keuangan-waha-bot-queue.service
```

WAHA tidak perlu direstart pada setiap deploy Laravel jika konfigurasi WAHA tidak berubah.

## 18. Risiko operasional

- jangan aktifkan open signup; alpha ini tetap invite-gated
- jangan biarkan akun demo dari seeder dipakai mentah di production
- jangan lupa backup database sebelum update schema
- jangan expose port WAHA ke publik
- jangan lupa samakan `WAHA_API_KEY` antara WAHA dan Laravel
