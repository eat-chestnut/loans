<?php

namespace App\Admin\Controllers;

use Illuminate\Http\Request;
use Slowlyo\OwlAdmin\Controllers\AdminController;

class SettingController extends AdminController
{
    public function index()
    {
        if ($this->actionOfGetData()) return $this->response()->success(settings()->all());

        $page = $this->basePage()->body([
            amis()->Alert()->showIcon()->body("系统配置管理，包括临期提醒等业务参数设置。"),
            $this->form(),
        ]);

        return $this->response()->success($page);
    }

    public function form()
    {
        return $this->baseForm(false)
            ->redirect('')
            ->api($this->getStorePath())
            ->initApi('/system/settings?_action=getData')
            ->body(
                amis()->Tabs()->tabs([
                    amis()->Tab()->title('临期提醒配置')->body([
                        amis()->Alert()->body('配置临期提醒的触发条件和频率，设置后实时生效。')->level('info'),
                        amis()->GroupControl()->body([
                            amis()->NumberControl('due_days', '临期天数阈值')
                                ->value(3)
                                ->min(1)
                                ->max(30)
                                ->step(1)
                                ->description('距离应还日期小于等于该天数视为临期'),
                            amis()->NumberControl('due_frequency', '临期提醒频率（次/天）')
                                ->value(2)
                                ->min(1)
                                ->max(10)
                                ->step(1)
                                ->description('同一客户每日发送提醒的次数，用于控制触达频率'),
                        ]),
                        amis()->TextControl('reminder_preview', '提醒效果预览')
                            ->static()
                            ->description('统计中...'),
                        amis()->TextControl('reminder_example', '示例提醒计划')
                            ->static()
                            ->type('textarea')
                            ->description('示例提醒计划将在输入变化后自动更新'),
                    ]),
                    amis()->Tab()->title('基本设置')->body([
                        amis()->TextControl()->label('网站名称')->name('site_name'),
                        amis()->InputKV()->label('附加配置')->name('addition_config'),
                    ]),
                    amis()->Tab()->title('上传设置')->body([
                        amis()->TextControl()->label('上传域名')->name('upload_domain'),
                        amis()->TextControl()->label('上传路径')->name('upload_path'),
                    ]),
                ])
            );
    }

    public function store(Request $request)
    {
        $data = $request->only([
            'due_days',
            'due_frequency',
            'site_name',
            'addition_config',
            'upload_domain',
            'upload_path',
        ]);

        // 生成预览数据
        $dueDays = $data['due_days'] ?? 3;
        $dueFreq = $data['due_frequency'] ?? 2;
        
        // 计算临期提醒统计
        $upcomingCount = \App\Models\RepaymentSchedule::whereRaw('DATEDIFF(due_date, NOW()) BETWEEN 0 AND ' . $dueDays)
            ->where('is_paid', 0)
            ->count();
            
        $data['reminder_preview'] = "未来{$dueDays}天内待提醒：{$upcomingCount}笔，每日最多：{$dueFreq}次/客户";
        
        // 生成示例计划
        $data['reminder_example'] = "提醒策略示例：\n" .
            "- T-{$dueDays}天：首次提醒\n" .
            "- T-" . max(1, $dueDays - 1) . "天：短信提醒\n" .
            "- T-" . max(1, $dueDays - 2) . "天：企微提醒\n" .
            "- T日：电话提醒\n" .
            "每日最多发送{$dueFreq}次提醒给同一客户";

        return settings()->adminSetMany($data);
    }
}
