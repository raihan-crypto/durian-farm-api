<div class="space-y-6 text-sm max-h-[75vh] overflow-y-auto pr-2">
    {{-- Header Banner & Stats --}}
    <div class="p-5 bg-gradient-to-r from-sky-500/10 via-indigo-500/10 to-emerald-500/10 dark:from-sky-950/40 dark:via-indigo-950/40 dark:to-emerald-950/40 rounded-2xl border border-sky-200/60 dark:border-sky-800/60">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-sky-100 text-sky-800 dark:bg-sky-900/50 dark:text-sky-300">
                        Blok Perkebunan
                    </span>
                    <span class="font-mono text-xs font-bold px-2.5 py-0.5 rounded-md bg-white dark:bg-gray-800 text-sky-700 dark:text-sky-400 border border-sky-200 dark:border-sky-700">
                        {{ $record->block_code ?: '-' }}
                    </span>
                </div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mt-1.5">{{ $record->name }}</h3>
                <p class="text-xs text-gray-600 dark:text-gray-400">
                    Desa {{ $record->village?->name ?? '-' }}, Kecamatan {{ $record->village?->district ?? '-' }}
                </p>
            </div>
            <div class="text-right">
                <span class="text-xs text-gray-500 dark:text-gray-400 block">Status Persetujuan</span>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium {{ $record->approval_status === 'approved' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $record->approval_status === 'approved' ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                    {{ ucfirst(str_replace('_', ' ', $record->approval_status ?? 'approved')) }}
                </span>
            </div>
        </div>

        {{-- 4 KPI Cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="p-3.5 bg-white/90 dark:bg-gray-900/90 rounded-xl border border-gray-200/80 dark:border-gray-800 shadow-xs">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider block">Total Kebun</span>
                <p class="text-2xl font-bold text-sky-600 dark:text-sky-400 mt-1">
                    {{ $record->gardens()->count() }}
                </p>
                <span class="text-[11px] text-gray-400">Kebun terdaftar</span>
            </div>

            <div class="p-3.5 bg-white/90 dark:bg-gray-900/90 rounded-xl border border-gray-200/80 dark:border-gray-800 shadow-xs">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider block">Total Pohon</span>
                <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">
                    {{ $record->trees()->count() }}
                </p>
                <span class="text-[11px] text-gray-400">Pohon durian</span>
            </div>

            <div class="p-3.5 bg-white/90 dark:bg-gray-900/90 rounded-xl border border-gray-200/80 dark:border-gray-800 shadow-xs">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider block">Luas Blok</span>
                <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">
                    {{ $record->area_hectares ? number_format($record->area_hectares, 2) : '0' }} <span class="text-xs font-normal text-gray-500">Ha</span>
                </p>
                <span class="text-[11px] text-gray-400">Cakupan wilayah</span>
            </div>

            <div class="p-3.5 bg-white/90 dark:bg-gray-900/90 rounded-xl border border-gray-200/80 dark:border-gray-800 shadow-xs">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider block">Tenaga Kerja</span>
                <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">
                    {{ $record->farmers()->count() }}
                </p>
                <span class="text-[11px] text-gray-400">Pekerja ditugaskan</span>
            </div>
        </div>
    </div>

    {{-- Info Blok & Auto-generate Rule Card --}}
    <div class="p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-xs">
        <h4 class="font-semibold text-gray-900 dark:text-white mb-3 text-sm">Informasi Detail Blok</h4>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2.5">
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">Nama Blok</dt>
                <dd class="font-semibold text-gray-900 dark:text-gray-100">{{ $record->name }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">Format Kode Blok</dt>
                <dd class="font-mono text-emerald-600 dark:text-emerald-400 font-bold">
                    {{ $record->block_code ?: '-' }}
                    <span class="text-[11px] font-normal text-gray-500 ml-1">([KodeDesa].[NomorBlok])</span>
                </dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">Desa Induk</dt>
                <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $record->village?->name ?? '-' }} (Kec. {{ $record->village?->district ?? '-' }})</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">Diajukan Oleh</dt>
                <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $record->requester?->name ?? 'Administrator' }}</dd>
            </div>
            @if($record->description)
                <div class="sm:col-span-2 mt-1">
                    <dt class="text-xs text-gray-500 dark:text-gray-400">Deskripsi / Catatan Blok</dt>
                    <dd class="text-xs text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-800/50 p-2.5 rounded-lg border border-gray-200/60 dark:border-gray-700/60 mt-1">
                        {{ $record->description }}
                    </dd>
                </div>
            @endif
        </dl>
    </div>

    {{-- Daftar Kebun dalam Blok --}}
    <div class="p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-xs">
        <div class="flex items-center justify-between mb-3">
            <h4 class="font-semibold text-gray-900 dark:text-white">Daftar Kebun di Blok {{ $record->name }}</h4>
            <span class="text-xs px-2 py-0.5 rounded-md bg-sky-50 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300 font-medium">
                {{ $record->gardens->count() }} Kebun
            </span>
        </div>

        @if($record->gardens->isNotEmpty())
            <div class="overflow-x-auto border border-gray-100 dark:border-gray-800 rounded-lg">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-gray-50/80 dark:bg-gray-800/80 border-b border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 font-semibold">
                            <th class="py-2.5 px-3">Kode Kebun</th>
                            <th class="py-2.5 px-3">Nama Kebun</th>
                            <th class="py-2.5 px-3">Tipe Kepemilikan</th>
                            <th class="py-2.5 px-3">Petani Pengelola</th>
                            <th class="py-2.5 px-3">Tenaga Kerja</th>
                            <th class="py-2.5 px-3">Pohon</th>
                            <th class="py-2.5 px-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($record->gardens as $garden)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition">
                                <td class="py-2 px-3 font-mono font-medium text-sky-600 dark:text-sky-400">
                                    {{ $garden->garden_code ?: '-' }}
                                </td>
                                <td class="py-2 px-3 font-medium text-gray-900 dark:text-gray-100">{{ $garden->name }}</td>
                                <td class="py-2 px-3">
                                    <span class="inline-flex px-2 py-0.5 rounded text-[11px] font-medium {{ $garden->ownership_type === 'group' ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' }}">
                                        {{ $garden->ownership_type === 'group' ? 'Kelompok Tani' : 'Perorangan' }}
                                    </span>
                                </td>
                                <td class="py-2 px-3 text-gray-800 dark:text-gray-200">
                                    {{ $garden->owners->pluck('name')->join(', ') ?: '-' }}
                                </td>
                                <td class="py-2 px-3 text-gray-600 dark:text-gray-400 font-medium">
                                    {{ $garden->farmers()->count() }} orang
                                </td>
                                <td class="py-2 px-3 text-emerald-600 dark:text-emerald-400 font-bold">
                                    {{ $garden->trees()->count() }}
                                </td>
                                <td class="py-2 px-3">
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
                Belum ada kebun yang terdaftar di blok ini.
            </div>
        @endif
    </div>
</div>
