<?php

declare(strict_types=1);

use EvanSchleret\LaravelTypeBridge\Attributes\TypeBridgeResource;

it('keeps attribute defaults', function (): void {
    $attribute = new TypeBridgeResource(name: 'User');

    expect($attribute->name)->toBe('User')
        ->and($attribute->structure)->toBe([])
        ->and($attribute->types)->toBe([])
        ->and($attribute->fileName)->toBeNull()
        ->and($attribute->append)->toBe([])
        ->and($attribute->aliasBase)->toBeNull()
        ->and($attribute->aliasPlural)->toBeNull();
});

it('accepts custom attribute values', function (): void {
    $attribute = new TypeBridgeResource(
        name: 'User',
        structure: ['id' => 'number'],
        types: ['Status' => "'active' | 'inactive'"],
        fileName: 'User.ts',
        append: ['export const marker = true'],
        aliasBase: 'Account',
        aliasPlural: 'Accounts',
    );

    expect($attribute->name)->toBe('User')
        ->and($attribute->structure)->toBe(['id' => 'number'])
        ->and($attribute->types)->toBe(['Status' => "'active' | 'inactive'"])
        ->and($attribute->fileName)->toBe('User.ts')
        ->and($attribute->append)->toBe(['export const marker = true'])
        ->and($attribute->aliasBase)->toBe('Account')
        ->and($attribute->aliasPlural)->toBe('Accounts');
});
