<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white shadow-sm border border-gray-200 rounded-lg p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Plugin Status</h2>

            @if(isset($error))
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                    <strong>Error:</strong> {{ $error }}
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <div class="text-2xl font-bold text-blue-600">{{ count($availablePlugins) }}</div>
                        <div class="text-sm text-blue-800">Available Plugins</div>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg">
                        <div class="text-2xl font-bold text-green-600">{{ count($enabledPlugins) }}</div>
                        <div class="text-sm text-green-800">Enabled Plugins</div>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="text-2xl font-bold text-gray-600">{{ $allPlugins->count() }}</div>
                        <div class="text-sm text-gray-800">Total Database Records</div>
                    </div>
                </div>
            @endif
        </div>

        @unless(isset($error))
        <div class="bg-white shadow-sm border border-gray-200 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Enabled Plugins (Dependency Order)</h3>
            @if(count($enabledPlugins) > 0)
                <div class="space-y-2">
                    @foreach($enabledPlugins as $pluginId)
                        <div class="flex items-center justify-between p-3 bg-green-50 border border-green-200 rounded">
                            <div>
                                <span class="font-medium text-green-900">{{ $pluginId }}</span>
                                <span class="text-sm text-green-700 ml-2">
                                    {{ $availablePlugins[$pluginId] ?? 'Class not found' }}
                                </span>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                ✅ Enabled
                            </span>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-gray-500">No plugins are currently enabled.</p>
            @endif
        </div>

        <div class="bg-white shadow-sm border border-gray-200 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">All Database Records</h3>
            @if($allPlugins->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plugin</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plugin Class</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Version</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($allPlugins as $plugin)
                                <tr class="{{ $plugin->is_enabled ? 'bg-green-50' : 'bg-gray-50' }}">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ $plugin->name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        @if($plugin->is_enabled)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                ✅ Enabled
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                ❌ Disabled
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">
                                        {{ $plugin->plugin_class ?: 'Not set' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $plugin->version ?? 'Unknown' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-gray-500">No plugin records found in database.</p>
            @endif
        </div>
        @endunless
    </div>
</x-filament-panels::page>