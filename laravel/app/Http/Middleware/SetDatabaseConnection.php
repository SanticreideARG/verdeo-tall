<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SetDatabaseConnection
{
    public function handle(Request $request, Closure $next): Response
    {
        $conn = session('db_env', 'alice');

        if (! in_array($conn, ['alice', 'betty'])) {
            $conn = 'alice';
        }

        config(['database.default' => $conn]);
        DB::setDefaultConnection($conn);

        return $next($request);
    }
}
