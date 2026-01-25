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
        $loanCloseCount = LoanService::make()->query()->whereIn('state', [Loan::STATE_CLOSED, Loan::STATE_COMPLETED])->count();
        $loanCloseAmount = LoanService::make()->query()->whereIn('state', [Loan::STATE_CLOSED, Loan::STATE_COMPLETED])->sum('amount');
        $loanCloseProfitAmount = LoanService::make()->query()->whereIn('state', [Loan::STATE_CLOSED, Loan::STATE_COMPLETED])->sum('profit_amount');
        $loanActivityCount = LoanService::make()->query()->where('state', Loan::STATE_NEW)->count();
        $customerActivityCount = LoanService::make()->query()->where('state', Loan::STATE_NEW)->pluck('customer_id')->unique()->count();
        $loanActivityAmount = LoanService::make()->query()->where('state', Loan::STATE_NEW)->sum('amount');
        $loanCount = LoanService::make()->query()->count();

        $loanPaidAmount = LoanService::make()->query()->sum('paid_amount');
        $loanProfitAmount = LoanService::make()->query()->sum('profit_amount');

        $dashboard = $this->basePage()
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
                        'value' => human_amount_cn($loanAmount)
                    ],
                    [
                        'title' => '在贷金额',
                        'value' => human_amount_cn($loanActivityAmount)
                    ],
                    [
                        'title' => '完贷金额',
                        'value' => human_amount_cn($loanCloseAmount)
                    ],
                    [
                        'title' => '累计回收本金',
                        'value' => human_amount_cn($loanPaidAmount)
                    ],
                    [
                        'title' => '累计收息',
                        'value' => human_amount_cn($loanProfitAmount)
                    ],
                    [
                        'title' => '实际收支',
                        'value' => human_amount_cn($loanPaidAmount+$loanProfitAmount-$loanAmount)
                    ],
                    [
                        'title' => '完贷收息',
                        'value' => human_amount_cn($loanCloseProfitAmount)
                    ]
                ]
            ])
            ->body([
                amis()->Cards()->source('${items}')->card(
                    amis()->Card()->header([
                        'title' => '${title}',
                    ])->body('${value}')
                ),
            ]);

        return $this->response()->success($dashboard);
    }

}
