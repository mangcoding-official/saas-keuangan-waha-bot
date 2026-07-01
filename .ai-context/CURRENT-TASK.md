# Current Task

## Scope

- Goal: Perbaiki UX tabel `internal/invites` agar tidak memunculkan scroll internal yang janggal dan popover aksi tampil rapi tanpa terpotong.
- Type: implement
- Mode: balanced
- Allowed modules: `app/Http/Controllers/Internal/OwnerRegistrationInviteController.php`, `resources/views/internal/invites/index.blade.php`, CSS invite/internal modal yang relevan, test feature invite internal yang paling dekat, `.ai-context/CURRENT-TASK.md`
- Explicit exclusions: tidak menambah backend baru untuk invite member tenant, tidak mengubah lifecycle invite, tidak mengubah flow registrasi public invite-gated

## Evidence and checkpoint

- Confirmed facts: route `internal.invites.*` saat ini khusus untuk `owner_registration_invites`, bukan invite member tenant lintas workspace.
- Confirmed facts: aksi backend yang benar-benar tersedia hanya create invite, kirim ulang via WhatsApp, revoke invite, dan konsumsi invite di halaman register owner.
- Confirmed facts: topbar search di layout internal masih visual-only dan belum tersambung ke query controller, jadi tidak layak dipakai sebagai fitur sungguhan di patch ini.
- Confirmed facts: tabel invite saat ini masih dioverride dengan `min-width: 760px` dan dibungkus `overflow-x: auto`, sehingga pada card kanan muncul perilaku scroll yang buruk meski data sedikit.
- Confirmed facts: popover aksi invite terbuka di dalam konteks tabel yang bisa discroll, jadi UX terasa patah dan area kanan bawah card terlihat janggal.
- Confirmed facts: pattern visual paling dekat untuk dirapikan ada di `members-*`, `accounts-action-menu`, dan modal detail yang baru dipakai di halaman internal users.
- Confirmed facts: user sekarang minta menghapus elemen filler yang ditandai merah, jadi kartu "workflow", badge "fungsi aktif", dan presentasi status yang terasa kurang pas perlu disederhanakan.
- Root cause or hypothesis: masalah utama bukan pada data atau backend, tetapi pada strategi layout tabel yang memaksa lebar minimum; solusi terkecil adalah membuat tabel invite memakai kolom fixed/wrapping yang muat dalam card dan memisahkan popover dari wrapper scroll.
- Next verification: review diff, jalankan `php artisan view:cache`, dan `npm run build`.

## Read ledger

| Path | Purpose / symbols | Changed since read? |
|---|---|---|
| `routes/internal.php` | `internal.invites.*` | re-read |
| `resources/views/internal/invites/index.blade.php` | current invite UI and actions | re-read |
| `resources/css/app.css` | invite table layout, scroll, and popover behavior | re-read |

## Change plan

- Files allowed to change: `.ai-context/CURRENT-TASK.md`, `resources/views/internal/invites/index.blade.php`, `resources/css/app.css`
- Contracts to preserve: action invite tetap `detail`, `copy code`, `copy link`, `send-whatsapp`, `revoke`; tidak mengubah backend atau pagination
- Verification plan: review `git diff`, jalankan `php artisan view:cache`, dan `npm run build`
