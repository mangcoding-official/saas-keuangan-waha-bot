@extends('layouts.tenant')

@php
    $pageCount = max(1, (int) $accountPaginator->lastPage());
    $currentPage = (int) $accountPaginator->currentPage();
    $visiblePages = collect(range(max(1, $currentPage - 1), min($pageCount, $currentPage + 1)))->all();
@endphp

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
        <div class="accounts-page-actions">
            @if ($activeSearch !== '')
                <a href="{{ route('tenant.accounts.index') }}" class="accounts-reset-link">Reset pencarian</a>
            @endif
            <a href="{{ route('tenant.accounts.create') }}" class="accounts-add-button">
                <span aria-hidden="true">+</span>
                <span>Tambah Akun</span>
            </a>
        </div>
    </section>

    <section class="accounts-summary-grid">
        <article class="accounts-summary-card">
            <p class="accounts-summary-label">TOTAL AKUN</p>
            <h2 class="accounts-summary-value">{{ $summary['total'] }}</h2>
            <p class="accounts-summary-note">Semua akun</p>
        </article>

        <article class="accounts-summary-card">
            <p class="accounts-summary-label">AKUN AKTIF</p>
            <h2 class="accounts-summary-value">{{ $summary['active'] }}</h2>
            <p class="accounts-summary-note">Akun yang dapat dipakai</p>
        </article>

        <article class="accounts-summary-card {{ $summary['inactive'] > 0 ? 'is-alert' : '' }}">
            <p class="accounts-summary-label">AKUN NONAKTIF</p>
            <h2 class="accounts-summary-value">{{ $summary['inactive'] }}</h2>
            <p class="accounts-summary-note">Perlu diaktifkan ulang</p>
        </article>

        <article class="accounts-summary-card">
            <p class="accounts-summary-label">AKUN DEFAULT</p>
            <h2 class="accounts-summary-value is-default-name">{{ $summary['default_name'] }}</h2>
            <p class="accounts-summary-note">Digunakan sebagai default account</p>
        </article>
    </section>

    <section class="accounts-content-grid">
        <article class="accounts-table-card">
            <div class="accounts-table-head">
                <h3>Data Rekening &amp; Saldo</h3>
            </div>

            <table class="accounts-table">
                <thead>
                    <tr>
                        <th>NAMA AKUN</th>
                        <th>TIPE</th>
                        <th>SALDO</th>
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
                                    <span>Dipakai sebagai default account</span>
                                @endif
                            </td>
                            <td>
                                <span class="accounts-type-pill {{ str_replace('_', '-', strtolower($account['account_type_value'])) }}">
                                    {{ $account['account_type'] }}
                                </span>
                            </td>
                            <td class="accounts-money-cell">
                                <strong>Rp {{ $account['current_balance'] }}</strong>
                                <span>Saldo awal Rp {{ $account['opening_balance'] }}</span>
                            </td>
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

            <div class="accounts-pagination">
                <p>Menampilkan {{ $accountPaginator->firstItem() ?? 0 }}-{{ $accountPaginator->lastItem() ?? 0 }} dari {{ $accountPaginator->total() }} akun</p>
                <div>
                    @if ($accountPaginator->onFirstPage())
                        <span class="accounts-page disabled">&lt;</span>
                    @else
                        <a href="{{ $accountPaginator->previousPageUrl() }}" class="accounts-page">&lt;</a>
                    @endif
                    @foreach ($visiblePages as $page)
                        <a href="{{ $accountPaginator->url($page) }}" class="accounts-page {{ $page === $currentPage ? 'active' : '' }}">{{ $page }}</a>
                    @endforeach
                    @if ($accountPaginator->hasMorePages())
                        <a href="{{ $accountPaginator->nextPageUrl() }}" class="accounts-page">&gt;</a>
                    @else
                        <span class="accounts-page disabled">&gt;</span>
                    @endif
                </div>
            </div>
        </article>

        <aside class="accounts-info-card">
            <h3>Informasi</h3>
            <p>Buat akun baru melalui halaman Create Account.</p>
            <p>Edit account menggunakan halaman terpisah agar alur lebih fokus.</p>
            <p>Saldo yang tampil pada daftar akun adalah saldo berjalan, bukan hanya saldo awal.</p>
        </aside>
    </section>
@endsection
