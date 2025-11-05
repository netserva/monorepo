# Bogofilter Database Migration: SQLite → Berkeley DB 5.3

## Problem
Different bogofilter installations use different database backends:
- **Source system** (nsorg, etc.): SQLite
- **Destination system** (gw OpenWrt): Berkeley DB 5.3.28

Binary database files are **incompatible** and cannot be copied directly.

## Solution: Text Dump/Load

Bogofilter's `bogoutil` tool can export databases to text format and import from text, allowing cross-database-backend migration.

---

## Step-by-Step Migration

### 1. Export from Source System (SQLite)

**On nsorg or any system with SQLite bogofilter:**

```bash
# Find the wordlist database
ls -la ~/.bogofilter/

# Dump to text format
bogoutil -d ~/.bogofilter/wordlist.db > /tmp/wordlist-export.txt

# Check file size (should be text)
ls -lh /tmp/wordlist-export.txt
file /tmp/wordlist-export.txt
# Output: ASCII text
```

**Format of exported file:**
```
word1 spam_count ham_count date
word2 spam_count ham_count date
...
```

### 2. Transfer to Destination System

```bash
# Copy to gw
scp /tmp/wordlist-export.txt gw:/tmp/

# Or use ssh pipe
cat /tmp/wordlist-export.txt | ssh gw 'cat > /tmp/wordlist-export.txt'
```

### 3. Import to Destination System (Berkeley DB)

**On gw OpenWrt:**

```bash
# Remove old database (if exists)
sx gw 'rm /srv/goldcoast.org/msg/admin/.bogofilter/wordlist.db'

# Load from text dump (as correct user)
sx gw 'sudo -u u1001 bogoutil -l /srv/goldcoast.org/msg/admin/.bogofilter/wordlist.db < /tmp/wordlist-export.txt'

# Verify database created
sx gw 'ls -lh /srv/goldcoast.org/msg/admin/.bogofilter/'
# Output: -rw-r--r-- 1 u1001 u1001 <size> wordlist.db

# Test classification
sx gw 'echo "Subject: test" | sudo -u u1001 bogofilter -u -p -d /srv/goldcoast.org/msg/admin/.bogofilter -v'
```

---

## For Multiple Users

If migrating databases for multiple users:

```bash
# On source system - export all users
for user in user1 user2 user3; do
  bogoutil -d /path/to/$user/.bogofilter/wordlist.db > /tmp/wordlist-$user.txt
done

# Transfer all files
scp /tmp/wordlist-*.txt gw:/tmp/

# On gw - import for each user
sx gw 'sudo -u u1001 bogoutil -l /srv/goldcoast.org/msg/admin/.bogofilter/wordlist.db < /tmp/wordlist-user1.txt'
sx gw 'sudo -u sysadm bogoutil -l /srv/mail.goldcoast.org/msg/admin/.bogofilter/wordlist.db < /tmp/wordlist-user2.txt'
```

---

## Verification

### Check Database Stats

```bash
# On gw - display histogram
sx gw 'sudo -u u1001 bogoutil -H /srv/goldcoast.org/msg/admin/.bogofilter/wordlist.db'
```

**Output:**
```
             cnt    min      max      sum     avg
messages     123      0      456    12345   100.37
tokens     45678      1     9999  1234567    27.03
```

### Test Classification

```bash
# Send test email through bogofilter
sx gw 'cat /tmp/test-spam.eml | sudo -u u1001 bogofilter -u -p -d /srv/goldcoast.org/msg/admin/.bogofilter -v'
```

**Expected output:**
- `X-Bogosity: Spam` (if spam tokens trained)
- `X-Bogosity: Ham` (if ham tokens trained)
- `X-Bogosity: Unsure` (if insufficient training data)

---

## Database Format Details

### Text Dump Format

Each line in the dump file represents one token:

```
token spam_count ham_count timestamp
```

**Example:**
```
viagra 150 2 1234567890
meeting 5 200 1234567890
```

**Fields:**
- `token`: The word/token
- `spam_count`: Number of times seen in spam
- `ham_count`: Number of times seen in ham
- `timestamp`: Unix timestamp (days since epoch)

### Database Backend Comparison

| Backend | File Format | Size | Cross-platform |
|---------|-------------|------|----------------|
| **Berkeley DB** | Binary | Smaller | ❌ No (arch-specific) |
| **SQLite** | Binary | Larger | ❌ No (version-specific) |
| **Text dump** | ASCII | Largest | ✅ Yes (portable) |

---

## Common Issues

### Issue 1: Permission Denied

**Error:**
```
Can't open file 'wordlist.db' - Permission denied
```

**Fix:**
```bash
# Ensure running as correct user
sx gw 'sudo -u u1001 bogoutil -l /srv/goldcoast.org/msg/admin/.bogofilter/wordlist.db < /tmp/export.txt'

# Check directory ownership
sx gw 'ls -ld /srv/goldcoast.org/msg/admin/.bogofilter/'
```

### Issue 2: Invalid Data Format

**Error:**
```
Invalid data in input file
```

**Cause:** Dump file corrupted or wrong format

**Fix:**
```bash
# Re-export from source
bogoutil -d wordlist.db > export.txt

# Verify format (should be: word count count timestamp)
head -5 export.txt
```

### Issue 3: Database Already Exists

**Error:**
```
File exists
```

**Fix:**
```bash
# Remove old database first
sx gw 'rm /srv/goldcoast.org/msg/admin/.bogofilter/wordlist.db'

# Then load
sx gw 'sudo -u u1001 bogoutil -l /srv/goldcoast.org/msg/admin/.bogofilter/wordlist.db < /tmp/export.txt'
```

---

## Automated Migration Script

```bash
#!/bin/bash
# migrate-bogofilter-db.sh
# Migrate bogofilter database from SQLite to Berkeley DB

SOURCE_DB="$1"
DEST_HOST="$2"
DEST_USER="$3"
DEST_PATH="$4"

if [ -z "$4" ]; then
  echo "Usage: $0 <source_db> <dest_host> <dest_user> <dest_path>"
  echo "Example: $0 ~/.bogofilter/wordlist.db gw u1001 /srv/goldcoast.org/msg/admin/.bogofilter/wordlist.db"
  exit 1
fi

echo "1. Exporting from $SOURCE_DB..."
bogoutil -d "$SOURCE_DB" > /tmp/wordlist-export.txt || exit 1

echo "2. Transferring to $DEST_HOST..."
scp /tmp/wordlist-export.txt "$DEST_HOST:/tmp/" || exit 1

echo "3. Importing to $DEST_PATH..."
ssh "$DEST_HOST" "sudo -u $DEST_USER bogoutil -l $DEST_PATH < /tmp/wordlist-export.txt" || exit 1

echo "4. Verifying..."
ssh "$DEST_HOST" "sudo -u $DEST_USER bogoutil -H $DEST_PATH"

echo "✅ Migration complete!"
rm /tmp/wordlist-export.txt
```

**Usage:**
```bash
chmod +x migrate-bogofilter-db.sh
./migrate-bogofilter-db.sh ~/.bogofilter/wordlist.db gw u1001 /srv/goldcoast.org/msg/admin/.bogofilter/wordlist.db
```

---

## Summary

✅ **Export:** `bogoutil -d wordlist.db > export.txt` (any backend)
✅ **Transfer:** `scp export.txt destination:/tmp/`
✅ **Import:** `bogoutil -l wordlist.db < export.txt` (any backend)
✅ **Verify:** `bogoutil -H wordlist.db`

**Key points:**
- Text format is portable across all backends (SQLite, Berkeley DB, QDBM, etc.)
- File size increases (binary → text) but is compressed well
- User ownership matters - use `sudo -u <user>` when importing
- No data loss - all tokens, counts, and timestamps preserved

**This is the official method recommended by bogofilter documentation.**
