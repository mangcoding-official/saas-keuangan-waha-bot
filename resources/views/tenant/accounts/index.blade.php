@extends('layouts.tenant')

@section('content')
    @if ($errors->any())
        <div class="tenant-inline-alert">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="accounts-page-header">
        <div>
            <h2 class="accounts-page-title">Daftar Akun Keuangan</h2>
            <p class="accounts-page-copy">Kelola saldo dan status akun perbankan serta e-wallet Anda.</p>
        </div>
        <a href="{{ route('tenant.accounts.create') }}" class="accounts-add-button">
            <span aria-hidden="true">+</span>
            <span>Tambah Akun</span>
        </a>
    </section>

    <section class="accounts-summary-grid">
        <article class="accounts-summary-card">
            <p class="accounts-summary-label">TOTAL AKUN</p>
            <h2 class="accounts-summary-value">{{ $summary['total'] }}</h2>
        </article>

        <article class="accounts-summary-card">
            <p class="accounts-summary-label">AKUN AKTIF</p>
            <h2 class="accounts-summary-value">{{ $summary['active'] }}</h2>
        </article>

        <article class="accounts-summary-card {{ $summary['inactive'] > 0 ? 'is-alert' : '' }}">
            <p class="accounts-summary-label">AKUN NONAKTIF</p>
            <h2 class="accounts-summary-value">{{ $summary['inactive'] }}</h2>
        </article>

        <article class="accounts-summary-card">
            <p class="accounts-summary-label">AKUN DEFAULT</p>
            <h2 class="accounts-summary-value is-default-name">{{ $summary['default_name'] }}</h2>
        </article>
    </section>

    <section class="accounts-table-wrap">
        <article class="accounts-table-card">
            <div class="accounts-table-head">
                <h3>Data Rekening &amp; Saldo</h3>
            </div>

            <table class="accounts-table">
                <thead>
                    <tr>
                        <th>NAMA AKUN</th>
                        <th>TIPE</th>
                        <th>SALDO AWAL</th>
                        <th>STATUS</th>
                        <th>LAST UPDATE</th>
                        <th>AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($accounts as $account)
                        <tr>
                            <td class="accounts-name-cell">
                                <strong>{{ $account['name'] }}</strong>
                                @if ($account['is_default'])
                                    <span>Default account aktif</span>
                                @endif
                            </td>
                            <td><span class="accounts-type-pill">{{ $account['account_type'] }}</span></td>
                            <td class="accounts-money-cell">Rp {{ $account['opening_balance'] }}</td>
                            <td>
                                <div class="accounts-status-stack">
                                    @if ($account['is_default'])
                                        <span class="accounts-status-chip neutral">Default</span>
                                    @endif
                                    <span class="accounts-status-chip {{ $account['is_active'] ? 'success' : 'warning' }}">
                                        {{ $account['is_active'] ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </div>
                            </td>
                            <td>{{ $account['updated_at'] }}</td>
                            <td>
                                <div class="accounts-actions-cell">
                                    <a href="{{ route('tenant.accounts.edit', $account['id']) }}" class="accounts-action-button">Edit</a>

                                    @unless ($account['is_default'])
                                        <form action="{{ route('tenant.accounts.set-default', $account['id']) }}" method="post">
                                            @csrf
                                            <button class="accounts-action-button ghost" type="submit">Set Default</button>
                                        </form>
                                    @endunless

                                    @if ($account['is_active'])
                                        <form action="{{ route('tenant.accounts.deactivate', $account['id']) }}" method="post">
                                            @csrf
                                            <button class="accounts-action-button ghost" type="submit">Nonaktifkan</button>
                                        </form>
                                    @else
                                        <form action="{{ route('tenant.accounts.activate', $account['id']) }}" method="post">
                                            @csrf
                                            <button class="accounts-action-button ghost" type="submit">Aktifkan</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="accounts-empty-cell">Belum ada akun untuk tenant ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </article>
    </section>
@endsection
