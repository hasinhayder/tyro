<?php

namespace HasinHayder\Tyro\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TyroLog {
    public function handle(Request $request, Closure $next) {
        return $next($request);
    }

    public function terminate($request, $response): void {
        if (! config('app.debug', false)) {
            return;
        }

        Log::info(str_repeat('=', 80));
        Log::debug('tyro.route', ['route' => optional($request->route())->uri()]);
        Log::debug('tyro.headers', $request->headers->all());
        Log::debug('tyro.request', $request->all());
        Log::debug('tyro.response', ['status' => $response->getStatusCode()]);
        Log::info(str_repeat('=', 80));
    }
}
