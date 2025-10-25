---
description: "Load recent context to continue from where we left off"
---

# Resume From Previous Session

Load recent Session Journal context to continue work seamlessly.

## Instructions

1. **Read active context** from `~/.ns/.claude/active-context.md`

2. **Read the 3 most recent Session Journal entries** from `~/.ns/.claude/journal/`
   - Sort by date (newest first)
   - Load full content of each

3. **Summarize for the user**:
   ```
   ## Recent Work Summary

   ### [Most Recent Session - Date]
   Focus: [what we were doing]
   Status: [completed/in-progress/blocked]

   ### [Previous Session - Date]
   Focus: [what we were doing]
   Status: [completed/in-progress/blocked]

   ### [Earlier Session - Date]
   Focus: [what we were doing]
   Status: [completed/in-progress/blocked]

   ## Current Active Tasks
   - [ ] Task from latest session
   - [ ] Task from latest session

   ## What would you like to work on today?
   - Continue [last task]?
   - Pick up [pending task]?
   - Start something new?
   ```

4. **Ask the user** what they want to focus on today

5. **Be ready to reference** any technical details from the Session Journal entries

## Context Priority

1. Active context file (most important)
2. Last 3 Session Journal entries
3. Project CLAUDE.md
4. Relevant documentation
