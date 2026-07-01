@extends('layouts.internal')

@php
    $currentPage = (int) $rows->currentPage();
    $pageCount = max(1, (int) $rows->lastPage());
    $visiblePages = collect(range(max(1, $currentPage - 1), min($pageCount, $currentPage + 1)))->all();
    $queryWithoutShow = request()->except('show');
@endphp

@section('content')
    @if ($errors->any())
        <div class="members-alert members-alert-warning">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="members-page">
        <section class="members-page-header">
            <div>
                <h1 class="members-page-title">Invite User</h1>
                <p class="members-page-copy">Halaman ini khusus untuk mengundang user baru masuk ke sistem.</p>
            </div>
        </section>

        <section class="members-summary-grid">
            <article class="members-summary-card members-summary-card-total">
                <div class="members-summary-card-head">
                    <div>
                        <p class="members-summary-label">TOTAL INVITE</p>
                        <div class="members-summary-ratio">
                            <strong>{{ $summary['total'] }}</strong>
                        </div>
                    </div>
                    <span class="members-summary-icon is-total" aria-hidden="true">
                        <img src="{{ asset('images/icon-total-pengguna.svg') }}" alt="">
                    </span>
                </div>
                <div class="members-summary-progress" aria-hidden="true">
                    <span style="width: {{ max(8, min(100, ($summary['pending'] / max(1, $summary['total'])) * 100)) }}%;"></span>
                </div>
            </article>

            <article class="members-summary-card">
                <div class="members-summary-card-head">
                    <div>
                        <p class="members-summary-label">PENDING</p>
                        <strong class="members-summary-value">{{ $summary['pending'] }}</strong>
                    </div>
                    <span class="members-summary-icon is-owner" aria-hidden="true">
                        <img src="{{ asset('images/icon-owner.svg') }}" alt="">
                    </span>
                </div>
                <p class="members-summary-note is-positive">Pending user</p>
            </article>

            <article class="members-summary-card">
                <div class="members-summary-card-head">
                    <div>
                        <p class="members-summary-label">USED</p>
                        <strong class="members-summary-value">{{ $summary['used'] }}</strong>
                    </div>
                    <span class="members-summary-icon is-member" aria-hidden="true">
                        <img src="{{ asset('images/icon-member.svg') }}" alt="">
                    </span>
                </div>
                <p class="members-summary-note">User aktif</p>
            </article>

            <article class="members-summary-card is-alert">
                <div class="members-summary-card-head">
                    <div>
                        <p class="members-summary-label">EXPIRED / REVOKED</p>
                        <strong class="members-summary-value">{{ $summary['blocked'] }}</strong>
                    </div>
                    <span class="members-summary-icon is-alert" aria-hidden="true">
                        <img src="{{ asset('images/icon-need-verification.svg') }}" alt="">
                    </span>
                </div>
                <p class="members-summary-note is-danger">Perlu generate invite baru bila onboarding dilanjutkan</p>
            </article>
        </section>

        <section class="invite-layout-grid">
            <article class="dashboard-card invite-form-card">
                <div class="invite-panel-head">
                    <div>
                        <h2 class="dashboard-section-title">Invite User</h2>
                    </div>
                </div>

                <form action="{{ route('internal.invites.store') }}" method="post" class="invite-form-grid invite-form-grid-polished">
                    @csrf

                    <x-ui.input
                        name="invited_email"
                        type="email"
                        label="Email  (opsional)"
                        placeholder="contoh: user@brand.com"
                        help="Kosongkan bila kode invite boleh dipakai tanpa batas waktu."
                        autocomplete="email"
                        autocapitalize="off"
                        spellcheck="false"
                        full
                    />

                    <x-ui.input
                        name="invited_whatsapp_number"
                        label="WhatsApp (opsional)"
                        placeholder="contoh: 0812xxxx"
                        help="Sistem akan coba kirim invite otomatis lewat bot aktif."
                        autocomplete="tel"
                        inputmode="numeric"
                        spellcheck="false"
                        full
                    />

                    <x-ui.input
                        name="expires_at"
                        type="datetime-local"
                        label="Berlaku sampai (opsional)"
                        help="Kosongkan bila invite tidak perlu batas waktu."
                        full
                    />

                    <x-ui.input
                        name="note"
                        label="Catatan (opsional)"
                        placeholder="mis. onboarding batch Juli"
                        help="Catatan lainnya."
                        full
                    />

                    <div class="members-form-actions invite-form-actions field field-full">
                        <button class="button button-primary" type="submit">Invite User</button>
                    </div>
                </form>
            </article>
            <article class="members-table-card invite-table-card">
                <div class="members-table-head">
                    <h2>Daftar User</h2>
                </div>

                <div class="invite-table-wrap">
                    <table class="members-table invite-table">
                        <thead>
                            <tr>
                                <th>TARGET USER</th>
                                <th>STATUS</th>
                                <th>INVITE</th>
                                <th>PROGRESS</th>
                                <th>AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr>
                                    <td class="members-name-cell">
                                        <span class="members-avatar">{{ strtoupper(substr($row['code'], 0, 2)) }}</span>
                                        <div>
                                            <strong>{{ $row['invited_email'] ?: 'Tanpa email target' }}</strong>
                                            <span>{{ $row['invited_whatsapp_number'] ?: 'Tanpa nomor WhatsApp target' }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <x-ui.badge tone="{{ $row['status_key'] === 'pending' ? 'success' : ($row['status_key'] === 'used' ? 'neutral' : 'warning') }}">
                                            {{ $row['status_label'] }}
                                        </x-ui.badge>
                                    </td>
                                    <td>
                                        <strong>{{ $row['code'] }}</strong>
                                        <span>{{ $row['expires_at'] ? 'Berlaku sampai '.$row['expires_at'] : 'Tanpa batas waktu' }}</span>
                                    </td>
                                    <td>
                                        @if ($row['used_tenant_name'])
                                            <strong>{{ $row['used_tenant_name'] }}</strong>
                                            <span>Dipakai {{ $row['used_at'] }}</span>
                                        @elseif ($row['revoked_at'])
                                            <strong>Invite dimatikan</strong>
                                            <span>Revoked {{ $row['revoked_at'] }}</span>
                                        @else
                                            <strong>Belum dipakai</strong>
                                            <span>Dibuat {{ $row['created_at'] }} oleh {{ $row['creator_name'] }}</span>
                                        @endif
                                    </td>
                                    <td class="accounts-action-menu-cell">
                                        <details class="accounts-action-menu" data-account-action-menu>
                                            <summary aria-label="Aksi invite">
                                                <img src="{{ asset('images/figma/accounts/kebab.svg') }}" alt="">
                                            </summary>
                                            <div class="accounts-action-popover">
                                                <a href="{{ route('internal.invites.index', array_merge(request()->query(), ['show' => $row['id']])) }}">Detail</a>
                                                <button
                                                    type="button"
                                                    data-copy-text="{{ $row['code'] }}"
                                                    data-copy-label="Copy Code"
                                                    data-copy-success-label="Copied"
                                                >
                                                    Copy Code
                                                </button>
                                                <button
                                                    type="button"
                                                    data-copy-text="{{ $row['share_url'] }}"
                                                    data-copy-label="Copy Link"
                                                    data-copy-success-label="Copied"
                                                >
                                                    Copy Link
                                                </button>
                                                @if ($row['can_send_whatsapp'])
                                                    <form action="{{ route('internal.invites.send-whatsapp', $row['id']) }}" method="post">
                                                        @csrf
                                                        <button type="submit">Send via WA</button>
                                                    </form>
                                                @endif
                                                @if ($row['can_revoke'])
                                                    <form action="{{ route('internal.invites.revoke', $row['id']) }}" method="post">
                                                        @csrf
                                                        <input type="hidden" name="reason" value="">
                                                        <button type="submit">Revoke Invite</button>
                                                    </form>
                                                @elseif (!$row['can_send_whatsapp'])
                                                    <button type="button" disabled>Tidak ada aksi lanjutan</button>
                                                @endif
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="members-empty-cell">Belum ada user yang tercatat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="accounts-pagination members-pagination">
                    <p>Menampilkan {{ $rows->firstItem() ?? 0 }}-{{ $rows->lastItem() ?? 0 }} dari {{ $rows->total() }} invite</p>
                    <div>
                        @if ($rows->onFirstPage())
                            <span class="accounts-page is-prev disabled" aria-hidden="true">
                                <img src="{{ asset('images/page-prev.svg') }}" alt="">
                            </span>
                        @else
                            <a href="{{ $rows->previousPageUrl() }}" class="accounts-page is-prev" aria-label="Halaman sebelumnya">
                                <img src="{{ asset('images/page-prev.svg') }}" alt="">
                            </a>
                        @endif
                        @foreach ($visiblePages as $visiblePage)
                            <a href="{{ $rows->url($visiblePage) }}" class="accounts-page {{ $visiblePage === $currentPage ? 'active' : '' }}">{{ $visiblePage }}</a>
                        @endforeach
                        @if ($rows->hasMorePages())
                            <a href="{{ $rows->nextPageUrl() }}" class="accounts-page is-next" aria-label="Halaman berikutnya">
                                <img src="{{ asset('images/page-next.svg') }}" alt="">
                            </a>
                        @else
                            <span class="accounts-page is-next disabled" aria-hidden="true">
                                <img src="{{ asset('images/page-next.svg') }}" alt="">
                            </span>
                        @endif
                    </div>
                </div>
            </article>
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
                    <a href="{{ route('internal.invites.index', $queryWithoutShow) }}" aria-label="Tutup modal">&times;</a>
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
                        <h3 class="dashboard-section-title">Aksi Invite</h3>
                        <div class="internal-user-modal-actions">
                            <button
                                type="button"
                                class="button button-secondary button-compact"
                                data-copy-text="{{ $detail['code'] }}"
                                data-copy-label="Copy Code"
                                data-copy-success-label="Copied"
                            >
                                Copy Code
                            </button>
                            <button
                                type="button"
                                class="button button-ghost button-compact"
                                data-copy-text="{{ $detail['share_url'] }}"
                                data-copy-label="Copy Link"
                                data-copy-success-label="Copied"
                            >
                                Copy Link
                            </button>
                            @if ($detail['can_send_whatsapp'])
                                <form action="{{ route('internal.invites.send-whatsapp', $detail['id']) }}" method="post">
                                    @csrf
                                    <button class="button button-secondary button-compact" type="submit">Send via WA</button>
                                </form>
                            @endif
                            @if ($detail['can_revoke'])
                                <form action="{{ route('internal.invites.revoke', $detail['id']) }}" method="post">
                                    @csrf
                                    <input type="hidden" name="reason" value="">
                                    <button class="button button-ghost button-compact internal-user-modal-action-wide" type="submit">Revoke Invite</button>
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
