# Narvik Backend - Agents Documentation

## Overview

This is the backend API of **Narvik**, a comprehensive application for managing French associations, particularly sporting associations. The application is built using modern Symfony and API Platform technologies.

**Official Website:** https://about.narvik.app/

## Technology Stack

### Backend Framework
- **Symfony 8.0** - Modern PHP web framework
- **API Platform 4.0** - Powerful REST/GraphQL API platform
- **PHP 8.5+** - Required PHP version with latest features

### Key Dependencies
- **Doctrine ORM 3.3** - Database abstraction layer
- **PostgreSQL** - Primary database (requires `unaccent` extension)
- **FrankenPHP** - Modern PHP runtime for production
- **Twig** - Templating engine
- **Monolog** - Logging system
- **League OAuth2 Server** - Authentication and authorization

### Frontend Integration
The frontend application is available at: https://github.com/Narvik-app/frontend

The frontend is built with:
- **Nuxt** - Vue.js framework
- **Nuxt UI** - UI component library
- Makes API calls to this backend for all data operations

## Project Structure

### Core Application (`src/`)
```
src/
├── Command/           # Symfony console commands
├── Controller/        # API and web controllers
├── DataFixtures/      # Database fixtures
├── Doctrine/          # Doctrine extensions
├── DQL/              # Custom Doctrine Query Language functions
├── Entity/           # Domain entities (see Plugin Architecture below)
├── Enum/             # PHP enumerations
├── EventSubscriber/  # Symfony event subscribers
├── Filter/           # API Platform filters
├── Importer/         # Data import services
├── Mailer/           # Email services
├── Message/          # Symfony Messenger messages
├── MessageHandler/   # Symfony Messenger handlers
├── OpenApi/          # OpenAPI documentation customizations
├── Repository/       # Doctrine repositories
├── Security/         # Security components (Voters, etc.)
├── Serializer/       # Custom serializers
└── Service/          # Business logic services
```

### Plugin Architecture

The application uses a **plugin-based architecture** under `src/Entity/ClubDependent/Plugin/`. This allows for modular functionality that can be enabled/disabled per club:

```
src/Entity/ClubDependent/Plugin/
├── Emailing/         # Email management functionality
│   ├── Email.php
│   └── EmailTemplate.php
└── Presence/         # Member presence tracking
    ├── ExternalPresence.php
    └── MemberPresence.php
```

Each plugin module represents a separate feature set that clubs can optionally use, making the system highly flexible and modular.

### Configuration (`config/`)
```
config/
├── packages/         # Symfony package configurations
├── routes.yaml       # Route definitions
└── services.yaml     # Dependency injection configuration
```

### Database (`migrations/`)
Contains Doctrine migration files for database schema versioning and updates.

Generate using `make db-make-migration`

See @CONTRIBUTING.md for more information, specially step 7 with the specific fields to not include in the migration.

## Agent Context Exclusions

When working with agents, certain files and directories should be excluded from the agent's context to ensure security and performance:

### Sensitive Configuration Files
- **`.env*`** - Environment configuration files containing secrets, API keys, and database credentials
- **`config/jwt/`** - JWT configuration and keys (this directory is gitignored)

### Generated and Cache Directories
- **`var/`** - Symfony cache, logs, and temporary files
  - `var/cache/` - Application cache
  - `var/log/` - Application logs
  - `var/tmp/` - Temporary files
- **`vendor/`** - PHP dependencies (handled by Composer)

### Development and Build Artifacts
- **`node_modules/`** - Node.js dependencies (if present)
- **`.git/`** - Git repository data and history
- **`*.log`** - Log files
- **`*.tmp`** - Temporary files
- **`*.bak`** - Backup files

### Database and Storage
- Database dumps and backups
- Uploaded user files in `private/`

### Recommended Agent Focus Areas
Agents should primarily focus on:
- **`src/`** - Application source code
- **`config/`** - Configuration files (excluding sensitive JWT configs)
- **`tests/`** - Test files
- **`migrations/`** - Database migrations
- **`docs/`** - Documentation
- **Project root files** - `README.md`, `composer.json`, `Makefile`, etc.

This ensures agents work with the actual application code while avoiding sensitive data, generated files, and dependencies that could slow down processing.

## Test Structure

The testing architecture is comprehensive and well-organized:

### Test Organization (`tests/`)
```
tests/
├── AbstractTestCase.php      # Base test class
├── bootstrap.php             # Test bootstrap configuration
├── e2e/                      # End-to-end API tests
│   ├── AbstractApiTestCase.php
│   ├── Controller/           # API endpoint tests
│   ├── Entity/               # Entity behavior tests
│   │   ├── Abstract/         # Abstract test cases
│   │   ├── ClubDependent/    # Club-dependent entity tests
│   │   └── Plugin/           # Plugin-specific tests
│   │       ├── Emailing/
│   │       └── Presence/
│   └── CustomApiTestAssertionsTrait.php
├── Factory/                  # Zenstruck Foundry factories
│   ├── *.php                 # Entity factories for testing
│   └── ClubDependent/
│       └── Plugin/
│           ├── Emailing/
│           └── ...
├── fixtures/                 # Test data files
│   ├── *.csv
│   ├── *.xlsx
│   └── *.pdf
├── functional/               # Functional tests
│   └── DQL/                  # Custom DQL function tests
└── Story/                    # Zenstruck Foundry stories
    └── *.php
```

### Testing Tools
- **PHPUnit 12** - Primary testing framework
- **Zenstruck Foundry** - Factory library for test data
- **DAMA Doctrine Test Bundle** - Database testing isolation
- **API Platform Testing** - API-specific test utilities

### Test Types
1. **Unit Tests** - Individual component testing
2. **Entity Tests** - Domain model behavior validation
3. **API End-to-End Tests** - Full HTTP request/response testing
4. **Functional Tests** - Business logic validation
5. **Factory Tests** - Test data generation validation

## Development Workflow

### Using Makefile

**All commands should be run through the Makefile** provided in the project root. This ensures consistency and proper container execution.

#### Essential Commands:
```bash
make help           # Show all available commands
make start          # Build and start development containers
make build          # Build Docker images
make up             # Start containers in detached mode
make down           # Stop containers
make sh             # Connect to PHP container shell
make test           # Run test suite
make test-with-coverage  # Run tests with coverage report
make reload-fixture # Reset database with fixtures
make cc             # Clear cache
make db-dump        # Create database backup
```

#### Database Management:
```bash
make db-restore     # Restore from dump
make db-post-install # Install PostgreSQL extensions
```

#### Code Quality:
```bash
make rector         # Run rector to fix code issues
make rector-dry-run # Run rector in dry-run mode to see what would be changed
```
#### Production Deployment:
```bash
make start-prod     # Start production environment
make build-prod     # Build production images
```

### Development Setup

1. **Prerequisites:**
   - Docker and Docker Compose installed
   - PostgreSQL database (provided via Docker)
     - `unaccent` extension enabled, can be installed using `make db-post-install`

2. **Quick Start:**
   ```bash
   make start
   ```

3. **Access Points:**
   - API Base URL: http://localhost:8000
   - API Documentation: http://localhost:8000/api
   - Profiler: http://localhost:8000/_profiler

### API Documentation

The API is automatically documented using API Platform's OpenAPI integration. Access the interactive documentation at:
- **Development:** http://localhost:8000/api

### Docker and Makefile Command Execution

**CRITICAL REQUIREMENT:** All PHP commands, Composer operations, PHPUnit commands, and Rector processes **MUST** be executed exclusively through the Makefile or Docker container environment.  
**NEVER** run PHP-related commands directly on the host system.

#### Prohibited Commands (Do NOT run on host):
```bash
# ❌ WRONG - Never run these on host system
php -v
php artisan --version  # If applicable
composer install
composer update
./vendor/bin/rector process
./vendor/bin/rector process --dry-run
phpunit
```

#### Correct Command Execution Patterns:

**1. Using Makefile Targets (Recommended):**
```bash
# ✅ CORRECT - Use Makefile for all operations
make sh                    # Connect to PHP container shell
make test                  # Run tests
make test-with-coverage    # Run tests with coverage
make cc                    # Clear cache
make build                 # Build Docker images
make start                 # Start development environment
```

**2. Using Docker Compose Directly:**
```bash
# ✅ CORRECT - Execute via Docker container
docker compose exec php php -v
docker compose exec php composer install
docker compose exec php ./vendor/bin/rector process
docker compose exec php phpunit
```

**3. From Within PHP Container:**
```bash
# ✅ CORRECT - When inside container via `make sh`
php -v                    # Works inside container
composer install          # Works inside container
./vendor/bin/rector process  # Works inside container
phpunit                   # Works inside container
```

#### Why This Requirement Exists:

1. **Environment Consistency:** Ensures all developers use the same PHP version, extensions, and dependencies
2. **Dependency Management:** All PHP dependencies are installed within the containerized environment
3. **Reproducible Builds:** Guarantees consistent behavior across different development machines
4. **Security:** Isolates PHP execution from the host system for better security

#### Quick Reference for Common Operations:

| Operation            | Correct Command                   | Incorrect Command                                 |
|----------------------|-----------------------------------|---------------------------------------------------|
| Check PHP version    | `make sh` then `php -v`           | `php -v` (on host)                                |
| Install dependencies | `make sh` then `composer install` | `composer install` (on host)                      |
| Run Rector           | `make rector`                     | `./vendor/bin/rector process` (on host)           |
| Rector dry-run       | `make rector-dry-run`             | `./vendor/bin/rector process --dry-run` (on host) |
| Run tests            | `make test`                       | `phpunit` (on host)                               |
| Clear cache          | `make cc`                         | `php bin/console cache:clear` (on host)           |

**Always remember:** If you need to execute any PHP, Composer, or Rector command, use the Makefile targets or Docker container execution patterns shown above.

## Permission System

### Overview
The application implements a granular permission system with **Access/Edit** levels for supervisors. Admins automatically have all permissions.

### Permission Enum (`src/Enum/Permission.php`)
```php
enum Permission: string {
  case EMAIL_ACCESS = 'EMAIL_ACCESS';
  case EMAIL_EDIT = 'EMAIL_EDIT';
  case EMAIL_TEMPLATE_ACCESS = 'EMAIL_TEMPLATE_ACCESS';
  case EMAIL_TEMPLATE_EDIT = 'EMAIL_TEMPLATE_EDIT';
  case IMPORT_MEMBERS_ACCESS = 'IMPORT_MEMBERS_ACCESS';
  case IMPORT_MEMBERS_EDIT = 'IMPORT_MEMBERS_EDIT';
  // ... etc
}
```

### Hierarchy Rule
**EDIT implies ACCESS**: If a user has `*_EDIT`, they automatically have `*_ACCESS`. This is enforced in:
- Backend: `Member.hasPermission()` and `PermissionVoter`
- Frontend: `useSelfUser.can()`

### Cross-Feature Implied Permissions

Beyond the generic EDIT→ACCESS rule, some permissions imply *other features'* ACCESS permissions because one feature's UI/workflow depends on another (e.g. making a sale needs to browse inventory; recording a loan needs to browse loan items). These special cases live in `Permission::getImpliedPermissions()` (`src/Enum/Permission.php`):

```php
if ($this === self::SALE_NEW) {
  $implied[] = self::SALE_HISTORY_ACCESS;
  $implied[] = self::SALE_INVENTORY_ACCESS;
  $implied[] = self::SALE_CATEGORIES_ACCESS;
  $implied[] = self::SALE_PAYMENT_MODES_ACCESS;
}

if ($this === self::LOAN_EDIT) {
  $implied[] = self::LOAN_ITEMS_ACCESS;
}
```

Enforcement is fully generic and needs no per-case wiring beyond the enum:
- **`MemberPermissionSubscriber::onPostWrite`** - when a `MemberPermission` is granted, auto-persists every permission returned by `getImpliedPermissions()` that isn't already explicitly granted.
- **`MemberPermissionSubscriber::onPreWrite`** - on delete, blocks removing a permission that is still implied by another permission the member/template currently holds.

**When a new permission's feature depends on another feature's read access, add a case to `getImpliedPermissions()`** rather than special-casing it in controllers/voters — the subscriber picks it up automatically for both grant and delete-protection. Keep the frontend `Permission` enum comment (`app/types/api/permissions.ts`) in sync for discoverability, since the frontend has no implication logic of its own — it just reloads permissions from the backend after each toggle to reflect auto-grants.

### Permission Templates

Permission Templates allow defining reusable sets of permissions that can be assigned to multiple members. This simplifies permission management.

#### Key Entities
- **`PermissionTemplate` Entity** - Defines a named template with a set of permissions
  - Club-dependent entity (each club has its own templates)
  - Contains a name and a collection of `MemberPermission` records
- **`MemberPermission` Entity** - Stores individual permissions
  - Can be linked to either a `Member` OR a `PermissionTemplate` (not both)
  - Validation constraint ensures exactly one is set
- **`Member` Entity** - Has an optional `permissionTemplate` field
  - When template is deleted, the field is set to NULL (not cascade delete)

#### Permission Resolution Order
When checking if a member has a permission:
1. Check member-specific permissions first (override)
2. If not found, check template permissions (if template is assigned)
3. Apply EDIT implies ACCESS rule at both levels

```php
// In Member.hasPermission()
// 1. Check member-specific permissions first
foreach ($this->permissions as $memberPermission) { ... }

// 2. Check template permissions if assigned
if ($this->permissionTemplate !== null) {
  foreach ($this->permissionTemplate->getPermissions() as $templatePermission) { ... }
}
```

#### API Endpoints
- `GET /clubs/{clubUuid}/permission-templates` - List templates (supervisor+)
- `POST /clubs/{clubUuid}/permission-templates` - Create template (admin)
- `PATCH /clubs/{clubUuid}/permission-templates/{uuid}` - Update template (admin)
- `DELETE /clubs/{clubUuid}/permission-templates/{uuid}` - Delete template (admin)

### Key Components
- **`MemberPermission` Entity** - Stores permissions per member or template (API Platform resource)
- **`PermissionTemplate` Entity** - Defines reusable permission sets
- **`PermissionVoter`** - Symfony voter for permission checks
- **`Member.hasPermission()`** - Checks permission with hierarchy and template support

### Security Annotations
Use permission checks in API Platform security annotations:
```php
// For read operations (ACCESS)
security: "is_granted('" . Permission::EMAIL_ACCESS->value . "', request)"

// For write operations (EDIT)
security: "is_granted('" . Permission::EMAIL_EDIT->value . "', request)"
```

## Architecture Principles

### 1. **Multi-Tenancy**
- Each club operates as an independent tenant
- Data isolation through club-based filtering
- Plugin system allows feature customization per club

### 2. **API-First Design**
- RESTful API built with API Platform
- OpenAPI documentation auto-generation
- Standardized error handling and validation
- Single source of truth: default values, computed labels, and validation rules belong here, not duplicated in the frontend. Only apply a default when the client omits the field entirely (don't let the frontend send a placeholder value that masks the backend default).

### 3. **Modular Architecture**
- Plugin-based feature system
- Clear separation of concerns
- Flexible configuration per club

### 4. **Security**
- OAuth2 authentication system
- Role-based access control (RBAC)
- CSRF protection and input validation

### 5. **Testing Strategy**
- Comprehensive test coverage
- Realistic test data with factories
- End-to-end API testing
- Database isolation for tests

## Contributing

For detailed development guidelines, see the [Contributing Guide](CONTRIBUTING.md) and [Development Documentation](docs/0.README.md).

### Code Quality
- Conventional commit messages recommended

### LLM
- Never commit automatically, an human should always review and approve changes.
- Do not create any PR or github action yourself. (e.g: `git push -f`, `gh pr create`)

## License

GNU AGPLv3 License - See [LICENSE](LICENSE) file for details.

---

**Created by:** Benoît VIGNAL  
**Version:** 3.15  
**Last Updated:** 2026-01-03
