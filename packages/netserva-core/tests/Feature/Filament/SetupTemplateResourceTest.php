<?php

use Livewire\Livewire;
use NetServa\Core\Filament\Resources\SetupTemplateResource\Pages\CreateSetupTemplate;
use NetServa\Core\Filament\Resources\SetupTemplateResource\Pages\EditSetupTemplate;
use NetServa\Core\Filament\Resources\SetupTemplateResource\Pages\ListSetupTemplates;
use Ns\Setup\Models\SetupTemplate;

uses()
    ->group('feature', 'filament', 'setup-template-resource', 'priority-2');

it('can render setup template list page', function () {
    SetupTemplate::factory()->count(3)->create();

    Livewire::test(ListSetupTemplates::class)
        ->assertSuccessful()
        ->assertSee('Setup Templates')
        ->assertCanSeeTableRecords(SetupTemplate::all());
});

it('can create setup template', function () {
    $newData = [
        'name' => 'test-template',
        'description' => 'Test template description',
        'template_type' => 'server',
        'configuration' => ['nginx' => true, 'php' => '8.4'],
        'is_active' => true,
    ];

    Livewire::test(CreateSetupTemplate::class)
        ->fillForm($newData)
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('setup_templates', [
        'name' => 'test-template',
        'description' => 'Test template description',
        'template_type' => 'server',
        'is_active' => true,
    ]);
});

it('can edit setup template', function () {
    $template = SetupTemplate::factory()->create([
        'name' => 'original-name',
        'description' => 'Original description',
    ]);

    $newData = [
        'name' => 'updated-name',
        'description' => 'Updated description',
        'template_type' => 'application',
    ];

    Livewire::test(EditSetupTemplate::class, ['record' => $template->getRouteKey()])
        ->fillForm($newData)
        ->call('save')
        ->assertHasNoFormErrors();

    expect($template->fresh())
        ->name->toBe('updated-name')
        ->description->toBe('Updated description')
        ->template_type->toBe('application');
});

it('can delete setup template', function () {
    $template = SetupTemplate::factory()->create();

    Livewire::test(ListSetupTemplates::class)
        ->callTableAction('delete', $template);

    $this->assertModelMissing($template);
});

it('validates required fields on create', function () {
    Livewire::test(CreateSetupTemplate::class)
        ->fillForm([
            'name' => '',
            'description' => '',
        ])
        ->call('create')
        ->assertHasFormErrors([
            'name' => 'required',
            'description' => 'required',
        ]);
});

it('validates unique name on create', function () {
    SetupTemplate::factory()->create(['name' => 'existing-template']);

    Livewire::test(CreateSetupTemplate::class)
        ->fillForm([
            'name' => 'existing-template',
            'description' => 'Test description',
            'template_type' => 'server',
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique']);
});

it('can filter templates by type', function () {
    SetupTemplate::factory()->create(['template_type' => 'server', 'name' => 'server-template']);
    SetupTemplate::factory()->create(['template_type' => 'application', 'name' => 'app-template']);

    Livewire::test(ListSetupTemplates::class)
        ->filterTable('template_type', 'server')
        ->assertCanSeeTableRecords(SetupTemplate::where('template_type', 'server')->get())
        ->assertCanNotSeeTableRecords(SetupTemplate::where('template_type', 'application')->get());
});

it('can search templates by name', function () {
    SetupTemplate::factory()->create(['name' => 'nginx-setup']);
    SetupTemplate::factory()->create(['name' => 'mysql-setup']);

    Livewire::test(ListSetupTemplates::class)
        ->searchTable('nginx')
        ->assertCanSeeTableRecords(SetupTemplate::where('name', 'like', '%nginx%')->get())
        ->assertCanNotSeeTableRecords(SetupTemplate::where('name', 'like', '%mysql%')->get());
});

it('can sort templates by name', function () {
    SetupTemplate::factory()->create(['name' => 'z-template']);
    SetupTemplate::factory()->create(['name' => 'a-template']);

    Livewire::test(ListSetupTemplates::class)
        ->sortTable('name')
        ->assertCanSeeTableRecords(
            SetupTemplate::orderBy('name')->get(),
            inOrder: true
        );
});

it('can bulk delete templates', function () {
    $templates = SetupTemplate::factory()->count(3)->create();

    Livewire::test(ListSetupTemplates::class)
        ->callTableBulkAction('delete', $templates);

    foreach ($templates as $template) {
        $this->assertModelMissing($template);
    }
});

it('shows template configuration as json', function () {
    $template = SetupTemplate::factory()->create([
        'configuration' => ['nginx' => true, 'php_version' => '8.4'],
    ]);

    Livewire::test(ListSetupTemplates::class)
        ->assertTableColumnFormattedStateSet('configuration', $template->id, 'nginx: true, php_version: 8.4');
});

it('can toggle template active status', function () {
    $template = SetupTemplate::factory()->create(['is_active' => true]);

    Livewire::test(ListSetupTemplates::class)
        ->callTableAction('toggle_active', $template);

    expect($template->fresh()->is_active)->toBeFalse();
});

it('validates json configuration format', function () {
    Livewire::test(CreateSetupTemplate::class)
        ->fillForm([
            'name' => 'test-template',
            'description' => 'Test description',
            'configuration' => 'invalid-json',
        ])
        ->call('create')
        ->assertHasFormErrors(['configuration' => 'json']);
});
