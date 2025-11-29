<x-filament-panels::page>
    <style>
        /* Classic Terminal Emulator Styling */
        .terminal-emulator {
            background: #1a1a1a;
            border: 2px solid #333;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            max-width: 100%;
            overflow: hidden;
        }

        .terminal-header {
            background: linear-gradient(to bottom, #4a4a4a, #2a2a2a);
            border-bottom: 1px solid #555;
            padding: 8px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 28px;
        }

        .terminal-title {
            color: #fff;
            font-size: 12px;
            font-weight: 500;
            font-family: system-ui, -apple-system, sans-serif;
        }

        .terminal-buttons {
            display: flex;
            gap: 6px;
        }

        .terminal-button {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            cursor: pointer;
            transition: opacity 0.2s;
        }

        .terminal-button:hover {
            opacity: 0.8;
        }

        .terminal-button.close { background: #ff5f57; }
        .terminal-button.minimize { background: #ffbd2e; }
        .terminal-button.maximize { background: #28ca42; }

        .terminal-screen {
            background: #000;
            color: #00ff00;
            padding: 16px;
            margin: 0;
            border: none;
            width: 100%;
            min-height: 400px;
            max-height: 500px;
            overflow-y: auto;
            overflow-x: hidden;
            white-space: pre-wrap;
            word-wrap: break-word;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 14px;
            line-height: 1.4;
        }

        .terminal-screen::-webkit-scrollbar { width: 8px; }
        .terminal-screen::-webkit-scrollbar-track { background: #000; }
        .terminal-screen::-webkit-scrollbar-thumb {
            background: #00ff00;
            border-radius: 4px;
        }
        .terminal-screen::-webkit-scrollbar-thumb:hover { background: #00dd00; }

        /* Terminal text styling */
        .terminal-error { color: #ff4444; }
        .terminal-status { color: #44ff44; font-style: italic; }
        .terminal-prompt { color: #ffff44; }
        .terminal-host { color: #74c0fc; }
        .terminal-exit-success { color: #28ca42; }
        .terminal-exit-error { color: #ff5f57; }

        /* Debug panel styling */
        .debug-panel {
            background: #1e1e1e;
            border: 1px solid #333;
            border-radius: 6px;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 12px;
        }

        .debug-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            border-bottom: 1px solid #333;
        }

        .debug-row:last-child {
            border-bottom: none;
        }

        .debug-label {
            color: #888;
        }

        .debug-value {
            font-weight: 500;
        }

        .debug-value.success { color: #28ca42; }
        .debug-value.error { color: #ff5f57; }
        .debug-value.warning { color: #ffbd2e; }
        .debug-value.info { color: #74c0fc; }
        .debug-value.muted { color: #666; }
    </style>

    {{-- Command Form Section --}}
    <x-filament::section>
        {{ $this->form }}
    </x-filament::section>

    {{-- Terminal Output Section --}}
    <div class="terminal-emulator mt-6">
        <div class="terminal-header">
            <div class="terminal-title">
                SSH Terminal - {{ $this->lastHost ?? 'Ready' }}
                @if($this->executionTime)
                    <span class="text-gray-400 ml-2">({{ $this->executionTime }}ms)</span>
                @endif
            </div>
            <div class="terminal-buttons">
                <span class="terminal-button close" wire:click="clearOutput" title="Clear Output"></span>
                <span class="terminal-button minimize"></span>
                <span class="terminal-button maximize"></span>
            </div>
        </div>
        <pre class="terminal-screen">@if($this->lastOutput !== null)<span class="terminal-prompt">{{ $this->lastHost }}$</span> {{ $this->lastCommand }}
<span class="{{ $this->lastExitCode === 0 ? 'terminal-exit-success' : 'terminal-exit-error' }}">[Exit: {{ $this->lastExitCode }}]</span>

{{ $this->lastOutput }}@else<span class="terminal-status">Ready to execute commands...</span>
<span class="terminal-prompt">Tip:</span> Select a host, enter a command, and click "Run Command"
<span class="terminal-prompt">Bash Mode:</span> Enable to load .bashrc aliases and functions (bash -ci)@endif</pre>
    </div>

    {{-- Debug Section (Conditional) --}}
    @if ($this->showDebug)
        <x-filament::section class="mt-6">
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-bug-ant class="w-5 h-5" />
                    Debug Information
                </div>
            </x-slot>

            <div class="debug-panel p-4">
                <div class="debug-row">
                    <span class="debug-label">Connection</span>
                    <span class="debug-value info">{{ $this->connectionInfo ?? 'Not connected' }}</span>
                </div>
                <div class="debug-row">
                    <span class="debug-label">Selected Host</span>
                    <span class="debug-value {{ $this->selectedHost ? 'info' : 'muted' }}">{{ $this->selectedHost ?? 'None' }}</span>
                </div>
                <div class="debug-row">
                    <span class="debug-label">Bash Mode</span>
                    <span class="debug-value {{ $this->bashMode ? 'warning' : 'muted' }}">{{ $this->bashMode ? 'Enabled (bash -ci)' : 'Disabled' }}</span>
                </div>
                <div class="debug-row">
                    <span class="debug-label">Command</span>
                    <span class="debug-value" style="color: #00ff00; max-width: 70%; overflow: hidden; text-overflow: ellipsis;">{{ $this->command ?? 'None' }}</span>
                </div>
                <div class="debug-row">
                    <span class="debug-label">Exit Code</span>
                    <span class="debug-value {{ ($this->lastExitCode ?? 0) === 0 ? 'success' : 'error' }}">{{ $this->lastExitCode ?? 'N/A' }}</span>
                </div>
                <div class="debug-row">
                    <span class="debug-label">Execution Time</span>
                    <span class="debug-value {{ $this->executionTime ? ($this->executionTime < 1000 ? 'success' : ($this->executionTime < 5000 ? 'warning' : 'error')) : 'muted' }}">
                        @if($this->executionTime)
                            {{ $this->executionTime < 1000 ? $this->executionTime . 'ms' : round($this->executionTime / 1000, 2) . 's' }}
                        @else
                            N/A
                        @endif
                    </span>
                </div>
                <div class="debug-row">
                    <span class="debug-label">Status</span>
                    <span class="debug-value {{ $this->isRunning ? 'warning' : 'success' }}">{{ $this->isRunning ? 'Running...' : 'Ready' }}</span>
                </div>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
