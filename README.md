# laravel-typewriter

DDD-friendly unified TypeScript tooling for Laravel. One command generates:

- **DataPacket types** — a typed Back-to-Front transport envelope with defaults, metadata, and source/target routing
- **DTO / Value Object types** — from laravel-data `Data` classes, plain DTOs, and value objects
- **Route types** — via [laravel/wayfinder](https://github.com/laravel/wayfinder) (optional)

Powered by [spatie/laravel-typescript-transformer](https://github.com/spatie/laravel-typescript-transformer) under the hood.

---

## Installation

```bash
composer require splitstack/laravel-typewriter
```

Publish the config:

```bash
php artisan vendor:publish --tag=typewriter-config
```

Publish the TypeScript `DataPacket` utility class:

```bash
php artisan vendor:publish --tag=typewriter-ts
# → resources/js/utils/DataPacket.ts
```

---

## The DataPacket pattern

`DataPacket` is a typed envelope for passing data from PHP to the frontend. It gives you:

- **Typed payload** (`data`) — a laravel-data `Data` object, or an untyped array for convenience
- **Per-key defaults** (`defaults`) — validated against the keys in `data` so stale defaults can't sneak in
- **Metadata** (`meta`) — out-of-band context (pagination, permissions, flags)
- **Global fallback** (`default`) — a single value for when the whole packet has no specific match
- **Source / target routing** (`source`, `target`) — BackedEnum scalars that tell the frontend where data came from and where it's going, without magic strings on either side

### Convenience mode (untyped)

Use `DataPacket` directly when you don't need end-to-end type safety:

```php
use Splitstack\Typewriter\DataPacket;

return inertia('Orders/Index', [
    'orders' => DataPacket::from([
        'data'   => $orders->toArray(),
        'meta'   => ['total' => $orders->total()],
        'source' => 'orders',
    ]),
]);
```

TypeScript receives `DataPacket` where `data` is `Record<string, unknown>`.

### Typed mode (end-to-end type safety)

Scaffold a typed packet + its companion `Data` class:

```bash
php artisan make:packet OrderPacket --data=OrderData
```

This creates two files:

```php
// app/Domain/Data/OrderData.php
#[TypeScript]
class OrderData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $status,
        public readonly float $total,
    ) {}
}

// app/Domain/Packets/OrderPacket.php
class OrderPacket extends DataPacket
{
    public function __construct(
        public readonly OrderData $data,
        array $meta = [],
        array $defaults = [],
        mixed $default = null,
        string|int|null $source = null,
        string|int|null $target = null,
    ) {
        parent::__construct($data->toArray(), $meta, $defaults, $default, $source, $target);
    }
}
```

Use it in your controller:

```php
return inertia('Orders/Show', [
    'order' => OrderPacket::from([
        'data'    => OrderData::from($order),
        'defaults'=> ['status' => 'pending'],
        'source'  => OrderSource::Api->value,
        'target'  => OrderTarget::Checkout->value,
    ]),
]);
```

### TypeScript consumption

```ts
import type { OrderPacket } from '@/types/packets'
import { DataPacket } from '@/utils/DataPacket'

const packet = DataPacket.from<OrderPacket['data']>(props.order)

packet.get('status')        // OrderData['status'] — falls back to defaults if null
packet.raw('status')        // raw value, no default resolution
packet.isFrom('api')        // source routing check
packet.isFor('checkout')    // target routing check
packet.getMeta('total')     // metadata access
packet.fallback()           // global default value
```

---

## Type generation

### Source groups

Configure which directories to scan in `config/typewriter.php`:

```php
'sources' => [
    'value_objects' => [
        'directories' => ['Domain/ValueObjects'],
    ],
    'dtos' => [
        'directories' => ['Domain/DTOs', 'Http/Resources'],
    ],
    'packets' => [
        'directories' => ['Domain/Packets', 'Http/Packets'],
        'enums'       => ['Domain/Enums'],  // BackedEnums used as source/target
        'enabled'     => true,
    ],
],
```

### Transformer chain

For each source group, typewriter runs a single typescript-transformer pipeline:

| Priority | Transformer | Handles |
|---|---|---|
| 1 | `DataPacketTransformer` | Typed `DataPacket` subclasses |
| 2 | `DataClassTransformer` | laravel-data `Data` classes |
| 3 | `EnumTransformer` | BackedEnums (source/target routing) |
| 4 | `PlainClassTransformer` | Plain VOs, DTOs, any non-abstract class |

Classes that no transformer matches are silently skipped.

### Commands

```bash
# Everything: wayfinder routes + all source groups
php artisan typewriter:generate

# Types only (skip wayfinder)
php artisan typewriter:generate --types-only

# Individual source groups
php artisan typewriter:typegen --value-objects-only
php artisan typewriter:typegen --dtos-only
php artisan typewriter:typegen --packets-only

# Skip barrel index.ts
php artisan typewriter:typegen --no-barrel
```

### Output

```
resources/js/types/
├── value-objects.ts
├── dtos.ts
├── packets.ts        ← DataPacket types + routing enums
└── index.ts          ← barrel re-export
```

---

## Marking classes for export

**laravel-data `Data` classes** — add `#[TypeScript]`:

```php
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class OrderData extends Data { ... }
```

**Plain classes** — no attribute needed. Any public typed property or `@property` PHPDoc tag in a scanned directory is picked up automatically.

**DataPacket subclasses** — no attribute needed. The `DataPacketTransformer` detects them by class hierarchy.

**BackedEnums** — put them in the `enums` directories of the `packets` source group:

```php
enum OrderSource: string
{
    case Api = 'api';
    case Admin = 'admin';
}
```

Generated output:

```ts
export type OrderSource = 'api' | 'admin';
```

---

## Configuration reference

```php
// config/typewriter.php
return [
    'sources' => [
        'value_objects' => [
            'directories' => ['Domain/ValueObjects'],
            'include'     => ['*'],   // glob patterns
            'exclude'     => [],
        ],
        'dtos' => [
            'directories' => ['Domain/DTOs', 'Http/Resources'],
        ],
        'packets' => [
            'directories' => ['Domain/Packets', 'Http/Packets'],
            'enums'       => ['Domain/Enums'],
            'enabled'     => false,   // opt-in; enable once you have packets
        ],
    ],

    'output' => [
        'value_objects' => null,  // resource_path('js/types/value-objects.ts')
        'dtos'          => null,  // resource_path('js/types/dtos.ts')
        'packets'       => null,  // resource_path('js/types/packets.ts')
        'barrel'        => null,  // resource_path('js/types/index.ts')
    ],

    'ts_asset_path' => null, // resource_path('js/utils/DataPacket.ts')
];
```

---

## Requirements

- PHP 8.4+
- Laravel 11 / 12 / 13
- [spatie/laravel-data](https://github.com/spatie/laravel-data) ^4.0
- [spatie/laravel-typescript-transformer](https://github.com/spatie/laravel-typescript-transformer) ^3.0
- [laravel/wayfinder](https://github.com/laravel/wayfinder) *(optional)*

## License

MIT
