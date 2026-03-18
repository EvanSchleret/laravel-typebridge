<?php

declare(strict_types=1);

use EvanSchleret\LaravelTypeBridge\Exceptions\InvalidTypeDefinitionException;
use EvanSchleret\LaravelTypeBridge\Generation\TypeExpressionParser;

it('parses primitive, nullable, union and arrays', function (): void {
    $parser = new TypeExpressionParser();

    $result = $parser->parse('Role[] | string | null');

    expect($result->normalizedType)->toBe('Role[]|string|null')
        ->and($result->references)->toBe(['Role']);
});

it('parses unknown and void primitives', function (): void {
    $parser = new TypeExpressionParser();

    $result = $parser->parse('unknown|void');

    expect($result->normalizedType)->toBe('unknown|void')
        ->and($result->references)->toBe([]);
});

it('fails on invalid unions', function (): void {
    $parser = new TypeExpressionParser();

    expect(fn () => $parser->parse('string||number'))
        ->toThrow(InvalidTypeDefinitionException::class);
});

it('fails on unsupported tokens', function (): void {
    $parser = new TypeExpressionParser();

    expect(fn () => $parser->parse('Role['))
        ->toThrow(InvalidTypeDefinitionException::class);
});
