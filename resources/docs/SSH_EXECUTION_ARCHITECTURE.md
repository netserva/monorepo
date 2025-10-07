# NetServa SSH Execution Architecture

## Design Principles

1. **Heredoc-First**: All complex scripts use heredoc with quoted delimiter (`<< 'EOF'`)
2. **Clean Variable Separation**: Local variables passed as script arguments
3. **Error Handling**: Proper `set -euo pipefail` and exit code preservation
4. **Logging**: Comprehensive logging of all remote executions
5. **Testability**: Easy to debug with `--dry-run` mode

## Service Methods

### 1. `executeScript()` - Heredoc-Based Execution (RECOMMENDED)

**Use this for ALL complex operations:**

```php
$service->executeScript(
    host: 'markc',
    script: <<<'BASH'
        #!/bin/bash
        set -euo pipefail

        # Arguments from caller
        vhost_domain=$1
        web_path=$2
        user=$3

        # Remote logic with proper quoting
        if [ ! -d "$web_path" ]; then
            mkdir -p "$web_path"
            chown "$user:www-data" "$web_path"
        fi

        # Complex commands work naturally
        find "$web_path" -type d -exec chmod 755 {} \;
        find "$web_path" -type f -exec chmod 644 {} \;

        echo "Permissions fixed for: $vhost_domain"
        BASH,
    args: [$vhost->domain, $vhost->getEnvVar('WPATH'), $vhost->getEnvVar('UUSER')],
    asRoot: true
);
```

### 2. `executeCommand()` - Simple Single Commands

**Use for simple one-liners only:**

```php
$service->executeCommand(
    host: 'markc',
    command: 'systemctl restart nginx',
    asRoot: true
);
```

### 3. `executeWithEnv()` - Script with Environment Variables

**Use when you need environment vars from FleetVHost:**

```php
$service->executeWithEnv(
    host: 'markc',
    vhost: $vhost,  // FleetVHost model
    script: <<<'BASH'
        #!/bin/bash
        set -euo pipefail

        # Environment vars automatically loaded: $UPATH, $WPATH, $MPATH, etc.
        echo "Web path: $WPATH"
        cd "$WPATH"

        # Your logic here
        BASH
);
```

## Implementation Examples

### Example 1: Fix Permissions (chperms command)

```php
// ✅ CORRECT - Heredoc approach
$result = $this->remoteExecution->executeScript(
    host: $VNODE,
    script: <<<'BASH'
        #!/bin/bash
        set -euo pipefail

        upath=$1
        wpath=$2
        mpath=$3
        uuser=$4
        wugid=$5

        # User home directory
        if [ -d "$upath" ]; then
            chown -R "$uuser:$wugid" "$upath"
            chmod 755 "$upath"
        fi

        # Web directory
        if [ -d "$wpath" ]; then
            chown -R "$uuser:$wugid" "$wpath"
            find "$wpath" -type d -exec chmod 755 {} \;
            find "$wpath" -type f -exec chmod 644 {} \;
        fi

        # Mail directory
        if [ -d "$mpath" ]; then
            chown -R "$uuser:$wugid" "$mpath"
            chmod 750 "$mpath"
        fi

        echo "Permissions fixed successfully"
        BASH,
    args: [
        $vhost->getEnvVar('UPATH'),
        $vhost->getEnvVar('WPATH'),
        $vhost->getEnvVar('MPATH'),
        $vhost->getEnvVar('UUSER'),
        $vhost->getEnvVar('WUGID'),
    ],
    asRoot: true
);
```

### Example 2: Create VHost (addvhost command)

```php
// ✅ CORRECT - Multi-step operations
$result = $this->remoteExecution->executeScript(
    host: $VNODE,
    script: <<<'BASH'
        #!/bin/bash
        set -euo pipefail

        domain=$1
        uid=$2
        user=$3
        web_path=$4

        # Create user
        if ! id "$user" &>/dev/null; then
            useradd -u "$uid" -m -s /bin/bash "$user"
        fi

        # Create directory structure
        mkdir -p "$web_path"/{web,msg,log,tmp}
        chown -R "$user:www-data" "$web_path"

        # Configure nginx
        cat > "/etc/nginx/sites-available/$domain" <<NGINX
        server {
            listen 80;
            server_name $domain;
            root $web_path/web;

            location / {
                try_files \$uri \$uri/ =404;
            }
        }
        NGINX

        ln -sf "/etc/nginx/sites-available/$domain" "/etc/nginx/sites-enabled/$domain"
        nginx -t && systemctl reload nginx

        echo "VHost created: $domain"
        BASH,
    args: [
        $vhost->domain,
        $vhost->getEnvVar('U_UID'),
        $vhost->getEnvVar('UUSER'),
        $vhost->getEnvVar('WPATH'),
    ],
    asRoot: true
);
```

## Benefits

### ✅ Reliability
- Proper quoting handled by heredoc
- No shell injection vulnerabilities
- Exit codes preserved correctly

### ✅ Maintainability
- Scripts are readable, like normal bash
- Syntax highlighting works in editors
- Easy to debug and test locally

### ✅ Flexibility
- Supports any complexity: loops, pipes, functions
- Clean separation of local vs remote variables
- Can include inline heredocs for config files

### ✅ Error Handling
- `set -euo pipefail` catches all errors
- Exit codes bubble up to Laravel
- Comprehensive logging

## Migration Path

1. **Phase 1**: Add new methods to `RemoteExecutionService`
2. **Phase 2**: Update `ChpermsCommand` to use `executeScript()`
3. **Phase 3**: Migrate all other commands
4. **Phase 4**: Deprecate old `executeAsRoot()` method

## Anti-Patterns (DO NOT USE)

```php
// ❌ WRONG - String concatenation with quotes
$command = "cd {$path} && chown {$user} * && chmod 755 *";

// ❌ WRONG - escapeshellarg on entire command
$command = escapeshellarg("find {$path} -type f");

// ❌ WRONG - Double/triple quote escaping
$command = "bash -c \"echo \\\"$var\\\"\"";

// ❌ WRONG - Multiple separate exec() calls
$this->exec('mkdir /tmp/foo');
$this->exec('chown user:group /tmp/foo');
$this->exec('chmod 755 /tmp/foo');
```

## Testing

All SSH execution methods support dry-run mode:

```php
$result = $this->remoteExecution->executeScript(
    host: 'markc',
    script: $bashScript,
    args: $arguments,
    asRoot: true,
    dryRun: true  // Shows what would execute without running
);
```

---

**Summary**: Use heredoc-based `executeScript()` for everything except trivial single commands.
