<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,

    // NetServa Core Services (Load CLI first to claim 'ns' command)
    NetServa\Core\NetServaCoreServiceProvider::class,
    NetServa\Cli\NetServaCliServiceProvider::class,
];
