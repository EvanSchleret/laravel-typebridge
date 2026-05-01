<?php

declare(strict_types=1);

namespace EvanSchleret\LaravelTypeBridge\Generation;

use EvanSchleret\LaravelTypeBridge\Exceptions\InvalidTypeDefinitionException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use InvalidArgumentException;
use BackedEnum;
use UnitEnum;

final class TypeScriptGenerator
{
    public function __construct(
        private readonly ResourceDiscovery $resourceDiscovery,
        private readonly FileNameResolver $fileNameResolver,
        private readonly TypeExpressionParser $typeExpressionParser,
        private readonly RelationTypeResolver $relationTypeResolver,
        private readonly Filesystem $filesystem,
    ) {
    }

    public function generate(
        ?string $outputPathOverride = null,
        bool $dryRun = false,
        bool $clean = false,
        array $only = [],
        array $except = [],
    ): GenerationResult
    {
        $outputPath = $this->resolveOutputPath($outputPathOverride);
        $resources = $this->filterResources(
            $this->resourceDiscovery->discover($this->resolveSources()),
            $only,
            $except,
        );
        $extension = $this->resolveExtension();
        $namingPattern = (string) config('typebridge.files.naming_pattern', '{name}');
        $useSemicolons = (bool) config('typebridge.generation.use_semicolons', false);
        $indentation = $this->resolveIndentation();
        $sharedAppend = $this->normalizeLineList(config('typebridge.generation.shared_append', []));
        $sharedFileName = $this->resolveSharedFileName($extension, $sharedAppend !== []);
        $sharedExportNames = $this->extractExportNames($sharedAppend);
        $appendTemplates = $this->resolveAppendTemplates(config('typebridge.generation.append_templates', []));

        $resourceFileMap = [];
        $reserved = [];

        foreach ($resources as $resource) {
            $resolvedFile = $this->fileNameResolver->resolve(
                resourceName: $resource->name,
                fileName: $resource->fileName,
                pattern: $namingPattern,
                extension: $extension,
            );

            if (array_key_exists($resolvedFile, $reserved)) {
                throw new InvalidArgumentException("Duplicate output file '{$resolvedFile}' for {$resource->className} and {$reserved[$resolvedFile]}");
            }

            $reserved[$resolvedFile] = $resource->className;
            $resourceFileMap[$resource->className] = $resolvedFile;
        }

        if ($sharedFileName !== null && array_key_exists($sharedFileName, $reserved)) {
            throw new InvalidArgumentException("Shared file '{$sharedFileName}' conflicts with generated resource file '{$reserved[$sharedFileName]}'");
        }

        $typeToFileMap = [];
        $modelToTypeMap = [];

        foreach ($resources as $resource) {
            $fileName = $resourceFileMap[$resource->className];
            $typeToFileMap[$resource->name] = $fileName;

            if ($resource->modelClass !== null && !array_key_exists($resource->modelClass, $modelToTypeMap)) {
                $modelToTypeMap[$resource->modelClass] = $resource->name;
            }
        }

        usort(
            $resources,
            fn (DiscoveredResource $left, DiscoveredResource $right): int => $resourceFileMap[$left->className] <=> $resourceFileMap[$right->className],
        );

        $generatedFiles = [];

        foreach ($resources as $resource) {
            $fileName = $resourceFileMap[$resource->className];
            $content = $this->renderResourceFile(
                resource: $resource,
                currentFileName: $fileName,
                typeToFileMap: $typeToFileMap,
                modelToTypeMap: $modelToTypeMap,
                useSemicolons: $useSemicolons,
                indentation: $indentation,
                extension: $extension,
                sharedFileName: $sharedFileName,
                sharedExportNames: $sharedExportNames,
                appendTemplates: $appendTemplates,
            );

            $generatedFiles[] = new GeneratedFile(
                path: $this->joinPaths($outputPath, $fileName),
                content: $content,
            );
        }

        if ($sharedFileName !== null) {
            $generatedFiles[] = new GeneratedFile(
                path: $this->joinPaths($outputPath, $sharedFileName),
                content: $this->renderSharedFile($sharedAppend),
            );
        }

        if ((bool) config('typebridge.generation.generate_index', true)) {
            $exportFiles = array_values($resourceFileMap);
            if ($sharedFileName !== null) {
                $exportFiles[] = $sharedFileName;
            }

            $generatedFiles[] = new GeneratedFile(
                path: $this->joinPaths($outputPath, 'index.ts'),
                content: $this->renderIndexFile($exportFiles, $extension, $useSemicolons),
            );
        }

        usort($generatedFiles, static fn (GeneratedFile $left, GeneratedFile $right): int => $left->path <=> $right->path);

        $deletedFiles = $clean
            ? $this->cleanOutputDirectory($outputPath, $generatedFiles, $extension, $dryRun)
            : [];

        if (!$dryRun) {
            foreach ($generatedFiles as $generatedFile) {
                $directory = dirname($generatedFile->path);
                if (!$this->filesystem->isDirectory($directory)) {
                    $this->filesystem->makeDirectory($directory, 0755, true);
                }

                $this->filesystem->put($generatedFile->path, $generatedFile->content);
            }
        }

        return new GenerationResult(
            outputPath: $outputPath,
            dryRun: $dryRun,
            files: $generatedFiles,
            deletedFiles: $deletedFiles,
        );
    }

    private function renderResourceFile(
        DiscoveredResource $resource,
        string $currentFileName,
        array $typeToFileMap,
        array $modelToTypeMap,
        bool $useSemicolons,
        string $indentation,
        string $extension,
        ?string $sharedFileName,
        array $sharedExportNames,
        array $appendTemplates,
    ): string {
        $imports = [];
        $fieldLines = [];
        $localTypeNames = $resource->localTypeNames();

        foreach ($resource->structure as $rawFieldName => $rawType) {
            $fieldName = (string) $rawFieldName;
            $isOptional = str_ends_with($fieldName, '?');
            if ($isOptional) {
                $fieldName = substr($fieldName, 0, -1);
            }

            if ($fieldName === '') {
                throw new InvalidTypeDefinitionException("Empty field name in {$resource->className}");
            }

            $typeExpression = is_string($rawType) ? trim($rawType) : '';
            if ($typeExpression === '') {
                throw new InvalidTypeDefinitionException("Empty type definition for field '{$fieldName}' in {$resource->className}");
            }

            $parsedType = $this->resolveFieldType($resource, $typeExpression, $modelToTypeMap);

            foreach ($parsedType->references as $referenceName) {
                if (in_array($referenceName, $localTypeNames, true)) {
                    continue;
                }

                if (!array_key_exists($referenceName, $typeToFileMap)) {
                    continue;
                }

                $targetFile = $typeToFileMap[$referenceName];
                if ($targetFile === $currentFileName) {
                    continue;
                }

                $imports[$referenceName] = $this->toImportPath($currentFileName, $targetFile, $extension);
            }

            $optionalToken = $isOptional ? '?' : '';
            $fieldTerminator = $useSemicolons ? ';' : '';
            $fieldLines[] = sprintf(
                '%s%s%s: %s%s',
                $indentation,
                $this->normalizeFieldName($fieldName),
                $optionalToken,
                $parsedType->normalizedType,
                $fieldTerminator,
            );
        }

        $templateLines = $this->renderTemplateLines($resource, $appendTemplates, $useSemicolons);

        if ($sharedFileName !== null && $templateLines !== [] && $sharedExportNames !== []) {
            foreach ($this->extractUsedSymbols($templateLines, $sharedExportNames) as $sharedSymbol) {
                $imports[$sharedSymbol] = $this->toImportPath($currentFileName, $sharedFileName, $extension);
            }
        }

        $importLines = $this->renderImportLines($imports, $useSemicolons);
        $additionalTypesLines = $this->renderAdditionalTypes($resource->types, $useSemicolons);
        $appendLines = $this->normalizeLineList($resource->append);

        $lines = [];

        if ($importLines !== []) {
            $lines = array_merge($lines, $importLines, ['']);
        }

        $lines[] = "export interface {$resource->name} {";
        $lines = array_merge($lines, $fieldLines);
        $lines[] = '}';

        if ($additionalTypesLines !== []) {
            $lines[] = '';
            $lines = array_merge($lines, $additionalTypesLines);
        }

        if ($appendLines !== []) {
            $lines[] = '';
            $lines = array_merge($lines, $appendLines);
        }

        if ($templateLines !== []) {
            $lines[] = '';
            $lines = array_merge($lines, $templateLines);
        }

        return rtrim(implode("\n", $lines)) . "\n";
    }

    private function renderTemplateLines(DiscoveredResource $resource, array $appendTemplates, bool $useSemicolons): array
    {
        $lines = [];

        foreach ($appendTemplates as $template) {
            if (!$this->templateMatches($resource, $template)) {
                continue;
            }

            $baseName = $this->resolveBaseName($resource, $template);
            $basePlural = $resource->aliasPlural ?? Str::pluralStudly($baseName);

            foreach ($template['lines'] as $line) {
                if (!is_string($line) || trim($line) === '') {
                    continue;
                }

                $rendered = strtr($line, [
                    '{name}' => $resource->name,
                    '{base}' => $baseName,
                    '{basePlural}' => $basePlural,
                    '{pascal}' => Str::studly($resource->name),
                    '{camel}' => Str::camel($resource->name),
                    '{snake}' => Str::snake($resource->name),
                    '{kebab}' => Str::kebab($resource->name),
                ]);

                $lines[] = $useSemicolons ? $this->addSemicolon($rendered) : rtrim($rendered);
            }
        }

        return $lines;
    }

    private function templateMatches(DiscoveredResource $resource, array $template): bool
    {
        $name = $resource->name;

        if (array_key_exists('name_equals', $template)) {
            $equals = $template['name_equals'];
            if (is_string($equals) && $equals !== '' && $name !== $equals) {
                return false;
            }

            if (is_array($equals) && $equals !== []) {
                $values = array_values(array_filter($equals, static fn (mixed $value): bool => is_string($value) && $value !== ''));
                if ($values !== [] && !in_array($name, $values, true)) {
                    return false;
                }
            }
        }

        if (is_string($template['name_starts_with'] ?? null) && $template['name_starts_with'] !== '' && !str_starts_with($name, $template['name_starts_with'])) {
            return false;
        }

        if (is_string($template['name_ends_with'] ?? null) && $template['name_ends_with'] !== '' && !str_ends_with($name, $template['name_ends_with'])) {
            return false;
        }

        if (is_string($template['name_matches'] ?? null) && $template['name_matches'] !== '' && preg_match($template['name_matches'], $name) !== 1) {
            return false;
        }

        return true;
    }

    private function resolveBaseName(DiscoveredResource $resource, array $template): string
    {
        if (is_string($resource->aliasBase) && $resource->aliasBase !== '') {
            return $resource->aliasBase;
        }

        $stripSuffix = '';

        if (is_string($template['strip_suffix'] ?? null)) {
            $stripSuffix = $template['strip_suffix'];
        } elseif (is_string($template['name_ends_with'] ?? null)) {
            $stripSuffix = $template['name_ends_with'];
        }

        if ($stripSuffix !== '' && str_ends_with($resource->name, $stripSuffix)) {
            $base = substr($resource->name, 0, -strlen($stripSuffix));
            if ($base !== '') {
                return $base;
            }
        }

        return $resource->name;
    }

    private function renderSharedFile(array $sharedAppend): string
    {
        return rtrim(implode("\n", $sharedAppend)) . "\n";
    }

    private function renderIndexFile(array $exportFiles, string $extension, bool $useSemicolons): string
    {
        $exports = [];

        foreach ($exportFiles as $fileName) {
            if (!is_string($fileName) || $fileName === '') {
                continue;
            }

            $importPath = $this->stripExtension(str_replace('\\', '/', $fileName), $extension);
            $importPath = str_starts_with($importPath, '.') ? $importPath : './' . ltrim($importPath, '/');

            $exports[] = "export * from '{$importPath}'" . ($useSemicolons ? ';' : '');
        }

        $exports = array_values(array_unique($exports));
        sort($exports);

        return implode("\n", $exports) . "\n";
    }

    private function renderImportLines(array $imports, bool $useSemicolons): array
    {
        if ($imports === []) {
            return [];
        }

        ksort($imports);
        $grouped = [];

        foreach ($imports as $typeName => $path) {
            if (!array_key_exists($path, $grouped)) {
                $grouped[$path] = [];
            }

            $grouped[$path][] = $typeName;
        }

        ksort($grouped);
        $lines = [];

        foreach ($grouped as $path => $typeNames) {
            sort($typeNames);
            $joinedNames = implode(', ', $typeNames);
            $lines[] = "import type { {$joinedNames} } from '{$path}'" . ($useSemicolons ? ';' : '');
        }

        return $lines;
    }

    private function renderAdditionalTypes(array $types, bool $useSemicolons): array
    {
        $lines = [];

        foreach ($types as $name => $definition) {
            if (!is_string($definition) || trim($definition) === '') {
                continue;
            }

            if (is_string($name) && $name !== '') {
                $line = "export type {$name} = {$definition}";
                $lines[] = $line . ($useSemicolons ? ';' : '');
                continue;
            }

            $lines[] = rtrim($definition);
        }

        return $lines;
    }

    private function resolveFieldType(DiscoveredResource $resource, string $typeExpression, array $modelToTypeMap): TypeParseResult
    {
        if (str_starts_with($typeExpression, '@relation(')) {
            if (preg_match('/^@relation\(([A-Za-z_][A-Za-z0-9_]*)\)$/', $typeExpression, $matches) !== 1) {
                throw new InvalidTypeDefinitionException("Invalid @relation syntax '{$typeExpression}' in {$resource->className}. Expected @relation(methodName)");
            }

            return $this->relationTypeResolver->resolve($resource, $matches[1], $modelToTypeMap);
        }

        if (str_starts_with($typeExpression, '@enum(')) {
            if (preg_match('/^@enum\(([^)]+)\)$/', $typeExpression, $matches) !== 1) {
                throw new InvalidTypeDefinitionException("Invalid @enum syntax '{$typeExpression}' in {$resource->className}. Expected @enum(Fully\\\\Qualified\\\\EnumClass)");
            }

            return $this->resolveEnumType(trim($matches[1]), $resource);
        }

        return $this->typeExpressionParser->parse($typeExpression);
    }

    private function resolveEnumType(string $enumClass, DiscoveredResource $resource): TypeParseResult
    {
        if ($enumClass === '' || !enum_exists($enumClass)) {
            throw new InvalidTypeDefinitionException("Cannot resolve @enum({$enumClass}) in {$resource->className}: enum class does not exist");
        }

        $cases = $enumClass::cases();
        if ($cases === []) {
            throw new InvalidTypeDefinitionException("Cannot resolve @enum({$enumClass}) in {$resource->className}: enum has no cases");
        }

        $values = [];

        foreach ($cases as $case) {
            if ($case instanceof BackedEnum) {
                $value = $case->value;
                $values[] = is_string($value)
                    ? "'" . str_replace("'", "\\'", $value) . "'"
                    : (string) $value;
                continue;
            }

            if ($case instanceof UnitEnum) {
                $values[] = "'" . str_replace("'", "\\'", $case->name) . "'";
                continue;
            }
        }

        if ($values === []) {
            throw new InvalidTypeDefinitionException("Cannot resolve @enum({$enumClass}) in {$resource->className}: unsupported enum values");
        }

        return new TypeParseResult(implode('|', $values), []);
    }

    private function resolveOutputPath(?string $overridePath): string
    {
        $path = $overridePath ?? config('typebridge.output.base_path');

        if (!is_string($path) || trim($path) === '') {
            throw new InvalidArgumentException('Invalid output path configuration');
        }

        $path = trim($path);

        if (!$this->isAbsolutePath($path)) {
            $path = base_path($path);
        }

        return rtrim($path, DIRECTORY_SEPARATOR);
    }

    private function resolveSources(): array
    {
        $sources = config('typebridge.sources', []);
        if (!is_array($sources)) {
            return [];
        }

        $resolved = [];

        foreach ($sources as $sourcePath) {
            if (!is_string($sourcePath) || trim($sourcePath) === '') {
                continue;
            }

            $sourcePath = trim($sourcePath);
            if (!$this->isAbsolutePath($sourcePath)) {
                $sourcePath = base_path($sourcePath);
            }

            $resolved[] = $sourcePath;
        }

        return array_values(array_unique($resolved));
    }

    private function resolveExtension(): string
    {
        $extension = config('typebridge.files.extension', 'ts');
        if (!is_string($extension)) {
            return 'ts';
        }

        $cleaned = ltrim(trim($extension), '.');

        return $cleaned === '' ? 'ts' : $cleaned;
    }

    private function resolveIndentation(): string
    {
        $size = config('typebridge.generation.indent_size', 2);
        if (!is_int($size)) {
            $size = is_numeric($size) ? (int) $size : 2;
        }

        if ($size < 0) {
            $size = 0;
        }

        return str_repeat(' ', $size);
    }

    private function resolveSharedFileName(string $extension, bool $hasSharedAppend): ?string
    {
        if (!$hasSharedAppend) {
            return null;
        }

        $sharedFile = config('typebridge.generation.shared_file', '_api');
        if (!is_string($sharedFile) || trim($sharedFile) === '') {
            $sharedFile = '_api';
        }

        $sharedFile = trim($sharedFile);

        if (Str::endsWith($sharedFile, ".{$extension}")) {
            return $sharedFile;
        }

        return "{$sharedFile}.{$extension}";
    }

    private function filterResources(array $resources, array $only, array $except): array
    {
        $onlyMap = $this->buildFilterMap($only);
        $exceptMap = $this->buildFilterMap($except);

        return array_values(array_filter(
            $resources,
            static function (DiscoveredResource $resource) use ($onlyMap, $exceptMap): bool {
                $byOnly = $onlyMap === [] || array_key_exists($resource->name, $onlyMap) || array_key_exists($resource->className, $onlyMap);
                $byExcept = !array_key_exists($resource->name, $exceptMap) && !array_key_exists($resource->className, $exceptMap);

                return $byOnly && $byExcept;
            },
        ));
    }

    private function buildFilterMap(array $values): array
    {
        $map = [];

        foreach ($values as $value) {
            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            $map[trim($value)] = true;
        }

        return $map;
    }

    private function cleanOutputDirectory(string $outputPath, array $generatedFiles, string $extension, bool $dryRun): array
    {
        if (!$this->filesystem->isDirectory($outputPath)) {
            return [];
        }

        $managedFilePaths = [];

        foreach ($generatedFiles as $generatedFile) {
            $managedFilePaths[realpath($generatedFile->path) ?: $generatedFile->path] = true;
        }

        $deletedFiles = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($outputPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $item) {
            if (!$item instanceof \SplFileInfo) {
                continue;
            }

            if ($item->getExtension() !== $extension) {
                continue;
            }

            $path = $item->getRealPath();
            if (!is_string($path)) {
                continue;
            }

            if (array_key_exists($path, $managedFilePaths)) {
                continue;
            }

            if ($dryRun) {
                $deletedFiles[] = $path;
                continue;
            }

            if ($this->filesystem->delete($path)) {
                $deletedFiles[] = $path;
            }
        }

        sort($deletedFiles);

        return $deletedFiles;
    }

    private function normalizeLineList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $lines = [];

        foreach ($value as $line) {
            if (!is_string($line)) {
                continue;
            }

            $lines[] = rtrim($line);
        }

        return $lines;
    }

    private function resolveAppendTemplates(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $templates = [];

        foreach ($value as $template) {
            if (!is_array($template)) {
                continue;
            }

            $lines = $template['lines'] ?? [];
            if (!is_array($lines) || $lines === []) {
                continue;
            }

            $templates[] = [
                'lines' => $lines,
                'name_equals' => $template['name_equals'] ?? null,
                'name_starts_with' => $template['name_starts_with'] ?? null,
                'name_ends_with' => $template['name_ends_with'] ?? null,
                'name_matches' => $template['name_matches'] ?? null,
                'strip_suffix' => $template['strip_suffix'] ?? null,
            ];
        }

        return $templates;
    }

    private function extractExportNames(array $lines): array
    {
        $names = [];

        foreach ($lines as $line) {
            if (preg_match('/^\s*export\s+(?:type|interface|class|enum|const)\s+([A-Za-z_][A-Za-z0-9_]*)/', $line, $matches) === 1) {
                $names[] = $matches[1];
            }
        }

        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }

    private function extractUsedSymbols(array $lines, array $symbols): array
    {
        $used = [];

        foreach ($symbols as $symbol) {
            foreach ($lines as $line) {
                if (preg_match('/\\b' . preg_quote($symbol, '/') . '\\b/', $line) === 1) {
                    $used[] = $symbol;
                    break;
                }
            }
        }

        $used = array_values(array_unique($used));
        sort($used);

        return $used;
    }

    private function addSemicolon(string $line): string
    {
        $trimmed = rtrim($line);

        if ($trimmed === '') {
            return $trimmed;
        }

        if (Str::endsWith($trimmed, [';', '{', '}'])) {
            return $trimmed;
        }

        return $trimmed . ';';
    }

    private function normalizeFieldName(string $fieldName): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $fieldName) === 1) {
            return $fieldName;
        }

        return "'" . str_replace("'", "\\'", $fieldName) . "'";
    }

    private function toImportPath(string $fromFile, string $toFile, string $extension): string
    {
        $fromDirectory = str_replace('\\', '/', dirname($fromFile));
        $fromParts = array_values(array_filter(explode('/', $fromDirectory), static fn (string $part): bool => $part !== '.' && $part !== ''));
        $toParts = array_values(array_filter(explode('/', str_replace('\\', '/', $toFile)), static fn (string $part): bool => $part !== ''));

        while ($fromParts !== [] && $toParts !== [] && $fromParts[0] === $toParts[0]) {
            array_shift($fromParts);
            array_shift($toParts);
        }

        $relativeParts = array_merge(array_fill(0, count($fromParts), '..'), $toParts);
        $relative = implode('/', $relativeParts);
        $relative = $this->stripExtension($relative, $extension);

        if ($relative === '' || Str::startsWith($relative, './') || Str::startsWith($relative, '../')) {
            return $relative === '' ? './' : $relative;
        }

        return './' . $relative;
    }

    private function stripExtension(string $path, string $extension): string
    {
        $suffix = ".{$extension}";

        return Str::endsWith($path, $suffix)
            ? substr($path, 0, -strlen($suffix))
            : $path;
    }

    private function joinPaths(string $basePath, string $relativePath): string
    {
        $basePath = rtrim($basePath, '/\\');
        $relativePath = ltrim($relativePath, '/\\');

        return $basePath . DIRECTORY_SEPARATOR . $relativePath;
    }

    private function isAbsolutePath(string $path): bool
    {
        if (Str::startsWith($path, ['/', '\\'])) {
            return true;
        }

        return preg_match('/^[A-Za-z]:[\\\/]/', $path) === 1;
    }
}
