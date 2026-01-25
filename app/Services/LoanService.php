<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\RepaymentSchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * 放款表
 *
 * @method Loan getModel()
 * @method Loan|\Illuminate\Database\Query\Builder query()
 */
class LoanService extends AdminService
{
    protected string $modelName = Loan::class;

    protected array $pendingCollateralIds = [];
    protected array $pendingCommunications = [];

    public function addRelations($query, string $scene = 'list')
    {
        $query->with(['customer', 'collaterals', 'communications', 'repaymentSchedules']);
    }

    public function searchable($query)
    {
        parent::searchable($query);
        $params = $this->request->all();
        $query->when(isset($params['is_end']), function ($query){
            $query->whereIn('state', [Loan::STATE_CLOSED, Loan::STATE_COMPLETED]);
        });
    }

    public function formatRows(array $rows)
    {
        foreach ($rows as &$row) {
            $row = $row->toArray();
            $row['amount'] = formatNumber($row['amount']);
            $row['total_interest'] = formatNumber($row['total_interest']);
            $row['collateral_total_value'] = formatNumber($row['collateral_total_value']);
            $row['paid_amount'] = formatNumber($row['paid_amount']);
            $row['profit_amount'] = formatNumber($row['profit_amount']);
            $row['now_period'] = $row['state'] == Loan::STATE_CLOSED ? '-' : (collect($row['repayment_schedules'])->filter(function ($repaymentSchedule) {
                return filled($repaymentSchedule) && $repaymentSchedule['is_paid'] == 0;
            })->min('period') ?? '-');
            $row['expected_date'] = Carbon::parse($row['disbursed_at'])->addMonths($row['term_months'])->toDateString();
        }
        return $rows;
    }

    public function saving(&$data, $primaryKey = null)
    {
        $data['term_months'] = data_get($data, 'term_months', 0);
        if (!$primaryKey) {
            $data['loan_number'] = Str::random();
        }

        if ($data['loan_type'] == 2) {
            if (!isset($data['repayment_schedules'])) {
                admin_abort('先息后本需要填写完整的还款计划');
            }
            foreach ($data['repayment_schedules'] as $schedule) {
                if (!$schedule['due_date'] || $schedule['principal'] === null || $schedule['interest'] === null) {
                    admin_abort('先息后本需要填写完整的还款计划');
                }
            }
        }
        if (!isset($data['customer'])) {
            admin_abort('客户信息必须填写');
        }

        // 创建客户记录
        $customerService = CustomerService::make();
        if ($customer = $customerService->query()->where('id_card', $data['customer']['id_card'])->first()) {
            $data['customer_id'] = $customer->id;
        }else{
            unset($data['customer']['id']);
            $customerService->store($data['customer']);
            $data['customer_id'] = $customerService->currentModel->id;
        }

        // 创建抵押物记录
        data_set($data['collaterals'], '*.customer_id', $data['customer_id']);

        $collateralIds = [];
        foreach ($data['collaterals'] as $collateral) {

            // 创建客户记录
            $collateralService = CollateralService::make();
            if (isset($collateral['id'])) {
                $collateralModel = $collateralService->query()->where('id', $collateral['id'])->first();
            }else{
                $collateralModel = $collateralService->getModel();
            }
            $collateralModel->fill($collateral);
            $collateralModel->save();

            $collateralIds[] = $collateralModel->id;
        }

        if (!$primaryKey) {
            // 保存抵押物ID数组用于后续关联
            $this->pendingCollateralIds = $collateralIds;
        }

        // 保存沟通记录数据
        if (isset($data['communications'])) {
            $this->pendingCommunications = $data['communications'];
        }

        $data['admin_user_id'] = $this->adminUser->id;

        // 清理不需要保存的字段
        unset($data['customer'], $data['collaterals'], $data['communications']);
    }

    public function saved($model, $isEdit = false)
    {
        // 在保存后建立贷款和抵押物的关联关系
        if (!empty($this->pendingCollateralIds)) {
            $model->collaterals()->attach($this->pendingCollateralIds);
            $this->pendingCollateralIds = []; // 清空数组
        }

        // 处理沟通记录
        if (!empty($this->pendingCommunications)) {
            // 删除旧的沟通记录（编辑模式）
            if ($isEdit) {
                \App\Models\Communication::where('loan_id', $model->id)->delete();
            }

            // 创建新的沟通记录
            foreach ($this->pendingCommunications as $comm) {
                if (!empty($comm['content'])) {
                    \App\Models\Communication::create([
                        'customer_id' => $model->customer_id,
                        'loan_id' => $model->id,
                        'admin_user_id' => admin_user()?->id ?? 1, // CLI环境使用默认值1
                        'channel' => $comm['channel'] ?? \App\Models\Communication::OTHER,
                        'content' => $comm['content'],
                        'happened_at' => $comm['happened_at'] ?? now(),
                    ]);
                }
            }

            $this->pendingCommunications = []; // 清空数组
        }

        $schedules = [];
        if ($model->loan_type == 2) {
            $remainingPrincipal = $model->amount;
            // 批量保存
            foreach (request()->get('repayment_schedules') as $index => $scheduleData) {
                $remainingPrincipal -= $scheduleData['principal'];
                $schedules[] = [
                    'loan_id' => $model->id,
                    'period' => $index + 1,
                    'due_date' => $scheduleData['due_date'],
                    'principal' => round($scheduleData['principal'], 2),
                    'interest' => round($scheduleData['interest'], 2),
                    'amount' => round($scheduleData['principal'] + $scheduleData['interest'], 2),
                    'remaining_principal' => round(max(0, $remainingPrincipal), 2),
                    'is_paid' => 0,
                    'paid_at' => null,
                    'state' => '待还款',
                    'remark' => null,
                ];
            }
        }
        $repaymentService = new \App\Services\RepaymentScheduleService();
        $repaymentService->saveSchedule($model, collect($schedules));

        $model->paid_amount = RepaymentSchedule::query()->where('loan_id', $model->id)->where('is_paid', 1)->sum('principal');
        $model->profit_amount = RepaymentSchedule::query()->where('loan_id', $model->id)->where('is_paid', 1)->sum('interest');
        if (RepaymentSchedule::query()->where('loan_id', $model->id)->where('is_paid', 0)->count() == 0) {
            $model->state = RepaymentSchedule::query()->where('loan_id', $model->id)->where('is_paid', 1) < $model->term_months ? Loan::STATE_COMPLETED :  Loan::STATE_CLOSED;
            $model->closed_at = RepaymentSchedule::query()->where('loan_id', $model->id)->where('is_paid', 1)->max('paid_at');
        }
        $model->save();
    }
}
