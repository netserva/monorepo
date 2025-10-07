<?php

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ns\Dns\Filament\Resources\DnsProviderResource;
use Ns\Dns\Filament\Resources\DnsRecordResource;
use Ns\Dns\Filament\Resources\DnsZoneResource;
use Ns\Plugins\Filament\Resources\InstalledPluginResource;
use Ns\Setup\Filament\Resources\SetupJobResource;
use Ns\Setup\Filament\Resources\SetupTemplateResource;
use Ns\Ssh\Filament\Resources\SshConnectionResource;
use Ns\Ssh\Filament\Resources\SshHostResource;
use Ns\Ssh\Filament\Resources\SshKeyResource;

uses(RefreshDatabase::class);

/**
 * Test that all Filament resources can load without throwing class not found errors
 * This helps catch Filament 4 compatibility issues early
 */
beforeEach(function () {
    // Create a test user for authentication
    $this->user = User::factory()->create();

    // Set current panel to admin
    Filament::setCurrentPanel('admin');
});

describe('Filament Resource Schema Loading', function () {
    it('can load SetupTemplateResource without errors', function () {
        $this->actingAs($this->user);

        // Test that the resource table can be instantiated
        expect(function () {
            $table = SetupTemplateResource::table(new \Filament\Tables\Table(new \Filament\Resources\Resource));

            return $table;
        })->not->toThrow(\Exception::class);

        // Test that the resource form can be instantiated
        expect(function () {
            $form = SetupTemplateResource::form(new \Filament\Schemas\Schema);

            return $form;
        })->not->toThrow(\Exception::class);
    });

    it('can load SetupJobResource without errors', function () {
        $this->actingAs($this->user);

        expect(function () {
            $table = SetupJobResource::table(new \Filament\Tables\Table(new \Filament\Resources\Resource));

            return $table;
        })->not->toThrow(\Exception::class);

        expect(function () {
            $form = SetupJobResource::form(new \Filament\Schemas\Schema);

            return $form;
        })->not->toThrow(\Exception::class);
    });

    it('can load SSH Manager resources without errors', function () {
        $this->actingAs($this->user);

        $resources = [
            SshHostResource::class,
            SshKeyResource::class,
            SshConnectionResource::class,
        ];

        foreach ($resources as $resourceClass) {
            expect(function () use ($resourceClass) {
                $table = $resourceClass::table(new \Filament\Tables\Table(new \Filament\Resources\Resource));

                return $table;
            })->not->toThrow(\Exception::class, "Failed to load table for {$resourceClass}");

            expect(function () use ($resourceClass) {
                $form = $resourceClass::form(new \Filament\Schemas\Schema);

                return $form;
            })->not->toThrow(\Exception::class, "Failed to load form for {$resourceClass}");
        }
    });

    it('can load DNS Manager resources without errors', function () {
        $this->actingAs($this->user);

        $resources = [
            DnsProviderResource::class,
            DnsZoneResource::class,
            DnsRecordResource::class,
        ];

        foreach ($resources as $resourceClass) {
            expect(function () use ($resourceClass) {
                $table = $resourceClass::table(new \Filament\Tables\Table(new \Filament\Resources\Resource));

                return $table;
            })->not->toThrow(\Exception::class, "Failed to load table for {$resourceClass}");

            expect(function () use ($resourceClass) {
                $form = $resourceClass::form(new \Filament\Schemas\Schema);

                return $form;
            })->not->toThrow(\Exception::class, "Failed to load form for {$resourceClass}");
        }
    });

    it('can load InstalledPluginResource without errors', function () {
        $this->actingAs($this->user);

        expect(function () {
            $table = InstalledPluginResource::table(new \Filament\Tables\Table(new \Filament\Resources\Resource));

            return $table;
        })->not->toThrow(\Exception::class);

        expect(function () {
            $form = InstalledPluginResource::form(new \Filament\Schemas\Schema);

            return $form;
        })->not->toThrow(\Exception::class);
    });
});

describe('Filament Resource Page Navigation', function () {
    it('can verify resource URLs are configured correctly', function () {
        $this->actingAs($this->user);

        // Instead of accessing actual pages, just verify resources define proper URLs
        expect(SetupTemplateResource::getUrl())->toContain('setup-templates');
        expect(SetupJobResource::getUrl())->toContain('setup-jobs');
        expect(SshHostResource::getUrl())->toContain('ssh-hosts');
        expect(SshKeyResource::getUrl())->toContain('ssh-keys');
        expect(SshConnectionResource::getUrl())->toContain('ssh-connections');
        expect(DnsProviderResource::getUrl())->toContain('dns-providers');
        expect(DnsZoneResource::getUrl())->toContain('dns-zones');
        expect(DnsRecordResource::getUrl())->toContain('dns-records');
        expect(InstalledPluginResource::getUrl())->toContain('installed-plugins');
    });

    it('can verify resource navigation items exist', function () {
        $this->actingAs($this->user);

        // Test that navigation items are properly configured
        expect(SetupTemplateResource::getNavigationLabel())->not->toBeEmpty();
        expect(SetupJobResource::getNavigationLabel())->not->toBeEmpty();
        expect(SshHostResource::getNavigationLabel())->not->toBeEmpty();
        expect(DnsProviderResource::getNavigationLabel())->not->toBeEmpty();
        expect(InstalledPluginResource::getNavigationLabel())->not->toBeEmpty();
    });
});

describe('Filament Component Compatibility', function () {
    it('verifies all required Filament 4 components are imported correctly', function () {
        // Check that SetupTemplateResource imports are correct
        $setupTemplateContent = file_get_contents(base_path('packages/ns-setup/src/Filament/Resources/SetupTemplateResource.php'));

        // Should use Filament\Schemas\Components\Grid, not Filament\Forms\Components\Grid
        expect($setupTemplateContent)->toContain('use Filament\Schemas\Components\Grid;');
        expect($setupTemplateContent)->not->toContain('use Filament\Forms\Components\Grid;');

        // Should use Filament\Schemas\Components\Section, not Filament\Forms\Components\Section
        expect($setupTemplateContent)->toContain('use Filament\Schemas\Components\Section;');
        expect($setupTemplateContent)->not->toContain('use Filament\Forms\Components\Section;');

        // Check that SetupJobResource imports are correct
        $setupJobContent = file_get_contents(base_path('packages/ns-setup/src/Filament/Resources/SetupJobResource.php'));

        // Should use Filament\Schemas\Components\Grid, not Filament\Forms\Components\Grid
        expect($setupJobContent)->toContain('use Filament\Schemas\Components\Grid;');
        expect($setupJobContent)->not->toContain('use Filament\Forms\Components\Grid;');

        // Should NOT use deprecated components
        expect($setupJobContent)->not->toContain('use Filament\Tables\Columns\BadgeColumn;');
        expect($setupJobContent)->not->toContain('use Filament\Tables\Columns\ProgressColumn;');

        // Should use correct Heroicon naming
        expect($setupJobContent)->toContain('Heroicon::OutlinedClipboardDocumentList');
        expect($setupJobContent)->not->toContain('Heroicon::OutlineClipboardDocumentList');

        expect($setupTemplateContent)->toContain('Heroicon::OutlinedServer');
        expect($setupTemplateContent)->not->toContain('Heroicon::OutlineServer');
    });

    it('verifies TextColumn badge usage is correct for Filament 4', function () {
        $setupJobContent = file_get_contents(base_path('packages/ns-setup/src/Filament/Resources/SetupJobResource.php'));

        // Should use TextColumn with badge() method
        expect($setupJobContent)->toContain('TextColumn::make(\'status\')');
        expect($setupJobContent)->toContain('->badge()');

        // Should use proper color closure syntax
        expect($setupJobContent)->toContain('->color(fn (string $state): string => match ($state)');
    });
});
