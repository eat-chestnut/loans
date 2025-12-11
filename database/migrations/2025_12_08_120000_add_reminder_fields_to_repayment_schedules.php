<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repayment_schedules', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable()->after('paid_at')->comment('短信提醒时间');
            $table->unsignedInteger('reminder_times')->default(0)->after('reminder_sent_at')->comment('短信提醒次数');
            $table->timestamp('wecom_reminder_sent_at')->nullable()->after('reminder_times')->comment('企微提醒时间');
            $table->unsignedInteger('wecom_reminder_times')->default(0)->after('wecom_reminder_sent_at')->comment('企微提醒次数');
        });
    }

    public function down(): void
    {
        Schema::table('repayment_schedules', function (Blueprint $table) {
            $table->dropColumn(['reminder_sent_at', 'reminder_times', 'wecom_reminder_sent_at', 'wecom_reminder_times']);
        });
    }
};
