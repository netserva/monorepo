# BMAD-METHOD Integration for NetServa 3.0

**BMAD-METHOD**: A comprehensive AI-driven development framework using specialized AI agents for planning and implementation.

🔗 **Official Repository**: https://github.com/bmad-code-org/BMAD-METHOD

## Why BMAD for NetServa 3.0?

NetServa 3.0 is a complex infrastructure management platform with multiple interconnected packages. BMAD-METHOD provides:

1. **Consistent Planning**: Analyst, PM, and Architect agents collaborate to create detailed PRDs
2. **Context Preservation**: Hyper-detailed development stories prevent context loss between sessions
3. **Multi-Package Coordination**: Structured approach for features spanning netserva-cli, netserva-dns, netserva-fleet
4. **Architectural Consistency**: Maintains database-first, SSH-execution, and Filament 4 patterns

## Installation & Setup

### Prerequisites

- Node.js 20+
- Existing NetServa 3.0 installation (Laravel 12, Filament 4, Pest 4.0)
- Claude Code or compatible AI coding assistant

### Quick Start Installation

```bash
# From NetServa project root (~/.ns/)
npx bmad-method install

# OR if you've already cloned BMAD-METHOD elsewhere
cd /path/to/bmad-method
git pull
npm run install:bmad
```

**What This Does:**
- Installs BMAD core framework
- Sets up project files and configuration
- Installs expansion packs from package.json
- Creates `.bak` backup files for custom modifications

### NetServa 3.0 Directory Structure

BMAD artifacts are stored in `resources/docs/bmad/`:

```
resources/docs/bmad/
├── README.md                    # This file
├── NETSERVA-BMAD-SETUP.md      # NetServa-specific BMAD configuration
├── prds/                        # Product Requirement Documents
│   └── [feature-name].md
├── architecture/                # Architecture Decision Documents
│   └── [feature-name]-arch.md
├── stories/                     # Development Stories (Sprint artifacts)
│   └── [feature-name]/
│       ├── story-001.md
│       └── story-002.md
└── team-configs/                # BMAD team configurations
    └── netserva-team.txt        # NetServa 3.0 specialized team
```

## BMAD Workflow for NetServa 3.0

### Phase 1: Agentic Planning (PRD & Architecture)

**Agents Involved:**
- **Analyst Agent**: Gathers requirements, understands NetServa context
- **PM Agent**: Creates structured PRD with acceptance criteria
- **Architect Agent**: Designs solution following NS 3.0 patterns

**Example: Mail Database Setup Feature**

1. **Start Planning Session**:
   ```
   User: "I need to implement mail database support for dovecot on markc.goldcoast.org"

   Analyst Agent: Investigates existing infrastructure, checks mgo setup,
                  identifies database schema requirements

   PM Agent: Creates PRD with:
             - Feature scope
             - User stories
             - Acceptance criteria
             - Dependencies (postfix, dovecot, mysql)

   Architect Agent: Designs solution:
                    - Database schema (mailboxes, domains, aliases tables)
                    - Migration strategy from mgo reference
                    - Integration with existing postfix/dovecot configs
                    - UID/GID mapping for /srv/ structure
   ```

2. **Review & Refine**: Human reviews and refines the PRD and architecture documents

3. **Store Documents**:
   ```bash
   resources/docs/bmad/prds/mail-database-setup.md
   resources/docs/bmad/architecture/mail-database-setup-arch.md
   ```

### Phase 2: Story Generation (Scrum Master Agent)

**Scrum Master Agent** transforms PRD + Architecture into hyper-detailed development stories:

```
Story 001: Create Mail Database Schema
- Context: NetServa 3.0 database-first architecture
- Implementation: CREATE TABLE statements with NS 3.0 conventions
- Testing: Pest 4.0 tests for database migrations
- Acceptance: Schema matches mgo reference structure

Story 002: Update Dovecot Configuration
- Context: Single /etc/dovecot/dovecot.conf pattern
- Implementation: SQL userdb/passdb configuration
- Testing: dovecot -n validation, connection tests
- Dependencies: Story 001 must be completed
```

### Phase 3: Implementation (Dev Agent + Human)

**Dev Agent** (Claude Code) uses stories to implement:

```bash
# Example: Implementing Story 001
claude: "I'm working on Story 001: Create Mail Database Schema
         Reading resources/docs/bmad/stories/mail-database-setup/story-001.md

         Step 1: Create migration with NS 3.0 naming...
         Step 2: Add mailboxes table with uid/gid columns...
         Step 3: Write Pest tests..."
```

### Phase 4: Review & Iterate

- Human reviews implementation against story acceptance criteria
- Updates stories if requirements change
- Generates new stories for discovered work

## NetServa 3.0 BMAD Team Configuration

### Specialized Agents for NetServa Context

Create `resources/docs/bmad/team-configs/netserva-team.txt` with:

**Analyst Agent - NetServa Infrastructure Specialist**
- Understands database-first architecture
- Familiar with SSH execution patterns (RemoteExecutionService)
- Knows VHost/VNode/VServ hierarchy
- References existing mgo/markc infrastructure

**PM Agent - NetServa Product Manager**
- Tracks 55 environment variables (vconfs table)
- Understands domain-based organization (/srv/domain.com/)
- Maintains consistency across netserva-cli/dns/fleet packages
- Enforces Laravel 12 + Filament 4 standards

**Architect Agent - NetServa Systems Architect**
- Designs within database-first constraints
- Uses heredoc SSH execution pattern
- Follows NetServa 3.0 directory structure
- Integrates with existing services (nginx, postfix, dovecot, powerdns)
- References: resources/docs/SSH_EXECUTION_ARCHITECTURE.md

**Scrum Master Agent - NetServa Sprint Coordinator**
- Generates stories with full NS 3.0 context
- Includes testing requirements (Pest 4.0, 100% coverage)
- References documentation paths
- Specifies command patterns (artisan commands)

**Dev Agent - NetServa Implementation Engineer**
- Implements using NetServa coding standards
- Uses Laravel Boost MCP for Laravel ecosystem queries
- Runs vendor/bin/pint for code style
- Creates comprehensive Pest tests

## BMAD Best Practices for NetServa

### 1. Always Reference Existing Documentation

In PRDs and stories, reference:
- `resources/docs/NETSERVA-3.0-CONFIGURATION.md` - Primary NS 3.0 reference
- `resources/docs/SSH_EXECUTION_ARCHITECTURE.md` - Remote execution patterns
- `resources/docs/VHOST-VARIABLES.md` - 55 variable standard
- `resources/docs/ai/proven-workflows.md` - Development process

### 2. Use NetServa 3.0 Path Conventions in Stories

Example story snippet:
```markdown
## Implementation Details

**Paths (NetServa 3.0 Standard):**
- Mail storage: `/srv/domain.com/msg/`
- Web root: `/srv/domain.com/web/app/public/`
- Logs: `/srv/domain.com/web/log/`
- Config storage: `vconfs` table in `~/.ns/database/database.sqlite`
- SSL certs: `/etc/ssl/domain.com/{fullchain.pem,privkey.pem}`
```

### 3. Include Testing Requirements

Every story must specify:
```markdown
## Testing (Pest 4.0)

**Unit Tests:**
- Test database schema creation
- Test uid/gid mapping logic

**Feature Tests:**
- Test dovecot authentication with test mailbox
- Test LMTP delivery to /srv/domain.com/msg/

**Coverage Target:** 100% for new code
```

### 4. Specify SSH Execution Pattern

For remote operations:
```markdown
## SSH Execution (heredoc pattern)

Use `RemoteExecutionService::executeScript()`:

```php
$this->remoteExecution->executeScript(
    host: 'markc',
    script: <<<'BASH'
        #!/bin/bash
        set -euo pipefail

        # Script logic here
        BASH,
    args: [$arg1, $arg2],
    asRoot: true
);
```

**Reference:** resources/docs/SSH_EXECUTION_ARCHITECTURE.md
```

### 5. Maintain Package Boundaries

Stories should specify which package they affect:
- `packages/netserva-cli/` - VHost management, validation, permissions
- `packages/netserva-dns/` - DNS zone/record management
- `packages/netserva-fleet/` - Infrastructure discovery, VNode/VHost models

## First Feature with BMAD

### Recommended: Mail Database Setup

**Why this feature?**
- Well-defined scope (dovecot configuration is already prepared)
- Clear reference implementation (mgo)
- Touches multiple NS 3.0 concepts (database, SSH execution, service config)
- Moderate complexity (good for testing BMAD workflow)

**Start the Planning Phase:**

1. **Create Claude Project with NetServa Context**:
   - Upload key docs: NETSERVA-3.0-CONFIGURATION.md, SSH_EXECUTION_ARCHITECTURE.md
   - Share mgo dovecot.conf reference
   - Provide current markc infrastructure state

2. **Engage Analyst Agent**:
   ```
   Analyst: Analyze the requirements for implementing mail database support
            on markc.goldcoast.org. Reference the working mgo setup and
            identify all tables, schemas, and configuration changes needed.
   ```

3. **Engage PM Agent** (after Analyst completes):
   ```
   PM: Create a PRD for mail database implementation based on the Analyst's
       findings. Include user stories for sysadm@markc.goldcoast.org mailbox
       creation and testing.
   ```

4. **Engage Architect Agent** (after PM completes):
   ```
   Architect: Design the database schema and dovecot configuration integration
              following NetServa 3.0 patterns. Specify migration strategy and
              testing approach.
   ```

5. **Store Planning Artifacts**:
   ```bash
   resources/docs/bmad/prds/mail-database-setup.md
   resources/docs/bmad/architecture/mail-database-setup-arch.md
   ```

## Post-/clear Recovery

After a `/clear` (context reset), resume BMAD workflow:

1. **Load BMAD Context**:
   ```
   Read resources/docs/bmad/README.md to understand our BMAD setup
   ```

2. **Load Current Feature Context**:
   ```
   Read resources/docs/bmad/prds/[feature-name].md
   Read resources/docs/bmad/architecture/[feature-name]-arch.md
   ```

3. **Load Current Story**:
   ```
   Read resources/docs/bmad/stories/[feature-name]/story-XXX.md
   ```

4. **Resume Implementation**:
   ```
   Continue implementing Story XXX: [description]
   Last completed: [checkpoint]
   Next step: [action]
   ```

## BMAD vs Traditional NetServa Workflow

| Aspect | Traditional | With BMAD |
|--------|------------|-----------|
| Planning | Ad-hoc, in chat | Structured PRD + Architecture docs |
| Context | Lost after /clear | Preserved in stories |
| Testing | Defined during implementation | Specified in planning phase |
| Coordination | Manual across packages | Tracked in PRD dependencies |
| Onboarding | Read all docs | Read PRD + current story |
| Feature scope | Can drift | Defined in acceptance criteria |

## Troubleshooting

### BMAD Not Generating Detailed Stories

**Issue**: Scrum Master produces vague stories

**Solution**: Ensure Architect document includes:
- Specific file paths
- Code patterns (e.g., heredoc SSH execution)
- Testing requirements
- NetServa 3.0 conventions

### Context Loss Despite BMAD

**Issue**: Dev agent doesn't follow NetServa patterns

**Solution**:
1. Reference architecture doc in story header
2. Include code examples in story
3. Add "NetServa 3.0 Context" section to each story

### Stories Out of Sync with Code

**Issue**: Implementation diverged from stories

**Solution**:
1. Update story acceptance criteria as issues discovered
2. Create new stories for unexpected work
3. Mark stories "blocked" if dependencies unclear

## Resources

**BMAD-METHOD Official:**
- Repository: https://github.com/bmad-code-org/BMAD-METHOD
- Documentation: See bmad-method/docs/

**NetServa 3.0 Documentation:**
- Core: resources/docs/NETSERVA-3.0-CONFIGURATION.md
- SSH Patterns: resources/docs/SSH_EXECUTION_ARCHITECTURE.md
- Variables: resources/docs/VHOST-VARIABLES.md
- AI Workflows: resources/docs/ai/proven-workflows.md

**Claude Code Integration:**
- CLAUDE.md in project root defines NetServa rules
- Laravel Boost MCP: `mcp__laravel-boost__*` tools

---

**Next Steps:**
1. Install BMAD-METHOD: `npx bmad-method install`
2. Create NetServa team config in `team-configs/netserva-team.txt`
3. Start first feature planning: Mail Database Setup
4. Generate first PRD and Architecture document
5. Create development stories
6. Implement with Claude Code

**Note:** This document is version-controlled. Update as BMAD workflow is refined for NetServa 3.0 specific needs.
