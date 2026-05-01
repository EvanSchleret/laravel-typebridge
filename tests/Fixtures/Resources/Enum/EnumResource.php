<?php

declare(strict_types=1);

namespace Tests\Fixtures\Resources\Enum;

use Illuminate\Http\Resources\Json\JsonResource;
use EvanSchleret\LaravelTypeBridge\Attributes\TypeBridgeResource;
use Tests\Fixtures\Enums\ApiStatus;

#[TypeBridgeResource(
    name: 'EnumPayload',
    structure: [
        'status' => '@enum(' . ApiStatus::class . ')',
    ],
)]
final class EnumResource extends JsonResource
{
}
