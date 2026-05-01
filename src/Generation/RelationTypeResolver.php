<?php

declare(strict_types=1);

namespace EvanSchleret\LaravelTypeBridge\Generation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use EvanSchleret\LaravelTypeBridge\Exceptions\RelationResolutionException;

final class RelationTypeResolver
{
    public function resolve(DiscoveredResource $resource, string $relationName, array $modelToTypeMap): TypeParseResult
    {
        if ($resource->modelClass === null) {
            throw new RelationResolutionException("Cannot resolve @relation({$relationName}) for {$resource->className}: model class is not resolvable");
        }

        if (!class_exists($resource->modelClass)) {
            throw new RelationResolutionException("Cannot resolve @relation({$relationName}) for {$resource->className}: model class {$resource->modelClass} does not exist");
        }

        $model = new $resource->modelClass();
        if (!$model instanceof Model) {
            throw new RelationResolutionException("Cannot resolve @relation({$relationName}) for {$resource->className}: {$resource->modelClass} is not an Eloquent model");
        }

        if (!method_exists($model, $relationName)) {
            $publicMethods = array_values(array_filter(
                get_class_methods($model),
                static fn (string $method): bool => !str_starts_with($method, '__'),
            ));
            sort($publicMethods);
            $methodPreview = implode(', ', array_slice($publicMethods, 0, 8));
            $suffix = $methodPreview === '' ? '' : ". Available methods include: {$methodPreview}";

            throw new RelationResolutionException("Cannot resolve @relation({$relationName}) for {$resource->className}: relation method not found{$suffix}");
        }

        try {
            $relation = $model->{$relationName}();
        } catch (\Throwable $exception) {
            $exceptionClass = $exception::class;
            throw new RelationResolutionException("Cannot resolve @relation({$relationName}) for {$resource->className}: relation method threw {$exceptionClass} with message '{$exception->getMessage()}'", previous: $exception);
        }

        if (!$relation instanceof Relation) {
            $actualType = get_debug_type($relation);
            throw new RelationResolutionException("Cannot resolve @relation({$relationName}) for {$resource->className}: method returned {$actualType}, expected an Eloquent relation");
        }

        $relatedModel = $relation->getRelated()::class;
        $baseType = $modelToTypeMap[$relatedModel] ?? 'any';
        $isCollection = $this->isCollectionRelation($relation);
        $resolvedType = $isCollection ? "{$baseType}[]" : $baseType;
        $references = $baseType === 'any' ? [] : [$baseType];

        return new TypeParseResult($resolvedType, $references);
    }

    private function isCollectionRelation(Relation $relation): bool
    {
        return $relation instanceof HasMany
            || $relation instanceof HasManyThrough
            || $relation instanceof BelongsToMany
            || $relation instanceof MorphMany
            || $relation instanceof MorphToMany;
    }
}
