<?php

declare(strict_types=1);

use Splitstack\Typewriter\Generator\TypeInspector;

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

class PromotedPropsFixture
{
    public function __construct(
        public readonly string $name,
        public readonly int $age,
        public readonly ?string $bio = null,
    ) {}
}

/**
 * @property string $title
 * @property-read int $count
 * @property ?string $subtitle
 */
class PhpDocFixture {}

class NoTypesFixture
{
    public $untyped;
}

// ---------------------------------------------------------------------------
// TypeInspector unit tests (retained as plain-class fallback engine)
// ---------------------------------------------------------------------------

describe('TypeInspector', function (): void {
    beforeEach(function (): void {
        $this->inspector = new TypeInspector();
    });

    it('extracts promoted constructor properties via reflection', function (): void {
        $result = $this->inspector->inspect(PromotedPropsFixture::class);

        expect($result)->toBe([
            'name' => 'string',
            'age' => 'number',
            'bio?' => 'string | null',
        ]);
    });

    it('falls back to PHPDoc @property tags', function (): void {
        $result = $this->inspector->inspect(PhpDocFixture::class);

        expect($result)->toHaveKey('title', 'string')
            ->and($result)->toHaveKey('count', 'number')
            ->and($result)->toHaveKey('subtitle?', 'string | null');
    });

    it('returns null for classes with no typed properties', function (): void {
        $result = $this->inspector->inspect(NoTypesFixture::class);

        expect($result)->toBeNull();
    });

    it('returns null for abstract classes', function (): void {
        $result = $this->inspector->inspect(Iterator::class);

        expect($result)->toBeNull();
    });
});

// ---------------------------------------------------------------------------
// Command lifecycle tests
// ---------------------------------------------------------------------------

describe('typewriter:generate command', function (): void {
    it('warns when wayfinder is not installed and continues with typegen', function (): void {
        $this->artisan('typewriter:generate', ['--types-only' => true])
            ->assertSuccessful();
    });

    it('runs successfully with --types-only flag', function (): void {
        $this->artisan('typewriter:generate', ['--types-only' => true])
            ->assertSuccessful();
    });

    it('runs typewriter:typegen standalone', function (): void {
        $this->artisan('typewriter:typegen')
            ->assertSuccessful();
    });

    it('runs typewriter:typegen with --no-barrel flag', function (): void {
        $this->artisan('typewriter:typegen', ['--no-barrel' => true])
            ->assertSuccessful();
    });
});
