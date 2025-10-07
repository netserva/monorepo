# NetServa CLI Ergonomic Usage Guide

## 🎯 Problem Solved: Command Line Bloat Elimination

**Before (Verbose):**
```bash
php artisan ns vhost add test.motd.com --shost=motd
```

**After (Natural):**
```bash
addvhost test.motd.com
```

## 🚀 Multiple Usage Patterns

### 1. **Environment Variable Pattern** (Recommended)
```bash
export VNODE=motd
addvhost test.motd.com
addvhost api.motd.com
listvhosts
```

### 2. **Context Management Pattern**
```bash
use-server motd
addvhost test.motd.com
addvhost api.motd.com
listvhosts
clear-context
```

### 3. **One-Shot Pattern**
```bash
with-server motd addvhost test.motd.com
```

### 4. **Traditional Flag Pattern**
```bash
addvhost test.motd.com --shost=motd
```

## 🔧 Setup Instructions

### 1. **Install Aliases**
```bash
cd ~/.ns/www/packages/netserva-cli
./bin/setup-ns-aliases
```

### 2. **Reload Shell**
```bash
source ~/.bashrc
# Or restart terminal
```

### 3. **Test Integration**
```bash
ns-help                    # Show available commands
ns-context                 # Show current context
use-server motd           # Set server context
addvhost test.motd.com    # Add vhost (no --shost needed!)
```

## 📋 Available Commands

### **VHost Management** (exact original names)
```bash
addvhost <domain>         # Add virtual host
delvhost <domain>         # Delete virtual host
listvhosts               # List all virtual hosts
```

### **Context Management** (new Laravel features)
```bash
use-server <shost>       # Set default server
clear-context           # Clear server context
show-context           # Show current context
```

### **SSH & Server**
```bash
sshtest                 # Test SSH connections
sshlist                 # List SSH hosts
```

### **SSL Management**
```bash
addssl <domain>         # Add SSL certificate
renewssl <domain>       # Renew SSL certificate
listssl                 # List SSL certificates
```

### **Database Operations**
```bash
adddb <name>           # Add database
deldb <name>           # Delete database
listdb                 # List databases
```

## 🎯 Command Comparison

| Task | Original Bash | New Laravel | Improvement |
|------|---------------|-------------|-------------|
| Add VHost | `ssh motd; addvhost test.motd.com` | `export VNODE=motd; addvhost test.motd.com` | **Same length!** |
| With Context | `ssh motd; addvhost test.motd.com` | `use-server motd; addvhost test.motd.com` | **Better context** |
| Multiple VHosts | `ssh motd; addvhost test1.com; addvhost test2.com` | `use-server motd; addvhost test1.com; addvhost test2.com` | **Much shorter** |

## 🔥 Advanced Usage

### **Batch Operations**
```bash
# Set context once, run multiple commands
use-server motd
addvhost test1.motd.com
addvhost test2.motd.com
addssl test1.motd.com
listvhosts
```

### **Environment Context**
```bash
# Set environment, no context switching needed
export VNODE=motd
export VHOST=primary.motd.com

addvhost $VHOST
addssl $VHOST
backup $VHOST
```

### **One-Shot Operations**
```bash
# No context pollution
with-server motd addvhost temp.motd.com
with-server nsorg addvhost temp.netserva.org
```

## 🧠 Smart Features

### **Environment Variable Hierarchy**
1. **Command arguments** (highest priority)
2. **Environment variables** (`VNODE`, `VHOST`)
3. **Context cache** (from `use-server`)
4. **Config defaults** (lowest priority)

### **Helpful Error Messages**
```bash
$ addvhost test.com
❌ VNODE required. Use one of:
  • --shost=motd
  • export VNODE=motd
  • php artisan use-server motd
```

### **Context Awareness**
```bash
$ use-server motd
✅ Default server context set to: motd

🎯 Now you can run commands without --shost:
   addvhost test.motd.com
   listvhosts
   status
```

### **Dry Run Support**
```bash
$ addvhost test.motd.com --dry-run
🔍 DRY RUN: Add VHost test.motd.com on motd
   → Generate VHost configuration for test.motd.com
   → Save config to ~/.ns/var/motd/test.motd.com
   → SSH to motd and execute vhost creation
   → Create user, directories, permissions
```

## 🎯 Migration Benefits

### **Before: Verbose Laravel**
```bash
alias a='php artisan'
a ns vhost add test.motd.com --shost=motd --verbose --dry-run
```

### **After: Natural Commands**
```bash
export VNODE=motd
addvhost test.motd.com --dry-run -v
```

### **Best of Both Worlds**
- ✅ **Original command names** (addvhost, delvhost, etc.)
- ✅ **Environment variable support** (VNODE, VHOST)
- ✅ **Context management** (use-server, clear-context)
- ✅ **Type safety** (PHP ENUMs, validation)
- ✅ **Lazy loading** (performance optimizations)
- ✅ **Modern features** (dry-run, verbose, caching)

## 🔧 Technical Implementation

### **Commands Created:**
- `BaseNetServaCommand` - Environment variable support
- `AddVhostCommand` - Flat command structure
- `UseServerCommand` - Context management
- `ClearContextCommand` - Context clearing
- `NetServaContext` - Context persistence service
- `LazyConfigurationCache` - Performance optimization

### **ENUMs for Type Safety:**
- `OsType` - Operating system types
- `NetServaConstants` - UIDs, ports, lengths
- `NetServaStrings` - Default values, character sets

### **Aliases Integration:**
- `nsrc_aliases.sh` - Bash aliases for all commands
- `setup-ns-aliases` - Automated integration script
- Environment variable detection and context switching

The result: **Laravel artisan commands that feel exactly like the original bash commands** while providing modern PHP benefits like type safety, lazy loading, and comprehensive error handling.