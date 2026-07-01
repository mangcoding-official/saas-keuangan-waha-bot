@extends('layouts.tenant')

@section('content')
    <section class="tenant-dashboard-frame support-page">
        <section class="transactions-page-header">
            <div>
                <h2 class="transactions-page-title">Bantuan & Feedback</h2>
                <p class="transactions-page-copy">Hubungi tim support lewat channel aktif atau kirim detail kendala Anda dari form ini.</p>
            </div>
        </section>

        <section class="support-page-grid">
            <x-ui.card
                title="Channel Support"
                description="Pilih jalur yang paling cepat untuk kebutuhan Anda. Jika WhatsApp CS belum aktif, form internal tetap bisa dipakai."
                class="support-page-card"
            >
                <div class="support-channel-stack">
                    @if ($supportChannels['whatsapp_url'])
                        <a href="{{ $supportChannels['whatsapp_url'] }}" class="button button-primary" target="_blank" rel="noreferrer">
                            Chat WhatsApp CS
                        </a>
                    @endif

                    @if ($supportChannels['feedback_form_url'])
                        <a href="{{ $supportChannels['feedback_form_url'] }}" class="button button-secondary" target="_blank" rel="noreferrer">
                            Buka Form Feedback
                        </a>
                    @endif

                    <div class="support-channel-note">
                        <strong>Form internal selalu tersedia</strong>
                        <p>Gunakan form di samping untuk pertanyaan, bug report, atau permintaan tindak lanjut yang perlu disimpan di sistem.</p>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card
                title="Kirim Permintaan Bantuan"
                description="Pesan ini akan tersimpan agar bisa di-follow-up tim internal meski channel eksternal belum diisi."
                class="support-page-card"
            >
                <form action="{{ route('tenant.support.store') }}" method="post" class="field-grid support-form">
                    @csrf

                    <x-ui.input
                        name="subject"
                        label="Subjek"
                        placeholder="Contoh: Aktivasi WhatsApp belum masuk"
                        required
                        full
                    />

                    <label class="field field-full" for="message">
                        <span class="field-label">Detail Kendala</span>
                        <textarea
                            id="message"
                            name="message"
                            class="input-control input-textarea"
                            placeholder="Jelaskan kendala, langkah yang Anda lakukan, dan hasil yang diharapkan."
                            required
                        >{{ old('message') }}</textarea>

                        <p class="field-help">Semakin jelas konteksnya, semakin cepat tim support bisa menindaklanjuti.</p>

                        @error('message')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </label>

                    <div class="button-row support-form-actions">
                        <x-ui.button variant="secondary" href="{{ route('tenant.dashboard') }}">Kembali ke Dashboard</x-ui.button>
                        <x-ui.button type="submit">Kirim Permintaan</x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        </section>
    </section>
@endsection
