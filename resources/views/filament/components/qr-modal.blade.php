<div class="flex flex-col items-center justify-center p-4 text-center space-y-4">
    <div class="p-4 bg-white rounded-xl shadow-sm border border-gray-200 inline-block">
        {!! $svg !!}
    </div>

    <div>
        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
            {{ $tree->tree_code }} - {{ $tree->tree_name ?? 'Pohon Durian' }}
        </h3>
        <p class="text-xs text-gray-500 mt-1">
            Kebun: {{ $tree->garden->name ?? '-' }} | Varietas: {{ $tree->variety->name ?? '-' }}
        </p>
    </div>

    <div class="w-full bg-gray-50 dark:bg-gray-800 p-3 rounded-lg border border-gray-200 dark:border-gray-700 text-left">
        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">URL Publik QR Code:</label>
        <div class="flex items-center space-x-2">
            <input type="text" readonly value="{{ $url }}" class="w-full text-xs bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded px-2 py-1 text-gray-700 dark:text-gray-300 font-mono select-all">
        </div>
    </div>

    <div class="flex flex-wrap items-center justify-center gap-3 pt-2">
        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium rounded-lg transition-colors shadow-sm">
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
            Buka Halaman Publik
        </a>

        <a href="/api/v1/trees/{{ $tree->id }}/qr-code/image?format=png&download=1" download="qr-{{ $tree->tree_code }}.png" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg transition-colors shadow-sm">
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            Unduh Gambar PNG
        </a>
    </div>
</div>
