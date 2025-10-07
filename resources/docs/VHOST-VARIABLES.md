# NetServa 3.0 VHost Configuration Variables

**Total Variables:** 53 (NetServa 1.0 had 54, removed LROOT in 3.0)

**Storage:** Database column `fleet_vhosts.environment_vars` (JSON)

**Naming Convention:** All variables are exactly 5 characters, uppercase

**Commands:** `shvconf`, `addvconf`, `chvconf`, `delvconf`

---

## Admin Variables (7)

| Variable | Description | Example |
|----------|-------------|---------|
| ADMIN | Admin email address | admin@example.com |
| AHOST | Admin hostname | ns1.example.com |
| AMAIL | Admin mail server | mail.example.com |
| ANAME | Admin full name | System Administrator |
| APASS | Admin password | [auto-generated] |
| A_GID | Admin group ID | 1000 |
| A_UID | Admin user ID | 1000 |

---

## Path Variables (6)

| Variable | Description | Example |
|----------|-------------|---------|
| BPATH | Backup path | /home/backups/example.com |
| DPATH | Data path | /srv/example.com/var |
| MPATH | Mail path | /srv/example.com/var/mail |
| UPATH | User home path | /srv/example.com |
| VPATH | VHost path | /srv/example.com/var/vhosts |
| WPATH | Web root path | /srv/example.com/web/app/public |

---

## Database Variables (8)

| Variable | Description | Example |
|----------|-------------|---------|
| DBMYS | MySQL database name | example_mysql |
| DBSQL | SQLite database path | /srv/example.com/var/example.db |
| DHOST | Database host | localhost |
| DNAME | Database name | example_db |
| DPASS | Database password | [auto-generated] |
| DPORT | Database port | 3306 |
| DTYPE | Database type | mysql \| sqlite |
| DUSER | Database username | example_user |

---

## Configuration Variables (7)

| Variable | Description | Example |
|----------|-------------|---------|
| CIMAP | IMAP configuration file | /etc/dovecot/conf.d/example.com |
| CSMTP | SMTP configuration file | /etc/postfix/example.com.cf |
| C_DNS | DNS configuration file | /etc/powerdns/zones/example.com |
| C_FPM | PHP-FPM pool config | /etc/php/8.4/fpm/pool.d/example.com.conf |
| C_SQL | Database config file | /etc/mysql/conf.d/example.com.cnf |
| C_SSL | SSL certificate path | /etc/ssl/example.com |
| C_WEB | Web server config | /etc/nginx/sites-available/example.com |

---

## User Variables (6)

| Variable | Description | Example |
|----------|-------------|---------|
| UUSER | VHost system username | u1001 |
| UPASS | VHost user password | [auto-generated] |
| U_GID | VHost group ID | 1001 |
| U_SHL | User shell | /bin/bash |
| U_UID | VHost user ID | 1001 |
| WUGID | Web user:group ID | 33:1001 (www-data:u1001) |

---

## VHost Variables (5)

| Variable | Description | Example |
|----------|-------------|---------|
| HDOMN | Primary domain | example.com |
| HNAME | Full hostname | www.example.com |
| VHOST | Virtual host identifier | example.com |
| VUSER | VHost owner username | u1001 |
| V_PHP | PHP version | 8.4 |

---

## Mail Variables (2)

| Variable | Description | Example |
|----------|-------------|---------|
| EPASS | Email account password | [auto-generated] |
| MHOST | Mail hostname | mail.example.com |

---

## System Variables (6)

| Variable | Description | Example |
|----------|-------------|---------|
| IP4_0 | Primary IPv4 address | 192.168.100.10 |
| OSMIR | OS package mirror | http://mirror.example.org |
| OSREL | OS release | trixie \| bookworm |
| OSTYP | OS type | debian \| alpine |
| TAREA | Timezone area | Australia |
| TCITY | Timezone city | Brisbane |

---

## Executable Variables (4)

| Variable | Description | Example |
|----------|-------------|---------|
| EXMYS | MySQL executable path | /usr/bin/mysql |
| EXSQL | SQLite executable path | /usr/bin/sqlite3 |
| SQCMD | SQL command utility | mysql \| sqlite3 |
| SQDNS | DNS server command | pdns_control \| named |

---

## WordPress Variables (2)

| Variable | Description | Example |
|----------|-------------|---------|
| WPASS | WordPress admin password | [auto-generated] |
| WPUSR | WordPress admin username | admin |

---

## Password Generation

All password variables (`APASS`, `DPASS`, `UPASS`, `EPASS`, `WPASS`) are auto-generated using `/dev/urandom` and should never be hardcoded.

---

## Migration Notes

**NetServa 1.0 → 3.0 Changes:**
- Removed: `LROOT` (no longer needed in 3.0 architecture)
- Total variables: 54 → 53

**Backward Compatibility:**
- NetServa 3.0 maintains naming compatibility with 1.0
- Existing 1.0 configurations can be migrated directly (minus LROOT)

---

## Storage Format

Variables are stored as JSON in the `fleet_vhosts.environment_vars` database column:

```json
{
  "VHOST": "example.com",
  "HNAME": "www.example.com",
  "HDOMN": "example.com",
  "ADMIN": "admin@example.com",
  "APASS": "[auto-generated]",
  "DNAME": "example_db",
  "DUSER": "example_user",
  "DPASS": "[auto-generated]",
  ...
}
```

---

## Usage

### Database-Centric Storage (NetServa 3.0)

All 53 variables are stored in `~/.ns/database/database.sqlite` in the `fleet_vhosts.environment_vars` JSON column.

### Command Reference

```bash
# Show variables (default: shell format)
shvconf markc.goldcoast.org

# Show as JSON
shvconf markc.goldcoast.org json

# Show as formatted table
shvconf markc.goldcoast.org table

# Initialize 53 variables for new vhost
addvconf example.com
addvconf example.com admin@example.com ns2

# Change a single variable
chvconf example.com V_PHP 8.3
chvconf example.com ADMIN new@example.com

# Delete configuration (with confirmation)
delvconf example.com

# Delete configuration (force, no confirmation)
delvconf example.com --force
```

### Source Variables into Shell

```bash
# Export all 53 variables to current shell
eval "$(shvconf example.com)"

# Now all variables are available
echo $VHOST
echo $DPASS
echo $WPATH
```

### Security Model

**NetServa 3.0 (Database-Centric):**
- All configuration centralized in `~/.ns/database/database.sqlite`
- Functions in `~/.ns/_nsrc` interact directly with database
- No shell script files in `~/.ns/bin/` or `~/.ns/var/`
- Passwords auto-generated on `addvconf`

**NetServa 1.0 (File-Based, Deprecated):**
- Configuration distributed across servers at `/root/.vhosts/$VHOST`
- Each server maintained its own config files

---

## Implementation Details

**Location:** Functions implemented in `~/.ns/_nsrc`

**Functions:**
- `shvconf` - Show VHost configuration variables
- `addvconf` - Add new VHost configuration (auto-generates passwords)
- `chvconf` - Change VHost configuration variable
- `delvconf` - Delete VHost configuration

**Database Access:**
- Uses `ns_db()` function from `_nsrc` (wraps `sqlite3`)
- Direct SQL queries to `fleet_vhosts` table
- JSON manipulation via `jq`

**UID Assignment:**
- Auto-increments from 1001 onwards
- Queries database for next available UID: `COALESCE(MAX(U_UID), 1000) + 1`
