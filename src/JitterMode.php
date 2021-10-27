<?php

declare(strict_types=1);

namespace PhilipRehberger\HttpRetry;

enum JitterMode: string
{
    case Full = 'full';
    case Equal = 'equal';
    case Decorrelated = 'decorrelated';
}
