@extends('layouts.internal')

@php
    $currentPage = (int) $users->currentPage();
    $pageCount = max(1, (int) $users->lastPage());
    $visiblePages = collect(range(max(1, $currentPage - 1), min($pageCount, $currentPage + 1)))->all();
    $queryWithoutShow = request()->except('show');
@endphp

@section('content')
    <div class="members-page">
        <section class="members-page-header">
            <div>
                <h1 class="members-page-title">{{ $page['title'] }}</h1>
                <p class="members-page-copy">{{ $page['description'] }}</p>
            </div>
            <a href="{{ route('internal.verification.index') }}" class="members-add-button">
                <span class="members-add-button-icon" aria-hidden="true">
                    <img src="{{ asset('images/figma/accounts/table-list.svg') }}" alt="">
                </span>
                <span>Buka Antrian Verifikasi</span>
            </a>
        </section>

        <section class="members-summary-grid">
            <article class="members-summary-card members-summary-card-total">
                <div class="members-summary-card-head">
                    <div>
                        <p class="members-summary-label">TOTAL USER</p>
                        <div class="members-summary-ratio">
                            <strong>{{ $summary['total_users'] }}</strong>
                            <span>lintas tenant</span>
                        </div>
                    </div>
                    <span class="members-summary-icon is-total" aria-hidden="true">
                        <img src="{{ asset('images/icon-total-pengguna.svg') }}" alt="">
                    </span>
                </div>
                <div class="members-summary-progress" aria-hidden="true">
                    <span style="width: {{ max(8, min(100, ($summary['pending_count'] / max(1, $summary['total_users'])) * 100)) }}%;"></span>
                </div>
            </article>

            <article class="members-summary-card">
                <div class="members-summary-card-head">
                    <div>
                        <p class="members-summary-label">OWNER</p>
                        <strong class="members-summary-value">{{ $summary['owner_count'] }}</strong>
                    </div>
                    <span class="members-summary-icon is-owner" aria-hidden="true">
                        <img src="{{ asset('images/icon-owner.svg') }}" alt="">
                    </span>
                </div>
                <p class="members-summary-note is-positive">Akses pengelola tenant</p>
            </article>

            <article class="members-summary-card">
                <div class="members-summary-card-head">
                    <div>
                        <p class="members-summary-label">MEMBER</p>
                        <strong class="members-summary-value">{{ $summary['member_count'] }}</strong>
                    </div>
                    <span class="members-summary-icon is-member" aria-hidden="true">
                        <img src="{{ asset('images/icon-member.svg') }}" alt="">
                    </span>
                </div>
                <p class="members-summary-note">Pengguna kolaborator tenant</p>
            </article>

            <article class="members-summary-card is-alert">
                <div class="members-summary-card-head">
                    <div>
                        <p class="members-summary-label">MENUNGGU VERIFIKASI</p>
                        <strong class="members-summary-value">{{ $summary['pending_count'] }}</strong>
                    </div>
                    <span class="members-summary-icon is-alert" aria-hidden="true">
                        <img src="{{ asset('images/icon-need-verification.svg') }}" alt="">
                    </span>
                </div>
                <p class="members-summary-note is-danger">
                    {{ $summary['pending_count'] > 0 ? 'Perlu resend atau regenerate code' : 'Tidak ada antrean tertunda' }}
                </p>
            </article>
        </section>

        <section class="members-table-card">
            <div class="members-table-head">
                <h2>Daftar User Tenant</h2>
                <div class="members-table-tools">
                    @if ($search !== '')
                        <x-ui.badge tone="neutral">Filter: {{ $search }}</x-ui.badge>
                    @endif
                </div>
            </div>

            <table class="members-table">
                <thead>
                    <tr>
                        <th>TENANT / USER</th>
                        <th>ROLE</th>
                        <th>WHATSAPP</th>
                        <th>STATUS AKUN</th>
                        <th>VERIFIKASI</th>
                        <th>CODE</th>
                        <th>TERAKHIR AKTIF</th>
                        <th>AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td class="members-name-cell">
                                <span class="members-avatar">{{ $user['initials'] }}</span>
                                <div>
                                    <strong>{{ $user['name'] }}</strong>
                                    <span>{{ $user['tenant_name'] }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="members-role-pill {{ $user['role_key'] === 'owner' ? 'is-owner' : 'is-member' }}">
                                    {{ $user['role_label'] }}
                                </span>
                            </td>
                            <td class="members-whatsapp-cell">{{ $user['whatsapp_number'] }}</td>
                            <td>
                                <span class="members-status-pill {{ $user['user_status_key'] === 'active' ? 'is-active' : 'is-inactive' }}">
                                    <span aria-hidden="true"></span>
                                    {{ $user['user_status_label'] }}
                                </span>
                            </td>
                            <td>
                                <span class="members-status-pill {{ $user['verification_status_key'] === 'verified' ? 'is-verified' : 'is-pending' }}">
                                    <span aria-hidden="true"></span>
                                    {{ $user['verification_status_label'] }}
                                </span>
                            </td>
                            <td>
                                <strong>{{ $user['activation_code'] }}</strong>
                                <span>{{ $user['activation_note'] }}</span>
                            </td>
                            <td class="members-last-active-cell">{{ $user['last_active_at'] }}</td>
                            <td class="accounts-action-menu-cell">
                                <details class="accounts-action-menu" data-account-action-menu>
                                    <summary aria-label="Aksi user">
                                        <img src="{{ asset('images/figma/accounts/kebab.svg') }}" alt="">
                                    </summary>
                                    <div class="accounts-action-popover">
                                        <a href="{{ route('internal.users.index', array_merge(request()->query(), ['show' => $user['id']])) }}">Detail</a>
                                        <a href="{{ route('internal.tenants.show', $user['tenant_id']) }}">Buka Tenant</a>
                                        @if ($user['can_manage_verification'])
                                            <form action="{{ route('internal.verification.resend', $user['id']) }}" method="post">
                                                @csrf
                                                <button type="submit">Resend Code</button>
                                            </form>
                                            <form action="{{ route('internal.verification.regenerate', $user['id']) }}" method="post">
                                                @csrf
                                                <button type="submit">Regenerate Code</button>
                                            </form>
                                        @else
                                            <button type="button" disabled>Sudah Terverifikasi</button>
                                        @endif
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="members-empty-cell">Belum ada user yang cocok dengan data pencarian ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="accounts-pagination members-pagination">
                <p>Menampilkan {{ $users->firstItem() ?? 0 }}-{{ $users->lastItem() ?? 0 }} dari {{ $users->total() }} user</p>
                <div>
                    @if ($users->onFirstPage())
                        <span class="accounts-page is-prev disabled" aria-hidden="true">
                            <img src="{{ asset('images/page-prev.svg') }}" alt="">
                        </span>
                    @else
                        <a href="{{ $users->previousPageUrl() }}" class="accounts-page is-prev" aria-label="Halaman sebelumnya">
                            <img src="{{ asset('images/page-prev.svg') }}" alt="">
                        </a>
                    @endif
                    @foreach ($visiblePages as $visiblePage)
                        <a href="{{ $users->url($visiblePage) }}" class="accounts-page {{ $visiblePage === $currentPage ? 'active' : '' }}">{{ $visiblePage }}</a>
                    @endforeach
                    @if ($users->hasMorePages())
                        <a href="{{ $users->nextPageUrl() }}" class="accounts-page is-next" aria-label="Halaman berikutnya">
                            <img src="{{ asset('images/page-next.svg') }}" alt="">
                        </a>
                    @else
                        <span class="accounts-page is-next disabled" aria-hidden="true">
                            <img src="{{ asset('images/page-next.svg') }}" alt="">
                        </span>
                    @endif
                </div>
            </div>
        </section>
    </div>

    @if ($detail)
        <div class="account-modal-backdrop">
            <div class="account-modal-card">
                <div class="account-modal-head">
                    <div class="internal-user-modal-head-copy">
                        <h3>{{ $detail['title'] }}</h3>
                        <p>{{ $detail['subtitle'] }}</p>
                    </div>
                    <a href="{{ route('internal.users.index', $queryWithoutShow) }}" aria-label="Tutup modal">&times;</a>
                </div>

                <div class="account-modal-body internal-user-modal-body">
                    <div class="dashboard-inline-badge internal-user-modal-badges">
                        @foreach ($detail['badges'] as $badge)
                            <x-ui.badge :tone="$badge['tone']">{{ $badge['label'] }}</x-ui.badge>
                        @endforeach
                    </div>

                    <div class="internal-user-modal-sections">
                        @foreach ($detail['sections'] as $section)
                            <section class="internal-user-modal-section">
                                <h3 class="dashboard-section-title">{{ $section['title'] }}</h3>
                                <div class="dashboard-alert-list internal-user-modal-lines">
                                    @foreach ($section['lines'] as $line)
                                        <p>{{ $line }}</p>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach
                    </div>

                    <section class="internal-user-modal-section internal-user-modal-section-actions">
                        <h3 class="dashboard-section-title">Aksi Manage</h3>
                        <div class="internal-user-modal-actions">
                            <a href="{{ $detail['tenant_href'] }}" class="button button-secondary button-compact">Buka Detail Tenant</a>
                            @if ($detail['can_manage_verification'])
                                <form action="{{ route('internal.verification.resend', request()->query('show')) }}" method="post">
                                    @csrf
                                    <button class="button button-secondary button-compact" type="submit">Resend Code</button>
                                </form>
                                <form action="{{ route('internal.verification.regenerate', request()->query('show')) }}" method="post">
                                    @csrf
                                    <button class="button button-ghost button-compact internal-user-modal-action-wide" type="submit">Regenerate Code</button>
                                </form>
                            @endif
                        </div>
                    </section>
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
