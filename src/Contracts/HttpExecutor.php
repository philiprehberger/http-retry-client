<?php

declare(strict_types=1);

namespace PhilipRehberger\HttpRetry\Contracts;

use PhilipRehberger\HttpRetry\HttpRequest;
use PhilipRehberger\HttpRetry\HttpResponse;

interface HttpExecutor
{
    public function execute(HttpRequest $request): HttpResponse;
}
