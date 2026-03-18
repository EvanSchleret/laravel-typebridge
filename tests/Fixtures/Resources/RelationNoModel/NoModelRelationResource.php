<?php

declare(strict_types=1);

namespace Tests\Fixtures\Resources\RelationNoModel;

use Illuminate\Http\Resources\Json\JsonResource;
use EvanSchleret\LaravelTypeBridge\Attributes\TypeBridgeResource;

#[TypeBridgeResource(
    name: 'NoModelRelation',
    structure: [
        'roles' => '@relation(roles)',
    ],
)]
final class NoModelRelationResource extends JsonResource
{
}
