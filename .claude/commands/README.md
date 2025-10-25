# Claude Code Project Journal Commands

**Security Notice:** Journal entries, active context, and timeline files contain sensitive data (IPs, passwords, API keys) and are gitignored. Only these command templates are tracked in the repo.

## Available Commands

### `/snapshot` - Save Current Session
Captures current conversation into a dated journal entry with:
- Session focus and key decisions
- Code changes with file:line references
- Problems solved and solutions
- Next steps and blockers
- Technical notes and gotchas

Creates: `.claude/journal/YYYY-MM-DD_topic-slug.md`

### `/resume` - Load Recent Context
Loads last 3 journal entries to continue from where you left off:
- Summarizes recent work
- Shows active tasks
- Asks what to work on today

Useful for starting fresh sessions with full context.

### `/timeline` - Update Project Roadmap
Maintains high-level milestone tracking:
- Current phase progress
- Completed milestones
- In-progress work
- Upcoming objectives
- Long-term vision

Updates: `.claude/project-timeline.md`

## Workflow

**End of day:**
```
/snapshot
```

**Start of day:**
```
/resume
```

**Weekly/milestone updates:**
```
/timeline
```

## File Structure

```
.claude/
├── commands/          # Tracked in git (safe templates)
│   ├── snapshot.md
│   ├── resume.md
│   ├── timeline.md
│   └── README.md
├── journal/           # GITIGNORED - sensitive data
│   └── 2025-10-25_*.md
├── active-context.md  # GITIGNORED - recent sessions
└── project-timeline.md # GITIGNORED - contains dates/IPs
```

## Why This System?

NetServa 3.0 is a multi-month project. This journal system prevents:
- ❌ Losing context between sessions
- ❌ Forgetting why decisions were made
- ❌ Repeating solved problems
- ❌ Unclear project progress

And enables:
- ✅ Instant session continuity
- ✅ Decision history tracking
- ✅ Searchable problem solutions
- ✅ Clear milestone visibility

## Security

All journal files are automatically gitignored (see `.gitignore:56-59`). They may contain:
- Server IPs and hostnames
- Database passwords
- API keys and tokens
- Infrastructure details

**Never commit journal files to public repos.**
