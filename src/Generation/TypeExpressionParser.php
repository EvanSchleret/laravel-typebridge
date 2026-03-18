<?php

declare(strict_types=1);

namespace EvanSchleret\LaravelTypeBridge\Generation;

use EvanSchleret\LaravelTypeBridge\Exceptions\InvalidTypeDefinitionException;

final class TypeExpressionParser
{
    private const PRIMITIVES = [
        'string',
        'number',
        'boolean',
        'any',
        'unknown',
        'void',
        'null',
    ];

    public function parse(string $expression): TypeParseResult
    {
        $normalized = preg_replace('/\s+/', '', $expression);
        if (!is_string($normalized) || $normalized === '') {
            throw new InvalidTypeDefinitionException('Type definition cannot be empty');
        }

        $parts = explode('|', $normalized);
        if ($parts === []) {
            throw new InvalidTypeDefinitionException("Invalid type definition: {$expression}");
        }

        $resolvedParts = [];
        $references = [];

        foreach ($parts as $part) {
            if ($part === '') {
                throw new InvalidTypeDefinitionException("Invalid union type definition: {$expression}");
            }

            [$resolvedPart, $reference] = $this->parseAtomic($part, $expression);
            $resolvedParts[] = $resolvedPart;

            if ($reference !== null && !in_array($reference, $references, true)) {
                $references[] = $reference;
            }
        }

        return new TypeParseResult(implode('|', $resolvedParts), $references);
    }

    private function parseAtomic(string $part, string $originalExpression): array
    {
        $arraySuffix = '';

        while (str_ends_with($part, '[]')) {
            $arraySuffix .= '[]';
            $part = substr($part, 0, -2);
        }

        if ($part === '') {
            throw new InvalidTypeDefinitionException("Invalid array type definition: {$originalExpression}");
        }

        if (in_array($part, self::PRIMITIVES, true)) {
            return ["{$part}{$arraySuffix}", null];
        }

        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $part)) {
            throw new InvalidTypeDefinitionException("Unsupported type token '{$part}' in definition: {$originalExpression}");
        }

        return ["{$part}{$arraySuffix}", $part];
    }
}
