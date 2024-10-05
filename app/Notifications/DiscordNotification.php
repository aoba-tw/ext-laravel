<?php

namespace AobaTw\ExtLaravel\App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class DiscordNotification extends Notification
{
    protected $username;
    protected $avatarURL;
    protected $webhookID;
    protected $webhookToken;
    protected $webhookURL;

    const COLOR_WARNING = 16776960; #FFFF00
    const COLOR_PRIMARY = 3447003; #3498DB
    const COLOR_DANGER = 15548997; #ED4245
    const COLOR_INFO = 1752220; #1ABC9C
    const COLOR_SECONDARY = 9807270; #95A5A6
    const COLOR_SUCCESS = 5763719; #57F287


    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->username = config('app.name').'('.config('app.env').') Logger';
        if (config('app.env') === 'local') {
            $this->avatarURL = 'https://minanam.ilrdf.org.tw/img/logo_w.png';
        }
        else {
            $this->avatarURL = config('app.url').'/img/logo_w.png';
        }
        $this->webhookID = config('services.discord.webhooks.log.id');
        $this->webhookToken = config('services.discord.webhooks.log.token');
        $this->webhookURL = "https://discord.com/api/webhooks/".$this->webhookID."/".$this->webhookToken;
    }

    protected function getField($name, $value, $inline = null): array
    {
        $arr = [
            'name' => $name,
            'value' => $value,
        ];
        if ($inline) {
            $arr['inline'] = $inline;
        }
        return $arr;
    }


    /**
     * 記錄 SQL
     * @param string $rawSQL
     * @param string $dbHost
     * @param string $dbName
     * @param float $executionTime
     * @param string $position
     * @param int $color
     */
    public static function logSQL(
        string $rawSQL,
        string $dbHost,
        string $dbName,
        float $executionTime,
        string $position,
        int $color = self::COLOR_WARNING
    ) {

        $instance = new self();

        $embeds = [
            [
                "title" => '緩慢查詢',
                "color" => $color,
                "fields" => [
                    $instance->getField('執行時間', "`".(string)$executionTime."`", true),
                    $instance->getField('資料庫名', $dbName, true),
                    $instance->getField('主機', $dbHost, true),
                ]
            ],
        ];

        $content = "緩慢查詢```sql\n".$rawSQL."```
位置```\n$position```\n";

        $instance->send($content, $embeds);
    }


    /**
     * 記錄 Error
     * @param string $title
     * @param array $message
     */
    public static function logError(string $title, array $fields = [], int $color = self::COLOR_WARNING)
    {
        $instance = new self();

        $embeds = [
            [
                "title" => '',
                "color" => $color,
                "fields" => $fields,
                "author" => [
                    "name" => $instance->username,
                ],
            ],
        ];

        $instance->send($title, $embeds);
    }


    /**
     * 記錄郵件結果
     * @param string $content
     * @param array $fields
     * @param int $color
     */
    public static function logMailResult(string $content, array $fields = [], int $color = 0) {
        $instance = new self();

        if ($fields) {
            $embeds = [
                [
                    "title" => '',
//                    "description" => $description,
                    "color" => $color,
                    "fields" => $fields,
//                    "timestamp" => now(),
//                    "footer" => [
//                        "text" => "這是頁尾文字",
//                        "icon_url" => $instance->avatarURL,
//                    ],
                    "author" => [
                        "name" => $instance->username,
//                        "icon_url" => $instance->avatarURL,
//                        "url" => $instance->avatarURL,
                    ],
//                    "thumbnail" => [
//                        "url" => $instance->avatarURL,
//                    ],
                ]
            ];
        }
        else {
            $embeds = null;
        }

        $instance->send($content, $embeds);
    }


    /**
     * 發送
     * @param string $content
     * @param array $embeds
     * @return bool
     */
    public function send(string $content="", $embeds=[]): bool
    {
        $message = [
            'content' => $content,
            "username" => $this->username,
            "avatar_url" => $this->avatarURL,
        ];

        if ($embeds) {
            $message["embeds"] = $embeds;
        }

        $logChannel = Log::channel('discord');

        $ch = curl_init($this->webhookURL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        try {
            if (curl_errno($ch)) {
                $logChannel->error('Curl error: ' . curl_error($ch));
                return false;
            }
            else if ($response === '') {
                $logChannel->info('OK');
                return true;
            }
            else if ($httpCode === 400) {
                // 取得回應
                $errorMessage = json_encode($response);

                // 輸出訊息
                echo $errorMessage;

                // 輸出至日誌
                $logChannel->error($errorMessage);

                if (config('app.debug')) {
                    dd('錯誤', $errorMessage, $embeds);
                }
                return true;
            }
            else if ($httpCode === 404) {
                $logChannel->error('Webhook Not Found');
                return false;
            }
            else {
                $response = json_decode($response);
                dd($httpCode, $response);
                if ($response->message) {

                }
                else {
                    $message = $response->message;
                    $code = $response->code;
                    $logChannel->error($code.' '.$message);
                    return false;
                }
            }
        }
        catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            echo $errorMessage;
            $logChannel->error($errorMessage);
            return false;
        }
        finally {
            curl_close($ch);
        }
    }
}
