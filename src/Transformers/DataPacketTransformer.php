<?php

declare(strict_types=1);

namespace Splitstack\Typewriter\Transformers;

use ReflectionNamedType;
use Splitstack\Typewriter\DataPacket;
use Spatie\TypeScriptTransformer\Data\TransformationContext;
use Spatie\TypeScriptTransformer\PhpNodes\PhpClassNode;
use Spatie\TypeScriptTransformer\References\ClassStringReference;
use Spatie\TypeScriptTransformer\References\PhpClassReference;
use Spatie\TypeScriptTransformer\Transformed\Transformed;
use Spatie\TypeScriptTransformer\Transformed\Untransformable;
use Spatie\TypeScriptTransformer\Transformers\Transformer;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptAlias;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptIdentifier;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptObject;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptProperty;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptRaw;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptReference;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptUnknown;

/**
 * Transforms typed DataPacket subclasses into a TypeScript type alias
 * with the correct envelope shape and a typed reference to the $data property.
 *
 * The base DataPacket class is handled directly by DataClassTransformer
 * via its #[LiteralTypeScriptType] attribute.
 */
final class DataPacketTransformer implements Transformer
{
    public function transform(PhpClassNode $phpClassNode, TransformationContext $context): Transformed|Untransformable
    {
        $reflection = $phpClassNode->reflection;

        if (
            $phpClassNode->isAbstract() ||
            $reflection->getName() === DataPacket::class ||
            ! $reflection->isSubclassOf(DataPacket::class)
        ) {
            return Untransformable::create();
        }

        if (! $reflection->hasProperty('data')) {
            return Untransformable::create();
        }

        $dataProp = $reflection->getProperty('data');
        $dataType = $dataProp->getType();
        $dataFqcn = $dataType instanceof ReflectionNamedType ? $dataType->getName() : null;

        $dataNode = $dataFqcn !== null
            ? TypeScriptReference::referencingPhpClass($dataFqcn)
            : new TypeScriptUnknown();

        $properties = [
            new TypeScriptProperty('data', $dataNode),
            new TypeScriptProperty('meta', new TypeScriptRaw('Record<string, unknown>')),
            new TypeScriptProperty('defaults', new TypeScriptRaw('Record<string, unknown>')),
            new TypeScriptProperty('default', new TypeScriptUnknown()),
            new TypeScriptProperty('source', new TypeScriptRaw('string | number | null')),
            new TypeScriptProperty('target', new TypeScriptRaw('string | number | null')),
        ];

        $transformed = new Transformed(
            new TypeScriptAlias(
                new TypeScriptIdentifier($context->name),
                new TypeScriptObject($properties),
            ),
            new PhpClassReference($phpClassNode),
            $context->nameSpaceSegments,
            true,
        );

        if ($dataFqcn !== null && $dataNode instanceof TypeScriptReference) {
            $transformed->references(new ClassStringReference($dataFqcn), $dataNode);
        }

        return $transformed;
    }
}
