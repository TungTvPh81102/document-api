<?php

namespace App\Http\Middleware;

use App\Services\LoggerService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LogSqlQueriesMiddleware
{
    public function __construct(
        private LoggerService $logger
    ) {}

    public function handle(Request $request, Closure $next)
    {
        DB::enableQueryLog();

        $response = $next($request);

        $queries = DB::getQueryLog();

        if (!empty($queries)) {
            $batch = [];
            foreach ($queries as $query) {
                $batch[] = [
                    'sql' => $query['query'],
                    'params' => $query['bindings'],
                    'duration' => $query['time'] / 1000,
                    'module' => $request->route()?->getActionName(),
                ];
            }

            $this->logger->logFullRequestToSqlLog($batch);
        }

        return $response;
    }
}
