<?php

namespace App\Admin\Controllers;

use App\Models\Loan;
use App\Services\CustomerService;
use App\Services\LoanService;
use Slowlyo\OwlAdmin\Renderers\Page;

/**
 * 仪表盘控制器
 */
class DashboardController extends AdminController
{
    public function index()
    {
        $customCount = CustomerService::make()->query()->count();
        $loanAmount = LoanService::make()->query()->sum('amount');
        $loanCloseCount = LoanService::make()->query()->where('state', Loan::STATE_CLOSED)->count();
        $loanCloseAmount = LoanService::make()->query()->where('state', Loan::STATE_CLOSED)->sum('amount');
        $loanActivityCount = LoanService::make()->query()->where('state', 1)->count();
        $customerActivityCount = LoanService::make()->query()->where('state', 1)->pluck('customer_id')->unique()->count();
        $loanActivityAmount = LoanService::make()->query()->where('state', 1)->sum('amount');
        $loanCount = LoanService::make()->query()->count();

        $loanPaidAmount = LoanService::make()->query()->sum('paid_amount');
        $loanProfitAmount = LoanService::make()->query()->sum('profit_amount');

        $dashboard = Page::make()
            ->data([
                'items' => [
                    [
                        'title' => '累计客户',
                        'value' => $customCount
                    ],
                    [
                        'title' => '放款总数',
                        'value' => $loanCount
                    ],
                    [
                        'title' => '在贷数量',
                        'value' => $loanActivityCount
                    ],
                    [
                        'title' => '完贷数量',
                        'value' => $loanCloseCount
                    ],
                    [
                        'title' => '在贷客户数量',
                        'value' => $customerActivityCount
                    ],
                    [
                        'title' => '放款总额',
                        'value' => number_format($loanAmount, 2,)
                    ],
                    [
                        'title' => '在贷金额',
                        'value' => number_format($loanActivityAmount, 2)
                    ],
                    [
                        'title' => '完贷金额',
                        'value' => number_format($loanCloseAmount, 2)
                    ],
                    [
                        'title' => '累计回收本金',
                        'value' => number_format($loanPaidAmount, 2)
                    ],
                    [
                        'title' => '累计盈利',
                        'value' => number_format($loanProfitAmount, 2)
                    ]
                ]
            ])
            ->body([
                amis()->Wrapper()->body([
                    amis()->Cards()->source('${items}')->card(
                        amis()->Card()->header([
                            'title' => '${title}',
                        ])->body('${value}')
                    ),
                ])
            ]);

        return $this->response()->success($dashboard);
    }

}
