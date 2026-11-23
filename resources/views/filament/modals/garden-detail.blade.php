<div class="space-y-6 text-sm max-h-[75vh] overflow-y-auto pr-2">
    {{-- Header Banner & Stats --}}
    <div class="p-5 bg-gradient-to-r from-emerald-500/10 via-teal-500/10 to-amber-500/10 dark:from-emerald-950/40 dark:via-teal-950/40 dark:to-amber-950/40 rounded-2xl border border-emerald-200/60 dark:border-emerald-800/60">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300">
                        Kebun Durian
                    </span>
                    <span class="font-mono text-xs font-bold px-2 py-0.5 rounded-md bg-white dark:bg-gray-800 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-700">
                        Kode: {{ $record->garden_code ?: '-' }}
                    </span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $record->ownership_type === 'group' ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' }}">
                        {{ $record->ownership_type === 'group' ? 'Kelompok Tani' : 'Perorangan' }}
                    </span>
                </div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mt-1.5">{{ $record->name }}</h3>
                <p class="text-xs text-gray-600 dark:text-gray-400">
                    {{ $record->block?->name ?? '-' }} • Desa {{ $record->block?->village?->name ?? '-' }} (Kec. {{ $record->block?->village?->district ?? '-' }})
                </p>
            </div>
            <div class="text-right">
                <span class="text-xs text-gray-500 dark:text-gray-400 block">Status Operasional</span>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium {{ $record->status === 'active' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $record->status === 'active' ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>
                    {{ $record->status === 'active' ? 'Aktif Berproduksi' : 'Tidak Aktif' }}
                </span>
            </div>
        </div>

        {{-- 4 KPI Cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="p-3.5 bg-white/90 dark:bg-gray-900/90 rounded-xl border border-gray-200/80 dark:border-gray-800 shadow-xs">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider block">Total Pohon</span>
                <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">
                    {{ $record->trees()->count() }}
                </p>
                <span class="text-[11px] text-gray-400">{{ $record->trees()->where('status', 'active')->count() }} produktif</span>
            </div>

            <div class="p-3.5 bg-white/90 dark:bg-gray-900/90 rounded-xl border border-gray-200/80 dark:border-gray-800 shadow-xs">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider block">Luas Kebun</span>
                <p class="text-2xl font-bold text-sky-600 dark:text-sky-400 mt-1">
                    {{ $record->area_hectares ? number_format($record->area_hectares, 2) : '0' }} <span class="text-xs font-normal text-gray-500">Ha</span>
                </p>
                <span class="text-[11px] text-gray-400">Total area tanam</span>
            </div>

            <div class="p-3.5 bg-white/90 dark:bg-gray-900/90 rounded-xl border border-gray-200/80 dark:border-gray-800 shadow-xs">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider block">Hasil Panen</span>
                <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">
                    {{ number_format((float) $record->harvestRecords()->sum('total_weight_kg'), 1) }} <span class="text-xs font-normal text-gray-500">Kg</span>
                </p>
                <span class="text-[11px] text-gray-400">{{ (int) $record->harvestRecords()->sum('fruit_count') }} butir durian</span>
            </div>

            <div class="p-3.5 bg-white/90 dark:bg-gray-900/90 rounded-xl border border-gray-200/80 dark:border-gray-800 shadow-xs">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider block">Tenaga Kerja</span>
                <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">
                    {{ $record->farmers()->count() }} <span class="text-xs font-normal text-gray-500">Org</span>
                </p>
                <span class="text-[11px] text-gray-400">Pekerja aktif</span>
            </div>
        </div>
    </div>

    {{-- Detail Kebun & Pengelola --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        {{-- Identitas Kebun --}}
        <div class="p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-xs">
            <h4 class="font-semibold text-gray-900 dark:text-white mb-3 text-sm flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Identitas Kebun
            </h4>
            <dl class="space-y-2 text-xs">
                <div class="flex justify-between border-b border-gray-100 dark:border-gray-800 pb-1.5">
                    <dt class="text-gray-500">Nama Kebun</dt>
                    <dd class="font-semibold text-gray-900 dark:text-gray-100">{{ $record->name }}</dd>
                </div>
                <div class="flex justify-between border-b border-gray-100 dark:border-gray-800 pb-1.5">
                    <dt class="text-gray-500">Kode Kebun</dt>
                    <dd class="font-mono text-emerald-600 font-bold">{{ $record->garden_code ?: '-' }}</dd>
                </div>
                <div class="flex justify-between border-b border-gray-100 dark:border-gray-800 pb-1.5">
                    <dt class="text-gray-500">Blok</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $record->block?->name ?? '-' }} ({{ $record->block?->block_code ?? '-' }})</dd>
                </div>
                <div class="flex justify-between border-b border-gray-100 dark:border-gray-800 pb-1.5">
                    <dt class="text-gray-500">Desa & Kecamatan</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $record->block?->village?->name ?? '-' }}, {{ $record->block?->village?->district ?? '-' }}</dd>
                </div>
                <div class="flex justify-between border-b border-gray-100 dark:border-gray-800 pb-1.5">
                    <dt class="text-gray-500">Koordinat</dt>
                    <dd class="font-mono text-gray-700 dark:text-gray-300">
                        {{ ($record->latitude && $record->longitude) ? "{$record->latitude}, {$record->longitude}" : 'Belum diisi' }}
                    </dd>
                </div>
                <div class="flex justify-between pt-0.5">
                    <dt class="text-gray-500">Alamat</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100 text-right">{{ $record->address ?: '-' }}</dd>
                </div>
            </dl>
        </div>

        {{-- Petani Pemilik & Tenaga Kerja --}}
        <div class="p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-xs flex flex-col justify-between">
            <div>
                <h4 class="font-semibold text-gray-900 dark:text-white mb-3 text-sm flex items-center gap-2">
                    <svg class="w-4 h-4 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    Petani Pengelola & Tenaga Kerja
                </h4>
                
                {{-- Daftar Petani (Pemilik) --}}
                <div class="mb-3">
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 block mb-1">
                        Petani Pengelola ({{ $record->ownership_type === 'group' ? 'Kelompok' : 'Perorangan' }}):
                    </span>
                    @if($record->owners->isNotEmpty())
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($record->owners as $owner)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-emerald-50 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 text-xs font-medium border border-emerald-200/60 dark:border-emerald-800/60">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    {{ $owner->name }}
                                </span>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-gray-400 italic">Belum ada petani pengelola terdaftar.</p>
                    @endif
                </div>

                {{-- Daftar Tenaga Kerja (Pekerja) --}}
                <div>
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 block mb-1">
                        Tenaga Kerja Lapangan:
                    </span>
                    @if($record->farmers->isNotEmpty())
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($record->farmers as $farmer)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-sky-50 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300 text-xs font-medium border border-sky-200/60 dark:border-sky-800/60">
                                    <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                                    {{ $farmer->name }}
                                </span>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-gray-400 italic">Belum ada tenaga kerja yang ditugaskan di kebun ini.</p>
                    @endif
                </div>
            </div>

            @if($record->description)
                <div class="mt-3 pt-2.5 border-t border-gray-100 dark:border-gray-800">
                    <span class="text-xs text-gray-500 block mb-0.5">Catatan / Deskripsi:</span>
                    <p class="text-xs text-gray-700 dark:text-gray-300">{{ $record->description }}</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Map Section jika koordinat ada --}}
    @if($record->latitude && $record->longitude)
        @php
            $lat = (float) $record->latitude;
            $lng = (float) $record->longitude;
            $bboxLeft = $lng - 0.01;
            $bboxBottom = $lat - 0.008;
            $bboxRight = $lng + 0.01;
            $bboxTop = $lat + 0.008;
        @endphp
        <div class="p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-xs">
            <h4 class="font-semibold text-gray-900 dark:text-white mb-2 text-sm flex items-center justify-between">
                <span>Peta Lokasi Kebun {{ $record->name }}</span>
                <span class="text-xs font-mono text-gray-500">{{ $lat }}, {{ $lng }}</span>
            </h4>
            <div class="w-full h-48 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                <iframe
                    title="Peta Kebun {{ $record->name }}"
                    width="100%"
                    height="100%"
                    frameborder="0"
                    scrolling="no"
                    src="https://www.openstreetmap.org/export/embed.html?bbox={{ $bboxLeft }}%2C{{ $bboxBottom }}%2C{{ $bboxRight }}%2C{{ $bboxTop }}&amp;layer=mapnik&amp;marker={{ $lat }}%2C{{ $lng }}">
                </iframe>
            </div>
        </div>
    @endif

    {{-- Daftar Seluruh Pohon dalam Kebun --}}
    <div class="p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-xs">
        <div class="flex items-center justify-between mb-3">
            <h4 class="font-semibold text-gray-900 dark:text-white text-sm flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                Seluruh Pohon Durian di Kebun Ini ({{ $record->trees->count() }})
            </h4>
        </div>

        @if($record->trees->isNotEmpty())
            <div class="overflow-x-auto max-h-64 overflow-y-auto border border-gray-100 dark:border-gray-800 rounded-lg">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="sticky top-0 bg-gray-50/95 dark:bg-gray-800/95 backdrop-blur-xs">
                        <tr class="border-b border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 font-semibold">
                            <th class="py-2.5 px-3">NIK Pohon</th>
                            <th class="py-2.5 px-3">Kode</th>
                            <th class="py-2.5 px-3">Varietas</th>
                            <th class="py-2.5 px-3">Status</th>
                            <th class="py-2.5 px-3">Tgl Tanam</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($record->trees()->with('variety')->get() as $tree)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition">
                                <td class="py-2 px-3 font-mono font-medium text-emerald-600 dark:text-emerald-400">
                                    {{ $tree->tree_identity_number ?: '-' }}
                                </td>
                                <td class="py-2 px-3 font-medium text-gray-900 dark:text-gray-100">
                                    {{ $tree->tree_code }}
                                </td>
                                <td class="py-2 px-3 text-gray-700 dark:text-gray-300">
                                    {{ $tree->variety?->name ?? '-' }}
                                </td>
                                <td class="py-2 px-3">
                                    <span class="inline-flex px-2 py-0.5 rounded text-[11px] font-medium {{ $tree->status === 'active' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' }}">
                                        {{ \App\Models\Tree::STATUS_LABELS[$tree->status] ?? $tree->status }}
                                    </span>
                                </td>
                                <td class="py-2 px-3 text-gray-500">
                                    {{ $tree->planted_at ? \Illuminate\Support\Carbon::parse($tree->planted_at)->format('d/m/Y') : '-' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-6 text-center text-xs text-gray-400 bg-gray-50/50 dark:bg-gray-800/50 rounded-lg border border-dashed border-gray-200 dark:border-gray-700">
                Belum ada pohon durian yang terdaftar di kebun ini.
            </div>
        @endif
    </div>

    {{-- Riwayat Aktivitas & Perawatan Kebun --}}
    <div class="p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-xs">
        <h4 class="font-semibold text-gray-900 dark:text-white mb-3 text-sm flex items-center gap-2">
            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
            Riwayat Aktivitas & Perawatan Kebun
        </h4>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
            {{-- Pemupukan Terbaru --}}
            <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200/60 dark:border-gray-700/60">
                <span class="font-semibold text-gray-800 dark:text-gray-200 block mb-2 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                    Pemupukan Terbaru
                </span>
                @php
                    $fertilizations = $record->fertilizationRecords()->with('tree')->latest('activity_date')->limit(5)->get();
                @endphp
                @if($fertilizations->isNotEmpty())
                    <ul class="space-y-1.5 divide-y divide-gray-200/50 dark:divide-gray-700/50">
                        @foreach($fertilizations as $fert)
                            <li class="pt-1.5 first:pt-0 flex justify-between items-center">
                                <span class="font-medium text-gray-800 dark:text-gray-200">{{ $fert->tree?->tree_code ?? 'Pohon' }}</span>
                                <span class="text-gray-500">{{ $fert->activity_date ? \Illuminate\Support\Carbon::parse($fert->activity_date)->format('d/m/Y') : '-' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-gray-400 italic">Belum ada catatan pemupukan.</p>
                @endif
            </div>

            {{-- Penyemprotan Terbaru --}}
            <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200/60 dark:border-gray-700/60">
                <span class="font-semibold text-gray-800 dark:text-gray-200 block mb-2 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                    Penyemprotan Terbaru
                </span>
                @php
                    $sprayings = $record->sprayingRecords()->with('tree')->latest('activity_date')->limit(5)->get();
                @endphp
                @if($sprayings->isNotEmpty())
                    <ul class="space-y-1.5 divide-y divide-gray-200/50 dark:divide-gray-700/50">
                        @foreach($sprayings as $spray)
                            <li class="pt-1.5 first:pt-0 flex justify-between items-center">
                                <span class="font-medium text-gray-800 dark:text-gray-200">{{ $spray->tree?->tree_code ?? 'Pohon' }}</span>
                                <span class="text-gray-500">{{ $spray->activity_date ? \Illuminate\Support\Carbon::parse($spray->activity_date)->format('d/m/Y') : '-' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-gray-400 italic">Belum ada catatan penyemprotan.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Laporan Hasil Panen Kebun --}}
    <div class="p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-xs">
        <div class="flex items-center justify-between mb-3">
            <h4 class="font-semibold text-gray-900 dark:text-white text-sm flex items-center gap-2">
                <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Laporan Hasil Panen Kebun
            </h4>
            <span class="text-xs font-semibold text-amber-600 dark:text-amber-400">
                Total: {{ number_format((float) $record->harvestRecords()->sum('total_weight_kg'), 1) }} kg
            </span>
        </div>

        @php
            $harvests = $record->harvestRecords()->with('tree')->latest('created_at')->limit(10)->get();
        @endphp

        @if($harvests->isNotEmpty())
            <div class="overflow-x-auto max-h-48 overflow-y-auto border border-gray-100 dark:border-gray-800 rounded-lg">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="sticky top-0 bg-gray-50/95 dark:bg-gray-800/95 backdrop-blur-xs">
                        <tr class="border-b border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 font-semibold">
                            <th class="py-2.5 px-3">Tanggal</th>
                            <th class="py-2.5 px-3">Pohon</th>
                            <th class="py-2.5 px-3">Jumlah Buah</th>
                            <th class="py-2.5 px-3">Berat Total (Kg)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($harvests as $h)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition">
                                <td class="py-2 px-3 text-gray-600 dark:text-gray-400">
                                    {{ $h->created_at?->format('d/m/Y') }}
                                </td>
                                <td class="py-2 px-3 font-medium text-gray-900 dark:text-gray-100">
                                    {{ $h->tree?->tree_code ?? '-' }}
                                </td>
                                <td class="py-2 px-3 text-gray-800 dark:text-gray-200 font-medium">
                                    {{ $h->fruit_count }} butir
                                </td>
                                <td class="py-2 px-3 font-semibold text-amber-600 dark:text-amber-400">
                                    {{ number_format($h->total_weight_kg, 2) }} kg
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-6 text-center text-xs text-gray-400 bg-gray-50/50 dark:bg-gray-800/50 rounded-lg border border-dashed border-gray-200 dark:border-gray-700">
                Belum ada catatan panen untuk kebun ini.
            </div>
        @endif
    </div>
</div>
