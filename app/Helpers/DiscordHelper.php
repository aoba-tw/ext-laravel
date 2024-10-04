<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DiscordHelper
{
    const TYPE_LOG = 'log';
    const TYPE_ERROR = 'error';
    const TYPE_SQL = 'sql';

    protected $labelLength = 20;
    protected $infoItems = [];

    public $webhookId;
    public $webhookToken;
    private $logChannel;

    public function __construct($config = null)
    {
        if ($config) {
            $this->webhookId = $config['webhook_id'];
            $this->webhookToken = $config['webhook_token'];
        }
        $this->logChannel = Log::channel('discord');
    }


    protected function addInfoItem($key, $value)
    {
        $this->infoItems[$key] = $value;
    }

    protected function getInfoItem(): array
    {
        return $this->infoItems;
    }


    /**
     * 壓平項目
     * @return string
     */
    protected function flatIntoItemsToText(): string
    {
        $labelLength = $this->labelLength;
        return collect($this->infoItems)->map(function ($value, $key) use ($labelLength) {
            return str_pad($key, $labelLength, ' ', STR_PAD_RIGHT) . $value;
        })->implode("\n");
    }

    /**
     * 傳送訊息至 Discord Channel
     * @param array $args
     */
    public static function sendMessageToChannel(array $args)
    {

        $type = $args['type'] ?? self::TYPE_LOG;
        $config = config('services.discord.' . $type);
        $helper = new DiscordHelper($config);
        $prefix = $args['prefix'] ?? '';

        $title = $args['title'] ?? '';
        $errorMessage = $args['errorMessage'] ?? null;

        $helper->addInfoItem($prefix . ' PROJECT_CODE', config('app.project_code'));
        $helper->addInfoItem($prefix . ' APP_NAME', config('app.name'));
        $helper->addInfoItem($prefix . ' APP_ENV', config('app.env'));
        $helper->addInfoItem($prefix . ' TIMESTAMP', date('Y-m-d H:i:s'));
        $helper->addInfoItem($prefix . ' METHOD', request()->method());
        $helper->addInfoItem($prefix . ' URL', request()->url());

        if ($user = Auth::user()) {
            $helper->addInfoItem($prefix . ' ACTOR', $user->name . '(#' . $user->id . ')');
        }
        foreach ($args['infoItems'] ?? [] as $key => $value) {
            $helper->addInfoItem($prefix . ' ' . $key, $value);
        }

        $message = implode("\n", [
            $title,
            "```$type",
            $helper->flatIntoItemsToText(),
            $errorMessage ? "\n" . $errorMessage : '',
            "```",
            $args['customMessage'] ?? '',
        ]);

        $result = $helper->sendMessage($message);
    }


    /**
     * 發送訊息
     * @param string $content
     * @return bool
     */
    public function sendMessage(string $content = ""): bool
    {
        $webhookUrl = "https://discord.com/api/webhooks/" . $this->webhookId . "/" . $this->webhookToken;

        $message = [
            'content' => $content,
        ];

        $this->logChannel->info('Send Discord Message Start');
        $this->logChannel->info($content);

        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));

        $response = curl_exec($ch);

        try {
            if (curl_errno($ch)) {
                $this->logChannel->error('Curl error: ' . curl_error($ch));
            } else if ($response === '') {
                $this->logChannel->info('OK');
                return true;
            } else {
                $response = json_decode($response);
                $message = $response->message;
                $code = $response->code;
                $this->logChannel->error($code . ' ' . $message);
                return false;
            }
        } catch (\Exception $e) {
            $this->logChannel->error($e->getMessage());
            return false;
        } finally {
            curl_close($ch);
            return true;
        }
    }
}
