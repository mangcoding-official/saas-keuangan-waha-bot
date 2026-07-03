# Current Task

## Scope

- Goal: Perbaiki bug WAHA webhook agar satu `source_message_id` tidak bisa diproses dan dibalas dua kali saat request duplicate masuk paralel.
- Type: debug
- Mode: low
- Allowed modules: `app/Services/Waha/WahaWebhookService.php`, test webhook WAHA yang paling dekat, `.ai-context/CURRENT-TASK.md`
- Explicit exclusions: tidak mengubah parsing transaksi, tidak mengubah format reply, tidak menambah flow produk baru, tidak mengubah modul invite/internal UI

## Evidence and checkpoint

- Confirmed facts: route webhook WAHA hanya masuk lewat `POST /webhooks/waha` dan diteruskan langsung ke `WahaWebhookService::handle()`.
- Confirmed facts: `incoming_messages.source_message_id` sudah unique, tetapi guard duplicate saat ini hanya berhenti jika row lama sudah punya `processed_at`.
- Confirmed facts: ketika insert pending row terkena unique collision, service saat ini mengambil `existingId` lalu tetap lanjut ke jalur business logic dan `attemptReply()`.
- Confirmed facts: pola ini masih membuka race condition bila dua request webhook dengan `source_message_id` yang sama masuk hampir bersamaan, karena request kedua bisa ikut lanjut sebelum request pertama selesai mengisi `processed_at`.
- Root cause or hypothesis: bug duplicate reply paling mungkin berasal dari tidak adanya claim/lock idempotency sebelum processing dan reply dijalankan.
- Next verification: review `git diff`, jalankan lint PHP pada file yang diubah, lalu jalankan test webhook terfokus bila environment mengizinkan.

## Read ledger

| Path | Purpose / symbols | Changed since read? |
|---|---|---|
| `app/Http/Controllers/Webhook/WahaWebhookController.php` | entrypoint `/webhooks/waha` | current |
| `app/Services/Waha/WahaWebhookService.php` | duplicate guard, pending insert, reply send | current |
| `app/Services/Waha/WahaWebhookPayloadNormalizer.php` | source message normalization | current |
| `app/Services/Waha/WahaAccessGateService.php` | access decision for webhook payload | current |
| `database/migrations/2026_06_19_220100_create_domain_tables.php` | `incoming_messages` schema | current |

## Change plan

- Files allowed to change: `.ai-context/CURRENT-TASK.md`, `app/Services/Waha/WahaWebhookService.php`, test webhook WAHA terdekat
- Contracts to preserve: satu message valid tetap menghasilkan reply yang sama seperti sebelumnya; duplicate event harus diabaikan tanpa side effect kedua
- Verification plan: review `git diff`, `php -l` file PHP yang diubah, dan `php artisan test --filter=WahaWebhook`
