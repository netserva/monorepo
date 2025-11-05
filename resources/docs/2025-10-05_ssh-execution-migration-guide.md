# SSH Execution Migration Guide

This guide explains how to migrate NetServa commands from the old SSH execution pattern to the new heredoc-based architecture.

## Why Migrate?

The old approach had several problems:
1. **String concatenation** with complex quoting led to injection vulnerabilities
2. **Multiple separate exec() calls** instead of atomic operations
3. **Incorrect use of escapeshellarg()** on entire command strings
4. **Poor error handling** with exit codes not properly preserved

## New Architecture

### Key Components

1. **`RemoteExecutionService::executeScript()`** - Executes heredoc-based bash scripts
2. **`RemoteExecutionService::executeScriptWithVhost()`** - Auto-injects FleetVHost environment variables
3. **`wrapScriptWithSafety()`** - Adds `set -euo pipefail` automatically

### Migration Pattern

#### ❌ OLD PATTERN (Do Not Use)

```php
// Bad: Multiple separate commands
protected function generatePermissionCommands(FleetVHost $vhost): array
{
    $commands = [];
    $UUSER = $vhost->getEnvVar('UUSER') ?? 'www-data';
    $WPATH = $vhost->getEnvVar('WPATH') ?? '';

    if ($WPATH) {
        $commands[] = "chown -R {$UUSER}:{$WUGID} {$WPATH}";
        $commands[] = "find {$WPATH} -type d -exec chmod 755 {} \\;";
    }
    return $commands;
}

// Bad: Loop executing each command separately
protected function executePermissionCommands(string $VNODE, array $commands): array
{
    foreach ($commands as $command) {
        $result = $this->remoteExecution->executeAsRoot($VNODE, $command);
        // ...
    }
}
```

#### ✅ NEW PATTERN (Correct)

```php
// Good: Single heredoc script
protected function buildPermissionScript(): string
{
    return <<<'BASH'
#!/bin/bash
set -euo pipefail

# Arguments from caller
upath=$1
wpath=$2
uuser=$3
wugid=$4

# User home directory
if [ -n "$upath" ] && [ -d "$upath" ]; then
    chown -R "$uuser:$wugid" "$upath"
    chmod 755 "$upath"
    echo "✓ Fixed user home: $upath"
fi

# Web directory permissions
if [ -n "$wpath" ] && [ -d "$wpath" ]; then
    chown -R "$uuser:$wugid" "$wpath"
    find "$wpath" -type d -exec chmod 755 {} \;
    find "$wpath" -type f -exec chmod 644 {} \;
    echo "✓ Fixed web directory: $wpath"
fi

echo "Permissions fixed successfully"
BASH;
}

// Good: Pass variables as arguments
protected function buildScriptArguments(FleetVHost $vhost): array
{
    return [
        $vhost->getEnvVar('UPATH') ?? '',
        $vhost->getEnvVar('WPATH') ?? '',
        $vhost->getEnvVar('UUSER') ?? 'www-data',
        $vhost->getEnvVar('WUGID') ?? 'www-data',
    ];
}

// Good: Single execution call
$result = $this->remoteExecution->executeScript(
    host: $VNODE,
    script: $this->buildPermissionScript(),
    args: $this->buildScriptArguments($vhost),
    asRoot: true
);
```

## Step-by-Step Migration Guide

### Step 1: Update Class Documentation

```php
/**
 * Change Permissions Command
 *
 * Follows NetServa CRUD pattern: chperms (critical NetServa operation)
 * Usage: chperms <vnode> <vhost>
 *
 * DATABASE-FIRST: Uses FleetVHost model (environment_vars JSON column)
 * SSH EXECUTION: Uses heredoc-based executeScript() for safe remote execution  ← ADD THIS
 */
```

### Step 2: Replace generateCommands() with buildScript()

**Before:**
```php
protected function generatePermissionCommands(FleetVHost $vhost): array
{
    $commands = [];
    // ... string concatenation
    return $commands;
}
```

**After:**
```php
protected function buildPermissionScript(): string
{
    return <<<'BASH'
#!/bin/bash
set -euo pipefail

# Your bash script here
BASH;
}

protected function buildScriptArguments(FleetVHost $vhost): array
{
    return [
        $vhost->getEnvVar('UPATH') ?? '',
        // ... all required variables
    ];
}
```

### Step 3: Replace executeCommands() with executeScript()

**Before:**
```php
foreach ($commands as $command) {
    $result = $this->remoteExecution->executeAsRoot($VNODE, $command);
}
```

**After:**
```php
$result = $this->remoteExecution->executeScript(
    host: $VNODE,
    script: $this->buildPermissionScript(),
    args: $this->buildScriptArguments($vhost),
    asRoot: true
);
```

### Step 4: Update Output Handling

**Before:**
```php
if ($results['success']) {
    foreach ($results['details'] as $detail) {
        $this->line("   <fg=green>✓</> {$detail}");
    }
}
```

**After:**
```php
if ($result['success']) {
    if (!empty($result['output'])) {
        $this->line('');
        $this->line('<fg=blue>📋 Output:</>');
        foreach (explode("\n", trim($result['output'])) as $line) {
            if (!empty($line)) {
                $this->line("   {$line}");
            }
        }
    }
}
```

## Bash Script Guidelines

### 1. Use Proper Quoting

```bash
# ✅ Good - quoted variables
chown -R "$uuser:$wugid" "$wpath"

# ❌ Bad - unquoted variables
chown -R $uuser:$wugid $wpath
```

### 2. Check Existence Before Operations

```bash
# ✅ Good - check directory exists
if [ -n "$wpath" ] && [ -d "$wpath" ]; then
    chmod 755 "$wpath"
fi

# ❌ Bad - assume directory exists
chmod 755 "$wpath"
```

### 3. Provide Feedback

```bash
# ✅ Good - echo progress
chown -R "$uuser:$wugid" "$wpath"
echo "✓ Fixed web directory: $wpath"

# ❌ Bad - silent execution
chown -R "$uuser:$wugid" "$wpath"
```

### 4. Use Arguments, Not Variable Interpolation

```bash
# ✅ Good - receive as arguments
upath=$1
wpath=$2

# ❌ Bad - embed PHP variables (vulnerable to injection)
# Don't use: "export WPATH={$wpath}" in the heredoc
```

## Testing Updates

Update tests to mock `executeScript()` instead of old service methods:

```php
$remoteExecutionService = Mockery::mock(RemoteExecutionService::class);
$this->app->instance(RemoteExecutionService::class, $remoteExecutionService);

$remoteExecutionService->shouldReceive('executeScript')
    ->once()
    ->withArgs(function ($host, $script, $args, $asRoot) {
        return $host === 'markc'
            && str_contains($script, 'set -euo pipefail')
            && str_contains($script, 'chown -R')
            && $asRoot === true
            && $args[0] === '/srv/example.com';  // Verify arguments
    })
    ->andReturn([
        'success' => true,
        'output' => "✓ Fixed user home: /srv/example.com",
        'return_code' => 0,
    ]);
```

## Commands to Migrate

### Already Migrated
- ✅ **ChpermsCommand** - Fix permissions (reference implementation)

### Pending Migration
- ⏳ **AddVhostCommand** - Create virtual host
- ⏳ **ChvhostCommand** - Update virtual host
- ⏳ **DelvhostCommand** - Delete virtual host
- ⏳ **Other commands** using RemoteExecutionService

## Benefits of New Architecture

### ✅ Security
- No shell injection vulnerabilities
- Proper quoting handled by heredoc
- Arguments passed safely via SSH stdin

### ✅ Reliability
- Exit codes preserved correctly
- `set -euo pipefail` catches all errors
- Atomic operations (one SSH connection)

### ✅ Maintainability
- Scripts readable like normal bash
- Syntax highlighting works in editors
- Easy to test locally (copy heredoc to file)

### ✅ Performance
- Single SSH connection instead of multiple
- No overhead from repeated authentication
- Faster execution for multi-step operations

## Common Pitfalls

### 1. Don't Use Variable Interpolation in Heredoc

```php
// ❌ WRONG - PHP variables interpolated (vulnerable)
$script = <<<BASH
wpath="$wpath"
BASH;

// ✅ CORRECT - quoted delimiter prevents interpolation
$script = <<<'BASH'
wpath=$1  # Received as argument
BASH;
```

### 2. Don't Forget the Quoted Delimiter

```php
// ❌ WRONG - no quotes means PHP will interpolate
return <<<BASH
BASH;

// ✅ CORRECT - quotes prevent interpolation
return <<<'BASH'
BASH;
```

### 3. Don't Mix Old and New Patterns

```php
// ❌ WRONG - mixing approaches
$this->remoteExecution->executeAsRoot($VNODE, $command);  // Old
$this->remoteExecution->executeScript(...);               // New

// ✅ CORRECT - use new pattern exclusively
$this->remoteExecution->executeScript(...);  // New only
```

## Reference Documentation

- [SSH_EXECUTION_ARCHITECTURE.md](./SSH_EXECUTION_ARCHITECTURE.md) - Detailed architecture guide
- [CLAUDE.md](../CLAUDE.md) - Project-wide conventions
- [ChpermsCommand.php](../packages/netserva-cli/src/Console/Commands/ChpermsCommand.php) - Reference implementation

---

**Last Updated:** 2025-10-05
**Migrated Commands:** 1/10+
**Reference Implementation:** ChpermsCommand.php
