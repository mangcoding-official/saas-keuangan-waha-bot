@extends('layouts.tenant')

@php
    $currentPage = (int) $memberPaginator->currentPage();
@endphp

@section('content')
    @if ($errors->any() && !$memberModal)
        <div class="tenant-inline-alert">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="members-page">
        <section class="members-page-header">
            <div>
                <h1 class="members-page-title">Manajemen Anggota</h1>
                <p class="members-page-copy">Kelola dan pantau akses seluruh anggota tim Anda.</p>
            </div>
            <a href="{{ route('tenant.members.index', array_merge(request()->query(), ['create' => 1])) }}" class="members-add-button">
                <span class="members-add-button-icon" aria-hidden="true">
                    <img src="{{ asset('images/figma/accounts/plus.svg') }}" alt="">
                </span>
                <span>Tambah Anggota</span>
            </a>
        </section>

        <section class="members-summary-grid">
            <article class="members-summary-card members-summary-card-total">
                <div class="members-summary-card-head">
                    <div>
                        <p class="members-summary-label">TOTAL PENGGUNA</p>
                        <div class="members-summary-ratio">
                            <strong>{{ $summary['total_users'] }}</strong>
                            <span>/ {{ $summary['limit'] }}</span>
                        </div>
                    </div>
                    <span class="members-summary-icon is-total" aria-hidden="true">
                        <img src="{{ asset('images/icon-total-pengguna.svg') }}" alt="">
                    </span>
                </div>
                <div class="members-summary-progress" aria-hidden="true">
                    <span style="width: {{ max(8, min(100, ($summary['total_users'] / max(1, $summary['limit'])) * 100)) }}%;"></span>
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
                <p class="members-summary-note is-positive">Akses Penuh</p>
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
                <p class="members-summary-note">Aktif Berlangganan</p>
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
                    {{ $summary['pending_count'] > 0 ? 'Perlu tindak lanjut' : 'Semua data bersih' }}
                </p>
            </article>
        </section>

        <section class="members-table-card">
            <div class="members-table-head">
                <h2>Daftar Anggota</h2>
                <div class="members-table-tools">
                    <button type="button" class="accounts-table-tool" aria-label="Urutkan data">
                        <img src="{{ asset('images/figma/accounts/table-list.svg') }}" alt="">
                    </button>
                </div>
            </div>

            <table class="members-table">
                    <thead>
                        <tr>
                            <th>NAMA</th>
                            <th>ROLE</th>
                            <th>WHATSAPP</th>
                            <th>STATUS AKUN</th>
                            <th>VERIFIKASI WA</th>
                            <th>TERAKHIR AKTIF</th>
                            <th>AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($members as $member)
                            <tr>
                                <td class="members-name-cell">
                                    <span class="members-avatar">{{ $member['initials'] }}</span>
                                    <div>
                                        <strong>{{ $member['name'] }}</strong>
                                        <span>{{ $member['email'] }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="members-role-pill {{ $member['role_key'] === 'owner' ? 'is-owner' : 'is-member' }}">
                                        {{ $member['role'] }}
                                    </span>
                                </td>
                                <td class="members-whatsapp-cell">{{ $member['whatsapp_number'] }}</td>
                                <td>
                                    <span class="members-status-pill {{ $member['user_status_key'] === 'active' ? 'is-active' : 'is-inactive' }}">
                                        <span aria-hidden="true"></span>
                                        {{ $member['user_status_label'] }}
                                    </span>
                                </td>
                                <td>
                                    <span class="members-status-pill {{ $member['verification_status_key'] === 'verified' ? 'is-verified' : 'is-pending' }}">
                                        <span aria-hidden="true"></span>
                                        {{ $member['verification_status_label'] }}
                                    </span>
                                </td>
                                <td class="members-last-active-cell">{{ $member['last_active_at'] }}</td>
                                <td class="accounts-action-menu-cell">
                                    <details class="accounts-action-menu" data-account-action-menu>
                                        <summary aria-label="Aksi anggota">
                                            <img src="{{ asset('images/figma/accounts/kebab.svg') }}" alt="">
                                        </summary>
                                        <div class="accounts-action-popover">
                                            @if ($member['can_manage_code'])
                                                <form action="{{ route('tenant.members.resend', $member['id']) }}" method="post">
                                                    @csrf
                                                    <button type="submit">Kirim Ulang Kode</button>
                                                </form>
                                                <form action="{{ route('tenant.members.regenerate', $member['id']) }}" method="post">
                                                    @csrf
                                                    <button type="submit">Generate Kode Baru</button>
                                                </form>
                                            @else
                                                <button type="button" disabled>Tidak ada aksi</button>
                                            @endif
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="members-empty-cell">Belum ada anggota untuk tenant ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

            <div class="accounts-pagination members-pagination">
                <p>Menampilkan {{ $memberPaginator->firstItem() ?? 0 }}-{{ $memberPaginator->lastItem() ?? 0 }} dari {{ $memberPaginator->total() }} anggota</p>
                <div>
                    @if ($memberPaginator->onFirstPage())
                        <span class="accounts-page is-prev disabled" aria-hidden="true">
                            <img src="{{ asset('images/page-prev.svg') }}" alt="">
                        </span>
                    @else
                        <a href="{{ $memberPaginator->previousPageUrl() }}" class="accounts-page is-prev" aria-label="Halaman sebelumnya">
                            <img src="{{ asset('images/page-prev.svg') }}" alt="">
                        </a>
                    @endif

                    <span class="accounts-page active">{{ $currentPage }}</span>

                    @if ($memberPaginator->hasMorePages())
                        <a href="{{ $memberPaginator->nextPageUrl() }}" class="accounts-page is-next" aria-label="Halaman selanjutnya">
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

        <section class="members-tips-grid">
            <article class="members-tip-card is-security">
                <span class="members-tip-icon" aria-hidden="true">i</span>
                <div>
                    <h3>Tips Keamanan</h3>
                    <p>Pastikan selalu melakukan audit log berkala untuk memantau perubahan role yang dilakukan oleh sesama owner.</p>
                </div>
            </article>

            <article class="members-tip-card is-verification">
                <span class="members-tip-icon" aria-hidden="true">&#10003;</span>
                <div>
                    <h3>Verifikasi WhatsApp</h3>
                    <p>Anggota yang terverifikasi WhatsApp akan mendapatkan notifikasi transaksi langsung ke ponsel mereka secara real-time.</p>
                </div>
            </article>
        </section>

        <footer class="members-footer">
            <p>&copy; 2024 Macau Financial System. Made for Modern Enterprise.</p>
        </footer>
    </div>

    @if ($memberModal)
        <div class="account-modal-backdrop">
            <div class="account-modal-card">
                <div class="account-modal-head">
                    <div>
                        <h3>Tambah Anggota</h3>
                        <p>Tambahkan anggota tim baru dan kirim activation code untuk verifikasi WhatsApp.</p>
                    </div>
                    <a href="{{ $memberModal['close_url'] }}" aria-label="Tutup modal">&times;</a>
                </div>

                <div class="account-modal-body">
                    @if ($slotIsFull)
                        <div class="members-alert members-alert-warning">
                            Member sudah penuh. Maksimal 1 owner dan 4 member.
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="members-alert members-alert-warning">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form action="{{ route('tenant.members.store') }}" method="post" class="field-grid">
                        @csrf

                        <x-ui.input
                            name="name"
                            label="Nama anggota"
                            placeholder="Contoh: Budi Finance"
                            :disabled="$slotIsFull"
                            required
                        />

                        <x-ui.input
                            name="whatsapp_number"
                            label="Nomor WhatsApp"
                            placeholder="08xxxxxxxxxx"
                            :disabled="$slotIsFull"
                            required
                        />

                        <div class="field field-full">
                            <div class="members-form-actions">
                                <button class="button button-primary" type="submit" @disabled($slotIsFull)>Simpan Anggota</button>
                                <a href="{{ $memberModal['close_url'] }}" class="button button-secondary">Batal</a>
                            </div>
                        </div>
                    </form>
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
