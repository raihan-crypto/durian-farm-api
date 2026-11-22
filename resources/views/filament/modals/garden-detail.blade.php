<div class="space-y-6 text-sm max-h-[75vh] overflow-y-auto pr-2">
    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
            <span class="text-xs text-gray-500 dark:text-gray-400">Total Pohon</span>
            <p class="text-xl font-bold text-primary-600 dark:text-primary-400 mt-0.5">
                {{ $record->trees()->count() }}
            </p>
        </div>
        <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
            <span class="text-xs text-gray-500 dark:text-gray-400">Status Kebun</span>
            <p class="text-base font-bold mt-0.5 {{ $record->status === 'active' ? 'text-green-600 dark:text-green-400' : 'text-gray-500' }}">
                {{ $record->status === 'active' ? 'Aktif' : 'Tidak aktif' }}
            </p>
        </div>
        <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
            <span class="text-xs text-gray-500 dark:text-gray-400">Total Panen</span>
            <p class="text-xl font-bold text-success-600 dark:text-success-400 mt-0.5">
                {{ $record->harvestRecords()->count() }}
            </p>
        </div>
        <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
            <span class="text-xs text-gray-500 dark:text-gray-400">Tenaga Kerja</span>
            <p class="text-xl font-bold text-warning-600 dark:text-warning-400 mt-0.5">
                {{ $record->farmers()->count() }}
            </p>
        </div>
    </div>

    {{-- Detail Kebun --}}
    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
        <h4 class="font-semibold text-gray-900 dark:text-white mb-3 text-base">Identitas & Lokasi Kebun</h4>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2">
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">Kode Kebun</dt>
                <dd class="font-medium text-gray-900 dark:text-gray-100 font-mono">{{ $record->garden_code ?: '-' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">Nama Kebun</dt>
                <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $record->name }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">Blok</dt>
                <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $record->block?->name ?? '-' }} (Desa: {{ $record->block?->village?->name ?? '-' }})</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">Luas Kebun</dt>
                <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $record->area_hectares ? number_format($record->area_hectares, 2) . ' Ha' : '-' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">Koordinat Geografis</dt>
                <dd class="font-medium text-gray-900 dark:text-gray-100 font-mono">
                    {{ ($record->latitude !== null && $record->longitude !== null) ? "{$record->latitude}, {$record->longitude}" : '-' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">Alamat</dt>
                <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $record->address ?: '-' }}</dd>
            </div>
            @if($record->description)
                <div class="sm:col-span-2">
                    <dt class="text-xs text-gray-500 dark:text-gray-400">Deskripsi</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $record->description }}</dd>
                </div>
            @endif
        </dl>
    </div>

    {{-- Daftar Pemilik & Tenaga Kerja --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 border-t border-gray-200 dark:border-gray-700 pt-4">
        <div>
            <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Daftar Pemilik</h4>
            @if($record->owners->isNotEmpty())
                <ul class="divide-y divide-gray-100 dark:divide-gray-800 text-xs">
                    @foreach($record->owners as $owner)
                        <li class="py-1.5 flex justify-between">
                            <span class="font-medium text-gray-800 dark:text-gray-200">{{ $owner->name }}</span>
                            <span class="text-gray-500">Porsi: {{ $owner->pivot->ownership_percentage ?? 100 }}%</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-xs text-gray-400">Belum ada pemilik terdaftar.</p>
            @endif
        </div>

        <div>
            <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Daftar Tenaga Kerja Kebun</h4>
            @if($record->farmers->isNotEmpty())
                <ul class="divide-y divide-gray-100 dark:divide-gray-800 text-xs">
                    @foreach($record->farmers as $farmer)
                        <li class="py-1.5 flex justify-between">
                            <span class="font-medium text-gray-800 dark:text-gray-200">{{ $farmer->name }}</span>
                            <span class="text-gray-500">{{ $farmer->phone ?: '-' }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-xs text-gray-400">Belum ada tenaga kerja ditugaskan.</p>
            @endif
        </div>
    </div>

    {{-- Daftar Seluruh Pohon --}}
    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
        <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Daftar Seluruh Pohon dalam Kebun ({{ $record->trees->count() }})</h4>
        @if($record->trees->isNotEmpty())
            <div class="overflow-x-auto max-h-48 overflow-y-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="sticky top-0 bg-white dark:bg-gray-900">
                        <tr class="border-b border-gray-200 dark:border-gray-700 text-gray-500">
                            <th class="py-1.5">Kode Pohon</th>
                            <th class="py-1.5">NIK Pohon</th>
                            <th class="py-1.5">Varietas</th>
                            <th class="py-1.5">Status</th>
                            <th class="py-1.5">Tgl Tanam</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($record->trees as $tree)
                            <tr>
                                <td class="py-1.5 font-medium">{{ $tree->tree_code }}</td>
                                <td class="py-1.5 font-mono text-primary-600 dark:text-primary-400">{{ $tree->tree_identity_number ?: '-' }}</td>
                                <td class="py-1.5">{{ $tree->variety?->name ?? '-' }}</td>
                                <td class="py-1.5">
                                    <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] {{ $tree->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                                        {{ $tree->statusLabel() }}
                                    </span>
                                </td>
                                <td class="py-1.5">{{ $tree->planted_at?->format('d/m/Y') ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-xs text-gray-400">Belum ada pohon terdaftar di kebun ini.</p>
        @endif
    </div>

    {{-- Riwayat Aktivitas Kebun --}}
    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
        <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Riwayat Aktivitas Kebun Terakhir</h4>
        @php
            $fertilizations = $record->fertilizationRecords()->with('tree')->latest('activity_date')->limit(5)->get();
            $sprayings = $record->sprayingRecords()->with('tree')->latest('activity_date')->limit(5)->get();
        @endphp

        @if($fertilizations->isNotEmpty() || $sprayings->isNotEmpty())
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                <div>
                    <span class="font-medium text-gray-700 dark:text-gray-300 block mb-1">Pemupukan Terbaru</span>
                    <ul class="space-y-1">
                        @forelse($fertilizations as $fert)
                            <li class="p-1.5 bg-gray-50 dark:bg-gray-800 rounded flex justify-between">
                                <span>Pohon {{ $fert->tree?->tree_code }}: {{ $fert->method }}</span>
                                <span class="text-gray-500">{{ $fert->activity_date }}</span>
                            </li>
                        @empty
                            <li class="text-gray-400">Tidak ada data pemupukan.</li>
                        @endforelse
                    </ul>
                </div>

                <div>
                    <span class="font-medium text-gray-700 dark:text-gray-300 block mb-1">Penyemprotan Terbaru</span>
                    <ul class="space-y-1">
                        @forelse($sprayings as $spray)
                            <li class="p-1.5 bg-gray-50 dark:bg-gray-800 rounded flex justify-between">
                                <span>Pohon {{ $spray->tree?->tree_code }}: {{ $spray->spraying_method }}</span>
                                <span class="text-gray-500">{{ $spray->activity_date }}</span>
                            </li>
                        @empty
                            <li class="text-gray-400">Tidak ada data penyemprotan.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        @else
            <p class="text-xs text-gray-400">Belum ada riwayat aktivitas yang tercatat untuk pohon-pohon di kebun ini.</p>
        @endif
    </div>

    {{-- Laporan Hasil Panen --}}
    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
        <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Laporan Hasil Panen Kebun</h4>
        @php
            $harvests = $record->harvestRecords()->with('tree')->latest('created_at')->limit(8)->get();
        @endphp

        @if($harvests->isNotEmpty())
            <div class="overflow-x-auto max-h-40 overflow-y-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="sticky top-0 bg-white dark:bg-gray-900">
                        <tr class="border-b border-gray-200 dark:border-gray-700 text-gray-500">
                            <th class="py-1">Tanggal</th>
                            <th class="py-1">Pohon</th>
                            <th class="py-1">Jumlah Buah</th>
                            <th class="py-1">Berat Total (Kg)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($harvests as $h)
                            <tr>
                                <td class="py-1">{{ $h->created_at?->format('d/m/Y') }}</td>
                                <td class="py-1 font-medium">{{ $h->tree?->tree_code ?? '-' }}</td>
                                <td class="py-1">{{ $h->fruit_count }} butir</td>
                                <td class="py-1 font-medium">{{ number_format($h->total_weight_kg, 2) }} kg</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-xs text-gray-400">Belum ada catatan panen untuk kebun ini.</p>
        @endif
    </div>
</div>
