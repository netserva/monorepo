<div class="space-y-4">
    <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
        <div class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
            Bash Export Format
        </div>
        <div class="font-mono text-sm text-gray-900 dark:text-gray-100 bg-white dark:bg-gray-900 p-3 rounded border border-gray-200 dark:border-gray-700">
            {{ $export }}
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Variable Name</div>
            <div class="font-mono text-sm text-gray-900 dark:text-gray-100">{{ $name }}</div>
        </div>
        <div>
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Value</div>
            <div class="font-mono text-sm text-gray-900 dark:text-gray-100 break-all">{{ $value }}</div>
        </div>
    </div>

    <div class="text-xs text-gray-500 dark:text-gray-400 mt-4">
        💡 <strong>CLI Usage:</strong> <code class="bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">shvconf {{ request()->get('vnode', 'markc') }} {{ request()->get('vhost', 'example.com') }}</code>
    </div>
</div>
