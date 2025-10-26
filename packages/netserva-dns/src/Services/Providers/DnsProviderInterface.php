<?php

namespace NetServa\Dns\Services\Providers;

interface DnsProviderInterface
{
    public function testConnection(): bool;

    // Zone management
    public function getAllZones(): array;

    public function getZone(string $zoneId): array;

    public function createZone(array $data): array;

    public function updateZone(string $zoneId, array $data): array;

    public function deleteZone(string $zoneId): bool;

    // Record management
    public function getZoneRecords(string $zoneId): array;

    public function getRecord(string $zoneId, string $recordId): array;

    public function createRecord(string $zoneId, array $data): array;

    public function updateRecord(string $zoneId, string $recordId, array $data): array;

    public function deleteRecord(string $zoneId, string $recordId): bool;
}
