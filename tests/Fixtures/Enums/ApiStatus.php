<?php

declare(strict_types=1);

namespace Tests\Fixtures\Enums;

enum ApiStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
