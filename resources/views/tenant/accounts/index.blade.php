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
            <a href="{{ route('tenant.accounts.index', array_merge(request()->query(), ['create' => 1])) }}" class="accounts-add-button">
                <span class="accounts-add-button-icon" aria-hidden="true">
                    <img src="{{ asset('images/figma/accounts/plus.svg') }}" alt="">
                </span>
                <span>Tambah Akun</span>
            </a>
        </div>
    </section>

    <section class="accounts-summary-grid">
        <article class="accounts-summary-card is-total">
            <p class="accounts-summary-label">TOTAL AKUN</p>
            <h2 class="accounts-summary-value">{{ $summary['total'] }}</h2>
        </article>

        <article class="accounts-summary-card is-active-card">
            <p class="accounts-summary-label">AKUN AKTIF</p>
            <h2 class="accounts-summary-value">{{ $summary['active'] }}</h2>
        </article>

        <article class="accounts-summary-card is-inactive-card {{ $summary['inactive'] > 0 ? 'is-alert' : '' }}">
            <p class="accounts-summary-label">AKUN NONAKTIF</p>
            <h2 class="accounts-summary-value">{{ $summary['inactive'] }}</h2>
        </article>

        <article class="accounts-summary-card is-default-card">
            <p class="accounts-summary-label">AKUN DEFAULT</p>
            <h2 class="accounts-summary-value is-default-name">{{ $summary['default_name'] }}</h2>
        </article>
    </section>

    <section class="accounts-table-card">
        <div class="accounts-table-head">
            <h3>Data Rekening &amp; Saldo</h3>
            <div class="accounts-table-tools">
                <button type="button" class="accounts-table-tool is-list" aria-label="Urutkan data">
                    <img src="{{ asset('images/figma/accounts/table-list.svg') }}" alt="">
                </button>
            </div>
        </div>

        <table class="accounts-table">
            <thead>
                <tr>
                    <th>NAMA AKUN</th>
                    <th>TIPE</th>
                    <th>SALDO AWAL</th>
                    <th>SALDO SAAT INI</th>
                    <th>STATUS</th>
                    <th>AKSI</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($accounts as $account)
                    <tr class="{{ $account['is_active'] ? '' : 'is-inactive-row' }}">
                        <td class="accounts-name-cell">
                            <span class="accounts-name-icon {{ str_replace('_', '-', strtolower($account['account_type_value'])) }}">
                                <img src="{{ asset('images/figma/accounts/'.$account['icon_asset']) }}" alt="">
                            </span>
                            <div>
                                <strong>{{ $account['name'] }}</strong>
                            </div>
                        </td>
                        <td>
                            <span class="accounts-type-pill {{ str_replace('_', '-', strtolower($account['account_type_value'])) }}">
                                {{ $account['account_type'] }}
                            </span>
                        </td>
                        <td class="accounts-money-only is-opening">Rp {{ $account['opening_balance'] }}</td>
                        <td class="accounts-money-only is-current">Rp {{ $account['current_balance'] }}</td>
                        <td>
                            <span class="accounts-status-inline {{ $account['is_active'] ? 'is-active' : 'is-inactive' }}">
                                <span aria-hidden="true"></span>
                                {{ $account['is_active'] ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="accounts-action-menu-cell">
                            <details class="accounts-action-menu" data-account-action-menu>
                                <summary aria-label="Aksi akun">
                                    <img src="{{ asset('images/figma/accounts/kebab.svg') }}" alt="">
                                </summary>
                                <div class="accounts-action-popover">
                                    <a href="{{ route('tenant.accounts.index', array_merge(request()->query(), ['edit' => $account['id']])) }}">Edit</a>
                                    @unless ($account['is_default'])
                                        <form action="{{ route('tenant.accounts.set-default', $account['id']) }}" method="post">
                                            @csrf
                                            <button type="submit">Set Default</button>
                                        </form>
                                    @endunless
                                    @if ($account['is_active'])
                                        <form action="{{ route('tenant.accounts.deactivate', $account['id']) }}" method="post">
                                            @csrf
                                            <button type="submit">Nonaktifkan</button>
                                        </form>
                                    @else
                                        <form action="{{ route('tenant.accounts.activate', $account['id']) }}" method="post">
                                            @csrf
                                            <button type="submit">Aktifkan</button>
                                        </form>
                                    @endif
                                </div>
                            </details>
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
                    <span class="accounts-page is-prev disabled" aria-hidden="true">
                        <img src="{{ asset('images/figma/accounts/page-prev.svg') }}" alt="">
                    </span>
                @else
                    <a href="{{ $accountPaginator->previousPageUrl() }}" class="accounts-page is-prev" aria-label="Halaman sebelumnya">
                        <img src="{{ asset('images/figma/accounts/page-prev.svg') }}" alt="">
                    </a>
                @endif
                @foreach ($visiblePages as $page)
                    <a href="{{ $accountPaginator->url($page) }}" class="accounts-page {{ $page === $currentPage ? 'active' : '' }}">{{ $page }}</a>
                @endforeach
                @if ($accountPaginator->hasMorePages())
                    <a href="{{ $accountPaginator->nextPageUrl() }}" class="accounts-page is-next" aria-label="Halaman berikutnya">
                        <img src="{{ asset('images/figma/accounts/page-next.svg') }}" alt="">
                    </a>
                @else
                    <span class="accounts-page is-next disabled" aria-hidden="true">
                        <img src="{{ asset('images/figma/accounts/page-next.svg') }}" alt="">
                    </span>
                @endif
            </div>
        </div>
    </section>

    @if ($accountModal)
        <div class="account-modal-backdrop">
            <div class="account-modal-card">
                <div class="account-modal-head">
                    <div>
                        <h3>{{ $accountModal['form']['title'] }}</h3>
                        <p>Atur identitas akun, tipe akun, opening balance, dan set default account.</p>
                    </div>
                    <a href="{{ $accountModal['form']['close_url'] }}" aria-label="Tutup modal">&times;</a>
                </div>

                <div class="account-modal-body">
                    @include('tenant.accounts.partials.account-form', [
                        'form' => $accountModal['form'],
                        'account' => $accountModal['account'],
                        'accountTypeOptions' => $accountTypeOptions,
                    ])
                </div>
            </div>
        </div>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const actionMenus = Array.from(document.querySelectorAll('[data-account-action-menu]'));

            if (actionMenus.length === 0) {
                return;
            }

            document.addEventListener('click', function (event) {
                actionMenus.forEach(function (menu) {
                    if (menu.open && !menu.contains(event.target)) {
                        menu.open = false;
                    }
                });
            });

            document.addEventListener('keydown', function (event) {
                if (event.key !== 'Escape') {
                    return;
                }

                actionMenus.forEach(function (menu) {
                    menu.open = false;
                });
            });
        });
    </script>
@endsection
