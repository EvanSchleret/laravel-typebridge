<?php

declare(strict_types=1);

namespace Tests\Fixtures\Resources\Templates;

use EvanSchleret\LaravelTypeBridge\Attributes\TypeScriptResource;
use Illuminate\Http\Resources\Json\JsonResource;

#[TypeScriptResource(
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
