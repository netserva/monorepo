---
description: "Save current session summary to Session Journal"
---

# Snapshot Current Session

Please create a concise session summary and save it to the Session Journal.

## Instructions

1. **Analyze the current conversation** and identify:
   - Main task/feature being worked on
   - Key decisions made
   - Code/configuration changes
   - Problems solved or encountered
   - Next steps/blockers

2. **Create Session Journal entry** at `~/.ns/.claude/journal/YYYY-MM-DD_topic-slug.md` with:
   ```markdown
   # [Date] - [Topic Title]

   ## Session Focus
   [What we were working on]

   ## Key Decisions
   - Decision 1
   - Decision 2

   ## Changes Made
   - Change 1 (file references with line numbers)
   - Change 2

   ## Challenges & Solutions
   - Challenge: [problem]
     Solution: [resolution]

   ## Next Steps
   - [ ] Task 1
   - [ ] Task 2

   ## Technical Notes
   [Important details, gotchas, or insights]

   ## Related Files
   - path/to/file.php:123
   - path/to/config.php:45
   ```

3. **Update active context** at `~/.ns/.claude/active-context.md`:
   - Replace content with current focus
   - Keep only the most recent 2-3 sessions summarized
   - Include links to relevant Session Journal entries

4. **Confirm** by showing:
   - Session Journal entry filename
   - Brief summary of what was captured
   - Top 3 next steps

## Format Guidelines

- Use clear, actionable language
- Include specific file paths and line numbers
- Keep technical details concise but complete
- Focus on "why" not just "what"
- Make next steps specific and testable
