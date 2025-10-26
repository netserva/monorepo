-- NetServa 3.0 sysadm Database Schema (SQLite)
-- Version: 1.0
-- Purpose: Runtime mail server configuration for remote NetServa deployments
-- Date: 2025-10-14
--
-- This SQL schema is generated from sysadm.dbml
-- Target: SQLite (Development and lightweight deployments)
-- Location: /var/lib/sqlite/sysadm/sysadm.db

-- ============================================================================
-- DOMAINS TABLE
-- Virtual mail domains with filesystem ownership
-- ============================================================================

CREATE TABLE IF NOT EXISTS domains (
    domain VARCHAR(255) PRIMARY KEY NOT NULL,
    uid INTEGER NOT NULL,
    gid INTEGER NOT NULL,
    maxquota BIGINT DEFAULT 0,
    active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- MAILBOXES TABLE
-- Virtual mailboxes with intelligent redundancy for zero-JOIN queries
-- ============================================================================

CREATE TABLE IF NOT EXISTS mailboxes (
    username VARCHAR(255) PRIMARY KEY NOT NULL,
    password VARCHAR(255) NOT NULL,
    maildir VARCHAR(255) NOT NULL,
    domain VARCHAR(255) NOT NULL,
    uid INTEGER NOT NULL,
    gid INTEGER NOT NULL,
    quota BIGINT DEFAULT 500000000,
    active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_mailbox_domain ON mailboxes(domain);
CREATE INDEX IF NOT EXISTS idx_mailbox_active ON mailboxes(active);

-- ============================================================================
-- ALIASES TABLE
-- Email aliases and forwarding rules with multi-destination support
-- ============================================================================

CREATE TABLE IF NOT EXISTS aliases (
    address VARCHAR(255) PRIMARY KEY NOT NULL,
    goto TEXT NOT NULL,
    domain VARCHAR(255) NOT NULL,
    active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_alias_domain ON aliases(domain);
CREATE INDEX IF NOT EXISTS idx_alias_active ON aliases(active);

-- ============================================================================
-- NOTES AND DOCUMENTATION
-- ============================================================================

-- Virtual mailboxes for Dovecot/Postfix authentication and delivery.
-- Redundant domain/uid/gid fields enable zero-JOIN queries for maximum performance.
-- Maildir format: /srv/{domain}/msg/{user}/Maildir
-- Password format: Use doveadm pw -s SHA256-CRYPT to generate hashes

-- Design Principles:
-- 1. No Foreign Keys: By design for maximum flexibility and portability
-- 2. Redundant Fields: domain/uid/gid in mailboxes table for zero-JOIN performance
-- 3. Laravel Compatibility: created_at/updated_at timestamp fields
-- 4. Soft Deletes: Active flags enable soft deletes without data loss
-- 5. VARCHAR(255): Optimal length for maximum compatibility across platforms
-- 6. TEXT for goto: Supports comma-separated multi-destination aliases

-- Performance Optimization:
-- 1. Indexes: Critical for production deployments with 1000+ mailboxes
-- 2. Zero JOINs: Redundant fields eliminate JOINs in hot query paths
-- 3. Active flags: Fast filtering without checking deleted_at timestamps
-- 4. Maildir format: Efficient file-based storage, no database overhead
-- 5. BIGINT quotas: Supports quotas up to 9.2 exabytes
