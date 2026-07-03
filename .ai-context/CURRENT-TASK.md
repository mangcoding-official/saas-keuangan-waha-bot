# Current Task

## Scope

- Goal: Tambahkan diagnostik terarah untuk membuktikan apakah runtime server gagal di identitas pengirim (`@c.us` vs `@lid`) atau di continuity guided session saat input lanjutan jatuh ke menu umum.
- Type: debug
- Mode: low
- Allowed modules: `app/Services/Waha/WahaWebhookService.php`, `app/Services/TransactionMessageService.php`, `app/Services/ConversationSessionService.php`, `.ai-context/CURRENT-TASK.md`
- Explicit exclusions: tidak mengubah aturan bisnis guided flow, tidak menambah parser/fuzzy matching baru, tidak mengubah format reply bisnis, tidak mengubah modul invite/internal UI

## Evidence and checkpoint

- Confirmed facts: `app/Services/Waha/WahaWebhookPayloadNormalizer.php` sudah menganggap `@c.us`, `@lid`, dan digit polos sebagai chat personal, lalu mencoba mengubah semuanya ke nomor normalisasi internal.
- Confirmed facts: untuk identifier `@lid`, code memanggil WAHA API agar memperoleh nomor telepon asli; jika lookup ini gagal maka `sender_normalized` akan `null` dan pesan akan berhenti di access gate sebagai `unknown_sender`.
- Confirmed facts: gejala user saat ini adalah bot tetap membalas menu umum, jadi ada indikasi pesan lolos access gate dan masalahnya lebih dekat ke continuity session daripada sekadar suffix identifier.
- Root cause or hypothesis: perbedaan `@c.us` vs `@lid` bisa menjadi faktor hanya bila lookup nomor dari `@lid` gagal, tetapi untuk kasus yang jatuh ke menu umum masih perlu bukti log apakah identifier sukses dinormalisasi dan session guided apa yang ditemukan tepat sebelum fallback parser umum.
- Next verification: review `git diff`, lint PHP, lalu uji di server sambil melihat log identifier masuk, hasil normalisasi sender, active/recovered session, dan kondisi fallback parser.

## Read ledger

| Path | Purpose / symbols | Changed since read? |
|---|---|---|
| `app/Services/Waha/WahaWebhookService.php` | webhook entry, lock, access evaluation, processing log | current |
| `app/Services/Waha/WahaWebhookPayloadNormalizer.php` | `@c.us` / `@lid` normalization path | current |
| `app/Services/Waha/WahaClient.php` | lookup nomor untuk `@lid` | current |
| `app/Services/Waha/WahaAccessGateService.php` | tenant user lookup by `sender_normalized` | current |
| `app/Services/ConversationSessionService.php` | active session lookup and guided continuation | current |
| `app/Services/TransactionMessageService.php` | fallback to parser umum saat active session null | current |

## Change plan

- Files allowed to change: `.ai-context/CURRENT-TASK.md`, `app/Services/Waha/WahaWebhookService.php`, `app/Services/TransactionMessageService.php`, `app/Services/ConversationSessionService.php`
- Contracts to preserve: perilaku bisnis existing tetap sama; perubahan hanya menambah observability untuk webhook sender normalization dan guided-session lookup/fallback
- Verification plan: review `git diff`, `php -l` file PHP yang diubah, lalu uji di server dan baca `storage/logs/laravel.log` untuk event identifier/session yang baru
