# Current Task

## Scope

- Goal: Tindak lanjuti bukti log server terbaru dengan memperbaiki penyebab utama session guided cepat expired akibat timestamp webhook disimpan dalam timezone yang salah.
- Type: debug
- Mode: low
- Allowed modules: `app/Services/Waha/WahaWebhookPayloadNormalizer.php`, `app/Services/TransactionMessageService.php`, `app/Services/ConversationSessionService.php`, test webhook/guided terdekat, `.ai-context/CURRENT-TASK.md`
- Explicit exclusions: tidak mengubah parser bisnis guided, tidak mengubah format reply selain jalur error existing, tidak mengubah modul invite/internal UI, tidak mengubah integrasi WAHA di luar kebutuhan kasus ini

## Evidence and checkpoint

- Confirmed facts: log server menunjukkan message dengan `chat_id` dan `sender_raw` berbentuk `@lid` tetap lolos normalisasi dan access gate; jadi akar masalah bukan kegagalan identifier `@lid`.
- Confirmed facts: tepat sebelum error, text `Hari ini` masih memakai active session dan berhasil memindahkan flow ke `guided_income_attachment_offer`.
- Confirmed facts: 15 detik kemudian pesan gambar diproses sebagai `attachment_not_expected`, yang berarti jalur lampiran tidak menemukan session aktif yang menerima attachment pada saat itu.
- Confirmed facts: pesan `Lanjut` tidak dibalas karena helper log baru crash di `ConversationSessionService::latestSessionSummary()` saat mencoba cast enum status ke string.
- Confirmed facts: log terbaru menunjukkan session `guided_expense_amount` expired sebelum `expires_at` yang user-facing, dengan selisih jam yang konsisten dengan UTC disimpan ke kolom DATETIME lalu dibaca sebagai Asia/Jakarta.
- Root cause or hypothesis: `WahaWebhookPayloadNormalizer::resolveTimestamp()` membuat Carbon UTC dari unix timestamp WAHA, lalu Eloquent menyimpannya ke DATETIME tanpa timezone; saat dibaca kembali nilainya bergeser 7 jam lebih tua dan session dianggap expired oleh proses lain.
- Next verification: review `git diff`, lint PHP, lalu verifikasi timestamp unix WAHA masuk sebagai jam aplikasi Asia/Jakarta dan session expiry tidak lagi mundur 7 jam.

## Read ledger

| Path | Purpose / symbols | Changed since read? |
|---|---|---|
| `app/Services/Waha/WahaWebhookPayloadNormalizer.php` | source timestamp WAHA ke Carbon app | current |
| `app/Services/ConversationSessionService.php` | active session lookup and guided continuation | current |
| `app/Services/TransactionMessageService.php` | fallback parser umum dan jalur attachment | current |
| `tests/Feature/WahaWebhookTest.php` | regresi timestamp webhook | current |
| `tests/Feature/GuidedConversationRecoveryTest.php` | regresi recovery session guided | current |

## Change plan

- Files allowed to change: `.ai-context/CURRENT-TASK.md`, `app/Services/Waha/WahaWebhookPayloadNormalizer.php`, `app/Services/TransactionMessageService.php`, `app/Services/ConversationSessionService.php`, `tests/Feature/WahaWebhookTest.php`, `tests/Feature/GuidedConversationRecoveryTest.php`
- Contracts to preserve: flow guided yang sehat tetap sama; timestamp WAHA tetap dipakai sebagai waktu pesan, tetapi harus berada di timezone aplikasi sebelum disimpan/dibandingkan
- Verification plan: review `git diff`, `php -l` file PHP yang diubah, simulasi runtime timestamp, dan jalankan test terdekat jika environment mengizinkan
