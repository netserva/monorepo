# NetServa 3.0 Documentation

**Last Reorganization:** 20251008-061623
**Archive Location:** resources/docs-archive-20251008-061623

## 📋 Core Documentation

### NetServa 3.0 Foundation
- **[NETSERVA-3.0-CONFIGURATION.md](NETSERVA-3.0-CONFIGURATION.md)** - Primary NS 3.0 reference guide
- **[SSH_EXECUTION_ARCHITECTURE.md](SSH_EXECUTION_ARCHITECTURE.md)** - RemoteExecutionService patterns
- **[SSH_EXECUTION_MIGRATION_GUIDE.md](SSH_EXECUTION_MIGRATION_GUIDE.md)** - Migration to executeScript()
- **[VHOST-VARIABLES.md](VHOST-VARIABLES.md)** - 54 environment variable standard
- **[PowerDNS_ACME_DNS01_Solution.md](PowerDNS_ACME_DNS01_Solution.md)** - DNS-01 ACME challenge

## 🤖 AI-Assisted Development

**New in 2025-10-08:** Comprehensive guides for Claude Code workflows

### Claude Code Essentials
- **[Claude Code Essentials](ai/claude-code-essentials.md)** - Core patterns, memory system, MCP integration
- **[Memory Management](ai/memory-management.md)** - Context optimization, CLAUDE.md strategy
- **[Documentation Standards](ai/documentation-standards.md)** - AI-readable documentation patterns
- **[Proven Workflows](ai/proven-workflows.md)** - 4-phase development, TDD, technical debt management

### Quick Start
1. Read [claude-code-essentials.md](ai/claude-code-essentials.md) for overview
2. Optimize your CLAUDE.md files using [memory-management.md](ai/memory-management.md)
3. Follow [proven-workflows.md](ai/proven-workflows.md) for development process
4. Use `.claude/commands/` for workflow automation

## 🏗️ Architecture & Patterns

**New in 2025-10-08:** Laravel architectural patterns and Filament organization

- **[Service & Action Patterns](architecture/service-action-patterns.md)** - Service layer, actions, thin models
- **[Business Logic Documentation](architecture/business-logic-documentation.md)** - Business rules catalog, state machines
- **[Filament Organization](architecture/filament-organization.md)** - Filament 4.1 structure, resources, testing

### Architectural Decision Records
- **[ADR System](../../docs/adr/README.md)** - Track significant architectural decisions
- [ADR-0001: Database-First Architecture](../../docs/adr/0001-use-database-first-architecture.md)
- [ADR-0002: executeScript Heredoc Pattern](../../docs/adr/0002-adopt-executeScript-pattern.md)

## 🔧 Service Configuration

### Mail Server
- [Mail Configuration](mail/) - Postfix, Dovecot, Rspamd, SpamProbe
- [Bayes Spam Learning](mail/bayes-spam-learning-configuration.md)
- [Dovecot Setup](mail/dovecot-lean-setup.md)
- [Rspamd + Redis](mail/rspamd-redis-configuration.md)

### DNS Management
- [PowerDNS Nameservers](dns/NetServa_nameservers.md)
- [AdGuard Integration](dns/pdns-adguard-integration.md)
- [Split DNS Configuration](dns/pdns-split-dns.md)

### Infrastructure
- [Incus Management](infrastructure/) - Backup, resource limits
- [Centralized Logging](infrastructure/centralized-logging-architecture.md)
- **[SSH Troubleshooting](infrastructure/ssh-troubleshooting.md)** - SSH multiplexing, SFTP issues, config debugging

### Security
- [Firewalld Gotchas](security/firewalld-first-install-gotchas.md)
- [TLS Testing](security/tls-testing-readme.md)

## 💻 Development

- [Development Guidelines](reference/development_guidelines.md) - Laravel Boost, Pest 4.0
- [Testing Strategy](reference/testing_strategy.md) - Comprehensive testing requirements
- [Filament v4 Architecture](reference/FILAMENT-V4-PLUGIN-ARCHITECTURE.md)
- [Standards](reference/standards.md) - NetServa coding standards

## 🔒 Private Documentation

Customer-specific and sensitive configurations in `private/` directory.

## 📦 Archive

Previous documentation archived at: `resources/docs-archive-20251008-061623/`
- Complete snapshot of all docs before reorganization
- Git history also preserves all previous versions
- Retrieve with: `git log --all --full-history -- resources/docs/filename.md`

---

**Note:** This is a fresh start focusing on NetServa 3.0 standards. Legacy NetServa 1.0/2.0
documentation has been archived. If you need historical migration patterns, check the archive.
