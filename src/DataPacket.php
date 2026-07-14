<?php

declare(strict_types=1);

namespace Splitstack\Typewriter;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A uniform data envelope for Back-to-Front transport.
 *
 * Use directly for convenience (data is untyped on the TS side), or extend
 * with a typed $data property for end-to-end type safety.
 */
#[TypeScript]
#[LiteralTypeScriptType([
    'data' => 'Record<string, unknown>',
    'meta' => 'Record<string, unknown>',
    'defaults' => 'Record<string, unknown>',
    'default' => 'unknown',
    'source' => 'string | number | null',
    'target' => 'string | number | null',
])]
class DataPacket extends Data
{
    public function __construct(
        public readonly array $data,
        public array $meta = [],
        public array $defaults = [],
        public mixed $default = null,
        public string|int|null $source = null,
        public string|int|null $target = null,
    ) {
        $this->defaults = array_intersect_key($defaults, $data);
    }

    /**
     * Strip defaults keys that are not present in $data.
     * Call from subclass constructors after $this->data is set.
     */
    protected function filterDefaults(array $data): void
    {
        $this->defaults = array_intersect_key($this->defaults, $data);
    }
}
