<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Customer;
use App\Models\Loan;
use App\Models\RepaymentSchedule;
use Illuminate\Support\Facades\DB;

// 初始化Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== 信用分计算测试 ===\n\n";

// 获取所有客户
$customers = Customer::all();

foreach ($customers as $customer) {
    echo "客户: {$customer->name}\n";
    echo "身份证: {$customer->id_card}\n";
    
    // 计算动态信用分
    $dynamicScore = $customer->calculateCreditScore();
    $computedRisk = $customer->getComputedRiskLevel();
    
    echo "动态信用分: {$dynamicScore}\n";
    echo "计算风险等级: " . Customer::riskLevelOptions()[$computedRisk] ?? '未知' . "\n";
    
    // 获取存储的信用分
    $storedScore = $customer->credit_score;
    $storedRisk = $customer->risk_level;
    
    echo "存储信用分: {$storedScore}\n";
    echo "存储风险等级: " . Customer::riskLevelOptions()[$storedRisk] ?? '未评级' . "\n";
    
    // 获取贷款和还款信息
    $loans = $customer->loans;
    $totalLoans = $loans->count();
    $totalAmount = $loans->sum('amount');
    
    $repaymentSchedules = RepaymentSchedule::whereHas('loan', function($query) use ($customer) {
        $query->where('customer_id', $customer->id);
    })->get();
    
    $paidCount = $repaymentSchedules->where('is_paid', true)->count();
    $overdueCount = 0;
    
    foreach ($repaymentSchedules as $schedule) {
        if (!$schedule->is_paid && now()->gt($schedule->due_date)) {
            $overdueCount++;
        }
    }
    
    echo "贷款笔数: {$totalLoans}\n";
    echo "总贷款金额: {$totalAmount}\n";
    echo "已还款期数: {$paidCount}\n";
    echo "逾期期数: {$overdueCount}\n";
    echo "----------------------------------------\n";
}

echo "\n=== 批量计算测试 ===\n";
$customerIds = Customer::pluck('id')->toArray();
$batchScores = Customer::batchCalculateCreditScores($customerIds);

foreach ($batchScores as $customerId => $score) {
    $customer = Customer::find($customerId);
    echo "{$customer->name}: {$score}\n";
}
