<div class="space-y-4">
    @php
        $addresses = $network->ipAddresses()->orderBy('ip_address')->get();
    @endphp

    @if($addresses->isEmpty())
        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
            <x-heroicon-o-hashtag class="w-12 h-12 mx-auto mb-2 opacity-50" />
            <p>No IP addresses in this network yet.</p>
            <p class="text-sm mt-1">Add addresses via CLI: <code class="bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">php artisan ipam:add {{ $network->cidr }}</code></p>
        </div>
    @else
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">IP Address</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Hostname</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Status</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Owner</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Service</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">MAC</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($addresses as $address)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-2 font-mono text-gray-900 dark:text-gray-100">{{ $address->ip_address }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $address->hostname ?? '-' }}</td>
                            <td class="px-4 py-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                    @switch($address->status)
                                        @case('available') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 @break
                                        @case('allocated') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 @break
                                        @case('reserved') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 @break
                                        @case('gateway') bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 @break
                                        @default bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200
                                    @endswitch
                                ">
                                    {{ ucfirst($address->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-gray-600 dark:text-gray-400">{{ $address->owner ?? '-' }}</td>
                            <td class="px-4 py-2 text-gray-600 dark:text-gray-400">{{ $address->service ?? '-' }}</td>
                            <td class="px-4 py-2 font-mono text-xs text-gray-500 dark:text-gray-500">{{ $address->mac_address ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="text-xs text-gray-500 dark:text-gray-400 pt-2">
            Showing {{ $addresses->count() }} of {{ $network->total_addresses }} possible addresses
        </div>
    @endif
</div>
