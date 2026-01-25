<?php

namespace App\Admin\Controllers;

use App\Enums\CollateralType;
use App\Models\Customer;
use App\Models\Loan;

/**
 * 报表中心控制器
 */
class ReportsController extends AdminController
{
    public function index()
    {
        $page = $this->basePage()->body([
            amis()->Grid()->className('mb-1')->columns([
                $this->customPieByType(),
                $this->loanPieByCustomerType(),
            ]),
            amis()->Grid()->className('mb-1')->columns([
                $this->loanAmountPieByCollateralType(),
                $this->loanCountPieByCollateralType()
            ]),
        ]);
        return $this->response()->success($page);
    }

    public function customPieByType()
    {
        $customCountByType = Customer::query()->get()->groupBy(function ($item) {
            return $item->type ?? '无';
        })->transform(function ($item) {
            return $item->count() + 0;
        })->sortDesc();
        $list = [];
        foreach ($customCountByType as $type => $count) {
            $list[] =[
                'name' => $type.': '.$count,
                'value' => $count,
            ];
        }
        return amis()->Card()->className('h-96')->body(
            amis()->Chart()->height(350)->config([
                'title' => [
                    'text' => '客户来源占比',
                    'subtext' => '总数：'.$customCountByType->sum()
                ],
                'tooltip'         => ['trigger' => 'item'],
                'legend'          => ['bottom' => 0, 'left' => 'center'],
                'series'          => [
                    [
                        'name'              => '客户来源: '.$customCountByType->sum(),
                        'type'              => 'pie',
                        'radius'            => '50%',
                        'emphasis'          => [
                            'label' => [
                                'show'       => true,
                                'fontSize'   => '40',
                                'fontWeight' => 'bold',
                            ],
                        ],
                        'data'              => $list,
                    ],
                ],
            ])
        );
    }

    public function loanPieByCustomerType()
    {
        $loanAmountByCustomerType = Loan::query()->with('customer')->get()->groupBy(function ($item) {
            return $item->customer->type ?? '无';
        })->transform(function ($item) {
            return $item->sum('amount') + 0;
        })->sortDesc();
        $list = [];
        foreach ($loanAmountByCustomerType as $type => $count) {
            $list[] =[
                'name' => $type.': '.human_amount_cn($count),
                'value' => $count,
            ];
        }
        return amis()->Card()->className('h-96')->body(
            amis()->Chart()->height(350)->config([
                'title' => [
                    'text' => '放贷来源占比',
                    'subtext' => '总数：'.human_amount_cn($loanAmountByCustomerType->sum())
                ],
                'tooltip'         => ['trigger' => 'item'],
                'legend'          => ['bottom' => 0, 'left' => 'center'],
                'series'          => [
                    [
                        'name'              => '客户来源: '.$loanAmountByCustomerType->sum(),
                        'type'              => 'pie',
                        'radius'            => '50%',
                        'emphasis'          => [
                            'label' => [
                                'show'       => true,
                                'fontSize'   => '40',
                                'fontWeight' => 'bold',
                            ],
                        ],
                        'data'              => $list,
                    ],
                ],
            ])
        );
    }

    public function loanAmountPieByCollateralType()
    {
        $loanAmountByCustomerType = Loan::query()->with('collaterals')->get()->groupBy(function ($item) {
            return in_array(CollateralType::HOUSE, $item->collaterals->pluck('type')->toArray()) ? CollateralType::HOUSE : CollateralType::GARAGE;
        })->transform(function ($item) {
            return $item->sum('amount') + 0;
        })->sortDesc();
        $list = [];
        foreach ($loanAmountByCustomerType as $type => $count) {
            $list[] =[
                'name' => CollateralType::getDescription($type).': '.human_amount_cn($count),
                'value' => $count,
            ];
        }
        return amis()->Card()->className('h-96')->body(
            amis()->Chart()->height(350)->config([
                'title' => [
                    'text' => '抵押物金额占比',
                    'subtext' => '总本金：'.human_amount_cn($loanAmountByCustomerType->sum())
                ],
                'tooltip'         => ['trigger' => 'item'],
                'legend'          => ['bottom' => 0, 'left' => 'center'],
                'series'          => [
                    [
                        'type'              => 'pie',
                        'radius'            => '50%',
                        'emphasis'          => [
                            'label' => [
                                'show'       => true,
                                'fontSize'   => '40',
                                'fontWeight' => 'bold',
                            ],
                        ],
                        'data'              => $list,
                    ],
                ],
            ])
        );
    }

    public function loanCountPieByCollateralType()
    {
        $loanAmountByCustomerType = Loan::query()->with('collaterals')->get()->groupBy(function ($item) {
            return in_array(CollateralType::HOUSE, $item->collaterals->pluck('type')->toArray()) ? CollateralType::HOUSE : CollateralType::GARAGE;
        })->transform(function ($item) {
            return $item->count() + 0;
        })->sortDesc();
        $list = [];
        foreach ($loanAmountByCustomerType as $type => $count) {
            $list[] =[
                'name' => CollateralType::getDescription($type).': '.$count,
                'value' => $count,
            ];
        }
        return amis()->Card()->className('h-96')->body(
            amis()->Chart()->height(350)->config([
                'title' => [
                    'text' => '抵押物占比',
                    'subtext' => '总放贷数：'.$loanAmountByCustomerType->sum()
                ],
                'tooltip'         => ['trigger' => 'item'],
                'legend'          => ['bottom' => 0, 'left' => 'center'],
                'series'          => [
                    [
                        'type'              => 'pie',
                        'radius'            => '50%',
                        'emphasis'          => [
                            'label' => [
                                'show'       => true,
                                'fontSize'   => '40',
                                'fontWeight' => 'bold',
                            ],
                        ],
                        'data'              => $list,
                    ],
                ],
            ])
        );
    }

}
