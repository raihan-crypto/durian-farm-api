<div class="space-y-6 text-sm">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Blok</span>
            <p class="text-2xl font-bold text-primary-600 dark:text-primary-400 mt-1">
                {{ $record->blocks()->count() }}
            </p>
        </div>
        <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Kebun</span>
            <p class="text-2xl font-bold text-success-600 dark:text-success-400 mt-1">
                {{ $record->gardens()->count() }}
            </p>
        </div>
        <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Pohon Tertanam</span>
            <p class="text-2xl font-bold text-warning-600 dark:text-warning-400 mt-1">
                {{ $record->gardens()->withCount('trees')->get()->sum('trees_count') }}
            </p>
        </div>
    </div>

    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
        <h4 class="font-semibold text-gray-900 dark:text-white mb-3">Informasi Wilayah Desa</h4>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3">
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">Nama Desa</dt>
                <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $record->name }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">Kode Desa</dt>
                <dd class="font-medium text-gray-900 dark:text-gray-100 font-mono">{{ $record->village_code ?: '-' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">Kecamatan</dt>
                <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $record->district ?: '-' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">Kabupaten / Kota</dt>
                <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $record->regency ?: '-' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">Provinsi</dt>
                <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $record->province ?: '-' }}</dd>
            </div>
        </dl>
    </div>

    @if($record->blocks->isNotEmpty())
        <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
            <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Daftar Blok di Desa {{ $record->name }}</h4>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700 text-gray-500">
                            <th class="py-2">Kode Blok</th>
                            <th class="py-2">Nama Blok</th>
                            <th class="py-2">Luas (Ha)</th>
                            <th class="py-2">Jumlah Kebun</th>
                            <th class="py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($record->blocks as $block)
                            <tr>
                                <td class="py-2 font-mono">{{ $block->block_code ?: '-' }}</td>
                                <td class="py-2 font-medium">{{ $block->name }}</td>
                                <td class="py-2">{{ $block->area_hectares ? number_format($block->area_hectares, 2) : '-' }}</td>
                                <td class="py-2">{{ $block->gardens()->count() }}</td>
                                <td class="py-2">
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs {{ $block->approval_status === 'approved' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' }}">
                                        {{ ucfirst(str_replace('_', ' ', $block->approval_status ?? 'approved')) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
