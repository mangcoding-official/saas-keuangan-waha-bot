# Current Task

## Scope

- Goal: Tambahkan pengiriman owner registration invite lewat WhatsApp memakai nomor WA bot yang sedang aktif.
- Type: implement
- Mode: balanced
- Allowed modules: internal invite controller/view/request/service/test, resolver bot aktif, migrasi tabel invite bila perlu
- Explicit exclusions: tidak mengubah alur registrasi tenant, tidak redesign halaman lain, tidak menambah provider pesan selain WAHA

## Evidence and checkpoint

- Confirmed facts: halaman invite saat ini hanya menyimpan email target, copy code, dan copy link; belum ada nomor WA target maupun aksi kirim lewat WA.
- Confirmed facts: sumber bot aktif sudah dipakai di `ActivationCodeDeliveryService` dan halaman sukses registrasi memakai tabel `bot_instances` (`is_active`, `is_default`, `bot_whatsapp_number_normalized`, `waha_instance_key`).
- Confirmed facts: `WahaClient::sendText()` sudah menjadi jalur kirim pesan WAHA yang aktif di aplikasi.
- Implementation hypothesis: invite butuh nomor WA penerima sendiri, lalu saat create/resend sistem menggunakan session `waha_instance_key` dari bot aktif untuk mengirim template pesan berisi kode invite dan link registrasi.
- Next verification: lint file PHP yang berubah, jalankan migrasi baru, lalu jalankan test feature invite yang relevan atau cek bootstrap route jika environment test masih terbatas.

## Read ledger

| Path | Purpose / symbols | Changed since read? |
|---|---|---|
| `app/Http/Controllers/Internal/OwnerRegistrationInviteController.php` | alur halaman invite | no |
| `resources/views/internal/invites/index.blade.php` | UI form/table invite | no |
| `app/Services/OwnerRegistrationInviteService.php` | create/revoke/consume invite | no |
| `app/Services/ActivationCodeDeliveryService.php` | pola resolve bot aktif + kirim WA | no |
| `app/Http/Controllers/Web/Auth/TenantRegistrationController.php` | pola nomor WA bot aktif di UI | no |
| `app/Support/PhoneNumberNormalizer.php` | normalisasi nomor target | no |
| `tests/Feature/InternalOwnerRegistrationInviteTest.php` | regression coverage invite | no |

## Change plan

- Files allowed to change: `.ai-context/CURRENT-TASK.md`, request/model/service/controller/view invite, route internal, bot-active resolver/service baru, migration baru, test invite
- Contracts to preserve: format kode invite, route register tenant yang sudah ada, pola bot aktif dari `bot_instances`
- Verification plan: syntax check PHP, migrate database, dan test feature invite yang menyentuh create/send/revoke
