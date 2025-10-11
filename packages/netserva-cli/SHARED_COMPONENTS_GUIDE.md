# NetServa CLI - Shared Components Quick Reference

**Quick guide for using shared validation, DTOs, and form components**

---

## 🎯 Quick Start

### 1. Import Validation Rules

```php
use NetServa\Cli\Validation\Rules\PasswordRules;
use NetServa\Cli\Validation\Rules\DomainRules;
use NetServa\Cli\Validation\Rules\EmailRules;
use NetServa\Cli\Validation\Rules\VhostRules;
```

### 2. Import DTOs

```php
use NetServa\Cli\DataObjects\VhostCreationData;
use NetServa\Cli\DataObjects\MigrationJobData;
use NetServa\Cli\DataObjects\UserPasswordData;
use NetServa\Cli\DataObjects\SetupJobData;
```

### 3. Import Form Components

```php
use NetServa\Cli\Filament\Components\VhostFormComponents;
use NetServa\Cli\Filament\Components\MigrationFormComponents;
use NetServa\Cli\Filament\Components\SetupFormComponents;
```

---

## 🔧 Validation Rules Reference

### PasswordRules

```php
// Secure password (12+ chars, mixed case, numbers)
PasswordRules::secure()

// Strong password (16+ chars, mixed case, numbers, special chars)
PasswordRules::strong()

// Basic password (8+ chars)
PasswordRules::basic()

// Get error messages
PasswordRules::messages()
```

**Usage in Console:**

```php
$validator = Validator::make(
    ['password' => $input],
    ['password' => PasswordRules::secure()],
    PasswordRules::messages()
);
```

**Usage in Filament:**

```php
TextInput::make('password')
    ->password()
    ->rules(PasswordRules::secure());
```

### DomainRules

```php
// Standard domain validation
DomainRules::domain()

// Nullable domain
DomainRules::domainNullable()

// Subdomain (allows wildcards)
DomainRules::subdomain()

// Check domain exists in vhost_configurations
DomainRules::existsInVhostConfigs($vnode)

// Check domain is unique for vnode
DomainRules::uniqueForVnode($vnode, $exceptId)

// Get error messages
DomainRules::messages()
```

### EmailRules

```php
// Standard email (with DNS check)
EmailRules::email()

// Nullable email
EmailRules::emailNullable()

// Basic email (no DNS check)
EmailRules::emailBasic()

// Email for specific domain
EmailRules::emailForDomain($domain)

// Multiple emails (comma-separated)
EmailRules::multipleEmails()

// Get error messages
EmailRules::messages()
```

### VhostRules

```php
// Server node validation
VhostRules::vnode()
VhostRules::vnodeExists()

// Domain validation (alias)
VhostRules::domain()

// PHP version
VhostRules::phpVersion()
VhostRules::phpVersionNullable()

// Database type
VhostRules::databaseType()

// OS type
VhostRules::osType()

// Unix username
VhostRules::unixUsername()

// Unix UID/GID
VhostRules::unixUid()

// File path
VhostRules::filePath()
VhostRules::filePathNullable()

// IP address
VhostRules::ipAddress()
VhostRules::ipAddressNullable()

// Get error messages
VhostRules::messages()
```

---

## 📦 Data Transfer Objects (DTOs)

### VhostCreationData

```php
// From Console Command
$data = VhostCreationData::fromConsoleInput($command);

// From Filament Form
$data = VhostCreationData::fromFilamentForm($formData);

// From Model
$data = VhostCreationData::fromModel($vhostConfig);

// Access properties (readonly)
$data->vnode;           // string
$data->vhost;           // string
$data->phpVersion;      // ?string (default: '8.4')
$data->sslEnabled;      // bool (default: true)
$data->databaseType;    // ?string (default: 'sqlite')
$data->databaseName;    // ?string
$data->adminEmail;      // ?string
$data->webroot;         // ?string
$data->uid;             // ?int
$data->username;        // ?string

// Helper methods
$data->toArray();
$data->getDatabaseName();   // Auto-generate if not set
$data->getUsername();       // Auto-generate if not set
$data->getWebroot();        // Auto-generate if not set
```

### MigrationJobData

```php
// From Console Command
$data = MigrationJobData::fromConsoleInput($command);

// From Filament Form
$data = MigrationJobData::fromFilamentForm($formData);

// From Model
$data = MigrationJobData::fromModel($migrationJob);

// Properties
$data->sourceServer;    // string
$data->targetServer;    // string
$data->domain;          // string
$data->jobName;         // string
$data->migrationType;   // string (full, database-only, files-only)
$data->description;     // ?string
$data->dryRun;          // bool
$data->stepBackup;      // bool
$data->stepCleanup;     // bool
$data->configuration;   // ?array
$data->sshHostId;       // ?int

// Helper methods
$data->toArray();
$data->getSteps();      // Get migration steps based on type
$data->isValid();       // Validate migration configuration
```

### UserPasswordData

```php
// From Console Command
$data = UserPasswordData::fromConsoleInput($command);

// From Filament Form
$data = UserPasswordData::fromFilamentForm($formData);

// Properties
$data->vnode;           // string
$data->email;           // string
$data->password;        // string
$data->generateHash;    // bool
$data->passwordType;    // ?string (mail, database, system)

// Helper methods
$data->toArray();
$data->getDomain();     // Extract domain from email
$data->getUsername();   // Extract username from email
$data->isValidEmail();  // Check email format
```

### SetupJobData

```php
// From Console Command
$data = SetupJobData::fromConsoleInput($command, $templateId);

// From Filament Form
$data = SetupJobData::fromFilamentForm($formData);

// From Model
$data = SetupJobData::fromModel($setupJob);

// Properties
$data->jobName;         // string
$data->templateId;      // int
$data->targetHost;      // string
$data->configuration;   // ?array
$data->description;     // ?string
$data->dryRun;          // bool
$data->priority;        // ?int

// Helper methods
$data->toArray();
$data->mergeWithDefaults($templateDefaults);
```

---

## 🎨 Filament Form Components

### VhostFormComponents

```php
// Server node select
VhostFormComponents::vnodeSelect()          // With relationship
VhostFormComponents::vnodeSelectSimple()    // Without relationship

// Domain input
VhostFormComponents::vhostInput($vnode)

// PHP version select
VhostFormComponents::phpVersionSelect()

// SSL toggle
VhostFormComponents::sslEnabledToggle()

// Database configuration
VhostFormComponents::databaseTypeSelect()
VhostFormComponents::databaseNameInput()

// Admin configuration
VhostFormComponents::adminEmailInput()

// Path configuration
VhostFormComponents::webrootInput()

// Unix user configuration
VhostFormComponents::usernameInput()
VhostFormComponents::uidInput()
VhostFormComponents::gidInput()
```

**Example Form:**

```php
use NetServa\Cli\Filament\Components\VhostFormComponents;

Schema::make([
    Section::make('Server Configuration')
        ->schema([
            VhostFormComponents::vnodeSelect(),
            VhostFormComponents::vhostInput(),
        ]),
    Section::make('Stack Configuration')
        ->schema([
            VhostFormComponents::phpVersionSelect(),
            VhostFormComponents::sslEnabledToggle(),
            VhostFormComponents::databaseTypeSelect(),
        ]),
]);
```

### MigrationFormComponents

```php
// Server selection
MigrationFormComponents::sourceServerSelect()
MigrationFormComponents::targetServerSelect()
MigrationFormComponents::sshHostSelect()

// Migration details
MigrationFormComponents::domainInput()
MigrationFormComponents::jobNameInput()
MigrationFormComponents::migrationTypeSelect()
MigrationFormComponents::descriptionTextarea()

// Options
MigrationFormComponents::dryRunToggle()
MigrationFormComponents::backupToggle()
MigrationFormComponents::cleanupToggle()

// Pre-built sections
MigrationFormComponents::serverSelectionSection()
MigrationFormComponents::migrationOptionsSection()
```

**Example Form:**

```php
use NetServa\Cli\Filament\Components\MigrationFormComponents;

Schema::make([
    MigrationFormComponents::serverSelectionSection(),
    MigrationFormComponents::migrationOptionsSection(),
]);
```

### SetupFormComponents

```php
// Template configuration
SetupFormComponents::templateSelect()
SetupFormComponents::targetHostSelect()
SetupFormComponents::jobNameInput()
SetupFormComponents::priorityInput()

// Template creation
SetupFormComponents::templateNameInput()
SetupFormComponents::templateDisplayNameInput()
SetupFormComponents::templateCategorySelect()
SetupFormComponents::supportedOsSelect()
SetupFormComponents::isActiveToggle()

// Configuration
SetupFormComponents::configurationKeyValue()
SetupFormComponents::descriptionTextarea()
SetupFormComponents::dryRunToggle()
```

---

## 📋 Form Request Classes

### CreateVhostRequest

```php
// In controller or service
public function store(CreateVhostRequest $request)
{
    $validated = $request->validated();
    // or with defaults
    $validated = $request->validatedWithDefaults();

    $data = VhostCreationData::fromFilamentForm($validated);
    $result = $this->vhostService->createVhost($data);
}
```

### UpdatePasswordRequest

```php
public function updatePassword(UpdatePasswordRequest $request)
{
    $validated = $request->validated();
    $data = UserPasswordData::fromFilamentForm($validated);
    // Email is automatically normalized to lowercase
}
```

### CreateMigrationJobRequest

```php
public function store(CreateMigrationJobRequest $request)
{
    $validated = $request->validatedWithDefaults();
    $data = MigrationJobData::fromFilamentForm($validated);
}
```

### CreateSetupJobRequest

```php
public function store(CreateSetupJobRequest $request)
{
    $validated = $request->validatedWithDefaults();
    $data = SetupJobData::fromFilamentForm($validated);
}
```

---

## 🎯 Common Patterns

### Pattern 1: Console Command with Validation

```php
use NetServa\Cli\Validation\Rules\DomainRules;
use NetServa\Cli\DataObjects\VhostCreationData;

class MyCommand extends Command
{
    public function handle()
    {
        // Validate inline with Laravel Prompts
        $domain = text(
            label: 'Enter domain',
            validate: fn($value) => Validator::make(
                ['domain' => $value],
                ['domain' => DomainRules::domain()],
                DomainRules::messages()
            )->fails()
                ? Validator::make(['domain' => $value], ['domain' => DomainRules::domain()])->errors()->first('domain')
                : null
        );

        // Create DTO
        $data = VhostCreationData::fromConsoleInput($this);

        // Use service
        $result = $this->vhostService->createVhost($data);
    }
}
```

### Pattern 2: Filament Form Schema

```php
use NetServa\Cli\Filament\Components\VhostFormComponents;

class MyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            VhostFormComponents::vnodeSelect(),
            VhostFormComponents::vhostInput(),
            VhostFormComponents::phpVersionSelect(),
        ]);
    }
}
```

### Pattern 3: Service Layer with DTOs

```php
class MyService
{
    public function createVhost(VhostCreationData $data): array
    {
        // Type-safe access to all properties
        $vnode = $data->vnode;
        $vhost = $data->vhost;

        // Auto-generated values
        $dbName = $data->getDatabaseName();
        $username = $data->getUsername();

        // ...
    }
}
```

---

## 🔍 Testing Examples

### Testing Validation Rules

```php
use NetServa\Cli\Validation\Rules\PasswordRules;

test('password validation enforces minimum length', function () {
    $validator = Validator::make(
        ['password' => 'short'],
        ['password' => PasswordRules::secure()]
    );

    expect($validator->fails())->toBeTrue();
});
```

### Testing DTOs

```php
use NetServa\Cli\DataObjects\VhostCreationData;

test('DTO converts from console input', function () {
    $command = Mockery::mock(Command::class);
    $command->shouldReceive('argument')->with('vnode')->andReturn('motd');
    $command->shouldReceive('argument')->with('vhost')->andReturn('example.com');

    $data = VhostCreationData::fromConsoleInput($command);

    expect($data->vnode)->toBe('motd');
    expect($data->vhost)->toBe('example.com');
});
```

---

## 📚 Additional Resources

- [Filament 4.1 Compliance Documentation](FILAMENT_4.1_COMPLIANCE.md)
- [NetServa 3.0 Coding Style](resources/docs/NetServa_3.0_Coding_Style.md)
- [Complete CRUD Pattern](COMPLETE_CRUD_PATTERN.md)

---

**Last Updated:** 2025-10-08
**Version:** 1.0.0
