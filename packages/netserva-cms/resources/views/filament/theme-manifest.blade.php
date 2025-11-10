<div class="space-y-4 text-sm">
    @if($theme->manifest)
        <div class="grid grid-cols-2 gap-4">
            {{-- Colors --}}
            @if(!empty($theme->colors()))
                <div class="space-y-2">
                    <h4 class="font-semibold">Colors</h4>
                    <div class="space-y-1">
                        @foreach($theme->colors() as $color)
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded border" style="background-color: {{ $color['value'] }}"></div>
                                <span>{{ $color['name'] }}</span>
                                <code class="text-xs text-gray-500">{{ $color['value'] }}</code>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Typography --}}
            @if(!empty($theme->typography()))
                <div class="space-y-2">
                    <h4 class="font-semibold">Typography</h4>
                    <div class="space-y-1">
                        @php $typography = $theme->typography(); @endphp
                        @if(isset($typography['fonts']['heading']))
                            <p><strong>Heading:</strong> {{ $typography['fonts']['heading']['family'] ?? 'N/A' }}</p>
                        @endif
                        @if(isset($typography['fonts']['body']))
                            <p><strong>Body:</strong> {{ $typography['fonts']['body']['family'] ?? 'N/A' }}</p>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Layout --}}
            @if(!empty($theme->manifest['settings']['layout']))
                <div class="space-y-2">
                    <h4 class="font-semibold">Layout</h4>
                    <div class="space-y-1">
                        @php $layout = $theme->manifest['settings']['layout']; @endphp
                        @if(isset($layout['contentWidth']))
                            <p><strong>Content Width:</strong> {{ $layout['contentWidth'] }}</p>
                        @endif
                        @if(isset($layout['wideWidth']))
                            <p><strong>Wide Width:</strong> {{ $layout['wideWidth'] }}</p>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Templates --}}
            @if(!empty($theme->manifest['templates']))
                <div class="space-y-2">
                    <h4 class="font-semibold">Available Templates</h4>
                    <div class="space-y-1">
                        @foreach($theme->manifest['templates'] as $type => $templates)
                            <p><strong class="capitalize">{{ $type }}:</strong> {{ count($templates) }}</p>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @else
        <p class="text-gray-500">No manifest data available</p>
    @endif
</div>
