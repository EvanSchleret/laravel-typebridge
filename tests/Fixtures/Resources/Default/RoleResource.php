<?php

declare(strict_types=1);

namespace Tests\Fixtures\Resources\Default;

use Illuminate\Http\Resources\Json\JsonResource;
use EvanSchleret\LaravelTypeBridge\Attributes\TypeBridgeResource;
use Tests\Fixtures\Models\Role;

#[TypeBridgeResource(
    name: 'Role',
    structure: [
        'id' => 'number',
        'label' => 'string',
    ],
)]
final class RoleResource extends JsonResource
{
    public static string $model = Role::class;
}
