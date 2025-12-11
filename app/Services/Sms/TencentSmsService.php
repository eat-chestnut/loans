<?php

namespace App\Services\Sms;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use TencentCloud\Common\Credential;
use TencentCloud\Common\Exception\TencentCloudSDKException;
use TencentCloud\Common\Profile\ClientProfile;
use TencentCloud\Common\Profile\HttpProfile;
use TencentCloud\Sms\V20210111\Models\SendSmsRequest;
use TencentCloud\Sms\V20210111\SmsClient;

class TencentSmsService
{
    public function enabled(): bool
    {
        return (bool) config('services.tencent_sms.enabled');
    }

    public function send(string $phone, array $templateParams = [], ?string $templateId = null): array
    {
        if (!$this->enabled()) {
            return [
                'status'  => 'disabled',
                'message' => 'Tencent SMS disabled',
            ];
        }

        $config = config('services.tencent_sms');

        foreach (['secret_id', 'secret_key', 'app_id', 'sign'] as $key) {
            if (empty($config[$key])) {
                throw new RuntimeException("Tencent SMS config `{$key}` missing.");
            }
        }

        $templateId = $templateId ?: Arr::get($config, 'template_id');

        $credential = new Credential($config['secret_id'], $config['secret_key']);

        $httpProfile = new HttpProfile();
        $httpProfile->setEndpoint('sms.tencentcloudapi.com');

        $clientProfile = new ClientProfile();
        $clientProfile->setHttpProfile($httpProfile);

        $client = new SmsClient($credential, $config['region'] ?? 'ap-guangzhou', $clientProfile);

        $request = new SendSmsRequest();
        $request->SmsSdkAppId = $config['app_id'];
        $request->SignName = $config['sign'];
        $request->TemplateId = $templateId;
        $request->TemplateParamSet = $templateParams;
        $request->PhoneNumberSet = [$this->formatPhone($phone)];

        try {
            $response = $client->SendSms($request);
            $status = $response->SendStatusSet[0] ?? null;

            return [
                'status'     => $status->Code ?? 'Unknown',
                'message'    => $status->Message ?? '',
                'request_id' => $response->RequestId ?? null,
            ];
        } catch (TencentCloudSDKException $e) {
            Log::error('Tencent SMS send failed', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);

            return [
                'status'  => 'SDKException',
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function formatPhone(string $phone): string
    {
        $phone = trim($phone);

        if (str_starts_with($phone, '+')) {
            return $phone;
        }

        if (preg_match('/^\d+$/', $phone)) {
            return '+86' . $phone;
        }

        return $phone;
    }
}
