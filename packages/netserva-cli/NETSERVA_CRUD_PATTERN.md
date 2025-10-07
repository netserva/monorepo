# NetServa CRUD Pattern Implementation

## 🎯 **Perfect Match: Original NetServa Pattern**

Based on your original command list, NetServa follows a **beautiful 3-verb CRUD pattern**:

### **Original Commands from ~/.shold/bin/**
```bash
# CREATE operations (21 commands)
add{buser,cfdns,db,muser,ncuser,oa,pdns,proxy,pwtxt,redir,ssl,valias,vdns,vhost,vip,vmail,wp}

# DELETE operations (12 commands)
del{buser,cfdns,pdns,proxy,pwtxt,rc,valias,vdns,vhost,vmail,vultr,wp}

# READ/SHOW operations (15 commands)
sh{alias,conf,du,home,host,m,mail,pdns,pw,pwtxt,user,vdns,vip,who}
```

## 🚀 **Laravel Implementation: Exact Pattern Match**

### **VHost Commands Implemented:**
- **`addvhost`** → `php artisan addvhost` → `addvhost` (with alias)
- **`delvhost`** → `php artisan delvhost` → `delvhost` (with alias)
- **`shvhost`** → `php artisan shvhost` → `shvhost` (with alias)

### **Full Command Matrix Planning:**

| Resource | Add Command | Delete Command | Show Command | Status |
|----------|-------------|----------------|--------------|--------|
| **vhost** | `addvhost` | `delvhost` | `shvhost` | ✅ **Implemented** |
| **ssl** | `addssl` | `delssl` | `shssl` | 📋 Planned |
| **db** | `adddb` | `deldb` | `shdb` | 📋 Planned |
| **vmail** | `addvmail` | `delvmail` | `shvmail` | 📋 Planned |
| **vdns** | `addvdns` | `delvdns` | `shvdns` | 📋 Planned |
| **proxy** | `addproxy` | `delproxy` | `shproxy` | 📋 Planned |
| **wp** | `addwp` | `delwp` | `shwp` | 📋 Planned |

## 🎯 **Usage Examples (All Patterns Work)**

### **1. Environment Variable Pattern**
```bash
export VNODE=motd
addvhost test.motd.com
delvhost test.motd.com
shvhost test.motd.com
```

### **2. Context Management Pattern**
```bash
use-server motd
addvhost test.motd.com
shvhost --list           # Show all vhosts on motd
delvhost test.motd.com
```

### **3. One-Shot Pattern**
```bash
addvhost test.motd.com --shost=motd
shvhost test.motd.com --shost=motd
delvhost test.motd.com --shost=motd --force
```

### **4. Batch Operations**
```bash
export VNODE=motd
addvhost api.motd.com
addvhost blog.motd.com
addssl api.motd.com      # (when implemented)
addssl blog.motd.com
shvhost --list
```

## 🔧 **Command Features**

### **`addvhost test.motd.com`**
- ✅ Creates VHost configuration (~/.ns/var/VNODE/VHOST)
- ✅ Creates credentials file (~/.ns/var/VNODE/VHOST.conf)
- ✅ Generates secure passwords using ENUMs
- ✅ Uses lazy loading for OS detection and server info
- ✅ Supports --dry-run to show what would happen
- ✅ Shows UID, paths, config locations after creation

### **`delvhost test.motd.com`**
- ✅ Removes VHost and credentials files
- ✅ Confirmation prompt (--force to skip)
- ✅ Supports --dry-run for safety
- ✅ Complete cleanup of all VHost resources

### **`shvhost [test.motd.com]`**
- ✅ **`shvhost`** - Show all servers and their vhosts
- ✅ **`shvhost --shost=motd --list`** - List all vhosts for server
- ✅ **`shvhost test.motd.com --shost=motd`** - Show specific vhost details
- ✅ **`shvhost test.motd.com --shost=motd --config`** - Show full configuration
- ✅ Table display with status indicators (✅ Config, 🔐 Credentials)

## 🎯 **Perfect Backwards Compatibility**

### **Original Pattern:**
```bash
ssh motd
addvhost test.motd.com
```

### **New Pattern (Same Length!):**
```bash
export VNODE=motd
addvhost test.motd.com
```

### **Or with Context:**
```bash
use-server motd
addvhost test.motd.com
```

## 🔥 **Benefits Achieved**

✅ **Exact command names** - `addvhost`, `delvhost`, `shvhost`
✅ **Natural CRUD pattern** - add/del/sh prefixes
✅ **Environment variables** - `VNODE`, `VHOST` support
✅ **Context management** - `use-server motd`
✅ **Type safety** - PHP ENUMs for all constants
✅ **Performance** - Lazy loading with caching
✅ **Safety** - Dry-run, confirmation prompts
✅ **Rich output** - Tables, colors, status indicators

## 🚀 **Next Implementation Priority**

1. **`addssl`/`delssl`/`shssl`** - SSL certificate management
2. **`adddb`/`deldb`/`shdb`** - Database operations
3. **`addvmail`/`delvmail`/`shvmail`** - Email account management
4. **`addvdns`/`delvdns`/`shvdns`** - DNS record management

This gives us the **exact NetServa CRUD pattern** users expect, with modern Laravel benefits like type safety, lazy loading, and comprehensive error handling!