# Current Task

## Scope

- Goal: Tambahkan view detail inbox support internal dan tampilkan detail pesan dalam modal pada page inbox yang sama.
- Type: implement
- Mode: balanced
- Allowed modules: controller/view support inbox internal, CSS modal minimal yang diperlukan, `.ai-context/CURRENT-TASK.md`
- Explicit exclusions: tidak menambah action reply/resolve, tidak mengubah flow submit tenant support, tidak mengubah route selain mempertahankan page inbox yang ada, tidak redesign shell internal

## Evidence and checkpoint

- Confirmed facts: data tenant feedback sekarang tersimpan di tabel `tenant_support_requests`.
- Confirmed facts: pola modul internal yang paling relevan di repo ini adalah dedicated controller + dedicated Blade view, bukan memasukkan semuanya ke `ResourcePageController`.
- Confirmed facts: repo sudah punya pola `show` query param untuk membuka detail dari list, dan sudah punya pola modal/drawer read-only yang dapat diadaptasi.
- Confirmed facts: inbox support saat ini hanya menampilkan daftar pesan dan belum punya selected state atau detail modal.
- Root cause or hypothesis: kebutuhan detail modal dapat dipenuhi dengan memuat selected row dari query `show`, menambahkan aksi `Detail`, lalu merender modal read-only pada Blade yang sama.
- Next verification: review diff terfokus, cek route tetap terdaftar, lalu validasi sintaks controller.

## Read ledger

| Path | Purpose / symbols | Changed since read? |
|---|---|---|
| `app/Http/Controllers/Internal/SupportInboxController.php` | query inbox support saat ini | re-read |
| `resources/views/internal/support/index.blade.php` | tabel inbox support saat ini | re-read |
| `resources/views/tenant/transactions/index.blade.php` | pola `show` + detail drawer/modal | re-read |
| `resources/views/tenant/accounts/index.blade.php` | pola modal close URL | re-read |
| `resources/css/app.css` | kelas modal yang bisa direuse | re-read |

## Change plan

- Files allowed to change: `.ai-context/CURRENT-TASK.md`, `app/Http/Controllers/Internal/SupportInboxController.php`, `resources/views/internal/support/index.blade.php`, dan CSS minimal bila perlu
- Contracts to preserve: page inbox support tetap route yang sama dan tetap read-only; daftar inbox existing tetap tampil
- Verification plan: review `git diff`, jalankan `php artisan route:list --name=support`, lalu `php -l` untuk controller inbox support
