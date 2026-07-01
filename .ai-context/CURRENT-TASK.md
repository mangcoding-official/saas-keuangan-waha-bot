# Current Task

## Scope

- Goal: Perbaiki error 500 pada halaman super admin invite management.
- Type: debug
- Mode: balanced
- Allowed modules: migrations, internal invite controller, route internal, verification command output
- Explicit exclusions: tidak mengubah modul tenant lain, tidak redesign UI, tidak menambah fitur baru

## Evidence and checkpoint

- Reproduction/evidence: `GET /internal/invites` gagal dengan `Base table or view not found` untuk tabel `owner_registration_invites`.
- Confirmed facts: file migration `2026_07_01_090000_create_owner_registration_invites_table.php` sudah ada di repo; `php artisan migrate:status` menandainya `Pending`; aplikasi aktif memakai `DB_CONNECTION=mysql` dan `DB_DATABASE=saas_waha_bot`.
- Root cause or hypothesis: kode invite sudah terdeploy ke aplikasi, tetapi migrasi tabel invite belum diterapkan ke database aktif.
- Next verification: jalankan migrasi pending lalu pastikan status migrasi berubah menjadi `Ran` dan route invite bisa bootstrap tanpa error schema.

## Read ledger

| Path | Purpose / symbols | Changed since read? |
|---|---|---|
| `app/Http/Controllers/Internal/OwnerRegistrationInviteController.php` | lokasi query error | no |
| `database/migrations/2026_07_01_090000_create_owner_registration_invites_table.php` | schema invite | no |
| `routes/internal.php` | entry route `/internal/invites` | no |
| `.env` | database aktif | no |

## Change plan

- Files allowed to change: `.ai-context/CURRENT-TASK.md` saja bila cukup; kode aplikasi hanya jika migrasi tidak cukup
- Contracts to preserve: route dan struktur module invite yang sudah ada
- Verification plan: `php artisan migrate`, `php artisan migrate:status`, dan pengecekan route/bootstrap

## Completion

- Result: error 500 pada `/internal/invites` diperbaiki dengan menjalankan migrasi invite setelah nama foreign key di migrasi dipendekkan agar kompatibel dengan batas identifier MySQL.
- Tests/checks: `php -l database/migrations/2026_07_01_090000_create_owner_registration_invites_table.php`; `php artisan migrate --force`; `php artisan migrate:status`; `php artisan tinker --execute="dump(Schema::hasTable('owner_registration_invites'))"`.
- Remaining risk: verifikasi browser-authenticated belum dijalankan dari agent; perlu refresh halaman invite di browser aktif untuk memastikan UI tampil normal tanpa session-specific issue.
