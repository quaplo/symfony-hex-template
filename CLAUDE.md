# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands
- Language preference: Slovak (user's preferred language for responses)

### Testing
- `composer test` - Run Pest tests
- `composer test-coverage` - Run tests with coverage report
- `composer test-watch` - Run tests in watch mode

### Code Quality
- `composer phpstan` - Run PHPStan static analysis (level 6)
- `composer phpcs` - Check code style (PSR-12)
- `composer phpcbf` - Auto-fix code style violations
- `composer rector` - Apply Rector refactoring rules
- `composer rector:dry-run` - Preview Rector changes without applying
- `composer deptrac` - Check architectural layer dependencies

### Database
- `composer doctrine:diff` - Generate migration from entity changes
- `composer doctrine:migrate` - Apply pending migrations
- `composer doctrine:sync` - Generate diff and migrate in sequence

### Symfony
- `composer sf:cache-clear` - Clear Symfony cache
- `php bin/console` - Access Symfony console commands

## Architecture Overview

This is a **Symfony 7.3** skeleton implementing **Event Sourcing + CQRS + Hexagonal Architecture + Domain-Driven Design**.

The skeleton contains infrastructure and shared components only — bounded contexts are added by the developer.

### Key Architectural Patterns

**Event Sourcing**: Aggregates are persisted as sequences of events rather than current state
- Events stored in `DoctrineEventStore` (PostgreSQL `event_store` table)
- `AggregateRoot` base class handles event application and replay
- Optimistic concurrency control using aggregate versions
- Snapshot support via `DoctrineSnapshotStore` (every 10 events by default)

**CQRS**: Command and Query Responsibility Segregation
- Separate `command.bus` and `query.bus` via Symfony Messenger
- Commands for state changes, Queries for reads
- `CompositeEventSerializer` aggregates serializers from all contexts (configured in `services.yaml`)

**Hexagonal Architecture**: Domain isolation from infrastructure
- Domain layer independent of external concerns
- Application layer defines ports (interfaces)
- Infrastructure layer implements adapters

**Domain-Driven Design**: Business logic organized by domain
- Bounded Contexts with own Domain/Application/Infrastructure layers
- Aggregate Roots with business rules
- Value Objects for data integrity
- Domain Events for decoupled communication

### Directory Structure

```
src/
├── Infrastructure/          # Global infrastructure adapters
│   ├── Bus/                 # SymfonyCommandBus, SymfonyQueryBus
│   ├── Event/               # CompositeEventSerializer, FrequencyBasedSnapshotStrategy
│   ├── Http/                # BaseController, ValidationException
│   └── Persistence/         # DoctrineEventStore, DoctrineSnapshotStore, AggregateTypeResolver
└── Shared/                  # Cross-domain skeleton components
    ├── Application/         # CommandBus, QueryBus interfaces
    ├── Domain/              # AggregateRoot, AggregateSnapshot, DomainEvent
    ├── Event/               # EventStore, EventSerializer, SnapshotStore interfaces
    ├── Infrastructure/      # DomainEventDispatcher
    └── ValueObject/         # Email, Uuid
```

### Dependency Rules (enforced by Deptrac)

- **Domain**: May only depend on Shared components
- **Application**: May depend on Domain + Shared
- **Infrastructure**: May depend on all layers
- **SharedValueObject**: Lowest layer, no dependencies
- Cross-domain communication only through Domain Events

## Testing Strategy

**Framework**: Pest (behavior-driven testing)
- Unit tests in `tests/{Context}/Unit/`
- Integration tests in `tests/{Context}/Integration/`

**Test Doubles**: In-memory implementations for unit testing
- Create `InMemoryEventStore`, `InMemoryRepository` per new context
- Located in `tests/{Context}/Doubles/`

**Current tests** (skeleton-level):
- `tests/Unit/Infrastructure/Persistence/EventStore/AggregateTypeResolverTest.php`
- `tests/Unit/Infrastructure/Persistence/EventStore/CompoundPrimaryKeyTest.php`

## Code Quality Tools

### PHPStan (Static Analysis)
- Level 6 configuration in `phpstan.dist.neon`
- Baseline file: `phpstan-baseline.neon`
- `reportUnmatchedIgnoredErrors: false` — safe for skeleton with no contexts yet

### Rector (Automated Refactoring)
- PHP 8.4 features enabled
- Symfony 7.3 rules applied
- Doctrine ORM rules included
- Configuration in `rector.php`

### PHP CodeSniffer
- PSR-12 coding standard
- Configuration in `phpcs.xml.dist`
- Auto-fix available via `phpcbf`

### Deptrac (Architecture Validation)
- Enforces clean architecture boundaries
- Configuration in `deptrac.yaml`
- Uses generic regex for new contexts: `src/(?!Shared|Infrastructure)[^/]+/Domain`

## Development Workflow

1. **Implementation**: Make changes following DDD patterns
2. **Testing**: `composer test` to verify functionality
3. **Quality**: `composer phpstan && composer phpcs` for standards
4. **Architecture**: `composer deptrac` to validate layer dependencies
5. **Refactoring**: `composer rector:dry-run` for modernization
6. **Database**: `composer doctrine:sync` for schema changes

## Event Sourcing Implementation

### Key Components
- `EventStore` interface → `DoctrineEventStore` implementation
- `AggregateRoot` base class for event-sourced aggregates
- `EventSerializer` interface → `CompositeEventSerializer` (pluggable per context)
- `AbstractEventStoreRepository` — base class for context repositories
- Optimistic concurrency control using aggregate versions

### Event Flow
1. Command → Application Handler
2. Load Aggregate from Event Stream (or Snapshot + delta)
3. Execute Business Logic → `apply(new SomeDomainEvent(...))`
4. Save Events to EventStore
5. Dispatch Events for Side Effects (read model projections, etc.)

### Database Schema
Two tables created by `migrations/Version20260101000000.php`:
- `event_store`: `aggregate_id`, `event_type`, `event_data`, `version`, `occurred_at`, `aggregate_type`
- `snapshots`: `aggregate_id`, `aggregate_type`, `snapshot_data`, `version`, `created_at`

## Adding New Bounded Contexts

1. Create directory structure: `src/NewContext/{Domain,Application,Infrastructure}/`
2. Implement `EventSerializer` for the context and register in `CompositeEventSerializer` args in `services.yaml`
3. Extend `AbstractEventStoreRepository` for aggregate persistence
4. Register Command/Query handlers as `messenger.message_handler` in `services.yaml`
5. Create test structure in `tests/NewContext/` with `Doubles/` and `Helpers/`
6. Add database migrations for any read model tables

### services.yaml pattern for new context

```yaml
# Command handlers
App\MyContext\Application\Command\:
    resource: '../src/MyContext/Application/Command/*/*Handler.php'
    tags:
        - { name: messenger.message_handler, bus: command.bus }

# Query handlers
App\MyContext\Application\Query\:
    resource: '../src/MyContext/Application/Query/*/*Handler.php'
    tags:
        - { name: messenger.message_handler, bus: query.bus }

# Register event serializer
App\Infrastructure\Event\CompositeEventSerializer:
    arguments:
        - '@App\MyContext\Infrastructure\Event\MyContextEventSerializer'

# Repository binding
App\MyContext\Domain\Repository\MyRepositoryInterface: '@App\MyContext\Infrastructure\Persistence\MyRepository'
```

## Docker Setup

### Building & Running

```bash
# Build image
docker build -t my-app .

# Run tools
docker run --rm my-app composer phpstan
docker run --rm my-app composer phpcs
docker run --rm my-app composer test

# With docker-compose (requires db + rabbitmq)
docker-compose up -d
docker exec <app-container> composer doctrine:migrate
docker exec <app-container> composer doctrine:migrate:test
```

> **Note**: The `hex-notes-app-1` container maps to a different project directory (`/Users/gregordemo/Projects/hex-notes`) — always build a new image for this project.

## Important Notes

- **kernel.request priority**: `RouterListener` beží na priorite 32 — auth/middleware listenery musia mať prioritu > 32 (odporúčané: 100), inak sa nespustia pre neexistujúce routes
- **config/packages/ only**: `MicroKernelTrait` auto-loaduje len `config/packages/*.yaml` — custom config (napr. `authorization.yaml`) musí byť v `config/packages/`, nie v `config/`
- **Docker env reload**: `docker compose restart` NEreloaduje `.env` — treba `docker compose up -d --force-recreate <service>`
- **PHP 8.4+** required with modern features enabled
- **PostgreSQL** recommended for JSON event data extraction
- All aggregates must extend `AggregateRoot`
- `DoctrineSnapshotStore::createSnapshotFromRow()` throws `RuntimeException` by default — implement snapshot factory per context
- `CompositeEventSerializer` with empty args will throw `RuntimeException` if an event is serialized without a matching serializer registered
- Deptrac uses generic regex patterns — new contexts are auto-detected without modifying `deptrac.yaml`
