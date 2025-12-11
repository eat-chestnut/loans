<?php

namespace App\Services;

use App\Models\Loan;
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
        $query->with(['customer', 'collaterals', 'communications']);
    }

    public function saving(&$data, $primaryKey = null)
    {
        if (!$primaryKey) {
            $data['loan_number'] = Str::random();

            // 创建客户记录
            $customerService = CustomerService::make();
            $customerService->store($data['customer']);
            $data['customer_id'] = $customerService->currentModel->id;
            
            // 创建抵押物记录
            data_set($data['collaterals'], '*.customer_id', $data['customer_id']);
            
            $collateralIds = [];
            foreach ($data['collaterals'] as $collateral) {
                $collateralService = CollateralService::make();
                $collateralService->store($collateral);
                $collateralIds[] = $collateralService->currentModel->id;
            }
            
            // 计算抵押物总价值
            $totalCollateralValue = array_sum(array_column($data['collaterals'], 'value'));
            $data['collateral_total_value'] = $totalCollateralValue;
            
            // 保存抵押物ID数组用于后续关联
            $this->pendingCollateralIds = $collateralIds;
            
            // 保存沟通记录数据
            if (isset($data['communications'])) {
                $this->pendingCommunications = $data['communications'];
            }
            
            // 清理不需要保存的字段
            unset($data['customer'], $data['collaterals'], $data['communications']);
        } else {
            // 编辑模式：处理沟通记录更新
            if (isset($data['communications'])) {
                $this->pendingCommunications = $data['communications'];
                unset($data['communications']);
            }
        }
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
        
        // 新增贷款时自动生成还款计划
        if (!$isEdit) {
            $repaymentService = new \App\Services\RepaymentScheduleService();
            $repaymentService->saveSchedule($model);
        }
    }
}
