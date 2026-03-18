<?php

declare(strict_types=1);

namespace Tests\Fixtures\Resources\RelationFallbackAny;

use Illuminate\Http\Resources\Json\JsonResource;
use EvanSchleret\LaravelTypeBridge\Attributes\TypeScriptResource;
use Tests\Fixtures\Models\User;

#[TypeScriptResource(
    name: 'UserLite',
    structure: [
        'roles' => '@relation(roles)',
    ],
)]
final class UserLiteResource extends JsonResource
{
    public static string $model = User::class;
}
