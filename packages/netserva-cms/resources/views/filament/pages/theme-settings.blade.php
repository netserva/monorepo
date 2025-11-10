<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="flex gap-3 mt-6">
            @foreach($this->getFormActions() as $action)
                {{ $action }}
            @endforeach
        </div>
    </form>

    {{-- Live Preview Section --}}
    <x-filament::section class="mt-6">
        <x-slot name="heading">
            Live Preview
        </x-slot>

        <x-slot name="description">
            Preview of your theme customizations
        </x-slot>

        <div class="space-y-4">
            {{-- Color Preview --}}
            <div>
                <h4 class="text-sm font-semibold mb-3">Color Palette</h4>
                <div class="grid grid-cols-4 gap-3">
                    @foreach($activeTheme->colors() as $color)
                        @php
                            $slug = $color['slug'];
                            $value = $data["color_{$slug}"] ?? $color['value'];
                        @endphp
                        <div class="text-center">
                            <div class="w-full h-16 rounded-lg border-2 border-gray-200 shadow-sm mb-2"
                                 style="background-color: {{ $value }}">
                            </div>
                            <p class="text-xs font-medium">{{ $color['name'] }}</p>
                            <code class="text-xs text-gray-500">{{ $value }}</code>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Typography Preview --}}
            <div class="border-t pt-4">
                <h4 class="text-sm font-semibold mb-3">Typography</h4>
                <div class="space-y-3">
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-xs text-gray-500 mb-1">Heading Font</p>
                        <p class="text-2xl font-bold" style="font-family: {{ $data['font_heading'] ?? 'Inter' }}, sans-serif">
                            The Quick Brown Fox Jumps
                        </p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-xs text-gray-500 mb-1">Body Font</p>
                        <p style="font-family: {{ $data['font_body'] ?? 'system-ui' }}, sans-serif">
                            Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Layout Preview --}}
            <div class="border-t pt-4">
                <h4 class="text-sm font-semibold mb-3">Layout Dimensions</h4>
                <div class="grid grid-cols-2 gap-3">
                    <div class="p-3 bg-gray-50 rounded">
                        <p class="text-xs text-gray-500">Content Width</p>
                        <code class="text-sm font-semibold">{{ $data['content_width'] ?? '800px' }}</code>
                    </div>
                    <div class="p-3 bg-gray-50 rounded">
                        <p class="text-xs text-gray-500">Wide Width</p>
                        <code class="text-sm font-semibold">{{ $data['wide_width'] ?? '1200px' }}</code>
                    </div>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
