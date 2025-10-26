-- NetServa 1.0 → 3.0 Database Migration
-- Transforms sysadm database from NS 1.0 to NS 3.0 schema
-- Based on /root/sysadm.dbml
-- Copyright (C) 2025 Mark Constable <markc@renta.net> (AGPL-3.0)

-- Step 1: Rename current database to backup
RENAME TABLE sysadm.vhosts TO sysadm_bkp.vhosts;
RENAME TABLE sysadm.vmails TO sysadm_bkp.vmails;
RENAME TABLE sysadm.valias TO sysadm_bkp.valias;

-- Step 2: Create NS 3.0 schema in sysadm database
USE sysadm;

-- vhosts table (NS 3.0)
CREATE TABLE vhosts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    domain VARCHAR(255) NOT NULL UNIQUE,
    uid INT UNSIGNED NOT NULL DEFAULT 1000,
    gid INT UNSIGNED NOT NULL DEFAULT 1000,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_vhosts_domain (domain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- vmails table (NS 3.0)
CREATE TABLE vmails (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user VARCHAR(255) NOT NULL UNIQUE,
    pass VARCHAR(255) NOT NULL,
    home VARCHAR(255) NOT NULL,
    uid INT UNSIGNED NOT NULL DEFAULT 1000,
    gid INT UNSIGNED NOT NULL DEFAULT 1000,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_vmails_user (user)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- valias table (NS 3.0)
CREATE TABLE valias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(255) NOT NULL UNIQUE,
    target TEXT NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_valias_source (source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 3: Migrate data from NS 1.0 to NS 3.0

-- Migrate vhosts
-- Drop: aid, uname, aliases, mailboxes, mailquota, diskquota
-- Rename: updated→updated_at, created→created_at
INSERT INTO sysadm.vhosts (id, domain, uid, gid, active, created_at, updated_at)
SELECT id, domain, uid, gid, active, created, updated
FROM sysadm_bkp.vhosts;

-- Migrate vmails
-- Drop: aid, hid, spamf, quota, mailbox (enum)
-- Rename: password→pass, updated→updated_at, created→created_at
-- Transform: home path from /home/u/<domain>/home/<user> to /srv/<domain>/msg/<user>
INSERT INTO sysadm.vmails (id, user, pass, home, uid, gid, active, created_at, updated_at)
SELECT
    id,
    user,
    password,
    CONCAT(
        '/srv/',
        SUBSTRING_INDEX(user, '@', -1),
        '/msg/',
        SUBSTRING_INDEX(user, '@', 1)
    ) AS home,
    uid,
    gid,
    active,
    created,
    updated
FROM sysadm_bkp.vmails;

-- Migrate valias
-- Drop: aid, hid
-- Rename: updated→updated_at, created→created_at
INSERT INTO sysadm.valias (id, source, target, active, created_at, updated_at)
SELECT id, source, target, active, created, updated
FROM sysadm_bkp.valias;

-- Step 4: Verification queries (run manually after migration)
-- SELECT COUNT(*) FROM sysadm.vhosts;
-- SELECT COUNT(*) FROM sysadm.vmails;
-- SELECT COUNT(*) FROM sysadm.valias;
-- SELECT * FROM sysadm.vmails LIMIT 5;
