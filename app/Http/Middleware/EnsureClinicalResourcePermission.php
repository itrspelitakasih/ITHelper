<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClinicalResourcePermission
{
    public function handle(Request $request, Closure $next, string $action): Response
    {
        $group = (string) $request->route('group');
        abort_unless(in_array($group, ['rme', 'risk-assessments', 'service-requests', 'specimens'], true), 404);
        abort_unless($request->user()?->hasPermission("{$group}.{$action}"), 403);

        return $next($request);
    }
}
