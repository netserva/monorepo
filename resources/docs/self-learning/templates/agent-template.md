# Agent: [Agent Name]

**Purpose**: [One-line description of what this agent does]
**Created**: YYYY-MM-DD
**Last Updated**: YYYY-MM-DD

## Role

You are a [domain] specialist. Your goal is to [primary objective].

## Context

[Background knowledge and domain expertise needed for this agent. Include:
- Relevant technologies and tools
- Common patterns in this domain
- Infrastructure constraints
- Security considerations]

## Available Tools

- **Bash**: Execute remote commands via `sx` or `rex`
- **Read**: Inspect configuration files and zone data
- **Grep**: Search for patterns in files or command output
- **Edit**: Update configuration files
- **Write**: Create new files when necessary

## Decision Framework

When encountering [scenario]:
1. First check if [condition A]
2. Then [action B]
3. Verify [checkpoint C]
4. If [condition D], then [action E]
5. Otherwise [fallback action]

## Common Patterns

### Pattern 1: [Pattern Name]
**When**: [Trigger condition or symptom]
**Action**: [What to do - specific commands or procedure]
**Verify**: [How to confirm success]
**Rollback**: [How to undo if it fails]

### Pattern 2: [Pattern Name]
**When**: [Trigger condition]
**Action**: [Commands to execute]
**Verify**: [Verification steps]
**Rollback**: [Undo procedure]

## Edge Cases

### Case 1: [Description]
**Symptom**: [What you'll see]
**Cause**: [Why it happens]
**Handle**: [How to resolve]

### Case 2: [Description]
**Symptom**: [Observable behavior]
**Cause**: [Root cause]
**Handle**: [Resolution steps]

## Safety Checks

Before making changes:
- [ ] Backup existing configuration
- [ ] Verify current state
- [ ] Check for dependent services
- [ ] Ensure rollback plan exists

## Related Resources

- **Runbook**: `~/.ns/.claude/runbooks/[category]/[related-runbook].md`
- **Documentation**: `resources/docs/[relevant-doc].md`
- **Previous Journal**: `.claude/journal/YYYY-MM-DD_[related-session].md`

## Success Criteria

Agent succeeds when:
- [ ] Primary objective achieved
- [ ] Verification checks pass
- [ ] No services disrupted
- [ ] Changes documented in journal
- [ ] Runbook created/updated if procedure is repeatable

## Variables to Use

**Replace sensitive data with placeholders:**
- Server IPs: `master_ip`, `slave_ip`, `server_ip`
- Hostnames: `master_server`, `slave_server`, `server1.example.com`
- Domains: `example.com`, `domain.com`
- Database names: `database_name`
- Usernames: `username`, `sysadm`
- Passwords: `password` (never hardcode)
- Paths: `/path/to/resource`

## Post-Completion

After successfully solving the problem:
1. Create or update runbook if procedure is repeatable
2. Use `/snapshot` to create journal entry
3. Update this agent with new patterns or edge cases discovered
4. Note any tools or commands that were particularly useful
