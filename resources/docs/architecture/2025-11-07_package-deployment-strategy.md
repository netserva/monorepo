# NetServa Package Deployment Strategy - Packagist vs VCS Direct

**Date:** 2025-11-07
**Status:** 🎯 Architectural Decision Record
**Complexity:** Medium

---

## Overview

NetServa 3.0 packages (admin, cms, core) are distributed as Composer packages via both **Packagist** (the standard PHP package repository) and **direct VCS repositories** (GitHub). This document explains when to use each approach, their trade-offs, and establishes best practices for different deployment scenarios.

**Key Decision:** Use a **hybrid strategy** - VCS for active development/testing, Packagist for production deployments.

---

## Background

NetServa maintains three core packages:
- **netserva/admin** - Filament admin panel for settings and plugin management
- **netserva/cms** - Professional Laravel CMS with Filament 4 admin panel
- **netserva/core** - Foundation package for NetServa infrastructure management

These packages can be installed via Composer using two different approaches:

### Approach A: Packagist (Standard)
```bash
composer require netserva/admin
```
*Requires no additional configuration in composer.json*

### Approach B: VCS Direct (Explicit)
```json
{
  "repositories": [
    {"type": "vcs", "url": "https://github.com/netserva/admin.git"}
  ],
  "require": {
    "netserva/admin": "^0.0.11"
  }
}
```
*Requires explicit repository declaration*

---

## Architecture Analysis

### Option A: Packagist (Public PHP Package Repository)

Packagist is the official Composer package repository that aggregates public PHP packages.

#### ✅ Advantages

**Discoverability**
- Packages appear on packagist.org search results
- Anyone can discover NetServa packages via `composer search netserva`
- Increases adoption and community awareness

**Zero Configuration**
- Consumers install with simple `composer require netserva/admin`
- No custom repository blocks in composer.json
- Standard workflow familiar to all PHP developers

**Ecosystem Integration**
- Download statistics and version badges available
- Community trust signals (stars, maintainers, update frequency)
- Integration with package analysis tools (Packagist Inspector, Libraries.io)

**Performance**
- Packagist uses CDN for metadata distribution
- Faster than querying GitHub API directly for package info
- Reduced GitHub API rate limit pressure

**Professional Standard**
- Expected practice in professional PHP/Laravel ecosystem
- Demonstrates maturity and stability
- Required by some enterprise security policies

**Multi-Project Benefits**
- Once registered on Packagist, usable in ANY project worldwide
- No per-project configuration overhead
- Documentation simplification

#### ❌ Disadvantages

**Update Lag**
- Main limitation: Packagist sync delay after GitHub releases
- **Observed sync times**: 3-36 minutes for NetServa packages
- Can feel slow during rapid development iteration

**Webhook Dependency**
- Requires GitHub webhook configuration to auto-update
- Webhook failures can cause prolonged staleness
- Requires maintenance of OAuth connection

**Cache Staleness**
- Packagist caches package metadata
- Manual "Force Update" sometimes required for problem packages
- Not fully under NetServa control

**Third-Party Dependency**
- If Packagist experiences downtime, package resolution blocked
- Adds external dependency to deployment pipeline
- Subject to Packagist rate limits (though generous)

---

### Option B: VCS Direct (GitHub Repositories)

VCS repositories allow Composer to fetch package metadata directly from GitHub without Packagist intermediary.

#### ✅ Advantages

**Instant Availability**
- Changes available **immediately** after pushing to GitHub
- Zero sync delay - perfect for rapid iteration
- No waiting for Packagist webhooks

**Full Control**
- No external service dependencies beyond GitHub
- Complete autonomy over package resolution
- Can bypass Packagist issues entirely

**Branch Flexibility**
- Easy to test `dev-main` or feature branches
- Can reference specific commits for testing
- Supports complex monorepo development workflows

**Private Repository Support**
- Works with private GitHub repos using authentication
- Enables internal package distribution
- Can mix public and private packages

**No Tag Discipline Required**
- Can reference any commit hash directly
- Useful for testing unreleased code
- Supports continuous deployment workflows

**Development-Optimized**
- Ideal for packages under active, coordinated development
- Perfect for testing environments
- Enables rapid feedback loops

#### ❌ Disadvantages

**Configuration Duplication**
- Every consuming project must add repository blocks
- Maintenance burden if repository URLs change
- Easy to forget or misconfigure

**Zero Discoverability**
- Package invisible to the PHP community
- No search results, no statistics, no presence
- Limits adoption and community growth

**Non-Standard Practice**
- Raises questions from experienced PHP developers
- Can signal immaturity or instability
- Some teams ban non-Packagist packages

**Documentation Overhead**
- Installation instructions must explain custom setup
- Higher barrier to adoption
- More support requests from confused users

**No Usage Metrics**
- Cannot track downloads or adoption
- No community feedback signals
- Difficult to measure package popularity

**Trust Concerns**
- Organizations may block non-Packagist packages in security audits
- Perceived as higher risk or less maintained
- Can exclude enterprise users

**Update Coordination**
- If GitHub URL changes, all consumers must update
- Breaking changes harder to communicate
- No centralized notification system

---

## Recommended Hybrid Strategy

**Use the right tool for the right context:**

### 🔧 Active Development / Testing Environments

**Use:** VCS Direct Repositories

**Configuration:**
```json
{
  "repositories": [
    {"type": "vcs", "url": "https://github.com/netserva/admin.git"},
    {"type": "vcs", "url": "https://github.com/netserva/cms.git"},
    {"type": "vcs", "url": "https://github.com/netserva/core.git"}
  ],
  "require": {
    "netserva/admin": "^0.0.11",
    "netserva/cms": "^0.0.11",
    "netserva/core": "^0.0.11"
  }
}
```

**Example Projects:**
- `~/Dev/netserva/test-cms-v004` (active testing)
- Internal development sandboxes
- CI/CD pipeline testing environments
- Monorepo coordinated development

**Rationale:**
- Need instant access to latest commits/branches
- Rapid iteration requires zero lag
- Testing pre-release code before tagging
- Full flexibility for experimental changes

### 🚀 Production / Customer Deployments

**Use:** Packagist (Standard)

**Configuration:**
```json
{
  "require": {
    "netserva/admin": "^0.0.11",
    "netserva/cms": "^0.0.11",
    "netserva/core": "^0.0.11"
  }
}
```
*No repositories section needed*

**Example Projects:**
- Customer/client Laravel applications
- Public open-source projects consuming NetServa
- Production deployments
- Third-party integrations

**Rationale:**
- Standard practice expected by professionals
- 3-36 minute sync delay acceptable for stable releases
- Discoverability benefits community growth
- Trust signals important for adoption
- Documentation simplicity

---

## Decision Matrix

| Scenario | Approach | Reason |
|----------|----------|--------|
| Active package development | VCS Direct | Instant updates, rapid iteration |
| Testing unreleased features | VCS Direct | Branch flexibility, commit-level control |
| Coordinated monorepo changes | VCS Direct | Synchronized updates across packages |
| Stable production deployment | Packagist | Standard practice, semantic versioning |
| Customer/client projects | Packagist | Professional, no special configuration |
| Public open-source packages | Packagist | Discoverability, community trust |
| Private internal tools | VCS Direct (private repos) | Security, controlled access |
| CI/CD test pipelines | VCS Direct | Latest code, no tag requirement |
| Package documentation examples | Packagist | Simplicity, standard workflow |

---

## Packagist Auto-Update Configuration

NetServa packages are configured for automatic updates via GitHub webhooks.

### Current Status ✅

All three NetServa packages are confirmed **auto-updated** on Packagist:

| Package | Packagist URL | Auto-Update | Last Verified |
|---------|--------------|-------------|---------------|
| netserva/admin | https://packagist.org/packages/netserva/admin | ✅ Yes | 2025-11-07 |
| netserva/cms | https://packagist.org/packages/netserva/cms | ✅ Yes | 2025-11-07 |
| netserva/core | https://packagist.org/packages/netserva/core | ✅ Yes | 2025-11-07 |

### Observed Sync Times

Based on v0.0.11 release (2025-11-06):

| Package | GitHub Release | Packagist Sync | Delay |
|---------|----------------|----------------|-------|
| netserva/admin | 11:08 UTC | 11:44 UTC | ~36 minutes |
| netserva/cms | 11:34 UTC | 11:44 UTC | ~10 minutes |
| netserva/core | 11:41 UTC | 11:44 UTC | ~3 minutes |

**Conclusion:** Sync delays are normal and acceptable for production use. For development work requiring instant updates, use VCS direct approach.

### Webhook Setup

NetServa's Packagist account is connected to GitHub via OAuth, enabling automatic webhook configuration.

**Verified Configuration:**
- ✅ GitHub account connected to Packagist user `markc`
- ✅ OAuth permissions granted for automatic webhook management
- ✅ All three packages show "This package is auto-updated"

### Manual Verification Steps

To verify webhooks are active:

```bash
# Check package auto-update status
curl -s https://packagist.org/packages/netserva/admin.json | \
  jq '.package.autoUpdated'
# Should return: true
```

Or visit GitHub repo settings:
- https://github.com/netserva/admin/settings/hooks
- https://github.com/netserva/cms/settings/hooks
- https://github.com/netserva/core/settings/hooks

**Expected webhook:**
- Payload URL: `https://packagist.org/api/github?username=markc`
- Content type: `application/json`
- Events: Push events
- Status: Recent deliveries showing 200 responses

---

## Implementation Details

### Switching from Packagist to VCS

**Scenario:** You're starting a development project and need instant package updates.

```bash
# 1. Add VCS repositories to composer.json
cat > composer-repos.json <<'EOF'
{
  "repositories": [
    {"type": "vcs", "url": "https://github.com/netserva/admin.git"},
    {"type": "vcs", "url": "https://github.com/netserva/cms.git"},
    {"type": "vcs", "url": "https://github.com/netserva/core.git"}
  ]
}
EOF

# 2. Merge into composer.json (manual edit or jq)
# Edit composer.json to add the repositories array

# 3. Force Composer to re-resolve from VCS
rm composer.lock
composer install

# 4. Verify packages are from GitHub
composer show netserva/admin -a | grep source
# Should show: [git] https://github.com/netserva/admin.git
```

### Switching from VCS to Packagist

**Scenario:** Moving a development project to production, want standard Packagist approach.

```bash
# 1. Remove VCS repositories section from composer.json
# Edit composer.json to delete the "repositories" array

# 2. Clear lock file and reinstall
rm composer.lock
composer install

# 3. Verify packages are from Packagist
composer show netserva/admin -a
# Should list all available versions from Packagist
# Will still show GitHub as source (this is normal - Packagist points to GitHub)
```

### Testing Which Source is Active

```bash
# Check if VCS repos are configured
grep -A 3 '"repositories"' composer.json

# If "repositories" section exists with netserva URLs -> VCS mode
# If no "repositories" section -> Packagist mode

# Verify available versions (Packagist only)
composer show netserva/admin --all | grep "versions"
# If you see full version list (v0.0.11, v0.0.10, etc.) -> Packagist is working
```

---

## Usage Examples

### Example 1: New Development Project (VCS Mode)

```bash
# Initialize new Laravel project
composer create-project laravel/laravel netserva-dev
cd netserva-dev

# Configure for VCS direct access
cat >> composer.json <<'EOF'
  "repositories": [
    {"type": "vcs", "url": "https://github.com/netserva/admin.git"},
    {"type": "vcs", "url": "https://github.com/netserva/cms.git"},
    {"type": "vcs", "url": "https://github.com/netserva/core.git"}
  ],
EOF

# Install NetServa packages
composer require netserva/admin:^0.0 netserva/cms:^0.0 netserva/core:^0.0

# Updates will now be instant from GitHub
composer update netserva/admin netserva/cms netserva/core
```

### Example 2: Production Customer Deployment (Packagist Mode)

```bash
# Initialize customer project
composer create-project laravel/laravel customer-site
cd customer-site

# Install NetServa packages (no special config needed)
composer require netserva/admin:^0.0.11 netserva/cms:^0.0.11 netserva/core:^0.0.11

# Standard composer workflow
composer update

# Composer will automatically use Packagist
```

### Example 3: Testing Unreleased Feature (VCS Mode)

```bash
# Add VCS repo if not already present
# Edit composer.json to add repositories section

# Require specific branch
composer require netserva/admin:dev-feature-new-settings

# Or specific commit
composer require netserva/admin:dev-main#abc123f

# Test the feature
php artisan test

# Revert to stable when done
composer require netserva/admin:^0.0.11
```

### Example 4: Transition from Development to Production

```bash
# Project starts in VCS mode during development
# Ready to deploy to production

# 1. Switch to Packagist mode
# Remove "repositories" section from composer.json

# 2. Lock to specific versions
composer require netserva/admin:0.0.11 netserva/cms:0.0.11 netserva/core:0.0.11 --no-update

# 3. Update lock file
rm composer.lock
composer install

# 4. Commit for production
git add composer.json composer.lock
git commit -m "Switch to Packagist for production deployment"
```

---

## Best Practices

### ✅ DO

**Use VCS for:**
- Active package development and testing
- Rapid iteration requiring instant updates
- Testing pre-release features or branches
- Coordinated monorepo development
- Internal development environments

**Use Packagist for:**
- Production deployments
- Customer/client projects
- Public documentation examples
- Stable release distribution
- Community/open-source consumption

**Version Management:**
- Always use semantic versioning for releases
- Tag releases on GitHub to trigger Packagist updates
- Use `^0.0.11` constraints (caret) for flexibility
- Lock exact versions (`0.0.11`) only for production

**Documentation:**
- Document which mode each project uses and why
- Update README with correct installation instructions
- Maintain separate docs for dev vs production setup

### ❌ DON'T

**Never:**
- Mix VCS and Packagist approaches randomly without reason
- Use VCS direct in production customer deployments
- Skip semantic versioning even in VCS mode
- Forget to tag releases (breaks Packagist updates)
- Use `dev-main` in production
- Point VCS URLs to temporary forks or branches
- Configure VCS without documenting why

**Avoid:**
- Hardcoding commit hashes unless absolutely necessary
- Using VCS mode just because "it's faster" without strategic reason
- Deploying to customers without testing via Packagist first
- Changing repository URLs without coordinating across projects

### ⚠️ Be Careful

**Version Conflicts:**
- VCS can access unreleased versions not on Packagist
- Switching modes may cause version resolution issues
- Always clear composer.lock when switching modes

**Branch Names:**
- `dev-main` is valid in VCS mode
- Feature branches need `dev-` prefix: `dev-feature-name`
- Packagist only shows tagged releases

**Caching:**
- Composer caches VCS metadata locally
- Use `composer clear-cache` if packages seem stale
- VCS mode bypasses Packagist cache but not Composer cache

---

## Verification & Testing

### Verify Packagist Auto-Update Status

```bash
# Check if package is auto-updated
curl -s https://packagist.org/packages/netserva/admin.json | jq '.package'

# Check last update timestamp
curl -s https://packagist.org/packages/netserva/admin.json | \
  jq '.package.time | to_entries | sort_by(.value) | reverse | .[0]'
```

### Verify VCS Mode is Active

```bash
# Check composer.json has VCS repositories
cat composer.json | jq '.repositories[]? | select(.type=="vcs")'

# Verify composer is using GitHub directly
composer show netserva/admin --all | grep -A 5 source
```

### Test Package Updates

```bash
# In VCS mode - should be instant
composer update netserva/admin --with-dependencies

# In Packagist mode - may take 3-36 minutes after GitHub release
composer update netserva/admin --with-dependencies

# Force refresh Composer cache
composer clear-cache
composer update netserva/admin
```

### Webhook Health Check

```bash
# Check recent webhook deliveries via GitHub API (requires auth)
gh api repos/netserva/admin/hooks --jq '.[] | select(.config.url | contains("packagist"))'

# Manual check: Visit GitHub repo settings
# https://github.com/netserva/admin/settings/hooks
# Look for green checkmarks on recent deliveries
```

---

## Troubleshooting

### Problem: Packagist Not Updating After Release

**Symptoms:**
- Tagged release on GitHub hours ago
- Packagist still shows old version
- `composer update` doesn't find new version

**Solution:**
```bash
# 1. Verify webhook exists and is active on GitHub
# Visit: https://github.com/netserva/[package]/settings/hooks

# 2. Check webhook recent deliveries for errors

# 3. Manually trigger Packagist update
# Visit: https://packagist.org/packages/netserva/[package]
# Click "Force Update" button (if logged in as maintainer)

# 4. Clear Composer cache
composer clear-cache

# 5. Try update again
composer update netserva/admin
```

### Problem: Composer Can't Find Package Version

**Symptoms:**
- `Could not find package netserva/admin with version constraint ^0.0.11`
- Package exists on GitHub but Composer can't resolve

**Solution:**
```bash
# Check if VCS mode is configured but shouldn't be
cat composer.json | jq '.repositories'

# If VCS repos exist and you want Packagist:
# 1. Remove "repositories" section from composer.json
# 2. Clear lock file
rm composer.lock
composer install

# If Packagist mode but need VCS:
# 1. Add repositories section to composer.json
# 2. Force re-resolve
rm composer.lock
composer install
```

### Problem: Version Conflict After Switching Modes

**Symptoms:**
- Package version installed in VCS mode not available in Packagist mode
- Dependency resolution failures after mode switch

**Solution:**
```bash
# 1. Check what version is currently installed
composer show netserva/admin | grep versions

# 2. If version not on Packagist, update to latest stable
composer require netserva/admin:^0.0 --no-update

# 3. Clear everything and reinstall
rm composer.lock
composer clear-cache
composer install

# 4. Verify resolved versions
composer show netserva/admin
```

---

## Migration Checklist

### Development → Production Migration

- [ ] Remove `repositories` section from composer.json
- [ ] Update version constraints to exact versions (e.g., `0.0.11`)
- [ ] Delete composer.lock
- [ ] Run `composer install` to generate new lock file
- [ ] Verify packages resolve from Packagist: `composer show netserva/admin --all`
- [ ] Test application functionality
- [ ] Commit composer.json and composer.lock changes
- [ ] Document deployment uses Packagist mode

### Production → Development Migration

- [ ] Add `repositories` section with VCS URLs to composer.json
- [ ] Update version constraints to flexible (e.g., `^0.0`)
- [ ] Delete composer.lock
- [ ] Run `composer install` to generate new lock file
- [ ] Verify packages resolve from GitHub: `grep github composer.lock`
- [ ] Test rapid update workflow: `composer update netserva/admin`
- [ ] Document project uses VCS mode for development

---

## Summary

### Key Takeaways

🎯 **Strategic Approach**
- VCS direct is not a "workaround" - it's a valid development strategy
- Packagist is the production standard for stability and discoverability
- Use both approaches strategically based on project phase

📊 **Performance Characteristics**
- VCS: Instant updates, requires configuration
- Packagist: 3-36 minute delay, zero configuration
- Both download from GitHub (Packagist is just a metadata layer)

🔧 **Configuration Decision Tree**
```
Is this active development?
├─ Yes → Use VCS (instant updates)
└─ No → Is this production/customer deployment?
    ├─ Yes → Use Packagist (standard practice)
    └─ No → Is rapid iteration needed?
        ├─ Yes → Use VCS
        └─ No → Use Packagist (default)
```

✅ **NetServa Package Status**
- All three packages (admin/cms/core) on Packagist ✅
- Auto-update webhooks configured and working ✅
- Sync times acceptable for production use (3-36 min) ✅

### Quick Reference

**VCS Mode Setup:**
```json
{
  "repositories": [
    {"type": "vcs", "url": "https://github.com/netserva/admin.git"},
    {"type": "vcs", "url": "https://github.com/netserva/cms.git"},
    {"type": "vcs", "url": "https://github.com/netserva/core.git"}
  ]
}
```

**Packagist Mode Setup:**
```json
{
  "require": {
    "netserva/admin": "^0.0.11"
  }
}
```
*No repositories section needed*

**Switch Modes:**
```bash
# Always clear lock file when switching
rm composer.lock
composer install
```

---

**Document Version:** 1.0.0 (2025-11-07)
**NetServa Platform:** 3.0
**Last Verified:** 2025-11-07 (webhook status, sync times)
**Maintainer:** NetServa Core Team
