<div class="space-y-6 text-sm">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Desa Induk</span>
            <p class="text-lg font-bold text-gray-900 dark:text-white mt-1">
                {{ $record->village?->name ?? '-' }}
            </p>
        </div>
        <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Kebun</span>
            <p class="text-2xl font-bold text-primary-600 dark:text-primary-400 mt-1">
                {{ $record->gardens()->count() }}
            </p>
        </div>
        <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Pohon</span>
            <p class="text-2xl font-bold text-success-600 dark:text-success-400 mt-1">
                {{ $record->trees()->count() }}
            </p>
        </div>
    </div>

    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
        <h4 class="font-semibold text-gray-900 dark:text-white mb-3">Informasi Detail Blok</h4>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3">
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">Nama Blok</dt>
                <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $record->name }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">Kode Blok</dt>
                <dd class="font-medium text-gray-900 dark:text-gray-100 font-mono">{{ $record->block_code ?: '-' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">Luas Area</dt>
                <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $record->area_hectares ? number_format($record->area_hectares, 2) . ' Hektar' : '-' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">Status Persetujuan</dt>
                <dd class="font-medium">
                    <span class="inline-flex px-2 py-0.5 rounded text-xs {{ $record->approval_status === 'approved' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' }}">
                        {{ ucfirst(str_replace('_', ' ', $record->approval_status ?? 'approved')) }}
                    </span>
                </dd>
            </div>
            @if($record->requested_by)
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">Diminta Oleh</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $record->requester?->name ?? 'User ('.$record->requested_by.')' }}</dd>
                </div>
            @endif
            @if($record->description)
                <div class="sm:col-span-2">
                    <dt class="text-xs text-gray-500 dark:text-gray-400">Deskripsi</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $record->description }}</dd>
                </div>
            @endif
        </dl>
    </div>

    @if($record->gardens->isNotEmpty())
        <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
            <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Daftar Kebun dalam Blok Ini</h4>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700 text-gray-500">
                            <th class="py-2">Kode Kebun</th>
                            <th class="py-2">Nama Kebun</th>
                            <th class="py-2">Luas (Ha)</th>
                            <th class="py-2">Jumlah Pohon</th>
                            <th class="py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($record->gardens as $garden)
                            <tr>
                                <td class="py-2 font-mono">{{ $garden->garden_code ?: '-' }}</td>
                                <td class="py-2 font-medium">{{ $garden->name }}</td>
                                <td class="py-2">{{ $garden->area_hectares ? number_format($garden->area_hectares, 2) : '-' }}</td>
                                <td class="py-2">{{ $garden->trees()->count() }}</td>
                                <td class="py-2">
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs {{ $garden->status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-400' }}">
                                        {{ $garden->status === 'active' ? 'Aktif' : 'Tidak aktif' }}
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
