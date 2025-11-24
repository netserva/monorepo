# Runbook: [Task Name]

**Created**: YYYY-MM-DD
**Last Used**: YYYY-MM-DD (context: what triggered this use)
**Success Rate**: X/Y (successful executions / total attempts)
**Average Time**: X minutes
**Difficulty**: [Simple/Moderate/Complex]

## When to Use This Runbook

**Symptoms:**
- Symptom 1 that indicates you need this procedure
- Symptom 2 that triggers this runbook
- Symptom 3 to watch for

**Root Cause:**
[Brief explanation of why this procedure is needed]

**Scope:**
[What this runbook covers and what it doesn't]

## Prerequisites

**Required Access:**
- [ ] SSH access to: [server1, server2]
- [ ] Database access to: [database_name]
- [ ] Permissions: [specific permissions needed]

**Required Information:**
- [ ] Domain name or resource identifier
- [ ] Backup location/credentials
- [ ] Notification contacts

**Tools/Dependencies:**
- [ ] Tool 1 installed
- [ ] Tool 2 configured
- [ ] Service 3 running

## Safety Checks

**Before Starting:**
```bash
# Backup current configuration
sx server 'backup command here'

# Verify prerequisites
sx server 'verification command'

# Check current state
sx server 'status check command'
```

**Expected Output:**
```
What successful prerequisite output looks like
```

## Procedure

### Step 1: [Action Name]

```bash
sx server 'command to execute'
```

**✅ Checkpoint:** [What to verify before proceeding to next step]

**Expected Output:**
```
What successful output looks like
Line by line what you should see
```

**If this fails:**
- Check [specific thing]
- Verify [another thing]
- See Common Issues section below

---

### Step 2: [Action Name]

```bash
sx server 'next command'
```

**✅ Checkpoint:** [Verification step]

**Expected Output:**
```
Expected output here
```

---

### Step 3: [Action Name]

```bash
sx server 'another command'
```

**✅ Checkpoint:** [What to confirm]

**Expected Output:**
```
Success indicators
```

---

### Step 4: [Continue pattern for all steps]

[Repeat the step structure above for each action in the procedure]

## Verification & Testing

**Final Checks:**
```bash
# Verify primary functionality
sx server 'test command 1'

# Verify dependent services
sx server 'test command 2'

# Check logs for errors
sx server 'log check command'
```

**Success Criteria:**
- [ ] Primary objective achieved (be specific)
- [ ] All services responding normally
- [ ] No error messages in logs
- [ ] Dependent systems functioning
- [ ] Performance metrics acceptable

## Rollback Procedure

**If Step 1 fails:**
1. Revert with:
   ```bash
   sx server 'undo command'
   ```
2. Verify rollback:
   ```bash
   sx server 'verification'
   ```

**If Step 2 fails:**
1. Undo Step 2:
   ```bash
   sx server 'rollback command'
   ```
2. Undo Step 1:
   ```bash
   sx server 'revert previous step'
   ```
3. Restore from backup:
   ```bash
   sx server 'restore command'
   ```

**If Step 3+ fails:**
[Continue pattern for all steps that need rollback procedures]

## Common Issues

### Issue 1: [Problem Description]

**Symptom**: [What you see when this happens]

**Cause**: [Why it occurs]

**Fix**:
```bash
sx server 'resolution command'
```

**Prevention**: [How to avoid this in the future]

---

### Issue 2: [Problem Description]

**Symptom**: [Observable behavior]

**Cause**: [Root cause]

**Fix**:
```bash
sx server 'fix command'
```

**Prevention**: [Preventive measure]

## Post-Completion

**Cleanup:**
- [ ] Remove temporary files
- [ ] Clear temporary credentials
- [ ] Reset test data

**Documentation:**
- [ ] Update this runbook with any new issues encountered
- [ ] Update **Last Used** date above
- [ ] Increment **Success Rate** if successful
- [ ] Add new edge cases to Common Issues if discovered
- [ ] Create journal entry with `/snapshot` if significant

**Notifications:**
- [ ] Notify stakeholders of completion
- [ ] Update monitoring/alerting if needed
- [ ] Document any changes in configuration management

## Related Documentation

- **Agent**: `.claude/agents/[category]/[agent-name].md` (if applicable)
- **Journal**: `.claude/journal/YYYY-MM-DD_[related-session].md`
- **Technical Docs**: `resources/docs/[relevant-doc].md`

## Variables Used

**This runbook uses placeholders for sensitive data:**
- `server_name` = actual server hostname
- `domain.com` = actual domain name
- `database_name` = actual database name
- `master_ip` / `slave_ip` = actual IP addresses
- `/path/to/resource` = actual file paths

**Always substitute real values when executing!**

## Execution Time

- **Simple case** (no issues): X minutes
- **Complex case** (with complications): Y minutes
- **Emergency situation** (rollback needed): Z minutes

## Notes

[Important gotchas, insights, or warnings that don't fit elsewhere]

[Lessons learned from previous executions]

[Quirks or oddities to be aware of]

---

**Remember**: Update this runbook after each use! Add new issues, refine steps, update timing estimates.
