<?php

declare(strict_types=1);

namespace Tests\Fixtures\Resources\EnumInvalid;

use Illuminate\Http\Resources\Json\JsonResource;
use EvanSchleret\LaravelTypeBridge\Attributes\TypeBridgeResource;

#[TypeBridgeResource(
    name: 'InvalidEnumPayload',
    structure: [
        'status' => '@enum(App\\Enums\\MissingEnum)',
    ],
)]
final class InvalidEnumResource extends JsonResource
{
}
