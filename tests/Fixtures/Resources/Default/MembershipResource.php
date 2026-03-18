<?php

declare(strict_types=1);

namespace Tests\Fixtures\Resources\Default;

use Illuminate\Http\Resources\Json\JsonResource;
use EvanSchleret\LaravelTypeBridge\Attributes\TypeScriptResource;

#[TypeScriptResource(
    name: 'Membership',
    structure: [
        'role' => 'Role',
        'user' => 'User',
    ],
)]
final class MembershipResource extends JsonResource
{
}
