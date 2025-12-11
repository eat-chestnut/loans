<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Collateral;
use App\Models\Loan;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Carbon\Carbon;

class GenerateLoanData extends Command
{
    protected $signature = 'loans:generate {count=50 : Number of loan records to generate}';
    protected $description = 'Generate sample loan records with customers and collaterals';

    public function handle()
    {
        $count = (int)$this->argument('count');
        
        $this->info("Generating {$count} loan records...");
        
        // Sample data
        $surnames = ['张', '李', '王', '刘', '陈', '杨', '赵', '黄', '周', '吴'];
        $names = ['伟', '芳', '娜', '敏', '静', '丽', '强', '磊', '洋', '勇'];
        $cities = ['福州市台江区', '福州市鼓楼区', '福州市仓山区', '福州市晋安区', '福州市马尾区'];
        
        for ($i = 0; $i < $count; $i++) {
            // Generate customer
            $customerData = $this->generateCustomerData($surnames, $names);
            $customer = Customer::create($customerData);
            
            // Generate 1-3 collaterals for each customer
            $collateralCount = rand(1, 3);
            $collaterals = [];
            $collateralIds = [];
            
            for ($j = 0; $j < $collateralCount; $j++) {
                $collateralData = $this->generateCollateralData($customer->id, $cities);
                $collateral = Collateral::create($collateralData);
                $collaterals[] = $collateralData;
                $collateralIds[] = $collateral->id;
            }
            
            // Generate loan
            $loanData = $this->generateLoanData($customer->id, $collateralIds, $collaterals);
            $loan = Loan::create($loanData);
            
            // Create junction table entries
            $loan->collaterals()->attach($collateralIds);
            
            $this->line("Generated loan #{$loan->id} for customer {$customer->name}");
        }
        
        $this->info("Successfully generated {$count} loan records!");
        return 0;
    }
    
    private function generateCustomerData($surnames, $names)
    {
        return [
            'name' => $surnames[array_rand($surnames)] . $names[array_rand($names)],
            'id_card' => $this->generateIdCard(),
            'phone' => '1' . rand(3, 9) . str_pad(rand(0, 999999999), 9, '0', STR_PAD_LEFT),
            'address' => $this->generateAddress(),
            'risk_level' => rand(1, 3),
            'credit_score' => rand(60, 100),
            'co_borrower' => rand(0, 1) ? [
                'name' => $surnames[array_rand($surnames)] . $names[array_rand($names)],
                'id_card' => $this->generateIdCard(),
                'phone' => '1' . rand(3, 9) . str_pad(rand(0, 999999999), 9, '0', STR_PAD_LEFT),
            ] : null,
        ];
    }
    
    private function generateCollateralData($customerId, $cities)
    {
        $types = [Collateral::TYPE_PROPERTY, Collateral::TYPE_VEHICLE, Collateral::TYPE_EQUITY];
        $type = $types[array_rand($types)];
        
        $data = [
            'customer_id' => $customerId,
            'name' => $this->generateCollateralName($type),
            'type' => $type,
            'valuation' => rand(500000, 5000000),
            'value' => rand(400000, 4500000),
            'certificate_no' => $this->generateCertificateNo(),
            'area' => rand(50, 200) + (rand(0, 99) / 100),
            'note' => rand(0, 1) ? '优质抵押物，手续齐全' : null,
        ];
        
        return $data;
    }
    
    private function generateLoanData($customerId, $collateralIds, $collaterals)
    {
        $totalCollateralValue = array_sum(array_column($collaterals, 'value'));
        $maxLoanAmount = $totalCollateralValue * 0.7; // 70% LTV
        $loanAmount = rand(100000, (int)$maxLoanAmount);
        
        return [
            'loan_number' => 'LP' . date('Ymd') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT),
            'ticket_no' => 'TK' . date('Ymd') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT),
            'customer_id' => $customerId,
            'collateral_total_value' => $totalCollateralValue,
            'amount' => $loanAmount,
            'total_interest_amount' => $loanAmount * 0.12 * rand(6, 24) / 12, // 12% annual rate
            'term_months' => rand(6, 24),
            'rate_month' => 1.0 + rand(0, 50) / 100, // 1% - 1.5% monthly
            'discount_ratio' => rand(50, 80) + (rand(0, 99) / 100),
            'month_profit_ratio' => 2.0 + rand(0, 100) / 100, // 2% - 3%
            'city' => '福州市台江区', // Default city
            'disbursed_at' => Carbon::now()->subDays(rand(0, 365))->format('Y-m-d'),
            'state' => Loan::STATE_NEW,
            'note' => rand(0, 1) ? '正常还款中' : null,
            'admin_user_id' => 1,
            '_collateral_ids' => $collateralIds, // For junction table
        ];
    }
    
    private function generateIdCard()
    {
        // Generate fake Chinese ID card number
        $areaCode = '350102'; // Fuzhou area code
        $birthDate = date('Ymd', strtotime('-' . rand(18, 65) . ' years'));
        $sequence = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        $checkDigit = rand(0, 9);
        
        return $areaCode . $birthDate . $sequence . $checkDigit;
    }
    
    private function generateAddress()
    {
        $streets = ['解放路', '中山路', '五四路', '华林路', '湖东路', '江滨路'];
        $numbers = [rand(1, 999), rand(1, 999), rand(1, 999)];
        
        return '福建省福州市' . $streets[array_rand($streets)] . $numbers[0] . '号' . $numbers[1] . '室';
    }
    
    private function generateCollateralName($type)
    {
        $properties = ['阳光花园', '滨江小区', '金山大厦', '五四新村', '湖前公寓'];
        $vehicles = ['奔驰E300', '宝马5系', '奥迪A6', '特斯拉Model 3', '凯迪拉克CT6'];
        $equities = ['阿里巴巴股份', '腾讯控股', '美团股票', '京东股权', '字节跳动股份'];
        
        switch ($type) {
            case Collateral::TYPE_PROPERTY:
                return $properties[array_rand($properties)] . rand(1, 20) . '栋' . rand(1, 30) . '单元';
            case Collateral::TYPE_VEHICLE:
                return $vehicles[array_rand($vehicles)];
            case Collateral::TYPE_EQUITY:
                return $equities[array_rand($equities)];
            default:
                return '其他资产';
        }
    }
    
    private function generateCertificateNo()
    {
        return 'FZ' . date('Y') . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
