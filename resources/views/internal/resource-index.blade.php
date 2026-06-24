@extends('layouts.internal')

@section('content')
    <section class="members-summary-grid">
        @foreach ($summary as $item)
            <article class="dashboard-kpi-card {{ ($item['tone'] ?? 'neutral') === 'alert' ? 'is-alert' : '' }}">
                <p class="dashboard-kpi-label">{{ $item['label'] }}</p>
                <h2 class="dashboard-kpi-value">{{ $item['value'] }}</h2>
                <p class="dashboard-kpi-note">{{ $item['note'] }}</p>
            </article>
        @endforeach
    </section>

    @if (($table['rows'] ?? []) === [])
        <article class="dashboard-card">
            <x-ui.state-shell
                :title="$emptyState['title'] ?? 'Belum ada data'"
                :description="$emptyState['description'] ?? 'Data operasional belum tersedia untuk modul ini.'"
                tone="warning"
            />
        </article>
    @else
        <section class="members-layout-grid">
            <article class="dashboard-card dashboard-table-card">
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            @foreach ($table['headers'] as $header)
                                <th>{{ $header }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($table['rows'] as $row)
                            <tr>
                                @foreach ($row['cells'] as $cell)
                                    <td>
                                        @if (!empty($cell['primary']))
                                            <div><strong>{{ $cell['primary'] }}</strong></div>
                                        @endif

                                        @if (!empty($cell['badges']))
                                            <div class="members-badge-stack">
                                                @foreach ($cell['badges'] as $badge)
                                                    <x-ui.badge :tone="$badge['tone'] ?? 'neutral'">{{ $badge['label'] }}</x-ui.badge>
                                                @endforeach
                                            </div>
                                        @endif

                                        @if (!empty($cell['lines']))
                                            <div class="dashboard-alert-list">
                                                @foreach ($cell['lines'] as $line)
                                                    <p>{{ $line }}</p>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                @endforeach

                                <td>
                                    <div class="members-action-stack">
                                        @foreach ($row['actions'] ?? [] as $action)
                                            <x-ui.button :href="$action['href']" :variant="$action['variant']" class="button-compact">{{ $action['label'] }}</x-ui.button>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </article>

            <div class="dashboard-side-column">
                <article class="dashboard-card">
                    <h2 class="dashboard-section-title">Inspector</h2>

                    @if (!empty($detail))
                        <div class="dashboard-inline-badge">
                            @foreach ($detail['badges'] ?? [] as $badge)
                                <x-ui.badge :tone="$badge['tone'] ?? 'neutral'">{{ $badge['label'] }}</x-ui.badge>
                            @endforeach
                        </div>

                        <div class="dashboard-alert-list">
                            <p><strong>{{ $detail['title'] }}</strong></p>
                        </div>

                        @foreach ($detail['sections'] ?? [] as $section)
                            <hr class="dashboard-divider">
                            <h3 class="dashboard-section-title">{{ $section['title'] }}</h3>

                            @if (!empty($section['lines']))
                                <div class="dashboard-alert-list">
                                    @foreach ($section['lines'] as $line)
                                        <p>{{ $line }}</p>
                                    @endforeach
                                </div>
                            @endif

                            @if (!empty($section['links']))
                                <div class="members-action-stack">
                                    @foreach ($section['links'] as $link)
                                        <x-ui.button :href="$link['href']" variant="secondary" class="button-compact">{{ $link['label'] }}</x-ui.button>
                                    @endforeach
                                </div>
                            @endif

                            @if (!empty($section['forms']))
                                <div class="members-action-stack">
                                    @foreach ($section['forms'] as $form)
                                        <form action="{{ $form['action'] }}" method="post">
                                            @csrf
                                            <button class="button button-{{ $form['variant'] ?? 'secondary' }} button-compact" type="submit">{{ $form['label'] }}</button>
                                        </form>
                                    @endforeach
                                </div>
                            @endif

                            @if (!empty($section['image']))
                                <img src="{{ $section['image'] }}" alt="{{ $section['title'] }}" class="dashboard-inline-image">
                            @endif

                            @if (!empty($section['code']))
                                <pre class="dashboard-code-block">{{ $section['code'] }}</pre>
                            @endif
                        @endforeach
                    @else
                        <x-ui.state-shell
                            title="Pilih data"
                            description="Klik tombol inspect pada tabel untuk membuka detail operasional di panel kanan."
                            tone="neutral"
                        />
                    @endif
                </article>
            </div>
        </section>
    @endif
@endsection
