# Current Task

## Scope

- Goal: Rapikan form `internal/invites` dengan membuat input yang masih bertumpuk menjadi satu baris full width tanpa mengubah kontrak invite yang sudah ada.
- Type: implement
- Mode: balanced
- Allowed modules: `app/Http/Controllers/Internal/OwnerRegistrationInviteController.php`, `resources/views/internal/invites/index.blade.php`, CSS invite/internal modal yang relevan, test feature invite internal yang paling dekat, `.ai-context/CURRENT-TASK.md`
- Explicit exclusions: tidak menambah backend baru untuk invite member tenant, tidak mengubah lifecycle invite, tidak mengubah flow registrasi public invite-gated

## Evidence and checkpoint

- Confirmed facts: route `internal.invites.*` saat ini khusus untuk `owner_registration_invites`, bukan invite member tenant lintas workspace.
- Confirmed facts: aksi backend yang benar-benar tersedia hanya create invite, kirim ulang via WhatsApp, revoke invite, dan konsumsi invite di halaman register owner.
- Confirmed facts: topbar search di layout internal masih visual-only dan belum tersambung ke query controller, jadi tidak layak dipakai sebagai fitur sungguhan di patch ini.
- Confirmed facts: field `expires_at` dan `note` di form invite masih mengikuti grid 2 kolom karena belum memakai modifier full width seperti field email dan WhatsApp.
- Confirmed facts: request kali ini hanya menyasar layout field form, bukan aksi backend atau struktur tabel.
- Confirmed facts: pattern visual paling dekat untuk dirapikan ada di `members-*`, `accounts-action-menu`, dan modal detail yang baru dipakai di halaman internal users.
- Confirmed facts: user sekarang minta menghapus elemen filler yang ditandai merah, jadi kartu "workflow", badge "fungsi aktif", dan presentasi status yang terasa kurang pas perlu disederhanakan.
- Root cause or hypothesis: grid form memang sudah benar; yang kurang hanya dua field bawah belum diberi `full`, jadi solusi terkecil adalah memperluas keduanya tanpa mengubah CSS grid global.
- Next verification: review diff dan jalankan `php artisan view:cache`.

## Read ledger

| Path | Purpose / symbols | Changed since read? |
|---|---|---|
| `routes/internal.php` | `internal.invites.*` | re-read |
| `resources/views/internal/invites/index.blade.php` | current invite UI and actions | re-read |
| `resources/css/app.css` | form grid and `field-full` behavior | re-read |

## Change plan

- Files allowed to change: `.ai-context/CURRENT-TASK.md`, `resources/views/internal/invites/index.blade.php`
- Contracts to preserve: field lain tetap mengikuti komponen input yang ada, tidak mengubah validasi, tidak mengubah struktur action invite
- Verification plan: review `git diff` dan jalankan `php artisan view:cache`
