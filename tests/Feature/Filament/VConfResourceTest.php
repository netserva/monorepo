<?php

use App\Filament\Resources\NetServa\Cli\Models\VConfs\Pages\CreateVConf;
use App\Filament\Resources\NetServa\Cli\Models\VConfs\Pages\EditVConf;
use App\Filament\Resources\NetServa\Cli\Models\VConfs\Pages\ListVConfs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use NetServa\Cli\Models\VConf;
use NetServa\Fleet\Models\FleetVHost;
use NetServa\Fleet\Models\FleetVNode;
use NetServa\Fleet\Models\FleetVSite;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create test data
    $vsite = FleetVSite::create([
        'name' => 'Test Site',
        'domain' => 'testsite.local',
        'status' => 'active',
    ]);

    $vnode = FleetVNode::create([
        'vsite_id' => $vsite->id,
        'name' => 'testnode',
        'description' => 'Test Node',
        'ip_address' => '192.168.1.100',
        'status' => 'active',
    ]);

    $this->vhost = FleetVHost::create([
        'domain' => 'test.example.com',
        'vnode_id' => $vnode->id,
        'status' => 'active',
        'is_active' => true,
    ]);
});

describe('VConf Resource List Page', function () {
    it('can list vconfs', function () {
        // Create test VConfs
        VConf::create([
            'fleet_vhost_id' => $this->vhost->id,
            'name' => 'WPATH',
            'value' => '/srv/test.example.com/web',
            'category' => 'paths',
            'is_sensitive' => false,
        ]);

        VConf::create([
            'fleet_vhost_id' => $this->vhost->id,
            'name' => 'DPASS',
            'value' => 'secret123',
            'category' => 'passwords',
            'is_sensitive' => true,
        ]);

        Livewire::test(ListVConfs::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords(VConf::all())
            ->assertSeeInOrder(['DPASS', 'WPATH']); // Alphabetical order
    });

    it('can filter by category', function () {
        VConf::create([
            'fleet_vhost_id' => $this->vhost->id,
            'name' => 'WPATH',
            'value' => '/srv/test.example.com/web',
            'category' => 'paths',
        ]);

        VConf::create([
            'fleet_vhost_id' => $this->vhost->id,
            'name' => 'DPASS',
            'value' => 'secret123',
            'category' => 'passwords',
        ]);

        Livewire::test(ListVConfs::class)
            ->filterTable('category', ['passwords'])
            ->assertCanSeeTableRecords(VConf::where('category', 'passwords')->get())
            ->assertCanNotSeeTableRecords(VConf::where('category', 'paths')->get());
    });

    it('masks sensitive values in table', function () {
        VConf::create([
            'fleet_vhost_id' => $this->vhost->id,
            'name' => 'DPASS',
            'value' => 'secret123',
            'category' => 'passwords',
            'is_sensitive' => true,
        ]);

        Livewire::test(ListVConfs::class)
            ->assertSuccessful()
            ->assertDontSee('secret123')
            ->assertSee('••••••••••••');
    });
});

describe('VConf Resource Create Page', function () {
    it('can create a vconf', function () {
        Livewire::test(CreateVConf::class)
            ->fillForm([
                'fleet_vhost_id' => $this->vhost->id,
                'name' => 'WPATH',
                'value' => '/srv/test.example.com/web',
                'category' => 'paths',
                'is_sensitive' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(VConf::where('name', 'WPATH')->exists())->toBeTrue();

        $vconf = VConf::where('name', 'WPATH')->first();
        expect($vconf->value)->toBe('/srv/test.example.com/web')
            ->and($vconf->category)->toBe('paths')
            ->and($vconf->is_sensitive)->toBeFalse();
    });

    it('validates 5-char naming rule', function () {
        Livewire::test(CreateVConf::class)
            ->fillForm([
                'fleet_vhost_id' => $this->vhost->id,
                'name' => 'TOOLONG',
                'value' => '/some/path',
            ])
            ->call('create')
            ->assertHasFormErrors(['name']);
    });

    it('validates uppercase only', function () {
        Livewire::test(CreateVConf::class)
            ->fillForm([
                'fleet_vhost_id' => $this->vhost->id,
                'name' => 'path',
                'value' => '/some/path',
            ])
            ->call('create')
            ->assertHasFormErrors(['name']);
    });

    it('auto-categorizes based on name', function () {
        Livewire::test(CreateVConf::class)
            ->fillForm([
                'fleet_vhost_id' => $this->vhost->id,
                'name' => 'WPATH',
                'value' => '/srv/test/web',
            ])
            ->assertFormSet([
                'category' => 'paths',
            ]);
    });

    it('auto-detects sensitive variables', function () {
        Livewire::test(CreateVConf::class)
            ->fillForm([
                'fleet_vhost_id' => $this->vhost->id,
                'name' => 'DPASS',
                'value' => 'secret',
            ])
            ->assertFormSet([
                'is_sensitive' => true,
            ]);
    });
});

describe('VConf Resource Edit Page', function () {
    it('can edit a vconf', function () {
        $vconf = VConf::create([
            'fleet_vhost_id' => $this->vhost->id,
            'name' => 'WPATH',
            'value' => '/srv/old/path',
            'category' => 'paths',
        ]);

        Livewire::test(EditVConf::class, ['record' => $vconf->id])
            ->fillForm([
                'value' => '/srv/new/path',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($vconf->fresh()->value)->toBe('/srv/new/path');
    });

    it('can delete a vconf', function () {
        $vconf = VConf::create([
            'fleet_vhost_id' => $this->vhost->id,
            'name' => 'WPATH',
            'value' => '/srv/test/web',
            'category' => 'paths',
        ]);

        Livewire::test(EditVConf::class, ['record' => $vconf->id])
            ->callAction('delete');

        expect(VConf::find($vconf->id))->toBeNull();
    });
});

describe('VConf Model Integration', function () {
    it('enforces unique vhost + name combination', function () {
        VConf::create([
            'fleet_vhost_id' => $this->vhost->id,
            'name' => 'WPATH',
            'value' => '/srv/test/web',
        ]);

        expect(fn () => VConf::create([
            'fleet_vhost_id' => $this->vhost->id,
            'name' => 'WPATH',
            'value' => '/srv/another/web',
        ]))->toThrow(Exception::class);
    });

    it('allows same name for different vhosts', function () {
        $vsite2 = FleetVSite::create([
            'name' => 'Test Site 2',
            'domain' => 'testsite2.local',
            'status' => 'active',
        ]);

        $vnode2 = FleetVNode::create([
            'vsite_id' => $vsite2->id,
            'name' => 'testnode2',
            'description' => 'Test Node 2',
            'ip_address' => '192.168.1.101',
            'status' => 'active',
        ]);

        $vhost2 = FleetVHost::create([
            'domain' => 'test2.example.com',
            'vnode_id' => $vnode2->id,
            'status' => 'active',
            'is_active' => true,
        ]);

        VConf::create([
            'fleet_vhost_id' => $this->vhost->id,
            'name' => 'WPATH',
            'value' => '/srv/test/web',
        ]);

        VConf::create([
            'fleet_vhost_id' => $vhost2->id,
            'name' => 'WPATH',
            'value' => '/srv/test2/web',
        ]);

        expect(VConf::count())->toBe(2);
    });
});
