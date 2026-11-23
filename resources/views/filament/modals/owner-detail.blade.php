<div class="space-y-6 text-sm max-h-[75vh] overflow-y-auto pr-2">
    {{-- Header Banner --}}
    <div class="p-5 bg-gradient-to-r from-emerald-500/10 via-teal-500/10 to-indigo-500/10 dark:from-emerald-950/40 dark:via-teal-950/40 dark:to-indigo-950/40 rounded-2xl border border-emerald-200/60 dark:border-emerald-800/60">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300">
                    Petani Pengelola Kebun
                </span>
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mt-1">{{ $record->name }}</h3>
                <p class="text-xs text-gray-600 dark:text-gray-400">
                    {{ $record->owner_type === 'organization' ? 'Badan Usaha / Kelompok Tani' : 'Petani Perorangan' }} • Telp: {{ $record->phone ?: '-' }}
                </p>
            </div>
            <div class="text-right">
                <span class="text-xs text-gray-500 dark:text-gray-400 block">Akun Terhubung</span>
                <span class="text-xs font-medium text-gray-700 dark:text-gray-300">
                    {{ $record->user?->email ?? 'Belum ada akun login' }}
                </span>
            </div>
        </div>

        {{-- 3 KPI Cards --}}
        @php
            $gardens = $record->gardens()->with(['block.village', 'trees'])->get();
            $totalTrees = $gardens->sum(fn ($g) => $g->trees->count());
            $distinctBlocks = $gardens->pluck('block_id')->unique()->count();
        @endphp
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="p-3.5 bg-white/90 dark:bg-gray-900/90 rounded-xl border border-gray-200/80 dark:border-gray-800 shadow-xs">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider block">Kebun Terikat</span>
                <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">
                    {{ $gardens->count() }}
                </p>
                <span class="text-[11px] text-gray-400">Kebun yang dikelola</span>
            </div>

            <div class="p-3.5 bg-white/90 dark:bg-gray-900/90 rounded-xl border border-gray-200/80 dark:border-gray-800 shadow-xs">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider block">Blok Terkait</span>
                <p class="text-2xl font-bold text-sky-600 dark:text-sky-400 mt-1">
                    {{ $distinctBlocks }}
                </p>
                <span class="text-[11px] text-gray-400">Sebaran blok</span>
            </div>

            <div class="p-3.5 bg-white/90 dark:bg-gray-900/90 rounded-xl border border-gray-200/80 dark:border-gray-800 shadow-xs">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider block">Total Populasi Pohon</span>
                <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">
                    {{ $totalTrees }}
                </p>
                <span class="text-[11px] text-gray-400">Pohon dalam kebun</span>
            </div>
        </div>
    </div>

    {{-- Detail Kontak & Alamat --}}
    <div class="p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-xs">
        <h4 class="font-semibold text-gray-900 dark:text-white mb-2.5 text-sm">Informasi Petani</h4>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-xs">
            <div>
                <dt class="text-gray-500">Nama Lengkap</dt>
                <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $record->name }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Nomor Telepon</dt>
                <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $record->phone ?: '-' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Tipe Petani</dt>
                <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $record->owner_type === 'organization' ? 'Organisasi / Kelompok Tani' : 'Perorangan' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Alamat</dt>
                <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $record->address ?: '-' }}</dd>
            </div>
        </dl>
    </div>

    {{-- Daftar Kebun yang Terikat Beserta Posisi Bloknya --}}
    <div class="p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-xs">
        <div class="flex items-center justify-between mb-3">
            <h4 class="font-semibold text-gray-900 dark:text-white text-sm flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                Daftar Kebun yang Terikat & Posisi Blok
            </h4>
            <span class="text-xs px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 font-semibold">
                {{ $gardens->count() }} Kebun
            </span>
        </div>

        @if($gardens->isNotEmpty())
            <div class="overflow-x-auto border border-gray-100 dark:border-gray-800 rounded-lg">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-gray-50/80 dark:bg-gray-800/80 border-b border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 font-semibold">
                            <th class="py-2.5 px-3">Kode Kebun</th>
                            <th class="py-2.5 px-3">Nama Kebun</th>
                            <th class="py-2.5 px-3">Posisi Blok</th>
                            <th class="py-2.5 px-3">Desa & Kec.</th>
                            <th class="py-2.5 px-3">Luas</th>
                            <th class="py-2.5 px-3">Pohon</th>
                            <th class="py-2.5 px-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($gardens as $garden)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition">
                                <td class="py-2.5 px-3 font-mono font-medium text-emerald-600 dark:text-emerald-400">
                                    {{ $garden->garden_code ?: '-' }}
                                </td>
                                <td class="py-2.5 px-3 font-medium text-gray-900 dark:text-gray-100">
                                    {{ $garden->name }}
                                </td>
                                <td class="py-2.5 px-3 text-sky-700 dark:text-sky-300 font-medium">
                                    {{ $garden->block?->name ?? '-' }} <span class="text-gray-400 font-mono text-[11px]">({{ $garden->block?->block_code ?? '-' }})</span>
                                </td>
                                <td class="py-2.5 px-3 text-gray-600 dark:text-gray-400">
                                    {{ $garden->block?->village?->name ?? '-' }} ({{ $garden->block?->village?->district ?? '-' }})
                                </td>
                                <td class="py-2.5 px-3 text-gray-700 dark:text-gray-300">
                                    {{ $garden->area_hectares ? number_format($garden->area_hectares, 2) . ' Ha' : '-' }}
                                </td>
                                <td class="py-2.5 px-3 font-bold text-amber-600 dark:text-amber-400">
                                    {{ $garden->trees->count() }} pohon
                                </td>
                                <td class="py-2.5 px-3">
                                    <span class="inline-flex px-2 py-0.5 rounded text-[11px] font-medium {{ $garden->status === 'active' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' }}">
                                        {{ $garden->status === 'active' ? 'Aktif' : 'Tidak aktif' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-6 text-center text-xs text-gray-400 bg-gray-50/50 dark:bg-gray-800/50 rounded-lg border border-dashed border-gray-200 dark:border-gray-700">
                Petani ini belum memiliki kebun yang terikat.
            </div>
        @endif
    </div>
</div>
