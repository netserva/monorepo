<div class="space-y-4">
    @php
        $reservations = $network->ipReservations()->orderBy('start_ip')->get();
    @endphp

    @if($reservations->isEmpty())
        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
            <x-heroicon-o-bookmark class="w-12 h-12 mx-auto mb-2 opacity-50" />
            <p>No reservations in this network yet.</p>
            <p class="text-sm mt-1">Reservations block IP ranges for specific purposes (DHCP, infrastructure, etc.)</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Name</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Range</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Type</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Count</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Purpose</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Active</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($reservations as $reservation)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-2 font-medium text-gray-900 dark:text-gray-100">{{ $reservation->name }}</td>
                            <td class="px-4 py-2 font-mono text-gray-700 dark:text-gray-300">
                                {{ $reservation->start_ip }} - {{ $reservation->end_ip }}
                            </td>
                            <td class="px-4 py-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                    @if($reservation->reservation_type === 'static_range')
                                        bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                    @else
                                        bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                    @endif
                                ">
                                    {{ \NetServa\Fleet\Models\IpReservation::RESERVATION_TYPES[$reservation->reservation_type] ?? $reservation->reservation_type }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-gray-600 dark:text-gray-400">{{ $reservation->address_count }} IPs</td>
                            <td class="px-4 py-2 text-gray-600 dark:text-gray-400">{{ $reservation->purpose ?? '-' }}</td>
                            <td class="px-4 py-2">
                                @if($reservation->is_active)
                                    <x-heroicon-s-check-circle class="w-5 h-5 text-green-500" />
                                @else
                                    <x-heroicon-s-x-circle class="w-5 h-5 text-gray-400" />
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="text-xs text-gray-500 dark:text-gray-400 pt-2">
            Total reserved: {{ $reservations->sum('address_count') }} addresses across {{ $reservations->count() }} reservations
        </div>
    @endif
</div>
