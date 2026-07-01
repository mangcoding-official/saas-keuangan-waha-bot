# Current Task

## Scope

- Goal: Rapikan UI halaman register pada area fieldset yang mengelompokkan card workspace + informasi akun, dengan jarak card seragam, tanpa border fieldset, dan alignment sejajar dengan card invite di atas.
- Type: refactor
- Mode: low
- Allowed modules: blade register public, CSS register public
- Explicit exclusions: tidak mengubah flow invite gate, controller/service/test registrasi, tidak redesign visual di luar area fieldset yang ditandai

## Evidence and checkpoint

- Confirmed facts: card invite atas memakai `.register-section-card`, sedangkan card workspace + informasi akun dibungkus `fieldset` polos browser.
- Confirmed facts: `fieldset` default memberi border, margin, dan padding sehingga blok bawah tampak bergeser ke dalam dan jarak antar card tidak seragam.
- Root cause or hypothesis: wrapper `fieldset` belum diberi reset layout khusus, jadi style bawaan browser bentrok dengan grid `.register-form-shell` dan card `.register-section-card`.
- Next verification: cek diff Blade/CSS dan lint file Blade-adjacent CSS secara visual lewat struktur hasil render.

## Read ledger

| Path | Purpose / symbols | Changed since read? |
|---|---|---|
| `resources/views/web/auth/register.blade.php` | wrapper fieldset area register | re-read |
| `resources/css/app.css` | spacing dan style section register | re-read |

## Change plan

- Files allowed to change: `.ai-context/CURRENT-TASK.md`, `resources/views/web/auth/register.blade.php`, `resources/css/app.css`
- Contracts to preserve: struktur field form, state disabled dari invite gate, copy dan CTA register
- Verification plan: review `git diff` terfokus pada Blade/CSS dan pastikan tidak ada perubahan logic
