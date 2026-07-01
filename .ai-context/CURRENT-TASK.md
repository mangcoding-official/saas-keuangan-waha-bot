# Current Task

## Scope

- Goal: Perbaiki error submit form contact CS agar tidak 500 saat menyimpan support request dan pastikan environment aktif sudah siap menerima submit.
- Type: debug
- Mode: balanced
- Allowed modules: controller/model/migration support tenant, verification command terkait migration status, `.ai-context/CURRENT-TASK.md`
- Explicit exclusions: tidak mengubah alur UX support page, tidak redesign dashboard tenant, tidak menyentuh modul invite/onboarding

## Evidence and checkpoint

- Confirmed facts: submit `POST /app/support` gagal di `SupportController::store()` saat `TenantSupportRequest::query()->create(...)` dieksekusi.
- Confirmed facts: migration `2026_07_01_150000_create_tenant_support_requests_table` masih `Pending` pada database aktif, sehingga tabel `tenant_support_requests` memang belum ada.
- Confirmed facts: ini adalah masalah environment + hard failure path; migration belum diterapkan dan controller belum punya fallback ketika tabel support belum siap.
- Root cause or hypothesis: runtime 500 terjadi karena form langsung menulis ke tabel yang belum dimigrasikan pada DB MySQL aktif.
- Next verification: tambahkan guard ringan di controller, jalankan migration pending, lalu verifikasi submit support dan route/migration status.

## Read ledger

| Path | Purpose / symbols | Changed since read? |
|---|---|---|
| `app/Http/Controllers/Tenant/SupportController.php` | jalur submit support request | re-read |
| `database/migrations/2026_07_01_150000_create_tenant_support_requests_table.php` | tabel target penyimpanan support | re-read |
| `tests/Feature/TenantSupportPageTest.php` | coverage support flow | re-read |
| `php artisan migrate:status` | status migration aktif | re-read |

## Change plan

- Files allowed to change: `.ai-context/CURRENT-TASK.md`, `app/Http/Controllers/Tenant/SupportController.php`, dan state database via migration pending
- Contracts to preserve: submit sukses tetap menyimpan ke `tenant_support_requests`; jika tabel belum siap, user mendapat flash warning alih-alih 500
- Verification plan: review `git diff`, jalankan `php artisan migrate`, cek `migrate:status`, lalu verifikasi submit/route terkait
