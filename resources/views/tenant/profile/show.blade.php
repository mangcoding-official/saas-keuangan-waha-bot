@extends('layouts.tenant')

@section('content')
    <div class="profile-page">
        <section class="profile-hero-card">
            <div class="profile-hero-main">
                <span class="profile-avatar">{{ $profile['initials'] }}</span>

                <div class="profile-hero-copy">
                    <div class="profile-hero-head">
                        <p class="profile-eyebrow">AKUN TENANT</p>
                        <h1>{{ $profile['name'] }}</h1>
                        <p>{{ $workspace['name'] }} &bull; {{ $profile['role'] }}</p>
                    </div>

                    <div class="profile-pill-row">
                        <span class="profile-pill is-primary">{{ $profile['user_status_label'] }}</span>
                        <span class="profile-pill {{ str_contains(strtolower($profile['verification_status_label']), 'menunggu') ? 'is-warning' : 'is-success' }}">
                            {{ $profile['verification_status_label'] }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="profile-hero-aside">
                <div class="profile-mini-stat">
                    <strong>{{ $workspace['workspace_user_count'] }}</strong>
                    <span>anggota workspace</span>
                </div>
                <div class="profile-mini-stat">
                    <strong>{{ $workspace['verified_user_count'] }}</strong>
                    <span>sudah terverifikasi</span>
                </div>
                <div class="profile-mini-stat">
                    <strong>{{ $workspace['pending_user_count'] }}</strong>
                    <span>masih menunggu</span>
                </div>
            </div>
        </section>

        <div class="profile-content-grid">
            <section class="profile-panel-card">
                <div class="profile-panel-head">
                    <div>
                        <p class="profile-panel-eyebrow">DETAIL AKUN</p>
                        <h2>Data yang dipakai saat Anda masuk</h2>
                    </div>
                </div>

                <dl class="profile-detail-grid">
                    <div class="profile-detail-item">
                        <dt>Nama lengkap</dt>
                        <dd>{{ $profile['name'] }}</dd>
                    </div>
                    <div class="profile-detail-item">
                        <dt>Role</dt>
                        <dd>{{ $profile['role'] }}</dd>
                    </div>
                    <div class="profile-detail-item">
                        <dt>Email</dt>
                        <dd>{{ $profile['email'] }}</dd>
                    </div>
                    <div class="profile-detail-item">
                        <dt>Nomor WhatsApp</dt>
                        <dd>{{ $profile['whatsapp_number'] }}</dd>
                    </div>
                    <div class="profile-detail-item">
                        <dt>Bergabung sejak</dt>
                        <dd>{{ $profile['joined_at'] }}</dd>
                    </div>
                    <div class="profile-detail-item">
                        <dt>Terakhir aktif</dt>
                        <dd>{{ $profile['last_login_at'] }}</dd>
                    </div>
                    <div class="profile-detail-item">
                        <dt>Status verifikasi</dt>
                        <dd>{{ $profile['verified_at'] }}</dd>
                    </div>
                    <div class="profile-detail-item">
                        <dt>Sumber pendaftaran</dt>
                        <dd>{{ $profile['invited_by'] }}</dd>
                    </div>
                </dl>
            </section>

            <aside class="profile-side-stack">
                <section class="profile-panel-card">
                    <div class="profile-panel-head">
                        <div>
                            <p class="profile-panel-eyebrow">WORKSPACE</p>
                            <h2>Ringkasan tenant</h2>
                        </div>
                    </div>

                    <dl class="profile-workspace-list">
                        <div>
                            <dt>Nama workspace</dt>
                            <dd>{{ $workspace['name'] }}</dd>
                        </div>
                        <div>
                            <dt>Tipe tenant</dt>
                            <dd>{{ $workspace['tenant_type'] }}</dd>
                        </div>
                        <div>
                            <dt>Zona waktu</dt>
                            <dd>{{ $workspace['timezone'] }}</dd>
                        </div>
                        <div>
                            <dt>Status workspace</dt>
                            <dd>{{ $workspace['tenant_status'] }}</dd>
                        </div>
                        <div>
                            <dt>Paket layanan</dt>
                            <dd>{{ $workspace['service_plan'] }}</dd>
                        </div>
                        <div>
                            <dt>Status layanan</dt>
                            <dd>{{ $workspace['service_status'] }}</dd>
                        </div>
                        <div>
                            <dt>AI addon</dt>
                            <dd>{{ $workspace['ai_addon_status'] }}</dd>
                        </div>
                    </dl>
                </section>
            </aside>
        </div>
    </div>
@endsection
