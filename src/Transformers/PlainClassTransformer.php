<?php

declare(strict_types=1);

namespace Splitstack\Typewriter\Transformers;

use Spatie\TypeScriptTransformer\PhpNodes\PhpClassNode;
use Spatie\TypeScriptTransformer\Transformers\ClassTransformer;

/**
 * Catch-all transformer for plain PHP classes (VOs, DTOs, plain objects) that
 * are not handled by more specific transformers. Uses typescript-transformer's
 * native reflection + PHPDoc type inspection engine.
 *
 * Place last in the transformer chain so specialized transformers (DataPacket,
 * laravel-data, enums) get priority.
 */
final class PlainClassTransformer extends ClassTransformer
{
    protected function shouldTransform(PhpClassNode $phpClassNode): bool
    {
        return ! $phpClassNode->isAbstract();
    }
}
