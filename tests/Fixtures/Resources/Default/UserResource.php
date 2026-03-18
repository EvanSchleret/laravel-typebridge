<?php

declare(strict_types=1);

namespace Tests\Fixtures\Resources\Default;

use Illuminate\Http\Resources\Json\JsonResource;
use EvanSchleret\LaravelTypeBridge\Attributes\TypeBridgeResource;
use Tests\Fixtures\Models\User;

#[TypeBridgeResource(
    name: 'User',
    structure: [
        'id' => 'number',
        'email' => 'string|null',
        'roles' => '@relation(roles)',
        'manager?' => '@relation(manager)',
        'customRole' => 'Role|string',
    ],
    types: [
        'UserState' => "'active' | 'inactive'",
    ],
    append: [
        'export const userMarker = true',
    ],
)]
final class UserResource extends JsonResource
{
    public static string $model = User::class;
}
