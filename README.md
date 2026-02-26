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
├── Authorization/                          # Bounded Context: autorizácia
│   └── Domain/
│       ├── TokenValidator.php             # Port (interface): isValid(string): bool
│       └── BearerTokenValidator.php       # Implementácia: validuje voči zoznamu z config
├── Infrastructure/
│   ├── Bus/
│   │   ├── SymfonyCommandBus.php
│   │   └── SymfonyQueryBus.php
│   ├── Event/
│   │   ├── CompositeEventSerializer.php   # Aggreguje serializers pre všetky kontexty
│   │   └── FrequencyBasedSnapshotStrategy.php
│   ├── Http/
│   │   ├── Controller/BaseController.php
│   │   ├── EventListener/
│   │   │   └── BearerTokenRequestListener.php  # kernel.request middleware (priorita 100)
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

# Nastaviť env premenné
cp .env .env.local
# Upraviť AUTH_TOKEN v .env.local:
# AUTH_TOKEN=tvoj-tajny-token

# Zostaviť image a spustiť kontajnery
docker compose up -d

# Aplikovať migrácie
docker compose exec app composer doctrine:migrate
```

### Env premenné

| Premenná | Popis | Default |
|----------|-------|---------|
| `AUTH_TOKEN` | Bearer token pre autorizáciu API requestov | `demo-token-change-me` |
| `DATABASE_URL` | PostgreSQL connection string | viz `.env` |
| `RABBITMQ_DSN` | RabbitMQ connection string | viz `.env` |

> Po zmene `.env.local` treba reštartovať kontajner s `docker compose up -d --force-recreate app`.

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

## Autorizácia (Bearer Token)

Každý HTTP request musí obsahovať `Authorization: Bearer <token>` header. Token sa overuje voči zoznamu nakonfigurovaných tokenov.

### Ako to funguje

```
HTTP Request
  → BearerTokenRequestListener (kernel.request, priorita 100)
      → extrakt tokenu z hlavičky Authorization: Bearer <token>
      → TokenValidator::isValid(token)
          → porovnanie voči zoznamu z config/packages/authorization.yaml
      → false → JsonResponse(401, {"error": "Unauthorized"})  [reťaz sa zastaví]
      → true  → request pokračuje ďalej
```

### Konfigurácia tokenov

`config/packages/authorization.yaml`:
```yaml
parameters:
    authorization.tokens:
        - '%env(AUTH_TOKEN)%'
```

`.env`:
```env
AUTH_TOKEN=demo-token-change-me
```

### Testovanie

```bash
# Bez tokenu → 401
curl -i http://localhost:8000/

# Neplatný token → 401
curl -i -H "Authorization: Bearer zly-token" http://localhost:8000/

# Platný token → pokračuje na routing
curl -i -H "Authorization: Bearer demo-token-change-me" http://localhost:8000/
```

### Použitie TokenValidator inde

`TokenValidator` je port (interface) — injectovateľný kdekoľvek (controller, command handler):

```php
public function __construct(private TokenValidator $validator) {}
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
