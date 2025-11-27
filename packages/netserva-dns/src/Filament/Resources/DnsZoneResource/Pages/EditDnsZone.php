<?php

namespace NetServa\Dns\Filament\Resources\DnsZoneResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use NetServa\Dns\Filament\Resources\DnsRecordResource\Schemas\DnsRecordForm;
use NetServa\Dns\Filament\Resources\DnsZoneResource;
use NetServa\Dns\Models\DnsRecord;

class EditDnsZone extends EditRecord
{
    protected static string $resource = DnsZoneResource::class;

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getRelationManagersContentComponent(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add_record')
                ->label('Add Record')
                ->icon('heroicon-o-plus')
                ->color('success')
                ->modalWidth('md')
                ->modalFooterActionsAlignment('end')
                ->form(DnsRecordForm::getGridComponents())
                ->action(function (array $data): void {
                    $zone = $this->record;
                    $zoneName = rtrim($zone->name, '.');

                    // Convert subdomain to FQDN
                    $name = trim($data['name']);
                    if ($name === '@' || $name === '' || $name === $zoneName || $name === $zoneName.'.') {
                        // Zone apex
                        $data['name'] = $zoneName.'.';
                    } elseif (str_ends_with($name, '.'.$zoneName.'.') || str_ends_with($name, '.'.$zoneName)) {
                        // Already FQDN, just ensure trailing dot
                        $data['name'] = rtrim($name, '.').'.';
                    } else {
                        // Subdomain - prepend to zone name
                        $data['name'] = rtrim($name, '.').'.'.$zoneName.'.';
                    }

                    // For TXT records, wrap content in quotes
                    if ($data['type'] === 'TXT') {
                        $content = trim($data['content'], '"');
                        $data['content'] = '"'.$content.'"';
                    }

                    // Ensure content ends with dot for hostnames
                    if (in_array($data['type'], ['MX', 'SRV', 'CNAME', 'NS', 'PTR'])) {
                        if (! str_ends_with($data['content'], '.')) {
                            $data['content'] = $data['content'].'.';
                        }
                    }

                    // Create the record - observer handles sync to provider
                    DnsRecord::create([
                        'dns_zone_id' => $zone->id,
                        'name' => $data['name'],
                        'type' => $data['type'],
                        'content' => $data['content'],
                        'ttl' => $data['ttl'] ?? 300,
                        'priority' => $data['priority'] ?? 0,
                        'comment' => $data['comment'] ?? null,
                        'disabled' => $data['disabled'] ?? false,
                    ]);

                    \Filament\Notifications\Notification::make()
                        ->title('Record Created')
                        ->success()
                        ->send();

                    // Refresh the page to show the new record
                    $this->dispatch('$refresh');
                }),
            $this->getSaveFormAction()
                ->submit(null)
                ->action(fn () => $this->save()),
            $this->getCancelFormAction(),
            DeleteAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }
}
