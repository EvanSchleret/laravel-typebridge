<?php

declare(strict_types=1);

namespace EvanSchleret\LaravelTypeBridge\Generation;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use ReflectionClass;
use EvanSchleret\LaravelTypeBridge\Attributes\TypeScriptResource;
use EvanSchleret\LaravelTypeBridge\Exceptions\InvalidResourceTargetException;

final class ResourceDiscovery
{
    public function __construct(
        private readonly ModelResolver $modelResolver,
    ) {
    }

    public function discover(array $sources): array
    {
        $resources = [];

        foreach ($this->collectPhpFiles($sources) as $filePath) {
            foreach ($this->loadClassesFromFile($filePath) as $className) {
                $reflection = new ReflectionClass($className);
                $attributes = $reflection->getAttributes(TypeScriptResource::class);

                foreach ($attributes as $attribute) {
                    if (!$reflection->isSubclassOf(JsonResource::class) && !$reflection->isSubclassOf(ResourceCollection::class)) {
                        throw new InvalidResourceTargetException("{$reflection->getName()} is annotated with #[TypeScriptResource] but is not a Laravel resource");
                    }

                    $instance = $attribute->newInstance();

                    $resources[] = new DiscoveredResource(
                        className: $reflection->getName(),
                        name: $instance->name,
                        structure: $instance->structure,
                        types: $instance->types,
                        fileName: $instance->fileName,
                        append: $instance->append,
                        modelClass: $this->modelResolver->resolve($reflection),
                        aliasBase: $instance->aliasBase,
                        aliasPlural: $instance->aliasPlural,
                    );
                }
            }
        }

        usort(
            $resources,
            static fn (DiscoveredResource $left, DiscoveredResource $right): int => [$left->name, $left->className] <=> [$right->name, $right->className],
        );

        return $resources;
    }

    private function collectPhpFiles(array $sources): array
    {
        $files = [];

        foreach ($sources as $sourcePath) {
            if (!is_string($sourcePath) || $sourcePath === '' || !is_dir($sourcePath)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($sourcePath, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY,
            );

            foreach ($iterator as $item) {
                if (!$item instanceof \SplFileInfo) {
                    continue;
                }

                if ($item->getExtension() !== 'php') {
                    continue;
                }

                $realPath = $item->getRealPath();
                if (is_string($realPath)) {
                    $files[] = $realPath;
                }
            }
        }

        sort($files);

        return array_values(array_unique($files));
    }

    private function loadClassesFromFile(string $filePath): array
    {
        require_once $filePath;
        $resolved = [];

        foreach (get_declared_classes() as $className) {
            $reflection = new ReflectionClass($className);
            $reflectionPath = $reflection->getFileName();

            if ($reflectionPath !== false && realpath($reflectionPath) === realpath($filePath)) {
                $resolved[] = $className;
            }
        }

        sort($resolved);

        return $resolved;
    }
}
