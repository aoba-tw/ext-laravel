<?php

namespace AobaTw\ExtLaravel\App\Helpers;

use Illuminate\Database\Events\QueryExecuted;

class BuilderHelper
{
    /**
     * 取得原始 SQL
     * @param QueryExecuted $queryExecuted
     * @return string
     */
    public static function getRawSQL(QueryExecuted $queryExecuted)
    {
        $sql = $queryExecuted->sql;
        $bindings = $queryExecuted->bindings;
        $rawSQL = vsprintf(str_replace(['?'], ["'%s'"], $sql), $bindings);
        return $rawSQL;
    }
}