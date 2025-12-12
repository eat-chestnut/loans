<?php

namespace App\Admin\Controllers;

use Illuminate\Http\Request;
use Slowlyo\OwlAdmin\Models\AdminSetting;

class SettingController extends AdminController
{
    public function index()
    {
        if ($this->actionOfGetData()) return $this->response()->success(settings()->all());

        $page = $this->basePage()->body([
            $this->form(),
        ]);

        return $this->response()->success($page);
    }

    public function form($isEdit = false)
    {
        return amis()->Page()->body(
            amis()->Tabs()->tabs([
                amis()->Tab()->title('临期提醒配置')->body(
                    $this->reminderForm()
                ),
                amis()->Tab()->title('企微配置')->body(
                    $this->wecomForm()
                ),
                amis()->Tab()->title('百度智能外呼配置')->body(
                    $this->baiduCallForm()
                ),
                amis()->Tab()->title('腾讯云短信配置')->body(
                    $this->smsForm()
                ),
            ])
        );
    }

    private function reminderForm()
    {
        return $this->baseForm(false)
            ->redirect('')
            ->api('/system/settings/reminder')
            ->panelClassName('px-0')
            ->initApi('/system/settings/reminder?_action=getData')
            ->body([
                amis()->Alert()
                    ->body('配置不同临期天数使用的提醒通道，系统将按规则自动发送提醒')
                    ->level('info')
                    ->showIcon(),
                amis()->ComboControl('reminder.rules', '提醒规则配置')
                    ->multiple()
                    ->draggable()
                    ->addable()
                    ->removable()
                    ->scaffold([
                        'days_before' => 3,
                        'channels' => ['sms'],
                        'max_times' => 1,
                        'enabled' => true,
                    ])
                    ->items([
                        amis()->NumberControl('days_before', '提前天数')
                            ->required()
                            ->min(0)
                            ->max(30)
                            ->description('距离应还日期的天数（0表示当天）'),
                        amis()->CheckboxesControl('channels', '提醒通道')
                            ->required()
                            ->options([
                                ['label' => '短信', 'value' => 'sms'],
                                ['label' => '企微', 'value' => 'wecom'],
                                ['label' => '电话', 'value' => 'phone'],
                            ])
                            ->description('可多选，将按顺序发送'),
                        amis()->NumberControl('max_times', '每日最多次数')
                            ->required()
                            ->min(1)
                            ->max(10)
                            ->value(1)
                            ->description('同一客户当天最多发送次数'),
                        amis()->SwitchControl('enabled', '启用')
                            ->value(true),
                    ])
                    ->value([
                        ['days_before' => 7, 'channels' => ['wecom'], 'max_times' => 1, 'enabled' => true],
                        ['days_before' => 3, 'channels' => ['sms', 'wecom'], 'max_times' => 2, 'enabled' => true],
                        ['days_before' => 1, 'channels' => ['sms', 'wecom', 'phone'], 'max_times' => 3, 'enabled' => true],
                        ['days_before' => 0, 'channels' => ['sms', 'wecom', 'phone'], 'max_times' => 5, 'enabled' => true],
                    ])
                    ->description('可拖拽排序，系统将按从上到下的顺序匹配规则'),
                amis()->Divider(),
                amis()->GroupControl()->body([
                    amis()->NumberControl('reminder.start_hour', '提醒开始时间（小时）')
                        ->value(9)
                        ->min(0)
                        ->max(23)
                        ->description('每日开始发送提醒的时间'),
                    amis()->NumberControl('reminder.end_hour', '提醒结束时间（小时）')
                        ->value(20)
                        ->min(0)
                        ->max(23)
                        ->description('每日停止发送提醒的时间'),
                ]),
            ]);
    }

    private function wecomForm()
    {
        return $this->baseForm(false)
            ->redirect('')
            ->api('/system/settings/wecom')
            ->panelClassName('px-0')
            ->initApi('/system/settings/wecom?_action=getData')
            ->body([
                        amis()->GroupControl()->body([
                            amis()->TextControl('wecom.corp_id', '企业ID (CorpID)')
                                ->required()
                                ->description('企业微信后台的企业ID'),
                            amis()->TextControl('wecom.agent_id', '应用AgentID')
                                ->required()
                                ->description('企业微信应用的AgentID'),
                            amis()->TextControl('wecom.secret', '应用Secret')
                                ->required()
                                ->type('input-password')
                                ->description('企业微信应用的Secret密钥'),
                        ]),
                        amis()->GroupControl()->body([
                            amis()->TextControl('wecom.token', 'Token')
                                ->description('用于接收消息的Token（可选）'),
                            amis()->TextControl('wecom.encoding_aes_key', 'EncodingAESKey')
                                ->description('用于消息加解密的AESKey（可选）'),
                        ]),
                        amis()->SwitchControl('wecom.enabled', '启用企微通知')
                            ->value(false)
                            ->description('开启后将通过企业微信发送提醒消息'),
            ]);
    }

    private function baiduCallForm()
    {
        return $this->baseForm(false)
            ->redirect('')
            ->api('/system/settings/baidu-call')
            ->panelClassName('px-0')
            ->initApi('/system/settings/baidu-call?_action=getData')
            ->body([
                        amis()->GroupControl()->body([
                            amis()->TextControl('baidu_call.app_id', 'App ID')
                                ->required()
                                ->description('百度智能外呼平台应用ID'),
                            amis()->TextControl('baidu_call.app_key', 'App Key')
                                ->required()
                                ->type('input-password')
                                ->description('百度智能外呼平台应用密钥'),
                        ]),
                        amis()->GroupControl()->body([
                            amis()->TextControl('baidu_call.bot_id', '机器人ID')
                                ->required()
                                ->description('外呼机器人ID'),
                            amis()->TextControl('baidu_call.task_name', '任务名称前缀')
                                ->value('还款提醒')
                                ->description('外呼任务名称前缀，系统将自动添加日期'),
                        ]),
                        amis()->GroupControl()->body([
                            amis()->TextControl('baidu_call.template_reminder', '临期提醒话术模板ID')
                                ->description('临期提醒的话术模板ID'),
                            amis()->TextControl('baidu_call.template_overdue', '逾期提醒话术模板ID')
                                ->description('逾期提醒的话术模板ID'),
                        ]),
                        amis()->GroupControl()->body([
                            amis()->NumberControl('baidu_call.max_retry', '最大重试次数')
                                ->value(2)
                                ->min(0)
                                ->max(5)
                                ->description('外呼失败时的最大重试次数'),
                            amis()->NumberControl('baidu_call.timeout', '外呼超时时间（秒）')
                                ->value(60)
                                ->min(30)
                                ->max(300)
                                ->description('单次外呼的超时时间'),
                        ]),
                        amis()->SwitchControl('baidu_call.enabled', '启用电话提醒')
                            ->value(false)
                            ->description('开启后将通过百度智能外呼平台发送电话提醒'),
            ]);
    }

    private function smsForm()
    {
        return $this->baseForm(false)
            ->redirect('')
            ->api('/system/settings/sms')
            ->panelClassName('px-0')
            ->initApi('/system/settings/sms?_action=getData')
            ->body([
                        amis()->GroupControl()->body([
                            amis()->TextControl('sms.secret_id', 'SecretId')
                                ->required()
                                ->description('腾讯云API密钥SecretId'),
                            amis()->TextControl('sms.secret_key', 'SecretKey')
                                ->required()
                                ->type('input-password')
                                ->description('腾讯云API密钥SecretKey'),
                        ]),
                        amis()->GroupControl()->body([
                            amis()->TextControl('sms.sdk_app_id', 'SDK AppID')
                                ->required()
                                ->description('短信应用SDK AppID'),
                            amis()->TextControl('sms.sign_name', '短信签名')
                                ->required()
                                ->description('已审核通过的短信签名内容'),
                        ]),
                        amis()->GroupControl()->body([
                            amis()->TextControl('sms.template_id_reminder', '临期提醒模板ID')
                                ->description('临期提醒短信模板ID'),
                            amis()->TextControl('sms.template_id_overdue', '逾期提醒模板ID')
                                ->description('逾期提醒短信模板ID'),
                        ]),
                        amis()->SwitchControl('sms.enabled', '启用短信通知')
                            ->value(false)
                            ->description('开启后将通过腾讯云短信发送提醒消息'),
            ]);
    }

    public function reminder(Request $request)
    {
        if ($this->actionOfGetData()) {
            return $this->response()->success(['reminder' => settings()->get('reminder')]);
        }

        $data = [
            'reminder' => $request->get('reminder')
        ];

        return settings()->adminSetMany($data);
    }

    public function wecom(Request $request)
    {
        if ($this->actionOfGetData()) {
            return $this->response()->success(['wecom' => settings()->get('wecom')]);
        }

        $data = [
            'wecom' => $request->get('wecom')
        ];

        return settings()->adminSetMany($data);
    }

    public function baiduCall(Request $request)
    {
        if ($this->actionOfGetData()) {
            return $this->response()->success(['baidu_call' => settings()->get('baidu_call')]);
        }

        $data = [
            'baidu_call' => $request->get('baidu_call')
        ];
        return settings()->adminSetMany($data);
    }

    public function sms(Request $request)
    {
        if ($this->actionOfGetData()) {
            return $this->response()->success(['sms' => settings()->get('sms')]);
        }

        $data = [
            'sms' => $request->get('sms')
        ];
        return settings()->adminSetMany($data);
    }
}
