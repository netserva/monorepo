<x-filament-panels::page>
    {{-- Command Table --}}
    {{ $this->table }}

    {{-- Output Panel (shown after command execution) --}}
    @if ($this->lastOutput !== null)
        <div class="mt-6">
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        @if ($this->lastExitCode === 0)
                            <x-heroicon-o-check-circle class="w-5 h-5 text-success-500" />
                        @else
                            <x-heroicon-o-exclamation-circle class="w-5 h-5 text-danger-500" />
                        @endif
                        <span>Command Output</span>
                        <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                            Exit code: {{ $this->lastExitCode }}
                        </span>
                    </div>
                </x-slot>

                <x-slot name="headerEnd">
                    <x-filament::button
                        wire:click="clearOutput"
                        color="gray"
                        size="sm"
                        icon="heroicon-o-x-mark"
                    >
                        Clear
                    </x-filament::button>
                </x-slot>

                @if ($this->lastCommand)
                    <div class="mb-4 p-2 bg-gray-100 dark:bg-gray-800 rounded-lg">
                        <code class="text-sm font-mono text-gray-700 dark:text-gray-300">
                            $ php artisan {{ $this->lastCommand }}
                        </code>
                    </div>
                @endif

                <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                    <pre class="text-sm font-mono text-gray-100 whitespace-pre-wrap">{{ $this->lastOutput ?: 'No output' }}</pre>
                </div>
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
