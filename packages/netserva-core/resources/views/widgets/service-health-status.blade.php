<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Service Health Status
        </x-slot>

        <x-slot name="description">
            Real-time status of critical infrastructure services
        </x-slot>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {{-- SSH Hosts --}}
            <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">SSH Hosts</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                            {{ $ssh_hosts['online'] }}/{{ $ssh_hosts['total'] }}
                        </p>
                    </div>
                    <div class="rounded-full bg-blue-100 p-3 dark:bg-blue-900">
                        <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                        <div class="h-full rounded-full {{ $ssh_hosts['health_percentage'] >= 80 ? 'bg-green-500' : ($ssh_hosts['health_percentage'] >= 50 ? 'bg-yellow-500' : 'bg-red-500') }}"
                             style="width: {{ $ssh_hosts['health_percentage'] }}%"></div>
                    </div>
                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                        {{ $ssh_hosts['health_percentage'] }}% online
                    </p>
                </div>
            </div>

            {{-- DNS Providers --}}
            <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">DNS Providers</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                            {{ $dns_providers['active'] }}/{{ $dns_providers['total'] }}
                        </p>
                    </div>
                    <div class="rounded-full bg-purple-100 p-3 dark:bg-purple-900">
                        <svg class="h-6 w-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                        <div class="h-full rounded-full {{ $dns_providers['health_percentage'] >= 80 ? 'bg-green-500' : ($dns_providers['health_percentage'] >= 50 ? 'bg-yellow-500' : 'bg-red-500') }}"
                             style="width: {{ $dns_providers['health_percentage'] }}%"></div>
                    </div>
                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                        {{ $dns_providers['health_percentage'] }}% active
                    </p>
                </div>
            </div>

            {{-- Mail Servers --}}
            <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Mail Servers</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                            {{ $mail_servers['running'] }}/{{ $mail_servers['total'] }}
                        </p>
                    </div>
                    <div class="rounded-full bg-green-100 p-3 dark:bg-green-900">
                        <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                        <div class="h-full rounded-full {{ $mail_servers['health_percentage'] >= 80 ? 'bg-green-500' : ($mail_servers['health_percentage'] >= 50 ? 'bg-yellow-500' : 'bg-red-500') }}"
                             style="width: {{ $mail_servers['health_percentage'] }}%"></div>
                    </div>
                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                        {{ $mail_servers['health_percentage'] }}% running
                    </p>
                </div>
            </div>

            {{-- Web Servers --}}
            <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Web Servers</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                            {{ $web_servers['running'] }}/{{ $web_servers['total'] }}
                        </p>
                    </div>
                    <div class="rounded-full bg-orange-100 p-3 dark:bg-orange-900">
                        <svg class="h-6 w-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                        <div class="h-full rounded-full {{ $web_servers['health_percentage'] >= 80 ? 'bg-green-500' : ($web_servers['health_percentage'] >= 50 ? 'bg-yellow-500' : 'bg-red-500') }}"
                             style="width: {{ $web_servers['health_percentage'] }}%"></div>
                    </div>
                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                        {{ $web_servers['health_percentage'] }}% running
                    </p>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
