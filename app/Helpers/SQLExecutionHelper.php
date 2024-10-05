<?php
namespace AobaTw\ExtLaravel\App\Helpers;

use AobaTw\ExtLaravel\App\Notifications\DiscordNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SQLExecutionHelper
{
    protected static function getSlowQueryThreshold() {
        return config('app.slow_query_threshold', 0.1);
    }

    /**
     * 記錄 SQL
     */
    public static function recordSQL() {
        DB::listen(function($query) {
            // SQL 執行時間大於臨界值
            Log::channel('sql')->info($query->time/1000);
            if ($query->time/1000 >= self::getSlowQueryThreshold()) {
                self::slowQueryNotify($query);
            }
        });
    }

    /**
     * 通知
     * @param $query
     */
    protected static function slowQueryNotify($query) {
        $rawSQL = BuilderHelper::getRawSQL($query);
        $dbHost = $query->connection->getConfig('host');
        $dbName = $query->connection->getConfig('database');
        $executionTime = $query->time/1000;

        $position = WebsiteHelper::getSimplifiedTraceLogs()
            ->map(function($log){ return $log['path']; })
            ->implode("\n")
        ;

        DiscordNotification::logSQL($rawSQL, $dbHost, $dbName, $executionTime, $position);
    }
}
