# Current Task

## Scope

- Goal: Samakan UI tabel tenant transactions dengan pola accounts, tambahkan modal filter untuk range tanggal, tipe, kategori, dan akun, lalu rapikan trigger buka/tutup filter agar tidak reload halaman, sambil mempertahankan default tabel semua transaksi terbaru, overview month-over-month, serta flow void/export yang sudah dikerjakan.
- Type: implement
- Mode: balanced
- Allowed modules: `app/Http/Controllers/Tenant/TransactionController.php`, `resources/views/tenant/transactions/index.blade.php`, test feature transaksi yang paling dekat, `.ai-context/CURRENT-TASK.md`
- Explicit exclusions: tidak redesign layout Figma, tidak mengubah enum/status transaksi, tidak menambah modul baru di luar kebutuhan flow transaksi

## Evidence and checkpoint

- Confirmed facts: summary cards memang harus tetap memakai perbandingan bulan berjalan vs bulan sebelumnya.
- Confirmed facts: tabel transaksi saat ini masih terfilter `this_month` secara default karena `applyFilters()` memberi default period meski user tidak mengirim query period.
- Confirmed facts: struktur visual `accounts-table-head` + icon tool di page accounts adalah referensi paling dekat untuk header tabel transaksi yang diminta user.
- Confirmed facts: CSS transaksi sudah punya shell modal yang bisa direuse untuk filter form, sehingga tidak perlu membuat komponen modal baru di luar style system.
- Confirmed facts: route backend untuk `tenant.transactions.void` sudah ada, tetapi belum ada jalur UI yang mengumpulkan `void_reason`.
- Confirmed facts: CTA `Export` sudah tampil di header, tetapi belum ada handling `export` di controller.
- Confirmed facts: setelah reread file penuh, handler close action menu untuk state normal sudah ada, sehingga bagian itu tidak perlu diubah.
- Confirmed facts: `formatTrend()` saat ini bisa menghasilkan angka negatif seperti `-100,0%`, dan itu terasa buruk di UI overview.
- Confirmed facts: query transaksi saat ini belum menerima `date_from` / `date_to` eksplisit, jadi UI filter range tanggal belum bisa disambungkan langsung.
- Confirmed facts: trigger filter saat ini masih memakai query `filter=1`, jadi buka/tutup modal selalu memicu request baru meski user hanya ingin menampilkan shell modal.
- Root cause or hypothesis: filter modal seharusnya menjadi state lokal di blade agar toggle open/close tidak bergantung pada query string, sementara submit filter tetap request normal untuk refresh tabel.
- Next verification: review diff, jalankan test feature transaksi terfokus, validasi sintaks PHP, lalu cek render transaksi tanpa query `filter`.

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
- Contracts to preserve: transaksi member tetap dibatasi ke transaksi miliknya, void hanya untuk owner, update tetap memakai service yang ada, card overview tetap berbasis bulan berjalan vs bulan sebelumnya, default tabel tetap latest-first, export tetap mengikuti filter aktif
- Verification plan: review `git diff`, jalankan `php artisan test --filter=TenantTransactionsPageTest`, `php -l` untuk controller transaksi, dan `php artisan route:list --name=tenant.transactions`
