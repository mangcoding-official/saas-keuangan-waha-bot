@php
    $accountTypeValue = old('account_type', $account['account_type'] ?? '');
    $accountTypeLabel = collect($accountTypeOptions)->firstWhere('value', $accountTypeValue)['label'] ?? 'Pilih tipe akun';
    $isDefault = (bool) old('is_default', $account['is_default'] ?? false);
@endphp

<article class="dashboard-card account-form-card">
    <h2 class="dashboard-section-title">{{ $form['title'] }}</h2>

    <form action="{{ $form['action'] }}" method="post" class="field-grid account-form" data-account-form>
        @csrf
        @if ($form['method'] !== 'post')
            @method($form['method'])
        @endif

        <x-ui.input
            name="name"
            label="Nama account"
            placeholder="Contoh: BCA Operasional"
            :value="$account['name'] ?? ''"
            required
        />

        <div class="field account-popover-field" data-account-type-popover>
            <span class="field-label">Tipe akun</span>
            <input type="hidden" name="account_type" value="{{ $accountTypeValue }}" data-account-type-input required>

            <button type="button" class="account-popover-trigger" data-account-type-trigger aria-expanded="false">
                <span data-account-type-label>{{ $accountTypeLabel }}</span>
                <span aria-hidden="true">▾</span>
            </button>

            <div class="account-popover-menu" data-account-type-menu hidden>
                @foreach ($accountTypeOptions as $option)
                    <button
                        type="button"
                        class="account-popover-option {{ $accountTypeValue === $option['value'] ? 'is-selected' : '' }}"
                        data-account-type-option
                        data-value="{{ $option['value'] }}"
                        data-label="{{ $option['label'] }}"
                    >
                        {{ $option['label'] }}
                    </button>
                @endforeach
            </div>

            @error('account_type')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <x-ui.input
            name="opening_balance"
            label="Opening balance"
            type="number"
            step="0.01"
            min="0"
            placeholder="0.00"
            :value="$account['opening_balance'] ?? '0.00'"
            required
        />

        <div class="field account-toggle-field">
            <span class="field-label">Setting</span>
            <input type="hidden" name="is_active" value="{{ ($account['is_active'] ?? true) ? '1' : '0' }}">
            <input type="hidden" name="is_default" value="{{ $isDefault ? '1' : '0' }}" data-default-toggle-input>

            <button type="button" class="account-default-toggle {{ $isDefault ? 'is-on' : '' }}" data-default-toggle>
                <span>Set default</span>
                <span class="account-default-toggle-knob" aria-hidden="true"></span>
            </button>

            @error('is_default')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field field-full">
            <div class="members-form-actions">
                <button class="button button-primary" type="submit">{{ $form['submit_label'] }}</button>
                <a href="{{ route('tenant.accounts.index') }}" class="button button-secondary">Kembali ke Daftar</a>
            </div>
        </div>
    </form>
</article>
