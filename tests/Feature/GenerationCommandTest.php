<?php

declare(strict_types=1);

use EvanSchleret\LaravelTypeBridge\Exceptions\InvalidResourceTargetException;
use EvanSchleret\LaravelTypeBridge\Exceptions\RelationResolutionException;
use Illuminate\Support\Facades\Artisan;

it('uses output base path when output-path option is not provided', function (): void {
    config()->set('typebridge.sources', [$this->fixturesPath('Resources/Default')]);

    Artisan::call('typebridge:generate');

    expect(is_file($this->defaultOutputPath() . '/User.ts'))->toBeTrue()
        ->and(is_file($this->defaultOutputPath() . '/index.ts'))->toBeTrue();
});

it('supports output-path override', function (): void {
    config()->set('typebridge.sources', [$this->fixturesPath('Resources/Default')]);

    Artisan::call('typebridge:generate', [
        '--output-path' => $this->alternateOutputPath(),
    ]);

    expect(is_file($this->alternateOutputPath() . '/User.ts'))->toBeTrue()
        ->and(is_file($this->defaultOutputPath() . '/User.ts'))->toBeFalse();
});

it('supports dry-run without writing files and prints generated content', function (): void {
    config()->set('typebridge.sources', [$this->fixturesPath('Resources/Default')]);

    Artisan::call('typebridge:generate', [
        '--dry-run' => true,
    ]);

    $output = Artisan::output();

    expect(is_dir($this->defaultOutputPath()))->toBeFalse()
        ->and($output)->toContain('Dry run mode:')
        ->and($output)->toContain('export interface User');
});

it('supports only filter', function (): void {
    config()->set('typebridge.sources', [$this->fixturesPath('Resources/Default')]);

    Artisan::call('typebridge:generate', [
        '--only' => 'User,Role',
    ]);

    expect(is_file($this->defaultOutputPath() . '/User.ts'))->toBeTrue()
        ->and(is_file($this->defaultOutputPath() . '/Role.ts'))->toBeTrue()
        ->and(is_file($this->defaultOutputPath() . '/Membership.ts'))->toBeFalse();
});

it('supports except filter', function (): void {
    config()->set('typebridge.sources', [$this->fixturesPath('Resources/Default')]);

    Artisan::call('typebridge:generate', [
        '--except' => 'Membership',
    ]);

    expect(is_file($this->defaultOutputPath() . '/User.ts'))->toBeTrue()
        ->and(is_file($this->defaultOutputPath() . '/Role.ts'))->toBeTrue()
        ->and(is_file($this->defaultOutputPath() . '/Membership.ts'))->toBeFalse();
});

it('supports clean option and removes stale generated files', function (): void {
    config()->set('typebridge.sources', [$this->fixturesPath('Resources/Default')]);
    $stalePath = $this->defaultOutputPath() . '/Stale.ts';

    if (!is_dir($this->defaultOutputPath())) {
        mkdir($this->defaultOutputPath(), 0755, true);
    }
    file_put_contents($stalePath, "export interface Stale {}\n");

    Artisan::call('typebridge:generate', [
        '--clean' => true,
    ]);

    expect(is_file($stalePath))->toBeFalse()
        ->and(is_file($this->defaultOutputPath() . '/User.ts'))->toBeTrue();
});

it('generates additional configured output paths by default', function (): void {
    $additionalOutputPath = $this->alternateOutputPath();
    config()->set('typebridge.sources', [$this->fixturesPath('Resources/Default')]);
    config()->set('typebridge.output.additional_paths', [$additionalOutputPath]);

    Artisan::call('typebridge:generate');

    expect(is_file($this->defaultOutputPath() . '/User.ts'))->toBeTrue()
        ->and(is_file($additionalOutputPath . '/User.ts'))->toBeTrue();
});

it('does not generate additional paths with output-path unless with-additional-paths is enabled', function (): void {
    $additionalOutputPath = $this->defaultOutputPath();
    $overrideOutputPath = $this->alternateOutputPath();
    config()->set('typebridge.sources', [$this->fixturesPath('Resources/Default')]);
    config()->set('typebridge.output.additional_paths', [$additionalOutputPath]);

    Artisan::call('typebridge:generate', [
        '--output-path' => $overrideOutputPath,
    ]);

    expect(is_file($overrideOutputPath . '/User.ts'))->toBeTrue()
        ->and(is_file($additionalOutputPath . '/User.ts'))->toBeFalse();

    Artisan::call('typebridge:generate', [
        '--output-path' => $overrideOutputPath,
        '--with-additional-paths' => true,
    ]);

    expect(is_file($additionalOutputPath . '/User.ts'))->toBeTrue();
});

it('generates semicolons when enabled', function (): void {
    config()->set('typebridge.sources', [$this->fixturesPath('Resources/Default')]);
    config()->set('typebridge.generation.use_semicolons', true);

    Artisan::call('typebridge:generate');

    $userContent = file_get_contents($this->defaultOutputPath() . '/User.ts');
    $indexContent = file_get_contents($this->defaultOutputPath() . '/index.ts');

    expect($userContent)->toContain("import type { Role } from './Role';")
        ->and($userContent)->toContain('id: number;')
        ->and($indexContent)->toContain("export * from './User';");
});

it('uses configured indentation size', function (): void {
    config()->set('typebridge.sources', [$this->fixturesPath('Resources/Default')]);
    config()->set('typebridge.generation.indent_size', 4);

    Artisan::call('typebridge:generate');

    $userContent = file_get_contents($this->defaultOutputPath() . '/User.ts');

    expect($userContent)->toContain("    id: number\n")
        ->and($userContent)->toContain("    roles: Role[]\n")
        ->and($userContent)->not->toContain("\n  id: number\n");
});

it('generates deterministic imports and avoids type duplication', function (): void {
    config()->set('typebridge.sources', [$this->fixturesPath('Resources/Default')]);

    Artisan::call('typebridge:generate');

    $membershipContent = file_get_contents($this->defaultOutputPath() . '/Membership.ts');
    $userContent = file_get_contents($this->defaultOutputPath() . '/User.ts');

    $roleImportPosition = strpos($membershipContent, "import type { Role } from './Role'");
    $userImportPosition = strpos($membershipContent, "import type { User } from './User'");

    expect($roleImportPosition)->not->toBeFalse()
        ->and($userImportPosition)->not->toBeFalse()
        ->and($roleImportPosition)->toBeLessThan($userImportPosition)
        ->and($userContent)->toContain('roles: Role[]')
        ->and($userContent)->not->toContain('export interface Role');
});

it('applies append order as generated content then attribute append then template append', function (): void {
    config()->set('typebridge.sources', [$this->fixturesPath('Resources/Templates')]);
    config()->set('typebridge.generation.shared_append', [
        "export interface ApiItemResponse<T> {",
        '  data: T',
        '}',
        "export interface ApiCollectionResponse<T> {",
        '  data: T[]',
        '}',
    ]);
    config()->set('typebridge.generation.append_templates', [
        [
            'name_ends_with' => 'Item',
            'lines' => [
                'export type {base} = ApiItemResponse<{name}>',
                'export type {basePlural} = ApiCollectionResponse<{name}>',
            ],
        ],
    ]);

    Artisan::call('typebridge:generate');

    $content = file_get_contents($this->defaultOutputPath() . '/RoleItem.ts');

    $interfacePosition = strpos($content, 'export interface RoleItem');
    $attributeAppendPosition = strpos($content, 'export const roleItemMarker = true');
    $templateAppendPosition = strpos($content, 'export type Role = ApiItemResponse<RoleItem>');

    expect($interfacePosition)->not->toBeFalse()
        ->and($attributeAppendPosition)->not->toBeFalse()
        ->and($templateAppendPosition)->not->toBeFalse()
        ->and($interfacePosition)->toBeLessThan($attributeAppendPosition)
        ->and($attributeAppendPosition)->toBeLessThan($templateAppendPosition);
});

it('generates shared file and imports shared types in template aliases', function (): void {
    config()->set('typebridge.sources', [$this->fixturesPath('Resources/Templates')]);
    config()->set('typebridge.generation.shared_file', '_api');
    config()->set('typebridge.generation.shared_append', [
        "export interface ApiItemResponse<T> {",
        "  status: 'success' | 'error'",
        '  data: T',
        '}',
        "export interface ApiCollectionResponse<T> {",
        '  data: T[]',
        '}',
    ]);
    config()->set('typebridge.generation.append_templates', [
        [
            'name_ends_with' => 'Item',
            'lines' => [
                'export type {base} = ApiItemResponse<{name}>',
                'export type {basePlural} = ApiCollectionResponse<{name}>',
            ],
        ],
    ]);

    Artisan::call('typebridge:generate');

    $sharedContent = file_get_contents($this->defaultOutputPath() . '/_api.ts');
    $roleItemContent = file_get_contents($this->defaultOutputPath() . '/RoleItem.ts');
    $personItemContent = file_get_contents($this->defaultOutputPath() . '/PersonItem.ts');
    $indexContent = file_get_contents($this->defaultOutputPath() . '/index.ts');

    expect($sharedContent)->toContain('export interface ApiItemResponse<T>')
        ->and($sharedContent)->toContain('export interface ApiCollectionResponse<T>')
        ->and($roleItemContent)->toContain("import type { ApiCollectionResponse, ApiItemResponse } from './_api'")
        ->and($roleItemContent)->toContain('export type Role = ApiItemResponse<RoleItem>')
        ->and($roleItemContent)->toContain('export type Roles = ApiCollectionResponse<RoleItem>')
        ->and($personItemContent)->toContain('export type Person = ApiItemResponse<PersonItem>')
        ->and($personItemContent)->toContain('export type People = ApiCollectionResponse<PersonItem>')
        ->and($indexContent)->toContain("export * from './_api'");
});

it('generates index file when enabled', function (): void {
    config()->set('typebridge.sources', [$this->fixturesPath('Resources/Default')]);
    config()->set('typebridge.generation.generate_index', true);

    Artisan::call('typebridge:generate');

    $indexContent = file_get_contents($this->defaultOutputPath() . '/index.ts');

    expect($indexContent)->toContain("export * from './CustomPayload'")
        ->and($indexContent)->toContain("export * from './Membership'")
        ->and($indexContent)->toContain("export * from './Role'")
        ->and($indexContent)->toContain("export * from './User'");
});

it('respects filename override and naming pattern', function (): void {
    config()->set('typebridge.sources', [$this->fixturesPath('Resources/Naming')]);
    config()->set('typebridge.files.naming_pattern', '{kebab}.dto');

    Artisan::call('typebridge:generate');

    expect(is_file($this->defaultOutputPath() . '/CustomAudit.ts'))->toBeTrue();

    config()->set('typebridge.sources', [$this->fixturesPath('Resources/Default')]);

    Artisan::call('typebridge:generate', [
        '--output-path' => $this->alternateOutputPath(),
    ]);

    expect(is_file($this->alternateOutputPath() . '/custom-payload.dto.ts'))->toBeTrue();
});

it('falls back to any when relation exists but no related generated type is available', function (): void {
    config()->set('typebridge.sources', [$this->fixturesPath('Resources/RelationFallbackAny')]);

    Artisan::call('typebridge:generate');

    $content = file_get_contents($this->defaultOutputPath() . '/UserLite.ts');

    expect($content)->toContain('roles: any[]');
});

it('fails when @relation is used without resolvable model', function (): void {
    config()->set('typebridge.sources', [$this->fixturesPath('Resources/RelationNoModel')]);

    expect(fn () => Artisan::call('typebridge:generate'))
        ->toThrow(RelationResolutionException::class, 'model class is not resolvable');
});

it('fails when @relation method does not exist', function (): void {
    config()->set('typebridge.sources', [$this->fixturesPath('Resources/RelationMissingMethod')]);

    expect(fn () => Artisan::call('typebridge:generate'))
        ->toThrow(RelationResolutionException::class, 'relation method not found');
});

it('fails when @relation method does not return eloquent relation', function (): void {
    config()->set('typebridge.sources', [$this->fixturesPath('Resources/RelationInvalidReturn')]);

    expect(fn () => Artisan::call('typebridge:generate'))
        ->toThrow(RelationResolutionException::class, 'expected an Eloquent relation');
});

it('supports enum generation via @enum', function (): void {
    config()->set('typebridge.sources', [$this->fixturesPath('Resources/Enum')]);

    Artisan::call('typebridge:generate');

    $content = file_get_contents($this->defaultOutputPath() . '/EnumPayload.ts');

    expect($content)->toContain("status: 'draft'|'published'");
});

it('fails when @enum class is invalid', function (): void {
    config()->set('typebridge.sources', [$this->fixturesPath('Resources/EnumInvalid')]);

    expect(fn () => Artisan::call('typebridge:generate'))
        ->toThrow(\EvanSchleret\LaravelTypeBridge\Exceptions\InvalidTypeDefinitionException::class, 'enum class does not exist');
});

it('fails when attribute target is not a laravel resource', function (): void {
    config()->set('typebridge.sources', [$this->fixturesPath('Resources/Invalid')]);

    expect(fn () => Artisan::call('typebridge:generate'))
        ->toThrow(InvalidResourceTargetException::class, 'is not a Laravel resource');
});
