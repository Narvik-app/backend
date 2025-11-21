# Narvik Backend - Agents Documentation

## Overview

This is the backend API of **Narvik**, a comprehensive application for managing French associations, particularly sporting associations. The application is built using modern Symfony and API Platform technologies.

**Official Website:** https://about.narvik.app/

## Technology Stack

### Backend Framework
- **Symfony 7.3** - Modern PHP web framework
- **API Platform 4.0** - Powerful REST/GraphQL API platform
- **PHP 8.4+** - Required PHP version with latest features

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

## Architecture Principles

### 1. **Multi-Tenancy**
- Each club operates as an independent tenant
- Data isolation through club-based filtering
- Plugin system allows feature customization per club

### 2. **API-First Design**
- RESTful API built with API Platform
- OpenAPI documentation auto-generation
- Standardized error handling and validation

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

## License

GNU AGPLv3 License - See [LICENSE](LICENSE) file for details.

---

**Created by:** Benoît VIGNAL  
**Version:** 3.12.1  
**Last Updated:** 2025-11-21
