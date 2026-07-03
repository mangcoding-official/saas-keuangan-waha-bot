# Current Task

## Scope

- Goal: Perbaiki guided chat agar input lanjutan tidak jatuh ke menu umum ketika session guided terakhir hilang dari jalur normal akibat anomali runtime live.
- Type: debug
- Mode: low
- Allowed modules: `app/Services/ConversationSessionService.php`, `app/Services/TransactionMessageService.php`, test guided/session terdekat, `.ai-context/CURRENT-TASK.md`
- Explicit exclusions: tidak menambah fuzzy matching baru, tidak mengubah format reply bisnis, tidak mengubah flow produk baru, tidak mengubah modul invite/internal UI

## Evidence and checkpoint

- Confirmed facts: reproduksi lokal `Pengeluaran lainnya`, `Hari ini`, dan `100000` berhasil lanjut normal, jadi exact-match/case sensitivity bukan akar masalah.
- Confirmed facts: ketika user membalas `100000` lalu bot menampilkan menu umum, jalur code yang terjadi berarti `TransactionMessageService` tidak menemukan session lanjutan yang bisa dipakai lalu jatuh ke parser umum.
- Confirmed facts: recovery sebelumnya baru menangani `ACTIVE` session dengan `active_lock = null`; itu belum cukup bila session live sempat berubah menjadi `EXPIRED` secara anomali sangat dekat dengan pesan lanjutan.
- Root cause or hypothesis: di runtime live ada anomali session continuity yang lebih luas daripada sekadar `active_lock`, sehingga perlu fallback untuk memulihkan guided session terakhir yang masih sangat baru.
- Next verification: review `git diff`, lint PHP, lalu verifikasi bahwa session guided yang baru saja `EXPIRED` tetap dapat dipulihkan untuk melanjutkan input berikutnya.

## Read ledger

| Path | Purpose / symbols | Changed since read? |
|---|---|---|
| `app/Services/ConversationSessionService.php` | active session lookup and guided continuation | current |
| `app/Services/TransactionMessageService.php` | fallback to parser umum saat active session null | current |
| `app/Models/ConversationSession.php` | session fields and casts | current |

## Change plan

- Files allowed to change: `.ai-context/CURRENT-TASK.md`, `app/Services/ConversationSessionService.php`, `app/Services/TransactionMessageService.php`, test guided/session terdekat
- Contracts to preserve: guided flow normal tetap sama; jika ada session guided sangat baru yang hilang dari jalur normal, input lanjutan harus tetap melanjutkan flow, bukan jatuh ke menu umum
- Verification plan: review `git diff`, `php -l` file PHP yang diubah, dan verifikasi runtime terfokus di environment MySQL aktif
