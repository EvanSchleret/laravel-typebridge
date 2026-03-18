<?php

declare(strict_types=1);

namespace Tests\Fixtures\Resources\RelationInvalidReturn;

use Illuminate\Http\Resources\Json\JsonResource;
use EvanSchleret\LaravelTypeBridge\Attributes\TypeBridgeResource;
use Tests\Fixtures\Models\User;

#[TypeBridgeResource(
    name: 'InvalidRelationReturn',
    structure: [
        'roles' => '@relation(brokenRelation)',
    ],
)]
final class InvalidRelationReturnResource extends JsonResource
{
    public static string $model = User::class;
}
