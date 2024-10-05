<?php

namespace AobaTw\ExtLaravel\App\DiscordLoggers;

use AobaTw\ExtLaravel\App\Helpers\DiscordHelper;

class DiscordSQLLogger extends  DiscordHelper
{
    public function __construct($config = null)
    {
        parent::__construct($config);
    }

    public function send($builder)
    {
        $this->addInfoItem('SQL', $rawSQL);
        $this->addInfoItem('DB Host', $dbHost);
        $this->addInfoItem('DB Name', $dbName);
        $this->addInfoItem('Execution Time', $executionTime);
        $this->addInfoItem('Position', $position);

        $this->sendMessageToChannel([
            'content' => $this->flatIntoItemsToText()
        ]);
    }
}