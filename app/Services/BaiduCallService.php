<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 百度智能外呼服务
 */
class BaiduCallService
{
    private string $appId;
    private string $appKey;
    private string $botId;
    private string $apiBaseUrl = 'https://aip.baidubce.com/rpc/2.0/smartapp';

    public function __construct()
    {
        $this->appId = settings()->get('baidu_call_app_id', '');
        $this->appKey = settings()->get('baidu_call_app_key', '');
        $this->botId = settings()->get('baidu_call_bot_id', '');
    }

    /**
     * 发起外呼任务
     *
     * @param string $phone 客户电话
     * @param array $params 话术参数
     * @param string $type 提醒类型：reminder（临期）或 overdue（逾期）
     * @return array
     */
    public function makeCall(string $phone, array $params = [], string $type = 'reminder'): array
    {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'message' => '百度智能外呼未启用',
            ];
        }

        try {
            $templateId = $this->getTemplateId($type);
            
            if (empty($templateId)) {
                return [
                    'success' => false,
                    'message' => '未配置话术模板ID',
                ];
            }

            $accessToken = $this->getAccessToken();
            
            if (empty($accessToken)) {
                return [
                    'success' => false,
                    'message' => '获取访问令牌失败',
                ];
            }

            $taskName = $this->generateTaskName($type);
            $maxRetry = (int)settings()->get('baidu_call_max_retry', 2);
            $timeout = (int)settings()->get('baidu_call_timeout', 60);

            $response = Http::timeout($timeout)
                ->post($this->apiBaseUrl . '/call/create', [
                    'access_token' => $accessToken,
                    'bot_id' => $this->botId,
                    'task_name' => $taskName,
                    'phone' => $phone,
                    'template_id' => $templateId,
                    'params' => $params,
                    'max_retry' => $maxRetry,
                ]);

            $result = $response->json();

            if (isset($result['error_code']) && $result['error_code'] !== 0) {
                Log::error('百度智能外呼失败', [
                    'phone' => $phone,
                    'error' => $result,
                ]);

                return [
                    'success' => false,
                    'message' => $result['error_msg'] ?? '外呼失败',
                    'error_code' => $result['error_code'],
                ];
            }

            return [
                'success' => true,
                'message' => '外呼任务创建成功',
                'task_id' => $result['task_id'] ?? null,
                'call_id' => $result['call_id'] ?? null,
            ];

        } catch (Exception $e) {
            Log::error('百度智能外呼异常', [
                'phone' => $phone,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => '外呼异常：' . $e->getMessage(),
            ];
        }
    }

    /**
     * 查询外呼任务状态
     *
     * @param string $taskId 任务ID
     * @return array
     */
    public function queryTaskStatus(string $taskId): array
    {
        try {
            $accessToken = $this->getAccessToken();
            
            if (empty($accessToken)) {
                return [
                    'success' => false,
                    'message' => '获取访问令牌失败',
                ];
            }

            $response = Http::get($this->apiBaseUrl . '/call/query', [
                'access_token' => $accessToken,
                'task_id' => $taskId,
            ]);

            $result = $response->json();

            if (isset($result['error_code']) && $result['error_code'] !== 0) {
                return [
                    'success' => false,
                    'message' => $result['error_msg'] ?? '查询失败',
                ];
            }

            return [
                'success' => true,
                'status' => $result['status'] ?? 'unknown',
                'call_duration' => $result['call_duration'] ?? 0,
                'result' => $result['result'] ?? null,
            ];

        } catch (Exception $e) {
            Log::error('查询外呼任务状态异常', [
                'task_id' => $taskId,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => '查询异常：' . $e->getMessage(),
            ];
        }
    }

    /**
     * 获取访问令牌
     *
     * @return string|null
     */
    private function getAccessToken(): ?string
    {
        try {
            $cacheKey = 'baidu_call_access_token';
            
            if (cache()->has($cacheKey)) {
                return cache()->get($cacheKey);
            }

            $response = Http::get('https://aip.baidubce.com/oauth/2.0/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $this->appId,
                'client_secret' => $this->appKey,
            ]);

            $result = $response->json();

            if (isset($result['access_token'])) {
                $expiresIn = $result['expires_in'] ?? 2592000;
                cache()->put($cacheKey, $result['access_token'], $expiresIn - 600);
                
                return $result['access_token'];
            }

            Log::error('获取百度访问令牌失败', ['result' => $result]);
            return null;

        } catch (Exception $e) {
            Log::error('获取百度访问令牌异常', ['exception' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * 获取话术模板ID
     *
     * @param string $type reminder 或 overdue
     * @return string|null
     */
    private function getTemplateId(string $type): ?string
    {
        $key = $type === 'overdue' 
            ? 'baidu_call_template_overdue' 
            : 'baidu_call_template_reminder';
        
        return settings()->get($key);
    }

    /**
     * 生成任务名称
     *
     * @param string $type
     * @return string
     */
    private function generateTaskName(string $type): string
    {
        $prefix = settings()->get('baidu_call_task_name', '还款提醒');
        $typeLabel = $type === 'overdue' ? '逾期' : '临期';
        $date = date('Y-m-d H:i:s');
        
        return "{$prefix}-{$typeLabel}-{$date}";
    }

    /**
     * 检查服务是否启用
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return (bool)settings()->get('baidu_call_enabled', false) 
            && !empty($this->appId) 
            && !empty($this->appKey) 
            && !empty($this->botId);
    }

    /**
     * 批量发起外呼
     *
     * @param array $calls 外呼列表 [['phone' => '13800138000', 'params' => [...], 'type' => 'reminder'], ...]
     * @return array
     */
    public function batchMakeCall(array $calls): array
    {
        $results = [];
        $successCount = 0;
        $failCount = 0;

        foreach ($calls as $call) {
            $phone = $call['phone'] ?? '';
            $params = $call['params'] ?? [];
            $type = $call['type'] ?? 'reminder';

            if (empty($phone)) {
                $failCount++;
                continue;
            }

            $result = $this->makeCall($phone, $params, $type);
            
            if ($result['success']) {
                $successCount++;
            } else {
                $failCount++;
            }

            $results[] = array_merge($result, ['phone' => $phone]);

            usleep(200000);
        }

        return [
            'total' => count($calls),
            'success' => $successCount,
            'fail' => $failCount,
            'details' => $results,
        ];
    }
}
