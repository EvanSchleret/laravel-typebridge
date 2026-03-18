<?php

declare(strict_types=1);

namespace Tests\Fixtures\Resources\Invalid;

use EvanSchleret\LaravelTypeBridge\Attributes\TypeScriptResource;

#[TypeScriptResource(
    name: 'NotAResource',
    structure: [
        'id' => 'number',
    ],
)]
final class NotAResource
{
}
