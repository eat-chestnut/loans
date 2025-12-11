<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->json('customer_snapshot')->nullable()->after('customer_id')->comment('客户信息快照');
            $table->json('co_borrower_snapshot')->nullable()->after('customer_snapshot')->comment('共同借款人');
            $table->json('collateral_items')->nullable()->after('collateral_info')->comment('抵押物列表');
            $table->decimal('collateral_total_value', 15, 2)->nullable()->after('collateral_items')->comment('抵押物总价值');
            $table->decimal('total_interest_amount', 15, 2)->nullable()->after('collateral_total_value')->comment('总利息');
            $table->decimal('discount_ratio', 5, 2)->nullable()->after('total_interest_amount')->comment('折当率');
            $table->decimal('month_profit_ratio', 5, 2)->nullable()->after('discount_ratio')->comment('月综合利润');
            $table->string('city')->nullable()->after('month_profit_ratio')->comment('归属地');
            $table->string('ticket_no')->nullable()->after('loan_number')->comment('票号');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn([
                'ticket_no',
                'customer_snapshot',
                'co_borrower_snapshot',
                'collateral_items',
                'collateral_total_value',
                'total_interest_amount',
                'discount_ratio',
                'month_profit_ratio',
                'city',
            ]);
        });
    }
};
