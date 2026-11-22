<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-blue-50 dark:bg-blue-950/40 p-4 rounded-xl border border-blue-200 dark:border-blue-800 flex items-start gap-3">
            <x-filament::icon icon="heroicon-o-shield-check" class="w-6 h-6 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" />
            <div class="flex-1 text-sm text-blue-900 dark:text-blue-200">
                <p class="font-semibold">Tempat Khusus Data Terhapus (Trash Bin Super Admin)</p>
                <p class="text-xs text-blue-700 dark:text-blue-300 mt-1">
                    Seluruh data yang dihapus oleh pengguna akan disimpan di sini secara aman (soft delete) selama <strong>30 hari</strong> sebelum dihapus permanen secara otomatis oleh sistem. Sebagai Super Admin, Anda dapat memulihkan kembali data (putback) atau menghapus permanen sebelum 30 hari.
                </p>
            </div>
            <x-filament::button wire:click="runPruneNow" color="gray" size="sm" icon="heroicon-o-arrow-path">
                Prune Data > 30 Hari
            </x-filament::button>
        </div>

        {{-- Statistics Grid --}}
        <div>
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3 uppercase tracking-wider">Statistik Data Terhapus Saat Ini</h3>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                @foreach($this->summary as $key => $count)
                    <div class="bg-white dark:bg-gray-900 p-3 rounded-lg border border-gray-200 dark:border-gray-800">
                        <span class="text-xs text-gray-500 uppercase">{{ ucfirst($key) }}</span>
                        <p class="text-2xl font-bold {{ $count > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-400' }} mt-1">
                            {{ $count }}
                        </p>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Table of Recent Deleted Items --}}
        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-3">Daftar Data yang Baru Dihapus</h3>

            @if(count($this->recentDeletedItems) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-800 text-xs text-gray-500 uppercase">
                                <th class="py-2.5 px-3">Tipe Entitas</th>
                                <th class="py-2.5 px-3">Nama / Identitas</th>
                                <th class="py-2.5 px-3">Waktu Dihapus</th>
                                <th class="py-2.5 px-3">Sisa Waktu Retensi</th>
                                <th class="py-2.5 px-3 text-right">Aksi Super Admin</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($this->recentDeletedItems as $item)
                                <tr>
                                    <td class="py-2.5 px-3">
                                        <span class="inline-flex px-2 py-0.5 rounded text-xs bg-gray-100 dark:bg-gray-800 font-medium">
                                            {{ $item['type'] }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 font-medium text-gray-900 dark:text-white">
                                        {{ $item['name'] }}
                                    </td>
                                    <td class="py-2.5 px-3 text-gray-500 text-xs">
                                        {{ $item['deleted_at'] }}
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <span class="text-xs font-semibold {{ $item['days_left'] <= 5 ? 'text-danger-600' : 'text-warning-600' }}">
                                            {{ $item['days_left'] }} hari lagi
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 text-right space-x-2">
                                        <button
                                            wire:click="restoreRecord('{{ addslashes($item['class']) }}', '{{ $item['id'] }}')"
                                            class="inline-flex items-center gap-1 text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">
                                            <x-filament::icon icon="heroicon-o-arrow-uturn-left" class="w-4 h-4" />
                                            Pulihkan (Putback)
                                        </button>

                                        <button
                                            wire:click="forceDeleteRecord('{{ addslashes($item['class']) }}', '{{ $item['id'] }}')"
                                            wire:confirm="PERINGATAN: Apakah Anda yakin ingin menghapus data ini secara permanen? Tindakan ini tidak dapat dibatalkan."
                                            class="inline-flex items-center gap-1 text-xs font-medium text-danger-600 hover:text-danger-700 dark:text-danger-400">
                                            <x-filament::icon icon="heroicon-o-trash" class="w-4 h-4" />
                                            Hapus Permanen
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="py-12 text-center text-gray-400">
                    <x-filament::icon icon="heroicon-o-check-circle" class="w-12 h-12 mx-auto text-success-500 mb-2" />
                    <p class="font-medium text-gray-600 dark:text-gray-300">Tidak ada data terhapus saat ini</p>
                    <p class="text-xs text-gray-400 mt-1">Seluruh data aktif dalam kondisi aman.</p>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
