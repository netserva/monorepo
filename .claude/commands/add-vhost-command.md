# Add NetServa CLI Command

Generate a complete NetServa 3.0 CLI command following conventions.

## Arguments

$ARGUMENTS should contain: `<command-name>` (e.g., `addvhost`, `chperms`, `shvconf`)

## Task

Create a complete NetServa CLI command for **$ARGUMENTS**:

### 1. Command File

Create `app/Console/Commands/${PascalCase}Command.php`:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Cli\Services\RemoteExecutionService;
use App\Models\FleetVHost;
use App\Models\FleetVNode;
use function Laravel\Prompts\{info, error, confirm, progress};

/**
 * ${CommandName} Command
 *
 * Description: [What this command does]
 *
 * SIGNATURE:
 * ${command-name} <vnode> <vhost> [options]
 *
 * EXAMPLES:
 * php artisan ${command-name} markc example.com
 * php artisan ${command-name} markc example.com --force
 *
 * Business Rules:
 * - BR-###: [Relevant business rule]
 *
 * See: docs/[relevant-doc].md
 */
class ${PascalCase}Command extends Command
{
    protected $signature = '${command-name}
                            {vnode : SSH host/VNode identifier}
                            {vhost : Domain name}
                            {--option= : Optional parameter}
                            {--force : Skip confirmations}';

    protected $description = 'Brief description of command';

    public function __construct(
        protected RemoteExecutionService $remoteExecution
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $vnodeName = $this->argument('vnode');
        $domain = $this->argument('vhost');

        // Get models from database
        $vnode = FleetVNode::where('name', $vnodeName)->first();
        if (!$vnode) {
            error("VNode not found: {$vnodeName}");
            info("Run: php artisan fleet:discover --vnode={$vnodeName}");
            return self::FAILURE;
        }

        $vhost = FleetVHost::where('domain', $domain)
            ->where('fleet_vnode_id', $vnode->id)
            ->first();
        if (!$vhost) {
            error("VHost not found: {$domain} on {$vnodeName}");
            return self::FAILURE;
        }

        // Confirmation (unless --force)
        if (!$this->option('force')) {
            if (!confirm("Perform action on {$domain}?")) {
                info('Cancelled');
                return self::SUCCESS;
            }
        }

        // Execute operation
        try {
            $result = $this->performOperation($vnode, $vhost);

            if ($result['success']) {
                info("✅ {$result['output']}");
                return self::SUCCESS;
            } else {
                error("❌ {$result['error']}");
                return self::FAILURE;
            }
        } catch (\Exception $e) {
            error("Error: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    protected function performOperation(FleetVNode $vnode, FleetVHost $vhost): array
    {
        return $this->remoteExecution->executeScriptWithVhost(
            host: $vnode->name,
            vhost: $vhost,
            script: <<<'BASH'
                #!/bin/bash
                set -euo pipefail

                # Environment vars auto-available: $WPATH, $UUSER, etc.

                # Your bash logic here
                echo "Success: $VHOST"
                BASH,
            asRoot: true  // or false depending on operation
        );
    }
}
```

### 2. Command Conventions

✅ **Signature Pattern:**
```
command-name {vnode : VNode name} {vhost : Domain} [options]
```

✅ **NO flags for vnode/vhost:**
```
❌ Wrong: addvhost --vnode=markc --vhost=example.com
✅ Right: addvhost markc example.com
```

✅ **Laravel Prompts:**
- Use `info()` for messages
- Use `error()` for errors
- Use `confirm()` for confirmations
- Use `progress()` for long operations

✅ **Database-First:**
- Get vnode from database: `FleetVNode::where('name', $vnodeName)`
- Get vhost from database: `FleetVHost::where('domain', $domain)`
- Access vconfs: `$vhost->vconf('WPATH')`

✅ **SSH Execution:**
- ALWAYS use `RemoteExecutionService::executeScript()` heredoc pattern
- Use `executeScriptWithVhost()` for vhost env vars
- NO string concatenation or escaped commands

### 3. Tests

Create `tests/Feature/Console/${PascalCase}CommandTest.php`:

```php
<?php

use App\Models\FleetVHost;
use App\Models\FleetVNode;
use NetServa\Cli\Services\RemoteExecutionService;

beforeEach(function () {
    RemoteExecutionService::fake([
        'markc' => RemoteExecutionService::fakeSuccess('Success'),
    ]);

    $this->vnode = FleetVNode::factory()->create(['name' => 'markc']);
    $this->vhost = FleetVHost::factory()->create([
        'fleet_vnode_id' => $this->vnode->id,
        'domain' => 'example.com'
    ]);
});

test('executes command successfully', function () {
    $this->artisan('${command-name}', [
        'vnode' => 'markc',
        'vhost' => 'example.com',
        '--force' => true,
    ])
        ->assertSuccessful()
        ->expectsOutput('✅');
});

test('fails when vnode not found', function () {
    $this->artisan('${command-name}', [
        'vnode' => 'nonexistent',
        'vhost' => 'example.com',
        '--force' => true,
    ])
        ->assertFailed()
        ->expectsOutput('VNode not found');
});

test('fails when vhost not found', function () {
    $this->artisan('${command-name}', [
        'vnode' => 'markc',
        'vhost' => 'nonexistent.com',
        '--force' => true,
    ])
        ->assertFailed()
        ->expectsOutput('VHost not found');
});
```

### 4. Documentation

Add to relevant docs:
- Update command reference in docs if needed
- Add examples to README
- Document any new business rules
- Reference in CLAUDE.md if critical pattern

### 5. Verification

After generating:
```bash
# Run tests
php artisan test --filter=${PascalCase}Command

# Test command manually
php artisan ${command-name} markc example.com --force

# Check command is registered
php artisan list | grep ${command-name}

# Run Pint
vendor/bin/pint app/Console/Commands/${PascalCase}Command.php
```

## Requirements

- ✅ Positional args: `<vnode> <vhost>` (NO --flags)
- ✅ Database-first: Get vnode/vhost from database
- ✅ SSH via executeScript() heredoc pattern
- ✅ Laravel Prompts for beautiful CLI
- ✅ Comprehensive Pest tests
- ✅ Business rule enforcement (BR-###)
- ✅ Error handling and validation

## Example Usage

```
claude /add-vhost-command chperms
```

This will create `app/Console/Commands/ChpermsCommand.php` with tests.

## Notes

- Reference existing commands in `app/Console/Commands/` for patterns
- Use `search-docs` for Laravel Prompts features
- All commands auto-register (Laravel 12 convention)
- Follow NetServa naming: `addvhost`, `chvhost`, `delvhost`, `shvhost` pattern
