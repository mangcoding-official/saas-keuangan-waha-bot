@extends('layouts.tenant')

@section('content')
    <section class="members-summary-grid">
        <article class="dashboard-kpi-card">
            <p class="dashboard-kpi-label">Total event</p>
            <h2 class="dashboard-kpi-value">{{ $summary['total'] }}</h2>
            <p class="dashboard-kpi-note">50 log perubahan transaksi terbaru</p>
        </article>

        <article class="dashboard-kpi-card">
            <p class="dashboard-kpi-label">Transaction updated</p>
            <h2 class="dashboard-kpi-value">{{ $summary['updated'] }}</h2>
            <p class="dashboard-kpi-note">Perubahan Detail transaksi</p>
        </article>

        <article class="dashboard-kpi-card">
            <p class="dashboard-kpi-label">Transaction voided</p>
            <h2 class="dashboard-kpi-value">{{ $summary['voided'] }}</h2>
            <p class="dashboard-kpi-note">Transaksi yang dibatalkan</p>
        </article>
    </section>

    <article class="dashboard-card dashboard-table-card">

        <table class="dashboard-table">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Transaksi</th>
                    <th>Ringkasan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($auditLogs as $log)
                    <tr>
                        <td>{{ $log['created_at'] }}</td>
                        <td>{{ $log['actor'] }}</td>
                        <td>{{ $log['action_label'] }}</td>
                        <td>{{ $log['target'] }}</td>
                        <td>
                            <div class="dashboard-alert-list">
                                @foreach ($log['summary'] as $line)
                                    <p>{{ $line }}</p>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="dashboard-empty-cell">Belum ada audit log transaksi untuk tenant ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </article>
@endsection
