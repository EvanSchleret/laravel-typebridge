<?php

declare(strict_types=1);

namespace Tests\Fixtures\Resources\Templates;

use EvanSchleret\LaravelTypeBridge\Attributes\TypeBridgeResource;
use Illuminate\Http\Resources\Json\JsonResource;

#[TypeBridgeResource(
    name: 'PersonItem',
    structure: [
        'id' => 'number',
    ],
    aliasBase: 'Person',
    aliasPlural: 'People',
)]
final class PersonItemResource extends JsonResource
{
}
