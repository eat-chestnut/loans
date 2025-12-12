<?php

namespace App\Admin\Controllers;

use App\Services\ReportService;
use Slowlyo\OwlAdmin\Renderers\Page;
use Slowlyo\OwlAdmin\Renderers\Card;
use Slowlyo\OwlAdmin\Renderers\Chart;
use Slowlyo\OwlAdmin\Renderers\Grid;
use Slowlyo\OwlAdmin\Renderers\Table;
use Slowlyo\OwlAdmin\Renderers\Button;
use Slowlyo\OwlAdmin\Renderers\Tpl;

/**
 * 报表中心控制器
 */
class ReportsController extends AdminController
{
    public function index()
    {
        $page = Page::make()
            ->className("p-3")
            ->title("报表中心")
            ->body([
                // 工具栏
                Card::make()
                    ->className("mb-3")
                    ->body([
                        Tpl::make()->tpl('<h3>指标报表</h3>'),
                        Tpl::make()->tpl('图表与分析')->className("pill"),
                        Tpl::make()->tpl('查看现金流、风险、提醒渠道等多维图表，可导出汇总结果用于对外汇报。')->className("text-muted"),
                        Grid::make()->className("toolbar")->columns([
                            Button::make()
                                ->label("导出Excel")
                                ->actionType("ajax")
                                ->api("/reports_export_excel"),
                            Button::make()
                                ->label("导出PDF")
                                ->actionType("ajax")
                                ->api("/reports_export_pdf"),
                        ]),
                    ]),

                // 第一行图表
                Grid::make()
                    ->className("mb-3")
                    ->columns([
                        // 现金流图表
                        Card::make()
                            ->body([
                                Tpl::make()->tpl('现金流（按月 应收 vs 实收）'),
                                amis()->Service()
                                    ->api("/reports_cashflow")
                                    ->body(
                                        Chart::make()
                                            ->config([
                                                'type' => 'line',
                                                'data' => [
                                                    'labels' => '${cashflow.labels}',
                                                    'datasets' => '${cashflow.datasets}',
                                                ],
                                            ])
                                    ),
                            ]),

                        // 风险等级分布
                        Card::make()
                            ->body([
                                Tpl::make()->tpl('风险等级分布'),
                                amis()->Service()
                                    ->api("/reports_risk")
                                    ->body(
                                        Chart::make()
                                            ->config([
                                                'type' => 'bar',
                                                'data' => [
                                                    'labels' => '${risk.labels}',
                                                    'datasets' => [[
                                                        'data' => '${risk.data}',
                                                        'backgroundColor' => '${risk.backgroundColor}',
                                                    ]],
                                                ],
                                            ])
                                    ),
                            ]),

                        // 净借还变化
                        Card::make()
                            ->body([
                                Tpl::make()->tpl('净借还变化（按月）'),
                                amis()->Service()
                                    ->api("/reports_net-change")
                                    ->body(
                                        Chart::make()
                                            ->config([
                                                'type' => 'line',
                                                'data' => [
                                                    'labels' => '${netChange.labels}',
                                                    'datasets' => [[
                                                        'label' => '净借还金额',
                                                        'data' => '${netChange.data}',
                                                        'borderColor' => '${netChange.borderColor}',
                                                        'backgroundColor' => '${netChange.backgroundColor}',
                                                        'fill' => true,
                                                    ]],
                                                ],
                                            ])
                                    ),
                            ]),
                    ]),

                // 第二行图表
                Grid::make()
                    ->className("mb-3")
                    ->columns([
                        // 提醒渠道占比
                        Card::make()
                            ->body([
                                Tpl::make()->tpl('提醒渠道占比（近7日）'),
                                amis()->Service()
                                    ->api("/reports_channel")
                                    ->body(
                                        Chart::make()
                                            ->config([
                                                'type' => 'pie',
                                                'data' => [
                                                    'labels' => '${channel.labels}',
                                                    'datasets' => [[
                                                        'data' => '${channel.data}',
                                                        'backgroundColor' => '${channel.backgroundColor}',
                                                    ]],
                                                ],
                                            ])
                                    ),
                            ]),

                        // 提醒活跃热力图（占位）
                        Card::make()
                            ->body([
                                Tpl::make()->tpl('提醒活跃热力图'),
                                Tpl::make()->tpl('加载中...')->className("text-center p-4"),
                            ]),

                        // 运营能力雷达图（占位）
                        Card::make()
                            ->body([
                                Tpl::make()->tpl('运营能力雷达图'),
                                Tpl::make()->tpl('加载中...')->className("text-center p-4"),
                            ]),
                    ]),

                // 资产质量表格
                Card::make()
                    ->className("mb-3")
                    ->body([
                        Tpl::make()->tpl('资产质量（PAR / NPL / Roll）'),
                        Tpl::make()->tpl('${assetQuality.total_outstanding}')->className("mb-2"),
                        Table::make()
                            ->className("table-striped")
                            ->columns([
                                ['name' => '指标', 'label' => '指标'],
                                ['name' => 'amount', 'label' => '金额'],
                                ['name' => 'rate', 'label' => '占比'],
                            ])
                            ->api("/reports_asset-quality"),
                    ]),

                // 队列分析和逾期漏斗
                Grid::make()
                    ->className("mb-3")
                    ->columns([
                        // 队列分析
                        Card::make()
                            ->body([
                                Tpl::make()->tpl('队列分析（Cohort Retention）'),
                                Table::make()
                                    ->className("table-striped table-sm")
                                    ->columns([
                                        ['name' => 'cohort', 'label' => '批次'],
                                        ['name' => 'count', 'label' => '笔数'],
                                        ['name' => 'retention_30', 'label' => '30天'],
                                        ['name' => 'retention_60', 'label' => '60天'],
                                        ['name' => 'retention_90', 'label' => '90天'],
                                    ])
                                    ->api("/reports_cohort"),
                            ]),

                        // 逾期漏斗
                        Card::make()
                            ->body([
                                Tpl::make()->tpl('逾期漏斗'),
                                amis()->Service()
                                    ->api("/reports_funnel")
                                    ->body(
                                        Chart::make()
                                            ->config([
                                                'type' => 'bar',
                                                'data' => [
                                                    'labels' => '${funnel.labels}',
                                                    'datasets' => [[
                                                        'data' => '${funnel.data}',
                                                        'backgroundColor' => '${funnel.backgroundColor}',
                                                    ]],
                                                ],
                                            ])
                                    ),
                            ]),
                    ]),

                // Vintage分析和迁移矩阵
                Grid::make()
                    ->className("mb-3")
                    ->columns([
                        // Vintage分析
                        Card::make()
                            ->body([
                                Tpl::make()->tpl('Vintage 分析'),
                                Table::make()
                                    ->className("table-striped table-sm")
                                    ->columns([
                                        ['name' => 'vintage', 'label' => '批次'],
                                        ['name' => 'loan_count', 'label' => '笔数'],
                                        ['name' => 'loan_amount', 'label' => '放款额'],
                                        ['name' => 'outstanding', 'label' => '在贷余额'],
                                        ['name' => 'overdue_rate', 'label' => '逾期率'],
                                        ['name' => 'npl', 'label' => 'NPL'],
                                    ])
                                    ->api("/reports_vintage"),
                            ]),

                        // 迁移矩阵（占位）
                        Card::make()
                            ->body([
                                Tpl::make()->tpl('迁移矩阵（近30日）'),
                                Tpl::make()->tpl('加载中...')->className("text-center p-4"),
                            ]),
                    ]),
            ]);

        return $this->response()->success($page);
    }

    /**
     * 获取现金流数据
     */
    public function cashflow()
    {
        $service = new ReportService();
        $data = $service->getCashFlowData();

        return $this->response()->success(['cashflow' => $data]);
    }

    /**
     * 获取风险分布数据
     */
    public function risk()
    {
        $service = new ReportService();
        $data = $service->getRiskDistribution();

        return $this->response()->success(['risk' => $data]);
    }

    /**
     * 获取净借还变化数据
     */
    public function netChange()
    {
        $service = new ReportService();
        $data = $service->getNetLoanChange();

        return $this->response()->success(['netChange' => $data]);
    }

    /**
     * 获取渠道分布数据
     */
    public function channel()
    {
        $service = new ReportService();
        $data = $service->getChannelDistribution();

        return $this->response()->success(['channel' => $data]);
    }

    /**
     * 获取资产质量数据
     */
    public function assetQuality()
    {
        $service = new ReportService();
        $data = $service->getAssetQuality();

        return $this->response()->success([
            'items' => $data['par'],
            'assetQuality' => [
                'total_outstanding' => '在贷余额：￥' . $data['total_outstanding'],
            ]
        ]);
    }

    /**
     * 获取队列分析数据
     */
    public function cohort()
    {
        $service = new ReportService();
        $data = $service->getCohortAnalysis();

        return $this->response()->success(['items' => $data]);
    }

    /**
     * 获取逾期漏斗数据
     */
    public function funnel()
    {
        $service = new ReportService();
        $data = $service->getOverdueFunnel();

        return $this->response()->success(['funnel' => $data]);
    }

    /**
     * 获取Vintage分析数据
     */
    public function vintage()
    {
        $service = new ReportService();
        $data = $service->getVintageAnalysis();

        return $this->response()->success(['items' => $data]);
    }

    /**
     * 导出Excel
     */
    public function exportExcel()
    {
        // TODO: 实现Excel导出功能
        return $this->response()->success('导出功能开发中...');
    }

    /**
     * 导出PDF
     */
    public function exportPDF()
    {
        // TODO: 实现PDF导出功能
        return $this->response()->success('导出功能开发中...');
    }
}
