<div class="space-y-4">
    @if($theme->manifest)
        {{-- Quick Stats Bar --}}
        <div class="flex items-center gap-4 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4 2a2 2 0 00-2 2v11a3 3 0 106 0V4a2 2 0 00-2-2H4zm1 14a1 1 0 100-2 1 1 0 000 2zm5-1.757l4.9-4.9a2 2 0 000-2.828L13.485 5.1a2 2 0 00-2.828 0L10 5.757v8.486zM16 18H9.071l6-6H16a2 2 0 012 2v2a2 2 0 01-2 2z" clip-rule="evenodd"/>
                </svg>
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Colors</span>
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ count($theme->colors()) }}</p>
                </div>
            </div>
            <div class="w-px h-8 bg-gray-300 dark:bg-gray-600"></div>
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                </svg>
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
                    <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
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
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 2a2 2 0 00-2 2v11a3 3 0 106 0V4a2 2 0 00-2-2H4zm1 14a1 1 0 100-2 1 1 0 000 2zm5-1.757l4.9-4.9a2 2 0 000-2.828L13.485 5.1a2 2 0 00-2.828 0L10 5.757v8.486zM16 18H9.071l6-6H16a2 2 0 012 2v2a2 2 0 01-2 2z" clip-rule="evenodd"/>
                            </svg>
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
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h6a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/>
                            </svg>
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
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/>
                            </svg>
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
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                            </svg>
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
                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
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
            <svg class="w-12 h-12 mx-auto text-gray-400 dark:text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">No manifest data available</p>
            <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">Create a theme.json file in your theme directory</p>
        </div>
    @endif
</div>
