<?php

namespace AobaTw\ExtLaravel\App\Helpers;

use Illuminate\Support\Collection;

class WebsiteHelper
{
    /**
     * @param $path
     * @return string
     */
    public static function asset($path): string
    {
        return asset($path).'?v='.config('app.version');
    }

    /**
     * @return Collection
     */
    public static function getSimplifiedTraceLogs(): Collection
    {
        try {
            throw new \Exception();
        }
        catch (\Exception $e) {
            $logs = collect($e->getTrace())->map(function($traceLog){
                if (
                    isset($traceLog['class']) && $traceLog['function'] === 'App\Models\{closure}'
                    || $traceLog['function'] === 'getSimplifiedTraceLogs'
                ){
                    return false;
                }
                else if (isset($traceLog['file'])) {
                    if ( strpos($traceLog['file'], 'public/index.php')
                        || strpos($traceLog['file'],'vendor')
                        || strpos($traceLog['file'],'Middleware')
                        || strpos($traceLog['class'],'Illuminate') === 0
                    ) {
                        return false;
                    }
                }
                else {
                    return false;
                }
                $traceLog['path'] = $traceLog['class'].$traceLog['type'].$traceLog['function'].'():'.$traceLog['line'];
                return $traceLog;
            })->filter();
        }
        return $logs;
    }

    /**
     * @param string $jsonArray
     * @param int $typeId
     * @return bool
     */
    public static function isInJsonArray(string $jsonArray, int $typeId): bool
    {
        $array = json_decode($jsonArray, true);
        return is_array($array) ? in_array($typeId, $array) : false;
    }
}
