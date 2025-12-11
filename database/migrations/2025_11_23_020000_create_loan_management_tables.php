<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('客户姓名');
            $table->string('id_card', 32)->nullable()->unique()->comment('身份证号');
            $table->string('phone', 32)->nullable()->index()->comment('联系电话');
            $table->string('address')->nullable()->comment('联系地址');
            $table->integer('risk_level')->default(0)->comment('风险等级');
            $table->unsignedSmallInteger('credit_score')->default(0)->comment('信用分');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('collaterals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name')->comment('抵押物名称');
            $table->tinyInteger('type')->default(0)->comment('抵押物类型');
            $table->string('city')->nullable()->comment('所属地');
            $table->decimal('discount_rate', 5, 2)->nullable()->comment('折扣率(%)');
            $table->decimal('pledge_value', 15, 2)->nullable()->comment('评估价值');
            $table->string('certificate_no')->nullable()->comment('权证编号');
            $table->decimal('area', 10, 2)->nullable()->comment('面积/规格');
            $table->text('note')->nullable()->comment('备注说明');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('wecom_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name')->comment('企微联系人姓名');
            $table->string('wechat_id')->unique()->comment('企微账号ID');
            $table->string('mobile', 32)->nullable()->comment('绑定手机');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_number')->unique()->comment('业务贷款编号');
            $table->foreignId('collateral_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_user_id')->nullable();
            $table->decimal('amount', 15, 2)->comment('放款金额');
            $table->unsignedInteger('term_months')->comment('贷款期数(月)');
            $table->decimal('rate_month', 5, 2)->comment('月利率');
            $table->date('start_date')->nullable()->comment('起始日期');
            $table->date('disbursed_at')->nullable()->comment('放款日期');
            $table->date('closed_at')->nullable()->comment('结清日期');
            $table->integer('state')->default(0)->index()->comment('流程状态');
            $table->unsignedInteger('overdue_days')->default(0)->comment('逾期天数');
            $table->text('note')->nullable()->comment('备注说明');
            $table->json('collateral_info')->nullable()->comment('抵押物信息');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained('loans')->cascadeOnDelete();
            $table->foreignId('admin_user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->tinyInteger('channel')->default(0)->comment('沟通渠道');
            $table->text('content')->nullable()->comment('沟通内容');
            $table->timestamp('happened_at')->nullable()->index()->comment('沟通时间');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('repayment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->unsignedInteger('period')->comment('期次');
            $table->date('due_date')->comment('应还日期');
            $table->decimal('amount', 15, 2)->comment('应还金额');
            $table->decimal('interest', 15, 2)->default(0)->comment('利息金额');
            $table->decimal('principal', 15, 2)->default(0)->comment('本金金额');
            $table->decimal('remaining_principal', 15, 2)->default(0)->comment('本期后剩余本金');
            $table->boolean('is_paid')->default(false)->comment('是否已还款');
            $table->timestamp('paid_at')->nullable()->comment('实还时间');
            $table->string('state', 20)->nullable()->comment('状态标记');
            $table->text('remark')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['loan_id', 'period']);
        });

        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained('loans')->nullOnDelete();
            $table->timestamp('sent_at')->nullable()->index()->comment('发送时间');
            $table->string('phone', 32)->nullable()->comment('发送手机号');
            $table->string('template_key')->nullable()->comment('模板标识');
            $table->tinyInteger('state')->default(0)->comment('发送状态');
            $table->text('content')->comment('短信内容');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('wecom_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained('loans')->nullOnDelete();
            $table->timestamp('sent_at')->nullable()->index()->comment('发送时间');
            $table->string('contact_name')->nullable()->comment('联系人名称');
            $table->string('wechat_id')->nullable()->comment('企微账号');
            $table->text('content')->comment('消息内容');
            $table->timestamps();
            $table->softDeletes();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wecom_logs');
        Schema::dropIfExists('sms_logs');
        Schema::dropIfExists('repayment_schedules');
        Schema::dropIfExists('communications');
        Schema::dropIfExists('loans');
        Schema::dropIfExists('wecom_contacts');
        Schema::dropIfExists('collaterals');
        Schema::dropIfExists('customers');
    }
};
