# Current Task

## Scope

- Goal: Perbaiki bug close pada popover akun topbar agar tombol close dan klik di luar benar-benar menutup panel.
- Type: debug
- Mode: balanced
- Allowed modules: CSS/JS topbar account yang terkait state tampil-sembunyi, `.ai-context/CURRENT-TASK.md`
- Explicit exclusions: tidak mengubah isi aksi popover, tidak mengubah route auth/logout, tidak redesign visual topbar di luar perbaikan state close

## Evidence and checkpoint

- Confirmed facts: JS close handler memang memanggil `popover.hidden = true` baik dari tombol close maupun klik di luar.
- Confirmed facts: class `.tenant-topbar-account-popover` mendefinisikan `display: grid`, sehingga atribut HTML `hidden` kalah oleh CSS author rule dan panel tetap tampak.
- Root cause or hypothesis: bug close bukan di event listener utama, tetapi di konflik antara state `hidden` dan CSS `display`.
- Next verification: pastikan ada rule khusus `[hidden]` untuk popover, review diff terfokus, lalu jalankan `npm run build`.

## Read ledger

| Path | Purpose / symbols | Changed since read? |
|---|---|---|
| `resources/views/tenant/partials/topbar.blade.php` | markup + hidden state popover | re-read |
| `resources/css/app.css` | tenant topbar styles | re-read |
| `resources/js/modules/topbar-account.js` | close/open handlers | re-read |

## Change plan

- Files allowed to change: `.ai-context/CURRENT-TASK.md`, `resources/css/app.css`, dan hanya JS jika ternyata masih perlu
- Contracts to preserve: perilaku open popover tetap sama, isi panel tetap sama, route aksi tidak berubah
- Verification plan: review `git diff` dan jalankan `npm run build`
