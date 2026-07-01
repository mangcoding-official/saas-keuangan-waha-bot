# Current Task

## Scope

- Goal: Tambahkan halaman Profil member yang memakai data nyata tenant user dan workspace agar flow member saat alpha tidak terasa placeholder.
- Type: implement
- Mode: deep
- Allowed modules: tenant route/controller/view profile, shared tenant CSS yang relevan, test feature tenant profile, `.ai-context/CURRENT-TASK.md`
- Explicit exclusions: tidak mengubah flow invite member, auth login, dashboard owner/member lain, service invitation, atau placeholder tenant module selain profile

## Evidence and checkpoint

- Confirmed facts: route `tenant.profile.show` sudah ada, tetapi masih diarahkan ke `ResourcePageController` placeholder umum.
- Confirmed facts: navigasi member memang menampilkan menu `Profil`, sehingga member yang login saat ini berakhir di halaman shell placeholder.
- Confirmed facts: data nyata yang bisa langsung dipakai sudah tersedia pada `tenant_users` dan `tenants`, termasuk nama, role, email, WhatsApp, status, verifikasi, waktu bergabung, terakhir aktif, timezone, plan, dan service status.
- Root cause or hypothesis: flow member terasa placeholder karena halaman profil belum dipisah ke implementasi khusus dan belum membaca data auth user/workspace yang sudah tersedia.
- Next verification: cek diff route/controller/view/CSS, jalankan feature test untuk render halaman profil tenant, dan lint PHP file yang berubah.

## Read ledger

| Path | Purpose / symbols | Changed since read? |
|---|---|---|
| `routes/app.php` | route `tenant.profile.show` | re-read |
| `app/Http/Controllers/Tenant/ResourcePageController.php` | placeholder profile lama | re-read |
| `app/Http/Controllers/Tenant/MemberController.php` | mapping label/status anggota | re-read |
| `app/Models/TenantUser.php` | field dan relasi user tenant | re-read |
| `app/Models/Tenant.php` | field workspace tenant | re-read |
| `database/migrations/2026_06_19_220100_create_domain_tables.php` | kontrak schema tenant + tenant_users | re-read |
| `resources/views/tenant/resource-page.blade.php` | shell placeholder saat ini | re-read |
| `resources/views/tenant/members/index.blade.php` | bahasa visual anggota | re-read |
| `resources/views/layouts/tenant.blade.php` | shell tenant shared | re-read |
| `resources/views/tenant/partials/topbar.blade.php` | topbar auth user | re-read |
| `app/Support/Navigation/TenantNavigation.php` | navigasi member ke profile | re-read |
| `resources/css/app.css` | family style tenant/member | re-read |
| `phpunit.xml` | konfigurasi DB testing | re-read |

## Change plan

- Files allowed to change: `.ai-context/CURRENT-TASK.md`, `routes/app.php`, `app/Http/Controllers/Tenant/ResourcePageController.php`, `app/Http/Controllers/Tenant/ProfileController.php`, `resources/views/tenant/profile/show.blade.php`, `resources/css/app.css`, `tests/Feature/TenantProfilePageTest.php`
- Contracts to preserve: URL profile tetap `/app/profile`, navigasi tenant tetap sama, auth guard `web` tetap dipakai, placeholder modul lain tetap utuh
- Verification plan: review `git diff`, jalankan feature test profile bila environment mendukung sqlite testing, dan lint PHP file yang diubah
