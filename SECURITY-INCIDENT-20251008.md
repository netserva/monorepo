# 🚨 CRITICAL SECURITY NOTICE - CREDENTIAL ROTATION REQUIRED

**Date:** 2025-10-08
**Severity:** HIGH
**Action Required:** IMMEDIATE

---

## ⚠️ Situation

During documentation reorganization, the following files containing credentials were **temporarily committed to git** and **pushed to GitHub**:

### Exposed Files (in commit 058145b)
```
resources/docs/private/Binarylane_nsgc_credentials.txt  ⚠️ VPS CREDENTIALS
resources/docs/private/nsorg.md                         ⚠️ Server configs
resources/docs/private/mgo.md                           ⚠️ Server configs
resources/docs/private/motd.md                          ⚠️ Server configs
... (11 other customer-specific files)
```

### Current Status
- ✅ **FIXED**: `private/` removed from current HEAD (commit 74f3489)
- ✅ **PROTECTED**: `private/` now gitignored (won't happen again)
- ✅ **PRESERVED**: Local files still exist (not lost)
- ⚠️ **EXPOSED**: Commit 058145b still in git history with credentials

---

## 🔐 Required Actions

### IMMEDIATE (Within 24 Hours)

#### 1. Rotate Binarylane VPS Credentials
**File:** `resources/docs/private/Binarylane_nsgc_credentials.txt`

**Action:**
1. Log into Binarylane account
2. Change account password
3. Regenerate/rotate API keys
4. Update SSH keys if exposed
5. Enable 2FA if not already active
6. Review access logs for suspicious activity

**Location to update:**
- Binarylane web console
- Local file: `~/.ns/resources/docs/private/Binarylane_nsgc_credentials.txt`

#### 2. Review Server Access
**Files:** `nsorg.md`, `mgo.md`, `motd.md`

**Action:**
1. Check for any passwords/keys in these files
2. Rotate any exposed credentials
3. Review server access logs
4. Update SSH authorized_keys if needed

#### 3. Check Customer-Specific Configs
**Files:** `ccs2-*.md`, `mrn-*.md`

**Action:**
1. Review files for sensitive data
2. Rotate any exposed credentials
3. Notify customers if their data was exposed (if applicable)

### RECOMMENDED (Within 7 Days)

#### 4. Complete Git History Rewrite (Optional)
To completely remove credentials from git history:

```bash
# Install git-filter-repo
yay -S git-filter-repo  # or pacman -S git-filter-repo

# Clone fresh copy
cd /tmp
git clone git@github.com:markc/ns.git ns-clean
cd ns-clean

# Remove private/ from entire history
git-filter-repo --path resources/docs/private/ --invert-paths

# Force push to GitHub (DESTRUCTIVE)
git remote add origin git@github.com:markc/ns.git
git push origin --force --all
```

**WARNING:** This rewrites ALL commit hashes. Coordination required if others have clones.

#### 5. Review GitHub Security
1. Check GitHub repository access logs
2. Review "Pulse" for unusual clones/forks
3. Consider making repository private temporarily
4. Enable GitHub Advanced Security if available

---

## 📋 Verification Checklist

After rotating credentials, verify:

- [ ] Binarylane account password changed
- [ ] Binarylane API keys rotated
- [ ] Can still access VPS with new credentials
- [ ] Old credentials no longer work
- [ ] 2FA enabled on critical accounts
- [ ] Access logs reviewed (no suspicious activity)
- [ ] Team/collaborators notified if needed
- [ ] Customer data exposure assessed
- [ ] Incident documented
- [ ] Preventive measures in place (.gitignore verified)

---

## 🛡️ Preventive Measures (Already Implemented)

✅ **Gitignore Updated:**
```gitignore
# NetServa Documentation - Private & Archives
resources/docs/private/
resources/docs-archive-*/
```

✅ **Files Removed from Tracking:**
```bash
git rm -r --cached resources/docs/private/
git commit -m "Remove private/ from tracking"
```

✅ **Force Pushed to GitHub:**
```bash
git push --force origin main
```

✅ **Local Files Preserved:**
- Files remain on disk in `resources/docs/private/`
- Accessible locally for reference
- Will never be tracked again

---

## 📊 Exposure Timeline

| Time | Event |
|------|-------|
| Unknown | private/ created with credentials |
| Commit 058145b | Files committed to git |
| 2025-10-08 | Files pushed to GitHub (public) |
| 2025-10-08 06:16 | Discovered during reorganization |
| 2025-10-08 06:30 | Files removed from HEAD (commit 74f3489) |
| 2025-10-08 06:35 | Force pushed to GitHub |
| **NOW** | **Credentials must be rotated** |

**Estimated Exposure:** Unknown duration (potentially days/weeks)

---

## 💡 Lessons Learned

1. **ALWAYS** check .gitignore before committing sensitive dirs
2. **VERIFY** private directories are ignored before first commit
3. **AUDIT** repository for sensitive data regularly
4. **USE** git hooks to prevent commits of sensitive patterns
5. **IMPLEMENT** pre-commit checks for patterns like:
   - `*credentials*`
   - `*password*`
   - `*secret*`
   - `*private*`

---

## 🔗 Resources

- [GitHub: Removing Sensitive Data](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/removing-sensitive-data-from-a-repository)
- [git-filter-repo](https://github.com/newren/git-filter-repo/)
- [BFG Repo-Cleaner](https://rtyley.github.io/bfg-repo-cleaner/)

---

## 📞 Next Steps

1. ✅ Read this notice completely
2. ⚠️ Rotate Binarylane credentials IMMEDIATELY
3. ⚠️ Check server access logs
4. ⚠️ Review all private/* files for other credentials
5. ⏳ Consider complete history rewrite
6. ✅ Update this file with actions taken

---

**This is a security incident. Treat with appropriate urgency.**

**File Location:** `/tmp/SECURITY-NOTICE-CREDENTIAL-ROTATION.md`
**Also save to:** `~/.ns/SECURITY-INCIDENT-20251008.md` (DO NOT commit)
