<?php

namespace App\Services\Wecom;

use App\Models\WecomContact;
use App\Models\WecomLog;
use App\Services\AdminService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WecomService extends AdminService
{
    protected string $modelName = WecomContact::class;

    public function enabled(): bool
    {
        return (bool) settings()->arrayGet('wecom', 'enabled', false);
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
        if ($sender = settings()->arrayGet('wecom', 'sender_user_id')) {
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
        $corpId = settings()->arrayGet('wecom','corp_id');
        $corpSecret = settings()->arrayGet('wecom','secret');
        $cacheKey = "wecom_token_{$corpId}";
        return Cache::remember($cacheKey, 5400, function () use ($corpId, $corpSecret) {
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

    /**
     * 同步企微客户信息
     *
     * @return array ['created' => int, 'updated' => int]
     */
    public function syncCustomers(): array
    {
        $token = $this->getAccessToken();
        $created = 0;
        $updated = 0;

        // 获取配置的跟进人员ID列表
        $userIds = $this->getFollowUserIds();

        foreach ($userIds as $userId) {
            $cursor = '';

            do {
                // 获取客户列表
                $params = [
                    'userid' => $userId,
                    'limit' => 100,
                ];

                if ($cursor) {
                    $params['cursor'] = $cursor;
                }

                $response = Http::get(
                    "https://qyapi.weixin.qq.com/cgi-bin/externalcontact/list?access_token={$token}&".Arr::query($params)
                )->json();

                if (($response['errcode'] ?? 0) !== 0) {
                    Log::error('获取企微客户列表失败', [
                        'user_id' => $userId,
                        'response' => $response,
                    ]);
                    admin_abort('获取企微客户列表失败: ' . ($response['errmsg'] ?? '未知'));
                }

                $externalUserIds = $response['external_userid'] ?? [];

                // 获取每个客户的详细信息
                foreach ($externalUserIds as $externalUserId) {
                    $result = $this->syncCustomerDetail($externalUserId, $token);
                    if ($result === 'created') {
                        $created++;
                    } elseif ($result === 'updated') {
                        $updated++;
                    }
                }

                $cursor = $response['next_cursor'] ?? '';

            } while ($cursor);
        }

        return [
            'created' => $created,
            'updated' => $updated,
        ];
    }

    /**
     * 同步单个客户详细信息
     */
    protected function syncCustomerDetail(string $externalUserId, string $token): ?string
    {
        $response = Http::get(
            "https://qyapi.weixin.qq.com/cgi-bin/externalcontact/get?access_token={$token}",
            ['external_userid' => $externalUserId]
        )->json();

        if (($response['errcode'] ?? 0) !== 0) {
            Log::error('获取企微客户详情失败', [
                'external_userid' => $externalUserId,
                'response' => $response,
            ]);
            return null;
        }

        $externalContact = $response['external_contact'] ?? [];

        if (empty($externalContact)) {
            return null;
        }

        // 查找或创建客户记录
        $contact = WecomContact::firstOrNew(['wechat_id' => $externalUserId]);

        $isNew = !$contact->exists;

        $contact->name = $externalContact['name'] ?? '';
        $contact->wechat_id = $externalUserId;

        // 如果有手机号，更新手机号
        if (!empty($externalContact['external_profile']['external_attr'])) {
            foreach ($externalContact['external_profile']['external_attr'] as $attr) {
                if ($attr['type'] == 0 && $attr['name'] == '手机') {
                    $contact->mobile = $attr['value']['text'] ?? null;
                    break;
                }
            }
        }

        $contact->save();

        return $isNew ? 'created' : 'updated';
    }

    /**
     * 获取跟进人员ID列表
     */
    protected function getFollowUserIds(): array
    {
        // 从配置中获取，如果没有配置则获取所有成员
        $userIds = settings()->arrayGet('wecom', 'follow_user_ids', []);

        if (empty($userIds)) {
            // 如果没有配置，获取所有成员
            $userIds = $this->getAllUserIds();
        }

        return is_array($userIds) ? $userIds : [];
    }

    /**
     * 获取所有成员ID
     */
    protected function getAllUserIds(): array
    {
        try {
            $token = $this->getAccessToken();

            $response = Http::get(
                "https://qyapi.weixin.qq.com/cgi-bin/user/simplelist?access_token={$token}",
                ['department_id' => 1, 'fetch_child' => 1]
            )->json();

            if (($response['errcode'] ?? 0) !== 0) {
                Log::error('获取企微成员列表失败', ['response' => $response]);
                return [];
            }

            return array_column($response['userlist'] ?? [], 'userid');
        } catch (\Exception $e) {
            Log::error('获取企微成员列表异常', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
