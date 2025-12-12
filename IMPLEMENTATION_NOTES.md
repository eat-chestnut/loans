# markAsPaid 方法调整实施说明

## 变更摘要

本次更新实现了以下功能：

1. **放款表（loans）新增字段**：
   - `paid_amount` - 已还金额
   - `profit_amount` - 盈利金额（累计已还利息）
   - `overdue_count` - 逾期次数

2. **还款明细表（repayment_schedules）新增字段**：
   - `is_overdue` - 是否逾期

3. **还款时自动更新统计**：
   - 调用 `markAsPaid` 方法时自动计算并更新放款表的已还金额和盈利金额

4. **每日任务**：
   - 新增定时任务每日凌晨 00:30 自动更新逾期状态和逾期次数

## 文件变更清单

### 1. 数据库迁移文件

#### `/database/migrations/2025_12_12_000001_add_payment_tracking_fields_to_loans.php`
```php
// 为 loans 表添加三个新字段
- paid_amount (decimal 15,2) - 已还金额
- profit_amount (decimal 15,2) - 盈利金额
- overdue_count (unsigned int) - 逾期次数
```

#### `/database/migrations/2025_12_12_000002_add_is_overdue_to_repayment_schedules.php`
```php
// 为 repayment_schedules 表添加逾期标记
- is_overdue (boolean) - 是否逾期
```

### 2. 模型文件更新

#### `/app/Models/Loan.php`
- 在 `$fillable` 数组中添加：`paid_amount`, `profit_amount`, `overdue_count`
- 在 `$casts` 数组中添加对应的类型转换

#### `/app/Models/RepaymentSchedule.php`
- 在 `$fillable` 数组中添加：`is_overdue`
- 在 `$casts` 数组中添加：`'is_overdue' => 'boolean'`

### 3. 服务层更新

#### `/app/Services/RepaymentScheduleService.php`

**新增方法**：
```php
private function updateLoanPaymentStats(int $loanId): void
```
- 功能：计算并更新放款记录的已还金额和盈利金额
- 逻辑：
  - 查询该笔贷款所有已还款的还款明细
  - 累加 `amount` 字段得到已还金额
  - 累加 `interest` 字段得到盈利金额
  - 更新到 loans 表

**修改方法**：
```php
public function markAsPaid(int $scheduleId): bool
```
- 在标记还款后调用 `updateLoanPaymentStats()` 更新统计数据

### 4. 定时任务

#### `/app/Console/Commands/UpdateOverdueStatus.php`
新增命令：`loan:update-overdue-status`

**功能**：
1. 查找所有未还款且到期日小于今天的还款明细
2. 将这些明细的 `is_overdue` 标记为 `true`
3. 统计每笔贷款的逾期次数
4. 更新 loans 表的 `overdue_count` 字段

**执行时间**：每日凌晨 00:30 自动执行

#### `/app/Console/Kernel.php`
- 注册 `UpdateOverdueStatus` 命令
- 添加定时调度：`dailyAt('00:30')`

## 运行步骤

### 1. 执行数据库迁移

```bash
php artisan migrate
```

这将创建新的字段：
- `loans.paid_amount`
- `loans.profit_amount`
- `loans.overdue_count`
- `repayment_schedules.is_overdue`

### 2. 验证定时任务注册

```bash
php artisan schedule:list
```

应该能看到：
- `loan:update-overdue-status` 每日 00:30 执行

### 3. 手动测试逾期更新命令

```bash
php artisan loan:update-overdue-status
```

输出示例：
```
开始更新逾期状态...
已更新 X 条还款明细为逾期状态
已更新 Y 个放款记录的逾期次数
逾期状态更新完成！
```

### 4. 测试还款功能

在管理后台或通过代码测试 `markAsPaid` 方法：

```php
use App\Services\RepaymentScheduleService;

$service = new RepaymentScheduleService();
$service->markAsPaid($scheduleId);

// 验证 loans 表的 paid_amount 和 profit_amount 是否正确更新
```

## 数据验证

### 验证已还金额和盈利金额

```sql
-- 查看某笔贷款的统计信息
SELECT 
    l.id,
    l.loan_number,
    l.amount AS 贷款金额,
    l.paid_amount AS 已还金额,
    l.profit_amount AS 盈利金额,
    l.overdue_count AS 逾期次数
FROM loans l
WHERE l.id = ?;

-- 验证计算是否正确
SELECT 
    loan_id,
    SUM(amount) AS 应还总额,
    SUM(CASE WHEN is_paid = 1 THEN amount ELSE 0 END) AS 已还金额,
    SUM(CASE WHEN is_paid = 1 THEN interest ELSE 0 END) AS 盈利金额,
    SUM(CASE WHEN is_overdue = 1 THEN 1 ELSE 0 END) AS 逾期次数
FROM repayment_schedules
WHERE loan_id = ?
GROUP BY loan_id;
```

### 验证逾期状态

```sql
-- 查看逾期的还款明细
SELECT 
    rs.id,
    rs.loan_id,
    rs.period AS 期数,
    rs.due_date AS 应还日期,
    rs.is_paid AS 是否已还,
    rs.is_overdue AS 是否逾期,
    DATEDIFF(CURDATE(), rs.due_date) AS 逾期天数
FROM repayment_schedules rs
WHERE rs.is_overdue = 1
ORDER BY rs.loan_id, rs.period;
```

## 业务逻辑说明

### 已还金额计算
- 每次调用 `markAsPaid` 时重新计算
- 累加所有 `is_paid = true` 的还款明细的 `amount` 字段

### 盈利金额计算
- 盈利金额 = 累计已还利息
- 累加所有 `is_paid = true` 的还款明细的 `interest` 字段

### 逾期判定
- 每日凌晨 00:30 自动检查
- 条件：`is_paid = false` AND `due_date < 今天`
- 满足条件则设置 `is_overdue = true`

### 逾期次数统计
- 统计该笔贷款下所有 `is_overdue = true` 的还款明细数量
- 更新到 loans 表的 `overdue_count` 字段

## 注意事项

1. **性能优化**：
   - 定时任务使用 `chunkById(100)` 分批处理，避免内存溢出
   - 已还金额和盈利金额的计算在还款时同步更新，避免实时查询

2. **数据一致性**：
   - 所有金额字段保留两位小数
   - 使用事务确保数据一致性（如需要可在 markAsPaid 中添加）

3. **回归测试点**：
   - 测试还款后 paid_amount 和 profit_amount 是否正确
   - 测试逾期状态更新是否准确
   - 测试逾期次数统计是否正确
   - 测试全部还清后贷款状态是否正确变更为"结清"

## 后续扩展建议

1. 可以添加逾期天数字段到 repayment_schedules 表
2. 可以添加逾期罚息计算逻辑
3. 可以添加还款历史记录表，记录每次还款的详细信息
4. 可以添加数据修复命令，用于修复历史数据的统计字段
