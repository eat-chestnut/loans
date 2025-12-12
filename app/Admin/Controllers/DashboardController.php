<?php

namespace App\Admin\Controllers;

use App\Services\DashboardService;
use Slowlyo\OwlAdmin\Renderers\Page;
use Slowlyo\OwlAdmin\Renderers\Card;
use Slowlyo\OwlAdmin\Renderers\Flex;
use Slowlyo\OwlAdmin\Renderers\Grid;
use Slowlyo\OwlAdmin\Renderers\Tpl;
use Slowlyo\OwlAdmin\Renderers\Button;
use Slowlyo\OwlAdmin\Renderers\Service;

/**
 * 仪表盘控制器
 */
class DashboardController extends AdminController
{
    public function index()
    {
        $dashboard = Page::make()
            ->className("p-3")
            ->title("首页概览")
            ->body([
                // 时间维度选择器
                Card::make()
                    ->className("period-selector-card mb-3")
                    ->body([
                        amis()->Form()
                            ->wrapWithPanel(false)
                            ->mode('inline')
                            ->body([
                                amis()->RadiosControl('period', '时间维度')
                                    ->options([
                                        ['label' => '本周', 'value' => 'week'],
                                        ['label' => '本月', 'value' => 'month'],
                                        ['label' => '本季度', 'value' => 'quarter'],
                                        ['label' => '本年度', 'value' => 'year'],
                                    ])
                                    ->value('month')
                                    ->selectFirst()
                            ])
                            ->onEvent([
                                'change' => [
                                    'actions' => [
                                        [
                                            'actionType' => 'reload',
                                            'componentId' => 'dashboard-metrics',
                                            'data' => [
                                                'period' => '${period}',
                                            ],
                                        ],
                                    ],
                                ],
                            ]),
                    ]),

                // 英雄卡片 - 业务全景
                Card::make()
                    ->className("hero-card")
                    ->id('dashboard-metrics')
                    ->body([
                        Flex::make()->items([
                            [
                                'type' => 'wrapper',
                                'className' => 'hero-text',
                                'body' => [
                                    Tpl::make()
                                        ->tpl('<p class="hero-eyebrow">实时 · 报表中心</p>')
                                        ->className("hero-eyebrow"),
                                    Tpl::make()
                                        ->tpl('<h2>业务全景一屏掌控 <span class="period-badge">${period_label}</span></h2>')
                                        ->className("hero-title"),
                                    Tpl::make()
                                        ->tpl('追踪现金流、风险与提醒履约，洞察每一个节点。')
                                        ->className("hero-desc"),
                                    Tpl::make()
                                        ->tpl('${date}')
                                        ->className("hero-date"),
                                ]
                            ],
                            [
                                'type' => 'wrapper',
                                'className' => 'hero-metrics',
                                'body' => [
                                    Flex::make()->items([
                                        [
                                            'type' => 'wrapper',
                                            'body' => [
                                                Tpl::make()->tpl('${active_loans}'),
                                                Tpl::make()->tpl('在贷笔数')->className("metric-label"),
                                            ]
                                        ],
                                        [
                                            'type' => 'wrapper',
                                            'body' => [
                                                Tpl::make()->tpl('${active_customers}'),
                                                Tpl::make()->tpl('服务客户')->className("metric-label"),
                                            ]
                                        ],
                                        [
                                            'type' => 'wrapper',
                                            'body' => [
                                                Tpl::make()->tpl('${period_loans}'),
                                                Tpl::make()->tpl('${period_label}放款')->className("metric-label"),
                                            ]
                                        ],
                                        [
                                            'type' => 'wrapper',
                                            'body' => [
                                                Tpl::make()->tpl('${overdue_rate}'),
                                                Tpl::make()->tpl('逾期率')->className("metric-label"),
                                            ]
                                        ],
                                    ]),
                                ]
                            ],
                            [
                                'type' => 'wrapper',
                                'className' => 'hero-actions',
                                'body' => [
                                    Button::make()
                                        ->label("查看通知中心")
                                        ->level("primary")
                                        ->className("ghost")
                                        ->actionType("link")
                                        ->link("notifications"),
                                    Button::make()
                                        ->label("逾期详情")
                                        ->className("ghost")
                                        ->actionType("link")
                                        ->link("overdue"),
                                ]
                            ],
                        ]),
                    ])
                    ->api("/dashboard_metrics"),

                // 主要KPI指标
                Service::make()
                    ->id('kpi-service')
                    ->api('/dashboard_metrics?period=${period|default:month}')
                    ->body(
                        Grid::make()
                            ->className("kpi-grid")
                            ->columns([
                        Card::make()
                            ->className("kpi-card")
                            ->body([
                                Tpl::make()->tpl('💰')->className("kpi-icon"),
                                Tpl::make()->tpl('<h3>待收款（本息）</h3>')->className("kpi-title"),
                                Tpl::make()->tpl('${due_amount}')->className("metric"),
                                Tpl::make()->tpl('未结清计划之和')->className("help"),
                            ]),
                        Card::make()
                            ->className("kpi-card")
                            ->body([
                                Tpl::make()->tpl('📈')->className("kpi-icon"),
                                Tpl::make()->tpl('<h3>已收利息</h3>')->className("kpi-title"),
                                Tpl::make()->tpl('${paid_interest}')->className("metric"),
                                Tpl::make()->tpl('已还计划中的利息累计')->className("help"),
                            ]),
                        Card::make()
                            ->className("kpi-card")
                            ->body([
                                Tpl::make()->tpl('⚠️')->className("kpi-icon"),
                                Tpl::make()->tpl('<h3>逾期金额</h3>')->className("kpi-title"),
                                Tpl::make()->tpl('${overdue_amount}')->className("metric"),
                                Tpl::make()->tpl('未还且已过期本息合计')->className("help"),
                            ]),
                        Card::make()
                            ->className("kpi-card")
                            ->body([
                                Tpl::make()->tpl('🔥')->className("kpi-icon"),
                                Tpl::make()->tpl('<h3>高风险客户</h3>')->className("kpi-title"),
                                Tpl::make()->tpl('${high_risk_customers}')->className("metric"),
                                Tpl::make()->tpl('风险等级：高/极高')->className("help"),
                            ]),
                    ]),
                    ),

                // 次要KPI指标
                Service::make()
                    ->id('kpi-secondary-service')
                    ->api('/dashboard_metrics?period=${period|default:month}')
                    ->body(
                        Grid::make()
                            ->className("kpi-grid secondary")
                            ->columns([
                        Card::make()
                            ->className("kpi-card")
                            ->body([
                                Tpl::make()->tpl('⛰️')->className("kpi-icon"),
                                Tpl::make()->tpl('<h3>${period_label}逾期笔数</h3>')->className("kpi-title"),
                                Tpl::make()->tpl('${period_overdue_count}')->className("metric"),
                                Tpl::make()->tpl('本期新增逾期计划')->className("help"),
                            ]),
                        Card::make()
                            ->className("kpi-card")
                            ->body([
                                Tpl::make()->tpl('🤝')->className("kpi-icon"),
                                Tpl::make()->tpl('<h3>企微绑定率</h3>')->className("kpi-title"),
                                Tpl::make()->tpl('${wecom_rate}')->className("metric"),
                                Tpl::make()->tpl('已绑定/客户总数')->className("help"),
                            ]),
                        Card::make()
                            ->className("kpi-card")
                            ->body([
                                Tpl::make()->tpl('🔄')->className("kpi-icon"),
                                Tpl::make()->tpl('<h3>代扣成功率</h3>')->className("kpi-title"),
                                Tpl::make()->tpl('${autopay_success}')->className("metric"),
                                Tpl::make()->tpl('近30次代扣统计')->className("help"),
                            ]),
                        Card::make()
                            ->className("kpi-card")
                            ->body([
                                Tpl::make()->tpl('💬')->className("kpi-icon"),
                                Tpl::make()->tpl('<h3>${period_label}提醒发送</h3>')->className("kpi-title"),
                                Tpl::make()->tpl('${period_reminder_total}')->className("metric"),
                                Tpl::make()->tpl('短信: ${period_sms_sent} | 企微: ${period_wecom_sent}')->className("help"),
                            ]),
                        Card::make()
                            ->className("kpi-card")
                            ->body([
                                Tpl::make()->tpl('🏪')->className("kpi-icon"),
                                Tpl::make()->tpl('<h3>${period_label}放款笔数</h3>')->className("kpi-title"),
                                Tpl::make()->tpl('${period_loan_count}')->className("metric"),
                                Tpl::make()->tpl('本期新增放款')->className("help"),
                            ]),
                        Card::make()
                            ->className("kpi-card")
                            ->body([
                                Tpl::make()->tpl('🪙')->className("kpi-icon"),
                                Tpl::make()->tpl('<h3>${period_label}应收 / 实收</h3>')->className("kpi-title"),
                                Tpl::make()->tpl('${period_receivable} / ${period_received}')->className("metric"),
                                Tpl::make()->tpl('对比回款节奏')->className("help"),
                            ]),
                        Card::make()
                            ->className("kpi-card")
                            ->body([
                                Tpl::make()->tpl('📊')->className("kpi-icon"),
                                Tpl::make()->tpl('<h3>${period_label}回款率</h3>')->className("kpi-title"),
                                Tpl::make()->tpl('${period_collection_rate}')->className("metric"),
                                Tpl::make()->tpl('本期应收 vs 实收')->className("help"),
                            ]),
                        Card::make()
                            ->className("kpi-card")
                            ->body([
                                Tpl::make()->tpl('⏮️')->className("kpi-icon"),
                                Tpl::make()->tpl('<h3>提前结清率</h3>')->className("kpi-title"),
                                Tpl::make()->tpl('${prepay_rate}')->className("metric"),
                                Tpl::make()->tpl('完结贷款中提前还清占比')->className("help"),
                            ]),
                        Card::make()
                            ->className("kpi-card")
                            ->body([
                                Tpl::make()->tpl('🚫')->className("kpi-icon"),
                                Tpl::make()->tpl('<h3>坏账率</h3>')->className("kpi-title"),
                                Tpl::make()->tpl('${baddebt_rate}')->className("metric"),
                                Tpl::make()->tpl('DPD120+占总放款额')->className("help"),
                            ]),
                        Card::make()
                            ->className("kpi-card")
                            ->body([
                                Tpl::make()->tpl('♻️')->className("kpi-icon"),
                                Tpl::make()->tpl('<h3>回收率</h3>')->className("kpi-title"),
                                Tpl::make()->tpl('待实现')->className("metric"),
                                Tpl::make()->tpl('逾期回收 vs 逾期敞口')->className("help"),
                            ]),
                        Card::make()
                            ->className("kpi-card")
                            ->body([
                                Tpl::make()->tpl('💰')->className("kpi-icon"),
                                Tpl::make()->tpl('<h3>${period_label}已收利息</h3>')->className("kpi-title"),
                                Tpl::make()->tpl('${period_paid_interest}')->className("metric"),
                                Tpl::make()->tpl('本期利息收入')->className("help"),
                            ]),
                    ]),
                    ),

                // 运营总览
                Card::make()
                    ->className("section-card")
                    ->body([
                        Tpl::make()->tpl('<h3>运营总览</h3>')->className("section-title"),
                        Tpl::make()->tpl('快速洞察')->className("pill"),
                        Tpl::make()->tpl('查看核心关键指标，若需深入分析，请进入"报表中心"查看更多图表。')->className("section-desc"),
                        Flex::make()->className("toolbar")->items([
                            Button::make()
                                ->label("进入报表中心")
                                ->level("primary")
                                ->actionType("link")
                                ->link("reports"),
                            Button::make()
                                ->label("导出Excel")
                                ->actionType("ajax")
                                ->api("/dashboard_export"),
                        ]),
                        Grid::make()
                            ->className("grid-cols-2")
                            ->columns([
                                Card::make()
                                    ->body([
                                        Tpl::make()->tpl('<h3>高风险 TOP5</h3>')->className("card-title"),
                                        Tpl::make()->tpl('${risk_top}')->className("risk-top"),
                                    ])
                                    ->api("/dashboard_risk-top"),
                                Card::make()
                                    ->body([
                                        Tpl::make()->tpl('<h3>提醒渠道统计（近7日）</h3>')->className("card-title"),
                                        Tpl::make()->tpl('${channel_stats}')->className("channel-stats"),
                                    ])
                                    ->api("/dashboard_channel-stats"),
                            ]),
                    ]),
            ]);

        return $this->response()->success($dashboard);
    }

    /**
     * 获取KPI指标数据
     */
    public function metrics()
    {
        $period = request('period', 'month');
        $service = new DashboardService();
        $metrics = $service->getCoreMetrics($period);

        return $this->response()->success(array_merge($metrics, [
            'date' => date('Y-m-d H:i:s'),
            'period' => $period,
        ]));
    }

    /**
     * 获取高风险TOP5
     */
    public function riskTop()
    {
        $service = new DashboardService();
        $data = $service->getHighRiskTop5();

        $html = '<table class="simple-table">';
        foreach ($data as $item) {
            $html .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                $item['name'],
                $item['risk_level'],
                $item['loan_count'] . '笔',
                '￥' . $item['total_amount']
            );
        }
        $html .= '</table>';

        return $this->response()->success(['risk_top' => $html]);
    }

    /**
     * 获取渠道统计
     */
    public function channelStats()
    {
        $period = request('period', 'month');
        $service = new DashboardService();
        $data = $service->getChannelStats($period);

        $html = '<table class="simple-table">';
        foreach ($data as $channel => $count) {
            $html .= sprintf(
                '<tr><td>%s</td><td>%d次</td></tr>',
                $channel,
                $count
            );
        }
        $html .= '</table>';

        return $this->response()->success(['channel_stats' => $html]);
    }

    /**
     * 导出Excel
     */
    public function export()
    {
        // TODO: 实现Excel导出功能
        return $this->response()->success('导出功能开发中...');
    }
}
