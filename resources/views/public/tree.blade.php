<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tree['tree_name'] ?? $tree['tree_code'] ?? 'Informasi Pohon Durian' }} — Perkebunan Durian</title>
    <style>
        :root {
            --bg: #f4f7f2;
            --card: #ffffff;
            --primary: #1f6b3a;
            --primary-soft: #e6f3ea;
            --text: #1c241e;
            --muted: #5a6a5e;
            --border: #dfe7e1;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.55;
            padding: 1.25rem 1rem 2.5rem;
        }
        .container { max-width: 640px; margin: 0 auto; }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 6px 20px rgba(16, 42, 24, 0.06);
        }
        .header {
            background: linear-gradient(135deg, #1f6b3a, #2f8a4e);
            color: #ffffff;
            padding: 1.5rem 1.25rem;
        }
        .badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 0.2rem 0.65rem;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.03em;
        }
        .title { margin: 0.5rem 0 0.25rem; font-size: 1.5rem; line-height: 1.25; }
        .subtitle { margin: 0; opacity: 0.92; font-size: 0.95rem; }
        .section { padding: 1.25rem; border-top: 1px solid var(--border); }
        .section:first-of-type { border-top: none; }
        .section-title {
            margin: 0 0 0.75rem;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--muted);
        }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 0.85rem; }
        .metric {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 0.75rem 0.9rem;
        }
        .metric-label { font-size: 0.78rem; color: var(--muted); display: block; }
        .metric-value { font-size: 1rem; font-weight: 600; color: var(--text); margin-top: 0.15rem; display: block; }
        .photos { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 0.75rem; }
        .photo-item { margin: 0; border-radius: 10px; overflow: hidden; border: 1px solid var(--border); background: var(--bg); }
        .photo-item img { width: 100%; height: 140px; object-fit: cover; display: block; }
        .photo-item figcaption { padding: 0.45rem 0.6rem; font-size: 0.8rem; color: var(--muted); }
        .footer { text-align: center; font-size: 0.8rem; color: var(--muted); margin-top: 1.25rem; }
    </style>
</head>
<body>
    <main class="container">
        <article class="card">
            <header class="header">
                @if (! empty($tree['tree_code']))
                    <span class="badge">{{ $tree['tree_code'] }}</span>
                @endif
                <h1 class="title">{{ $tree['tree_name'] ?? $tree['tree_code'] ?? 'Pohon Durian' }}</h1>
                @if (! empty($tree['variety']['name']))
                    <p class="subtitle">Varietas {{ $tree['variety']['name'] }}</p>
                @endif
            </header>

            <section class="section">
                <h2 class="section-title">Informasi Pohon</h2>
                <div class="grid">
                    @if (! empty($tree['garden']['name']))
                        <div class="metric">
                            <span class="metric-label">Kebun</span>
                            <span class="metric-value">{{ $tree['garden']['name'] }}</span>
                        </div>
                    @endif

                    @if (! empty($tree['general_location']))
                        @php
                            $locationParts = array_filter([
                                $tree['general_location']['village'] ?? null,
                                $tree['general_location']['district'] ?? null,
                                $tree['general_location']['regency'] ?? null,
                                $tree['general_location']['province'] ?? null,
                            ]);
                        @endphp
                        @if (! empty($locationParts))
                            <div class="metric">
                                <span class="metric-label">Lokasi Umum</span>
                                <span class="metric-value">{{ implode(', ', $locationParts) }}</span>
                            </div>
                        @endif
                    @endif

                    @if (! empty($tree['status']))
                        <div class="metric">
                            <span class="metric-label">Status</span>
                            <span class="metric-value">{{ $tree['status'] }}</span>
                        </div>
                    @endif

                    @if (! empty($tree['planted_at']))
                        <div class="metric">
                            <span class="metric-label">Tanggal Tanam</span>
                            <span class="metric-value">{{ $tree['planted_at'] }}</span>
                        </div>
                    @endif

                    @if (! empty($tree['age']['label']))
                        <div class="metric">
                            <span class="metric-label">Umur Pohon</span>
                            <span class="metric-value">{{ $tree['age']['label'] }}</span>
                        </div>
                    @endif
                </div>
            </section>

            @if (! empty($tree['variety']) && (! empty($tree['variety']['origin']) || ! empty($tree['variety']['description']) || ! empty($tree['variety']['characteristics'])))
                <section class="section">
                    <h2 class="section-title">Tentang Varietas</h2>
                    @if (! empty($tree['variety']['origin']))
                        <p style="margin: 0 0 0.5rem;"><strong>Asal:</strong> {{ $tree['variety']['origin'] }}</p>
                    @endif
                    @if (! empty($tree['variety']['description']))
                        <p style="margin: 0 0 0.5rem;">{{ $tree['variety']['description'] }}</p>
                    @endif
                    @if (! empty($tree['variety']['characteristics']))
                        <p style="margin: 0; color: var(--muted);">{{ $tree['variety']['characteristics'] }}</p>
                    @endif
                </section>
            @endif

            @if (! empty($tree['latest_measurement']))
                <section class="section">
                    <h2 class="section-title">Pengukuran Terakhir</h2>
                    <div class="grid">
                        @if ($tree['latest_measurement']['diameter_cm'] !== null)
                            <div class="metric">
                                <span class="metric-label">Diameter Batang</span>
                                <span class="metric-value">{{ $tree['latest_measurement']['diameter_cm'] }} cm</span>
                            </div>
                        @endif
                        @if ($tree['latest_measurement']['height_m'] !== null)
                            <div class="metric">
                                <span class="metric-label">Tinggi Pohon</span>
                                <span class="metric-value">{{ $tree['latest_measurement']['height_m'] }} m</span>
                            </div>
                        @endif
                        @if ($tree['latest_measurement']['canopy_width_m'] !== null)
                            <div class="metric">
                                <span class="metric-label">Lebar Tajuk</span>
                                <span class="metric-value">{{ $tree['latest_measurement']['canopy_width_m'] }} m</span>
                            </div>
                        @endif
                        @if (! empty($tree['latest_measurement']['measured_at']))
                            <div class="metric">
                                <span class="metric-label">Tanggal Ukur</span>
                                <span class="metric-value">{{ $tree['latest_measurement']['measured_at'] }}</span>
                            </div>
                        @endif
                    </div>
                </section>
            @endif

            @if (! empty($tree['photos']))
                <section class="section">
                    <h2 class="section-title">Dokumentasi Pohon</h2>
                    <div class="photos">
                        @foreach ($tree['photos'] as $photo)
                            <figure class="photo-item">
                                <img src="{{ $photo['url'] }}" alt="{{ $photo['caption'] ?? 'Foto pohon durian' }}" loading="lazy">
                                @if (! empty($photo['caption']))
                                    <figcaption>{{ $photo['caption'] }}</figcaption>
                                @endif
                            </figure>
                        @endforeach
                    </div>
                </section>
            @endif
        </article>

        <p class="footer">Sistem Informasi Manajemen Perkebunan Durian</p>
    </main>
</body>
</html>
