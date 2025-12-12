<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * 提醒规则服务 - 根据临期天数获取应使用的提醒通道
 */
class ReminderRuleService
{
    /**
     * 根据距离到期天数获取匹配的提醒规则
     *
     * @param int $daysBeforeDue 距离到期天数
     * @return array|null 返回匹配的规则，如果没有匹配则返回null
     */
    public function getRuleByDaysBeforeDue(int $daysBeforeDue): ?array
    {
        $rules = $this->getAllRules();
        
        foreach ($rules as $rule) {
            if (!($rule['enabled'] ?? true)) {
                continue;
            }
            
            if ($rule['days_before'] == $daysBeforeDue) {
                return $rule;
            }
        }
        
        return null;
    }

    /**
     * 获取所有启用的提醒规则
     *
     * @return array
     */
    public function getAllRules(): array
    {
        $rules = settings()->get('reminder_rules', []);
        
        if (empty($rules)) {
            return $this->getDefaultRules();
        }
        
        return is_array($rules) ? $rules : [];
    }

    /**
     * 获取默认提醒规则
     *
     * @return array
     */
    public function getDefaultRules(): array
    {
        return [
            ['days_before' => 7, 'channels' => ['wecom'], 'max_times' => 1, 'enabled' => true],
            ['days_before' => 3, 'channels' => ['sms', 'wecom'], 'max_times' => 2, 'enabled' => true],
            ['days_before' => 1, 'channels' => ['sms', 'wecom', 'phone'], 'max_times' => 3, 'enabled' => true],
            ['days_before' => 0, 'channels' => ['sms', 'wecom', 'phone'], 'max_times' => 5, 'enabled' => true],
        ];
    }

    /**
     * 检查当前时间是否在允许发送提醒的时间段内
     *
     * @return bool
     */
    public function isWithinReminderHours(): bool
    {
        $startHour = (int)settings()->get('reminder_start_hour', 9);
        $endHour = (int)settings()->get('reminder_end_hour', 20);
        $currentHour = Carbon::now()->hour;
        
        return $currentHour >= $startHour && $currentHour <= $endHour;
    }

    /**
     * 获取指定通道是否启用
     *
     * @param string $channel 通道名称：sms, wecom, phone
     * @return bool
     */
    public function isChannelEnabled(string $channel): bool
    {
        switch ($channel) {
            case 'sms':
                return (bool)settings()->get('sms_enabled', false);
            case 'wecom':
                return (bool)settings()->get('wecom_enabled', false);
            case 'phone':
                return (bool)settings()->get('baidu_call_enabled', false);
            default:
                return false;
        }
    }

    /**
     * 获取应该发送提醒的通道列表（已过滤禁用的通道）
     *
     * @param int $daysBeforeDue 距离到期天数
     * @return array 返回启用的通道列表
     */
    public function getEnabledChannels(int $daysBeforeDue): array
    {
        $rule = $this->getRuleByDaysBeforeDue($daysBeforeDue);
        
        if (!$rule) {
            return [];
        }
        
        $channels = $rule['channels'] ?? [];
        
        return array_filter($channels, function($channel) {
            return $this->isChannelEnabled($channel);
        });
    }

    /**
     * 获取指定天数的最大发送次数
     *
     * @param int $daysBeforeDue 距离到期天数
     * @return int
     */
    public function getMaxTimesPerDay(int $daysBeforeDue): int
    {
        $rule = $this->getRuleByDaysBeforeDue($daysBeforeDue);
        
        return $rule['max_times'] ?? 1;
    }

    /**
     * 获取所有需要提醒的天数列表
     *
     * @return array
     */
    public function getAllReminderDays(): array
    {
        $rules = $this->getAllRules();
        $days = [];
        
        foreach ($rules as $rule) {
            if ($rule['enabled'] ?? true) {
                $days[] = $rule['days_before'];
            }
        }
        
        return array_unique($days);
    }
}
