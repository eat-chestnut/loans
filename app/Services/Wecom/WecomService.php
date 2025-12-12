<?php

namespace App\Services\Wecom;

use App\Models\WecomContact;
use App\Models\WecomLog;
use App\Services\AdminService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WecomService extends AdminService
{
    protected string $modelName = WecomContact::class;

    public function enabled(): bool
    {
        return (bool) config('services.wecom.enabled');
    }

    public function sendText(WecomContact $contact, string $content, ?int $loanId = null): array
    {
        if (!$this->enabled()) {
            return [
                'status'  => 'disabled',
                'message' => 'WeCom disabled',
            ];
        }

        $token = $this->getAccessToken();

        $payload = [
            'external_userid'  => [$contact->wechat_id],
            'text'             => ['content' => $content],
            'msgtype'          => 'text',
        ];
        if ($sender = config('services.wecom.sender_user_id')) {
            $payload['sender'] = $sender;
        }

        $response = Http::asJson()
            ->post("https://qyapi.weixin.qq.com/cgi-bin/externalcontact/message/send?access_token={$token}", $payload);

        $result = $response->json();

        if (($result['errcode'] ?? 0) !== 0) {
            Log::warning('WeCom message failed', [
                'contact_id' => $contact->getKey(),
                'payload'    => $payload,
                'result'     => $result,
            ]);
        }

        WecomLog::create([
            'customer_id'  => $contact->customer_id,
            'loan_id'      => $loanId,
            'sent_at'      => now(),
            'contact_name' => $contact->name,
            'wechat_id'    => $contact->wechat_id,
            'content'      => json_encode([
                'payload' => $payload,
                'result'  => $result,
            ], JSON_UNESCAPED_UNICODE),
        ]);

        return $result;
    }

    protected function getAccessToken(): string
    {
        $corpId = config('services.wecom.corp_id');
        $corpSecret = config('services.wecom.app_secret');
        $cacheKey = "wecom_token_{$corpId}";

        return Cache::remember($cacheKey, (int) config('services.wecom.cache_ttl', 5400), function () use ($corpId, $corpSecret) {
            $response = Http::get('https://qyapi.weixin.qq.com/cgi-bin/gettoken', [
                'corpid'     => $corpId,
                'corpsecret' => $corpSecret,
            ])->json();

            if (($response['errcode'] ?? 0) !== 0) {
                throw new \RuntimeException('Failed to fetch WeCom token: ' . ($response['errmsg'] ?? 'unknown'));
            }

            return $response['access_token'];
        });
    }
}
