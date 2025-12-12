<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 还款明细表增加是否逾期字段
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repayment_schedules', function (Blueprint $table) {
            $table->boolean('is_overdue')->default(false)->after('is_paid')->comment('是否逾期');
        });
    }

    public function down(): void
    {
        Schema::table('repayment_schedules', function (Blueprint $table) {
            $table->dropColumn('is_overdue');
        });
    }
};
