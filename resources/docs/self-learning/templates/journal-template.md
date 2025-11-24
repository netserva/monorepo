# Session Journal: [Brief Title]

**Date**: YYYY-MM-DD
**Duration**: [Start time] - [End time] ([X hours/minutes])
**Session Type**: [Debugging/Feature Development/Infrastructure/Emergency/Exploration]
**Primary Goal**: [What was the main objective]

## Executive Summary

[2-3 sentence overview of what was accomplished, what problem was solved, or what was learned]

## Context

**Initial Situation:**
[What triggered this work? What was the starting state?]

**Previous Related Work:**
- Related journal: [YYYY-MM-DD_title.md]
- Related agent: [category/agent-name.md]
- Related runbook: [category/runbook-name.md]

## Timeline

### [HH:MM] - Initial Discovery
[What happened, what was observed, what triggered investigation]

### [HH:MM] - Investigation Phase
[What was checked, what commands were run, what was discovered]

### [HH:MM] - Root Cause Identified
[The actual problem, why it happened, what was the underlying issue]

### [HH:MM] - Solution Attempted
[What was tried, what worked, what didn't]

### [HH:MM] - Resolution Achieved
[Final state, confirmation of success, verification steps]

## Technical Details

### Systems Involved
- **Server/Service 1**: [hostname/purpose] - [role in the issue]
- **Server/Service 2**: [hostname/purpose] - [role in the solution]
- **Component 3**: [what/where] - [relevance]

### Key Commands Executed

```bash
# Purpose of this command
sx server 'command that was important'

# Another significant command
rex server 'complex script here'

# Verification command
sx server 'check command'
```

### Configuration Changes

**File**: `/path/to/config/file.conf`
**Change**: [What was modified and why]
```
before: old configuration
after: new configuration
```

**File**: `/path/to/another/file`
**Change**: [Description]
```
relevant snippets
```

### Code Changes

**File**: `packages/package-name/src/Services/ServiceName.php:123`
**Purpose**: [Why this change was made]
**Method**: `methodName()`
```php
// Key code that was added/modified
public function methodName(): bool
{
    // Relevant implementation
}
```

## Root Cause Analysis

**Problem**: [Clear statement of the actual issue]

**Why it happened**: [Underlying cause - be specific]

**Why it wasn't caught earlier**: [System gaps, monitoring gaps, etc.]

**How it manifested**: [Symptoms that were visible]

## Solution

**Approach taken**: [High-level strategy]

**Why this approach**: [Reasoning, alternatives considered]

**Steps executed**:
1. [Step 1 with purpose]
2. [Step 2 with verification]
3. [Step 3 with confirmation]

**Verification**:
- [Test 1 performed]
- [Test 2 confirmed]
- [Monitoring check]

## Decisions Made

### Decision 1: [Choice that was made]
**Options considered:**
- Option A: [pros/cons]
- Option B: [pros/cons]
- **Chosen**: Option C: [why]

**Rationale**: [Reasoning behind the decision]

**Tradeoffs**: [What was sacrificed, what was gained]

### Decision 2: [Another significant choice]
[Follow same structure]

## Challenges Encountered

### Challenge 1: [What went wrong]
**Issue**: [Specific problem]
**Attempted**: [What was tried]
**Resolution**: [How it was overcome]
**Time lost**: [X minutes]

### Challenge 2: [Another obstacle]
**Issue**: [Description]
**Attempted**: [Solutions tried]
**Resolution**: [Final solution]
**Lesson**: [What to do differently next time]

## Key Insights

1. **Insight 1**: [Important discovery or realization]
   - **Impact**: [How this changes future work]
   - **Action**: [What to do with this knowledge]

2. **Insight 2**: [Critical lesson learned]
   - **Impact**: [Implications]
   - **Action**: [Next steps]

3. **Insight 3**: [Valuable takeaway]
   - **Impact**: [What changes]
   - **Action**: [How to apply it]

## Artifacts Created

- **Agent**: `.claude/agents/[category]/[name].md` - [Purpose]
- **Runbook**: `.claude/runbooks/[category]/[name].md` - [When to use]
- **Documentation**: `resources/docs/[path]/[file].md` - [What it covers]
- **Code**: `packages/[package]/[path]` - [Functionality added]

## Testing

**Tests run**:
```bash
php artisan test --filter=TestName
```

**Results**: [Pass/fail, coverage, issues found]

**Tests created**:
- `tests/Feature/NewFeatureTest.php` - [What it tests]
- `tests/Unit/NewUnitTest.php` - [Coverage]

## Monitoring & Validation

**Short-term** (24-48 hours):
- [ ] Monitor [specific metric/log]
- [ ] Verify [particular behavior]
- [ ] Check [dependent system]

**Long-term** (ongoing):
- [ ] Track [metric over time]
- [ ] Watch for [potential regression]
- [ ] Alert on [condition]

## Next Steps

**Immediate** (this session or next):
- [ ] Task 1 to complete
- [ ] Task 2 to follow up
- [ ] Task 3 to verify

**Short-term** (this week):
- [ ] Improvement 1 to make
- [ ] Documentation 2 to update
- [ ] Investigation 3 to perform

**Long-term** (future):
- [ ] Enhancement 1 to consider
- [ ] Refactoring 2 to plan
- [ ] Feature 3 to implement

## Related Work

**Blocked by this**:
- [Task/feature that was waiting on this resolution]

**Unblocks**:
- [What can now proceed because of this work]

**Similar issues to watch for**:
- [Related symptoms that might indicate same problem]
- [Other systems that might have same issue]

## References

**External documentation**:
- [Link or reference to official docs]
- [Relevant Stack Overflow or GitHub issue]

**Internal documentation**:
- `resources/docs/[relevant-doc].md`
- `.claude/journal/[related-session].md`

**Commands/tools used**:
- `sx` - Remote execution
- `rex` - Complex script execution
- Other relevant tools

## Metrics

**Time breakdown**:
- Investigation: X minutes
- Solution development: Y minutes
- Testing: Z minutes
- Documentation: W minutes

**Effectiveness**:
- Initial approach worked: [Yes/No]
- Pivots required: [Number]
- Rollbacks needed: [Number]

## Security Notes

[Any security implications, credentials used, access required, sensitive information encountered]

## Cost/Impact

**Downtime**: [None/X minutes/hours]
**Services affected**: [List]
**Users impacted**: [Count or scope]
**Business impact**: [Revenue/SLA/reputation]

---

## Notes

[Any additional context, quirks, oddities, or information that doesn't fit elsewhere but is worth recording]

[Links to related conversations, external resources, or future considerations]

[Personal observations or hunches about related issues]
