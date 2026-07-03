# Current Task

## Scope

- Goal: Perbaiki guided chat agar input lanjutan seperti nominal tidak jatuh ke menu umum ketika session aktif masih ada tetapi lock/session marker bermasalah di runtime live.
- Type: debug
- Mode: low
- Allowed modules: `app/Services/ConversationSessionService.php`, test guided/session terdekat, `.ai-context/CURRENT-TASK.md`
- Explicit exclusions: tidak menambah fuzzy matching baru, tidak mengubah format reply bisnis, tidak mengubah flow produk baru, tidak mengubah modul invite/internal UI

## Evidence and checkpoint

- Confirmed facts: reproduksi lokal `Pengeluaran lainnya` dan `Hari ini` berhasil lanjut normal, jadi exact-match/case sensitivity bukan akar masalah.
- Confirmed facts: ketika user membalas `100000` lalu bot menampilkan menu umum, jalur code yang terjadi berarti `TransactionMessageService` tidak menemukan active session dan jatuh ke parser umum.
- Confirmed facts: `ConversationSessionService::findActiveSession()` saat ini hanya mengembalikan row `ACTIVE` yang masih memiliki `active_lock` non-null.
- Root cause or hypothesis: di runtime live ada anomali di mana session masih `ACTIVE` tetapi `active_lock` tidak terbaca/terset sebagaimana mestinya, sehingga continuation message tidak lagi dianggap bagian dari guided flow.
- Next verification: review `git diff`, lint PHP, lalu verifikasi bahwa session `ACTIVE` dengan `active_lock = null` tetap bisa dipulihkan dan melanjutkan guided flow.

## Read ledger

| Path | Purpose / symbols | Changed since read? |
|---|---|---|
| `app/Services/ConversationSessionService.php` | active session lookup and guided continuation | current |
| `app/Services/TransactionMessageService.php` | fallback to parser umum saat active session null | current |
| `app/Models/ConversationSession.php` | session fields and casts | current |

## Change plan

- Files allowed to change: `.ai-context/CURRENT-TASK.md`, `app/Services/ConversationSessionService.php`, test guided/session terdekat
- Contracts to preserve: guided flow normal tetap sama; jika ada session `ACTIVE` yang lock-nya hilang/null, input lanjutan harus tetap melanjutkan flow, bukan jatuh ke menu umum
- Verification plan: review `git diff`, `php -l` file PHP yang diubah, dan verifikasi runtime terfokus di environment MySQL aktif
