<div class="space-y-4">
    {{-- Event Information --}}
    <div class="grid grid-cols-2 gap-4">
        <div>
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Event Type</h3>
            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset
                    @if($record->event_type === 'created') bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-400/10 dark:text-green-400 dark:ring-green-400/20
                    @elseif($record->event_type === 'updated') bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-400/10 dark:text-blue-400 dark:ring-blue-400/20
                    @elseif(in_array($record->event_type, ['deleted', 'force_deleted'])) bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-400/10 dark:text-red-400 dark:ring-red-400/20
                    @else bg-gray-50 text-gray-700 ring-gray-600/20 dark:bg-gray-400/10 dark:text-gray-400 dark:ring-gray-400/20
                    @endif">
                    {{ $record->event_type_display }}
                </span>
            </p>
        </div>

        <div>
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Severity</h3>
            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset
                    @if($record->severity_level === 'critical' || $record->severity_level === 'high') bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-400/10 dark:text-red-400 dark:ring-red-400/20
                    @elseif($record->severity_level === 'medium') bg-yellow-50 text-yellow-700 ring-yellow-600/20 dark:bg-yellow-400/10 dark:text-yellow-400 dark:ring-yellow-400/20
                    @else bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-400/10 dark:text-green-400 dark:ring-green-400/20
                    @endif">
                    {{ $record->severity_display }}
                </span>
            </p>
        </div>

        <div>
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Category</h3>
            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ ucfirst($record->event_category) }}</p>
        </div>

        <div>
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Time</h3>
            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $record->occurred_at->format('M d, Y H:i:s') }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $record->age }}</p>
        </div>
    </div>

    {{-- User & Context Information --}}
    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
        <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Context</h3>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <h4 class="text-xs font-medium text-gray-600 dark:text-gray-400">User</h4>
                <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $record->user_display_name }}</p>
            </div>

            @if($record->ip_address)
                <div>
                    <h4 class="text-xs font-medium text-gray-600 dark:text-gray-400">IP Address</h4>
                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $record->ip_address }}</p>
                </div>
            @endif

            @if($record->resource_type)
                <div>
                    <h4 class="text-xs font-medium text-gray-600 dark:text-gray-400">Resource Type</h4>
                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $record->resource_type_display }}</p>
                </div>
            @endif

            @if($record->resource_name)
                <div>
                    <h4 class="text-xs font-medium text-gray-600 dark:text-gray-400">Resource Name</h4>
                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $record->resource_name }}</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Description --}}
    @if($record->description)
        <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Description</h3>
            <p class="text-sm text-gray-900 dark:text-gray-100">{{ $record->description }}</p>
        </div>
    @endif

    {{-- Changes (for updated events) --}}
    @if($record->event_type === 'updated' && $record->getChangesSummary())
        <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Changes</h3>
            <div class="space-y-2">
                @foreach($record->getChangesSummary() as $field => $change)
                    <div class="bg-gray-50 dark:bg-gray-800 rounded-md p-3">
                        <h4 class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                            {{ ucfirst(str_replace('_', ' ', $field)) }}
                        </h4>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div>
                                <span class="text-xs text-gray-500 dark:text-gray-400">Old:</span>
                                <code class="block mt-1 text-xs bg-white dark:bg-gray-900 rounded px-2 py-1 text-red-600 dark:text-red-400">
                                    {{ is_array($change['old']) ? json_encode($change['old']) : ($change['old'] ?? 'null') }}
                                </code>
                            </div>
                            <div>
                                <span class="text-xs text-gray-500 dark:text-gray-400">New:</span>
                                <code class="block mt-1 text-xs bg-white dark:bg-gray-900 rounded px-2 py-1 text-green-600 dark:text-green-400">
                                    {{ is_array($change['new']) ? json_encode($change['new']) : ($change['new'] ?? 'null') }}
                                </code>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Metadata --}}
    @if($record->metadata && count($record->metadata) > 0)
        <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Additional Metadata</h3>
            <pre class="text-xs bg-gray-50 dark:bg-gray-800 rounded-md p-3 overflow-auto text-gray-900 dark:text-gray-100">{{ json_encode($record->metadata, JSON_PRETTY_PRINT) }}</pre>
        </div>
    @endif

    {{-- User Agent --}}
    @if($record->user_agent)
        <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">User Agent</h3>
            <p class="text-xs text-gray-600 dark:text-gray-400 break-all">{{ $record->user_agent }}</p>
        </div>
    @endif

    {{-- Security Notice --}}
    @if($record->isSecuritySensitive())
        <div class="border-t border-red-200 dark:border-red-700 pt-4">
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md p-3">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800 dark:text-red-200">Security Sensitive Event</h3>
                        <p class="mt-1 text-xs text-red-700 dark:text-red-300">This audit log entry represents a security-sensitive action that should be monitored.</p>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
