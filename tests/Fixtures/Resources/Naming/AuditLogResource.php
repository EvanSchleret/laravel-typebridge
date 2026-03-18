<?php

declare(strict_types=1);

namespace Tests\Fixtures\Resources\Naming;

use Illuminate\Http\Resources\Json\JsonResource;
use EvanSchleret\LaravelTypeBridge\Attributes\TypeBridgeResource;

#[TypeBridgeResource(
    name: 'AuditLog',
    fileName: 'CustomAudit.ts',
    structure: [
        'id' => 'number',
    ],
)]
final class AuditLogResource extends JsonResource
{
}
