<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;

class CustomThrottleMiddleware extends ThrottleRequests
{
    protected function resolveRequestSignature($request)
    {
        //
    }
}