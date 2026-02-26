# symfony-hex-template

**Symfony 7.3 skeleton** pre aplikácie s **Event Sourcing + CQRS + Hexagonal Architecture + DDD**.

Obsahuje celú infraštruktúru pre Event Sourcing, ready-to-use CQRS buses a architektonické pravidlá — stačí pridať vlastné Bounded Contexty.

---

## Architektonické vzory

### Hexagonal Architecture (Ports & Adapters)

```
┌────────────────────────────────────────────────────┐
│                    Infrastructure                   │
│  ┌──────────────┐  ┌──────────────┐                │
│  │    HTTP       │  │   Doctrine   │  Symfony DI   │
│  │  Controllers  │  │  EventStore  │  Messenger     │
│  └──────────────┘  └──────────────┘                │
└────────────────────────────────────────────────────┘
┌────────────────────────────────────────────────────┐
│                    Application                      │
│  ┌──────────────┐  ┌──────────────┐                │
│  │   Commands   │  │   Queries    │  Event         │
│  │   Handlers   │  │   Handlers   │  Handlers      │
│  └──────────────┘  └──────────────┘                │
└────────────────────────────────────────────────────┘
┌────────────────────────────────────────────────────┐
│                      Domain                         │
│  ┌──────────────┐  ┌──────────────┐                │
│  │  Aggregates  │  │    Events    │  Value         │
│  │ (AggregateRoot) │  (DomainEvent) │  Objects      │
│  └──────────────┘  └──────────────┘                │
└────────────────────────────────────────────────────┘
```

### Event Sourcing

- Aggregáty sa ukladajú ako sekvencia udalostí do `event_store`
- `AggregateRoot` base class spracúva aplikovanie a replay eventov
- Optimistická konkurenčná kontrola pomocou verzie
- Snapshots pre optimalizáciu načítavania veľkých aggregátov

### CQRS

- Oddelený `command.bus` a `query.bus` cez Symfony Messenger
- Commands menia stav, Queries čítajú dáta
- `CompositeEventSerializer` pre plugovanie serializerov podľa kontextu

### Domain-Driven Design

- Bounded Contexts s vlastnou Domain/Application/Infrastructure vrstvou
- Aggregáty s business pravidlami
- Value Objects pre validáciu dát
- Domain Events pre decouplovanú komunikáciu

---

## Štruktúra projektu

```
src/
├── Kernel.php
├── Infrastructure/
│   ├── Bus/
│   │   ├── SymfonyCommandBus.php
│   │   └── SymfonyQueryBus.php
│   ├── Event/
│   │   ├── CompositeEventSerializer.php   # Aggreguje serializers pre všetky kontexty
│   │   └── FrequencyBasedSnapshotStrategy.php
│   ├── Http/
│   │   ├── Controller/BaseController.php
│   │   └── Exception/ValidationException.php
│   └── Persistence/
│       ├── Doctrine/Entity/EventStoreEntity.php
│       ├── EventStore/
│       │   ├── AbstractEventStoreRepository.php  # Base pre repository kontextov
│       │   ├── AggregateTypeResolver.php
│       │   └── DoctrineEventStore.php
│       ├── Exception/InfrastructureException.php
│       └── Snapshot/DoctrineSnapshotStore.php
└── Shared/
    ├── Application/
    │   ├── CommandBus.php
    │   └── QueryBus.php
    ├── Domain/
    │   ├── Event/DomainEvent.php
    │   └── Model/
    │       ├── AggregateRoot.php
    │       └── AggregateSnapshot.php
    ├── Event/
    │   ├── EventDispatcher.php
    │   ├── EventSerializer.php
    │   ├── EventStore.php
    │   ├── EventStoreRepository.php
    │   ├── EventStream.php
    │   ├── SnapshotStore.php
    │   └── SnapshotStrategy.php
    ├── Infrastructure/Event/DomainEventDispatcher.php
    └── ValueObject/
        ├── Email.php
        └── Uuid.php
```

---

## Technológie

- **PHP 8.4+** — moderné features (readonly, enums, fibers)
- **Symfony 7.3** — DI container, Messenger, EventDispatcher
- **Doctrine DBAL** — vrstva pre Event Store (bez ORM overhead)
- **PostgreSQL 16** — JSON podpora pre event data
- **RabbitMQ 3.13** — async spracovanie integration eventov
- **Docker** — kontajnerizácia

---

## Rýchly štart

### Prerekvizity
- Docker & Docker Compose

### Inštalácia

```bash
git clone <repository-url>
cd symfony-hex-template

# Zostaviť image a spustiť kontajnery
docker-compose up -d

# Nainštalovať závislosti
docker exec <app-container> composer install

# Aplikovať migrácie
docker exec <app-container> composer doctrine:migrate

# Aplikovať migrácie pre testovú DB
docker exec <app-container> composer doctrine:migrate:test
```

### Spustenie nástrojov (bez docker-compose)

```bash
# Zostaviť image
docker build -t my-app .

# Spustiť nástroje
docker run --rm my-app composer phpstan
docker run --rm my-app composer phpcs
docker run --rm my-app composer test
```

---

## Pridanie nového Bounded Contextu

### 1. Štruktúra adresárov

```
src/Order/
├── Domain/
│   ├── Model/Order.php                    # extends AggregateRoot
│   ├── Event/OrderCreatedEvent.php        # implements DomainEvent
│   ├── ValueObject/OrderAmount.php
│   └── Repository/OrderRepositoryInterface.php
├── Application/
│   ├── Command/Create/
│   │   ├── CreateOrderCommand.php
│   │   └── CreateOrderHandler.php
│   └── Query/Get/
│       ├── GetOrderQuery.php
│       └── GetOrderHandler.php
└── Infrastructure/
    ├── Event/OrderEventSerializer.php     # implements EventSerializer
    └── Persistence/OrderRepository.php   # extends AbstractEventStoreRepository
```

### 2. Aggregát (Domain layer)

```php
final class Order extends AggregateRoot
{
    private OrderAmount $amount;

    public static function create(Uuid $id, OrderAmount $amount): self
    {
        $order = new self();
        $order->apply(new OrderCreatedEvent($id, $amount));
        return $order;
    }

    protected function handleEvent(DomainEvent $event): void
    {
        match ($event::class) {
            OrderCreatedEvent::class => $this->handleOrderCreated($event),
        };
    }

    private function handleOrderCreated(OrderCreatedEvent $event): void
    {
        $this->uuid = $event->getOrderId();
        $this->amount = $event->getAmount();
    }
}
```

### 3. Event Serializer (Infrastructure layer)

```php
final class OrderEventSerializer implements EventSerializer
{
    public function serialize(DomainEvent $event): string
    {
        return json_encode($event->toArray());
    }

    public function deserialize(string $data, string $type): DomainEvent
    {
        return $type::fromArray(json_decode($data, true));
    }

    public function supports(string $eventType): bool
    {
        return str_starts_with($eventType, 'App\\Order\\');
    }
}
```

### 4. Repository (Infrastructure layer)

```php
final class OrderRepository extends AbstractEventStoreRepository
{
    protected function createAggregate(): AggregateRoot
    {
        return new Order();
    }
}
```

### 5. Registrácia v services.yaml

```yaml
# Command handlers
App\Order\Application\Command\:
    resource: '../src/Order/Application/Command/*/*Handler.php'
    tags:
        - { name: messenger.message_handler, bus: command.bus }

# Event serializer - pridať do CompositeEventSerializer
App\Infrastructure\Event\CompositeEventSerializer:
    arguments:
        - '@App\Order\Infrastructure\Event\OrderEventSerializer'

# Repository interface binding
App\Order\Domain\Repository\OrderRepositoryInterface: '@App\Order\Infrastructure\Persistence\OrderRepository'
```

### 6. Snapshot podpora (voliteľné)

Implementovať `AggregateSnapshot` a registrovať factory v `DoctrineSnapshotStore::createSnapshotFromRow()`.

---

## Databázová schéma

Vytváraná migráciou `Version20260101000000`:

```sql
-- Event Store
CREATE TABLE event_store (
    aggregate_id   UUID         NOT NULL,
    event_type     VARCHAR(255) NOT NULL,
    event_data     JSON         NOT NULL,
    version        INT          NOT NULL,
    occurred_at    TIMESTAMP    NOT NULL,
    aggregate_type VARCHAR(255) NOT NULL,
    PRIMARY KEY (aggregate_id, version)
);

-- Snapshots
CREATE TABLE snapshots (
    aggregate_id   UUID         NOT NULL,
    aggregate_type VARCHAR(255) NOT NULL,
    snapshot_data  JSON         NOT NULL,
    version        INT          NOT NULL,
    created_at     TIMESTAMP    NOT NULL,
    PRIMARY KEY (aggregate_id)
);
```

---

## Vývoj

### Workflow

```bash
# 1. Implementácia
# 2. Testy
composer test

# 3. Kód kvalita
composer phpstan
composer phpcs

# 4. Architekúra
composer deptrac

# 5. Automatické opravy
composer phpcbf
composer rector:dry-run
```

### Príkazy

| Príkaz | Popis |
|--------|-------|
| `composer test` | Spustí Pest testy |
| `composer test-coverage` | Testy s pokrytím |
| `composer phpstan` | Statická analýza (level 6) |
| `composer phpcs` | Kontrola štýlu (PSR-12) |
| `composer phpcbf` | Automatická oprava štýlu |
| `composer rector` | Automatický refactoring |
| `composer deptrac` | Kontrola architektonických hraníc |
| `composer doctrine:migrate` | Aplikovať migrácie |
| `composer doctrine:sync` | Generovať diff + migrovať |
| `composer sf:cache-clear` | Vyčistiť Symfony cache |

### Architektonické pravidlá (enforced by Deptrac)

- **Domain** môže závisieť len na `SharedDomain`, `SharedValueObject`, `SharedEvent`
- **Application** môže závisieť na `Domain` + všetkých Shared vrstvách
- **Infrastructure** môže závisieť na všetkých vrstvách
- Cross-context komunikácia len cez Domain Events

---

## Testovanie

```
tests/
├── bootstrap.php
├── TestCase.php
├── Pest.php
└── Unit/
    └── Infrastructure/
        └── Persistence/EventStore/
            ├── AggregateTypeResolverTest.php
            └── CompoundPrimaryKeyTest.php
```

Pre nový context pridať:

```
tests/Order/
├── Doubles/
│   ├── InMemoryEventStore.php
│   ├── InMemoryOrderRepository.php
│   └── InMemorySnapshotStore.php
├── Helpers/
│   └── OrderTestFactory.php
└── Unit/Domain/Model/OrderTest.php
```

---

## Ďalšie čítanie

- [Domain-Driven Design](https://martinfowler.com/bliki/DomainDrivenDesign.html)
- [CQRS Pattern](https://martinfowler.com/bliki/CQRS.html)
- [Event Sourcing](https://martinfowler.com/eaaDev/EventSourcing.html)
- [Hexagonal Architecture](https://alistair.cockburn.us/hexagonal-architecture/)

---

## Licencia

[MIT License](LICENSE)
