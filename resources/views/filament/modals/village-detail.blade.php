<div class="space-y-6 text-sm max-h-[75vh] overflow-y-auto pr-2">
    {{-- Header Banner & Stats --}}
    <div class="p-5 bg-gradient-to-r from-emerald-500/10 via-teal-500/10 to-sky-500/10 dark:from-emerald-950/40 dark:via-teal-950/40 dark:to-sky-950/40 rounded-2xl border border-emerald-200/60 dark:border-emerald-800/60">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300">
                    Wilayah Perkebunan Banyumas
                </span>
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mt-1">Desa {{ $record->name }}</h3>
                <p class="text-xs text-gray-600 dark:text-gray-400">
                    Kecamatan {{ $record->district ?: '-' }}, {{ $record->regency ?: 'Kabupaten Banyumas' }}, {{ $record->province ?: 'Jawa Tengah' }}
                </p>
            </div>
            <div class="text-right">
                <span class="text-xs text-gray-500 dark:text-gray-400 block">Status Data</span>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium {{ $record->trashed() ? 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $record->trashed() ? 'bg-rose-500' : 'bg-emerald-500' }}"></span>
                    {{ $record->trashed() ? 'Di Tempat Sampah' : 'Aktif' }}
                </span>
            </div>
        </div>

        {{-- 3 KPI Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="p-3.5 bg-white/90 dark:bg-gray-900/90 rounded-xl border border-gray-200/80 dark:border-gray-800 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Blok</span>
                    <span class="p-1.5 rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                    </span>
                </div>
                <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-2">
                    {{ $record->blocks()->count() }}
                </p>
                <span class="text-[11px] text-gray-500 dark:text-gray-400">Area blok terdaftar</span>
            </div>

            <div class="p-3.5 bg-white/90 dark:bg-gray-900/90 rounded-xl border border-gray-200/80 dark:border-gray-800 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Kebun</span>
                    <span class="p-1.5 rounded-lg bg-sky-50 text-sky-600 dark:bg-sky-900/40 dark:text-sky-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                    </span>
                </div>
                <p class="text-2xl font-bold text-sky-600 dark:text-sky-400 mt-2">
                    {{ $record->gardens()->count() }}
                </p>
                <span class="text-[11px] text-gray-500 dark:text-gray-400">Kebun durian binaan</span>
            </div>

            <div class="p-3.5 bg-white/90 dark:bg-gray-900/90 rounded-xl border border-gray-200/80 dark:border-gray-800 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Pohon Tertanam</span>
                    <span class="p-1.5 rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-900/40 dark:text-amber-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                    </span>
                </div>
                <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-2">
                    {{ $record->gardens()->withCount('trees')->get()->sum('trees_count') }}
                </p>
                <span class="text-[11px] text-gray-500 dark:text-gray-400">Populasi pohon terdata</span>
            </div>
        </div>
    </div>

    {{-- Map Section --}}
    @php
        // Resolve coordinates from village gardens or default to Banyumas center
        $gardenWithCoords = $record->gardens()->whereNotNull('latitude')->whereNotNull('longitude')->first();
        $lat = $gardenWithCoords?->latitude ?? -7.450000;
        $lng = $gardenWithCoords?->longitude ?? 109.250000;
        $zoom = $gardenWithCoords ? 15 : 12;
        $bboxLeft = $lng - 0.03;
        $bboxBottom = $lat - 0.02;
        $bboxRight = $lng + 0.03;
        $bboxTop = $lat + 0.02;
    @endphp
    <div class="p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-xs">
        <div class="flex items-center justify-between mb-2">
            <h4 class="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Peta Wilayah Desa {{ $record->name }}
            </h4>
            <span class="text-xs text-gray-500 font-mono">
                {{ number_format((float)$lat, 5) }}, {{ number_format((float)$lng, 5) }}
            </span>
        </div>
        <div class="w-full h-56 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800">
            <iframe
                title="Peta Desa {{ $record->name }}"
                width="100%"
                height="100%"
                frameborder="0"
                scrolling="no"
                marginheight="0"
                marginwidth="0"
                src="https://www.openstreetmap.org/export/embed.html?bbox={{ $bboxLeft }}%2C{{ $bboxBottom }}%2C{{ $bboxRight }}%2C{{ $bboxTop }}&amp;layer=mapnik&amp;marker={{ $lat }}%2C{{ $lng }}">
            </iframe>
        </div>
        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1.5 flex items-center justify-between">
            <span>Koordinat peta diambil dari lokasi kebun aktif di desa ini.</span>
            <a href="https://www.openstreetmap.org/?mlat={{ $lat }}&amp;mlon={{ $lng }}#map={{ $zoom }}/{{ $lat }}/{{ $lng }}" target="_blank" class="text-emerald-600 hover:underline">Buka peta lebih besar &rarr;</a>
        </p>
    </div>

    {{-- Daftar Blok di Desa --}}
    <div class="p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-xs">
        <div class="flex items-center justify-between mb-3">
            <h4 class="font-semibold text-gray-900 dark:text-white">Daftar Blok di Desa {{ $record->name }}</h4>
            <span class="text-xs px-2 py-0.5 rounded-md bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 font-medium">
                {{ $record->blocks->count() }} Blok
            </span>
        </div>

        @if($record->blocks->isNotEmpty())
            <div class="overflow-x-auto border border-gray-100 dark:border-gray-800 rounded-lg">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-gray-50/80 dark:bg-gray-800/80 border-b border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 font-semibold">
                            <th class="py-2.5 px-3">Kode Blok</th>
                            <th class="py-2.5 px-3">Nama Blok</th>
                            <th class="py-2.5 px-3">Luas</th>
                            <th class="py-2.5 px-3">Kebun</th>
                            <th class="py-2.5 px-3">Pohon</th>
                            <th class="py-2.5 px-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($record->blocks as $block)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition">
                                <td class="py-2 px-3 font-mono text-emerald-600 dark:text-emerald-400 font-medium">
                                    {{ $block->block_code ?: '-' }}
                                </td>
                                <td class="py-2 px-3 font-medium text-gray-900 dark:text-gray-100">{{ $block->name }}</td>
                                <td class="py-2 px-3 text-gray-600 dark:text-gray-400">
                                    {{ $block->area_hectares ? number_format($block->area_hectares, 2) . ' Ha' : '-' }}
                                </td>
                                <td class="py-2 px-3 font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $block->gardens()->count() }}
                                </td>
                                <td class="py-2 px-3 text-amber-600 dark:text-amber-400 font-medium">
                                    {{ $block->trees()->count() }}
                                </td>
                                <td class="py-2 px-3">
                                    <span class="inline-flex px-2 py-0.5 rounded text-[11px] font-medium {{ $block->approval_status === 'approved' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' }}">
                                        {{ ucfirst(str_replace('_', ' ', $block->approval_status ?? 'approved')) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-6 text-center text-xs text-gray-400 bg-gray-50/50 dark:bg-gray-800/50 rounded-lg border border-dashed border-gray-200 dark:border-gray-700">
                Belum ada blok yang terdaftar di Desa {{ $record->name }}.
            </div>
        @endif
    </div>
</div>
