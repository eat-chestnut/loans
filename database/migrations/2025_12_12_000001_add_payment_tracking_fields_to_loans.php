<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 放款表增加已还金额、盈利金额、逾期次数字段
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('paid_amount', 15, 2)->default(0)->after('amount')->comment('已还金额');
            $table->decimal('profit_amount', 15, 2)->default(0)->after('paid_amount')->comment('盈利金额');
            $table->unsignedInteger('overdue_count')->default(0)->after('overdue_days')->comment('逾期次数');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['paid_amount', 'profit_amount', 'overdue_count']);
        });
    }
};
