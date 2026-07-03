# Current Task

## Scope

- Goal: Perbaiki stabilitas WAHA webhook agar duplicate delivery dan event webhook yang noisy tidak membuat flow guided transaksi keluar ke menu umum saat user mengirim kategori.
- Type: debug
- Mode: low
- Allowed modules: `app/Services/Waha/WahaWebhookService.php`, `app/Services/Waha/WahaAccessGateService.php`, test webhook WAHA yang paling dekat, `.ai-context/CURRENT-TASK.md`
- Explicit exclusions: tidak mengubah parsing guided category itu sendiri bila tidak ada bukti bug di sana, tidak mengubah format reply bisnis, tidak menambah flow produk baru, tidak mengubah modul invite/internal UI

## Evidence and checkpoint

- Confirmed facts: reproduksi lokal `keluar -> nominal -> deskripsi -> Pengeluaran Lainnya` berhasil maju normal ke `guided_expense_source_account`, jadi guided category path di `ConversationSessionService` tidak menunjukkan bug fungsional dari current code.
- Confirmed facts: balasan daftar perintah umum seperti pada screenshot hanya muncul bila message diproses tanpa active conversation session, karena jalur parser normal untuk plain text tanpa command akan jatuh ke `helpText()`.
- Confirmed facts: dokumentasi WAHA menyarankan webhook event `message` untuk menerima pesan masuk, sedangkan `message.any` dipicu untuk semua pembuatan pesan termasuk pesan milik bot sendiri.
- Confirmed facts: code saat ini masih menormalisasi dan mencatat event `message.any` ke jalur webhook yang sama, sehingga event noisy berpotensi ikut memengaruhi dedupe atau state walau bot hanya butuh inbound user message.
- Root cause or hypothesis: gejala “keluar ke menu umum setelah kategori” lebih mungkin dipicu event webhook yang tidak perlu (`message.any`) atau delivery ganda dari WAHA, bukan dari guided category resolver itu sendiri.
- Next verification: review `git diff`, lint PHP, lalu verifikasi runtime bahwa `message.any` diabaikan sebelum dedupe/DB dan flow kategori tetap maju ke prompt akun.

## Read ledger

| Path | Purpose / symbols | Changed since read? |
|---|---|---|
| `app/Http/Controllers/Webhook/WahaWebhookController.php` | entrypoint `/webhooks/waha` | current |
| `app/Services/Waha/WahaWebhookService.php` | duplicate guard, pending insert, reply send | current |
| `app/Services/Waha/WahaAccessGateService.php` | accepted event names | current |
| `app/Services/Waha/WahaWebhookPayloadNormalizer.php` | source message normalization | current |
| `app/Services/ConversationSessionService.php` | guided expense category -> account transition | current |
| `database/migrations/2026_06_19_220100_create_domain_tables.php` | `incoming_messages` schema | current |

## Change plan

- Files allowed to change: `.ai-context/CURRENT-TASK.md`, `app/Services/Waha/WahaWebhookService.php`, `app/Services/Waha/WahaAccessGateService.php`, test webhook WAHA terdekat
- Contracts to preserve: event inbound `message` valid tetap menghasilkan reply yang sama seperti sebelumnya; event `message.any` tidak boleh mengganggu dedupe atau state; guided category tetap maju ke prompt akun
- Verification plan: review `git diff`, `php -l` file PHP yang diubah, dan verifikasi runtime terfokus di environment MySQL aktif
