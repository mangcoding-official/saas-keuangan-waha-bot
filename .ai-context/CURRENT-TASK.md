# Current Task

## Scope

- Goal: Perbaiki flow halaman tenant transactions supaya tabel default menampilkan semua transaksi terbaru, card overview tetap bulan ini vs bulan lalu, nilai trend negatif di-clamp ke 0%, dan flow void/export yang sudah dikerjakan tetap aman.
- Type: implement
- Mode: balanced
- Allowed modules: `app/Http/Controllers/Tenant/TransactionController.php`, `resources/views/tenant/transactions/index.blade.php`, test feature transaksi yang paling dekat, `.ai-context/CURRENT-TASK.md`
- Explicit exclusions: tidak redesign layout Figma, tidak mengubah enum/status transaksi, tidak menambah modul baru di luar kebutuhan flow transaksi

## Evidence and checkpoint

- Confirmed facts: summary cards memang harus tetap memakai perbandingan bulan berjalan vs bulan sebelumnya.
- Confirmed facts: tabel transaksi saat ini masih terfilter `this_month` secara default karena `applyFilters()` memberi default period meski user tidak mengirim query period.
- Confirmed facts: route backend untuk `tenant.transactions.void` sudah ada, tetapi belum ada jalur UI yang mengumpulkan `void_reason`.
- Confirmed facts: CTA `Export` sudah tampil di header, tetapi belum ada handling `export` di controller.
- Confirmed facts: setelah reread file penuh, handler close action menu untuk state normal sudah ada, sehingga bagian itu tidak perlu diubah.
- Confirmed facts: `formatTrend()` saat ini bisa menghasilkan angka negatif seperti `-100,0%`, dan itu terasa buruk di UI overview.
- Root cause or hypothesis: bug sekarang ada pada default filter tabel yang terlalu agresif dan formatter trend yang tidak meng-clamp nilai turun penuh.
- Next verification: review diff, jalankan test feature transaksi terfokus, validasi sintaks PHP, lalu cek route tenant transactions.

## Read ledger

| Path | Purpose / symbols | Changed since read? |
|---|---|---|
| `app/Http/Controllers/Tenant/TransactionController.php` | index, filters, selected/edit state, export branch | re-read |
| `resources/views/tenant/transactions/index.blade.php` | action menu, detail drawer, edit modal, export CTA | re-read |
| `app/Http/Requests/UpdateTransactionRequest.php` | edit validation contract | re-read |
| `app/Http/Requests/VoidTransactionRequest.php` | void validation contract | re-read |
| `app/Services/TransactionManagementService.php` | update/void service behavior | re-read |
| `tests/Feature/TenantProfilePageTest.php` | tenant feature test style | re-read |
| `tests/Feature/TenantSupportPageTest.php` | helper pattern for tenant feature setup | re-read |

## Change plan

- Files allowed to change: `.ai-context/CURRENT-TASK.md`, `app/Http/Controllers/Tenant/TransactionController.php`, `resources/views/tenant/transactions/index.blade.php`, `tests/Feature/TenantTransactionsPageTest.php`
- Contracts to preserve: transaksi member tetap dibatasi ke transaksi miliknya, void hanya untuk owner, update tetap memakai service yang ada, card overview tetap berbasis bulan berjalan vs bulan sebelumnya, filter period tetap bekerja jika query period memang dikirim
- Verification plan: review `git diff`, jalankan `php artisan test --filter=TenantTransactionsPageTest`, `php -l` untuk controller transaksi, dan `php artisan route:list --name=tenant.transactions`
