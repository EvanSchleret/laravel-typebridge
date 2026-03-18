<?php

declare(strict_types=1);

namespace Tests\Fixtures\Resources\RelationMissingMethod;

use Illuminate\Http\Resources\Json\JsonResource;
use EvanSchleret\LaravelTypeBridge\Attributes\TypeBridgeResource;
use Tests\Fixtures\Models\User;

#[TypeBridgeResource(
    name: 'MissingRelationMethod',
    structure: [
        'roles' => '@relation(doesNotExist)',
    ],
)]
final class MissingRelationMethodResource extends JsonResource
{
    public static string $model = User::class;
}
