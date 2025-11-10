<div class="space-y-6">
    <div>
        <h3 class="text-lg font-semibold mb-2">{{ $theme->display_name }} Manifest</h3>
        <p class="text-sm text-gray-600">Version {{ $theme->version }}</p>
    </div>

    @if($theme->manifest)
        {{-- Colors Section --}}
        @if(!empty($theme->colors()))
            <div class="space-y-3">
                <h4 class="font-semibold text-gray-900">Color Palette</h4>
                <div class="grid grid-cols-2 gap-3">
                    @foreach($theme->colors() as $color)
                        <div class="flex items-center gap-3 p-2 bg-gray-50 rounded">
                            <div class="w-10 h-10 rounded-lg border-2 border-gray-200 shadow-sm"
                                 style="background-color: {{ $color['value'] }}">
                            </div>
                            <div class="flex-1">
                                <p class="font-medium text-sm">{{ $color['name'] }}</p>
                                <code class="text-xs text-gray-500">{{ $color['slug'] }}</code>
                            </div>
                            <code class="text-xs font-mono text-gray-600">{{ $color['value'] }}</code>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Typography Section --}}
        @if(!empty($theme->typography()))
            <div class="space-y-3">
                <h4 class="font-semibold text-gray-900">Typography</h4>
                @php $typography = $theme->typography(); @endphp

                <div class="space-y-2">
                    @if(isset($typography['fonts']['heading']))
                        <div class="p-3 bg-gray-50 rounded">
                            <p class="text-sm font-semibold">Heading Font</p>
                            <p class="text-sm text-gray-600">
                                {{ $typography['fonts']['heading']['family'] }}
                                @if(isset($typography['fonts']['heading']['provider']))
                                    <span class="text-xs">({{ $typography['fonts']['heading']['provider'] }})</span>
                                @endif
                            </p>
                        </div>
                    @endif

                    @if(isset($typography['fonts']['body']))
                        <div class="p-3 bg-gray-50 rounded">
                            <p class="text-sm font-semibold">Body Font</p>
                            <p class="text-sm text-gray-600">
                                {{ $typography['fonts']['body']['family'] }}
                                @if(isset($typography['fonts']['body']['provider']))
                                    <span class="text-xs">({{ $typography['fonts']['body']['provider'] }})</span>
                                @endif
                            </p>
                        </div>
                    @endif
                </div>

                @if(isset($typography['sizes']))
                    <div>
                        <p class="text-sm font-semibold mb-2">Font Sizes</p>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach($typography['sizes'] as $size)
                                <div class="text-xs p-2 bg-gray-50 rounded">
                                    <code>{{ $size['slug'] }}:</code> {{ $size['value'] }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif

        {{-- Layout Section --}}
        @if(!empty($theme->manifest['settings']['layout']))
            <div class="space-y-3">
                <h4 class="font-semibold text-gray-900">Layout Settings</h4>
                @php $layout = $theme->manifest['settings']['layout']; @endphp
                <div class="grid grid-cols-2 gap-2">
                    @foreach($layout as $key => $value)
                        @if(is_string($value))
                            <div class="p-2 bg-gray-50 rounded text-sm">
                                <span class="font-medium">{{ ucfirst(preg_replace('/([A-Z])/', ' $1', $key)) }}:</span>
                                <code class="text-xs">{{ $value }}</code>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Templates Section --}}
        @if(!empty($theme->manifest['templates']))
            <div class="space-y-3">
                <h4 class="font-semibold text-gray-900">Available Templates</h4>
                @foreach($theme->manifest['templates'] as $type => $templates)
                    <div class="space-y-2">
                        <p class="text-sm font-medium capitalize">{{ $type }} Templates ({{ count($templates) }})</p>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach($templates as $template)
                                <div class="p-2 bg-gray-50 rounded text-sm">
                                    <p class="font-medium">{{ $template['label'] }}</p>
                                    @if(isset($template['description']))
                                        <p class="text-xs text-gray-600">{{ $template['description'] }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Features Section --}}
        @if(!empty($theme->manifest['support']))
            <div class="space-y-3">
                <h4 class="font-semibold text-gray-900">Supported Features</h4>
                <div class="flex flex-wrap gap-2">
                    @foreach($theme->manifest['support'] as $feature => $supported)
                        @if($supported)
                            <span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-medium rounded-full">
                                {{ ucfirst(str_replace('_', ' ', $feature)) }}
                            </span>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Raw JSON (Collapsible) --}}
        <details class="space-y-2">
            <summary class="cursor-pointer text-sm font-semibold text-gray-700 hover:text-gray-900">
                View Raw JSON
            </summary>
            <pre class="p-4 bg-gray-900 text-green-400 rounded text-xs overflow-x-auto"><code>{{ json_encode($theme->manifest, JSON_PRETTY_PRINT) }}</code></pre>
        </details>
    @else
        <p class="text-gray-500">No manifest data available</p>
    @endif
</div>
