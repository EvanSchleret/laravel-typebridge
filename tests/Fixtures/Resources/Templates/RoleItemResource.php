<?php

declare(strict_types=1);

namespace Tests\Fixtures\Resources\Templates;

use EvanSchleret\LaravelTypeBridge\Attributes\TypeScriptResource;
use Illuminate\Http\Resources\Json\JsonResource;

#[TypeScriptResource(
    name: 'RoleItem',
    structure: [
        'id' => 'number',
        'label' => 'string',
    ],
    append: [
        'export const roleItemMarker = true',
    ],
)]
final class RoleItemResource extends JsonResource
{
}
