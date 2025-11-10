<div class="space-y-4">
    @if($theme->manifest)
        {{-- Quick Stats Bar --}}
        <div class="flex items-center gap-4 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-2">
                <span class="text-lg">🎨</span>
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Colors</span>
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ count($theme->colors()) }}</p>
                </div>
            </div>
            <div class="w-px h-8 bg-gray-300 dark:bg-gray-600"></div>
            <div class="flex items-center gap-2">
                <span class="text-lg">📄</span>
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Templates</span>
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                        {{ !empty($theme->manifest['templates']) ? array_sum(array_map('count', $theme->manifest['templates'])) : 0 }}
                    </p>
                </div>
            </div>
            @if(!empty($theme->manifest['support']))
                <div class="w-px h-8 bg-gray-300 dark:bg-gray-600"></div>
                <div class="flex items-center gap-2">
                    <span class="text-lg">✅</span>
                    <div>
                        <span class="text-xs text-gray-500 dark:text-gray-400">Features</span>
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ count(array_filter($theme->manifest['support'])) }}
                        </p>
                    </div>
                </div>
            @endif
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- Colors Section --}}
            @if(!empty($theme->colors()))
                <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            <span>🎨</span>
                            Color Palette
                        </h4>
                    </div>
                    <div class="p-4 space-y-2 max-h-64 overflow-y-auto">
                        @foreach($theme->colors() as $color)
                            <div class="flex items-center gap-3 p-2 hover:bg-gray-50 dark:hover:bg-gray-800 rounded transition-colors">
                                <div class="w-8 h-8 rounded-md shadow-sm border-2 border-gray-200 dark:border-gray-600 flex-shrink-0"
                                     style="background-color: {{ $color['value'] }}"></div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $color['name'] }}</p>
                                    <code class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ $color['value'] }}</code>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Typography Section --}}
            @if(!empty($theme->typography()))
                <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            <span>🔤</span>
                            Typography
                        </h4>
                    </div>
                    <div class="p-4 space-y-3">
                        @php $typography = $theme->typography(); @endphp
                        @if(isset($typography['fonts']['heading']))
                            <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded border border-gray-100 dark:border-gray-700">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Heading Font</p>
                                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $typography['fonts']['heading']['family'] ?? 'N/A' }}
                                </p>
                                @if(isset($typography['fonts']['heading']['provider']))
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        via {{ ucfirst($typography['fonts']['heading']['provider']) }}
                                    </p>
                                @endif
                            </div>
                        @endif
                        @if(isset($typography['fonts']['body']))
                            <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded border border-gray-100 dark:border-gray-700">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Body Font</p>
                                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $typography['fonts']['body']['family'] ?? 'N/A' }}
                                </p>
                                @if(isset($typography['fonts']['body']['provider']))
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        via {{ ucfirst($typography['fonts']['body']['provider']) }}
                                    </p>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Layout Section --}}
            @if(!empty($theme->manifest['settings']['layout']))
                <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            <span>📐</span>
                            Layout
                        </h4>
                    </div>
                    <div class="p-4 space-y-2">
                        @php $layout = $theme->manifest['settings']['layout']; @endphp
                        @foreach(['contentWidth', 'wideWidth', 'containerWidth'] as $key)
                            @if(isset($layout[$key]) && is_string($layout[$key]))
                                <div class="flex items-center justify-between p-2 hover:bg-gray-50 dark:hover:bg-gray-800 rounded transition-colors">
                                    <span class="text-sm text-gray-600 dark:text-gray-300">
                                        {{ ucfirst(preg_replace('/([A-Z])/', ' $1', $key)) }}
                                    </span>
                                    <code class="text-sm font-mono font-semibold text-gray-900 dark:text-gray-100">
                                        {{ $layout[$key] }}
                                    </code>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Templates Section --}}
            @if(!empty($theme->manifest['templates']))
                <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            <span>📄</span>
                            Templates
                        </h4>
                    </div>
                    <div class="p-4 space-y-3 max-h-64 overflow-y-auto">
                        @foreach($theme->manifest['templates'] as $type => $templates)
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        {{ $type }}
                                    </span>
                                    <span class="text-xs font-semibold text-gray-900 dark:text-gray-100">
                                        {{ count($templates) }}
                                    </span>
                                </div>
                                <div class="flex flex-wrap gap-1">
                                    @foreach($templates as $template)
                                        <span class="inline-flex items-center px-2 py-1 text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded"
                                              title="{{ $template['description'] ?? '' }}">
                                            {{ $template['label'] }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Info Banner --}}
        <div class="p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
            <div class="flex items-start gap-2">
                <span class="text-lg">ℹ️</span>
                <div class="flex-1">
                    <p class="text-sm font-medium text-blue-900 dark:text-blue-100">
                        Read-only display
                    </p>
                    <p class="text-xs text-blue-800 dark:text-blue-200 mt-1">
                        Customize via <strong>Theme Settings</strong> page or edit <code class="px-1 py-0.5 bg-blue-100 dark:bg-blue-900 rounded font-mono">theme.json</code> and click <strong>Reload Manifest</strong>
                    </p>
                </div>
            </div>
        </div>
    @else
        <div class="p-6 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 text-center">
            <div class="text-5xl mb-3">📋</div>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">No manifest data available</p>
            <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">Create a theme.json file in your theme directory</p>
        </div>
    @endif
</div>
