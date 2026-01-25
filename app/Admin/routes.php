<?php

use App\Admin\Controllers\CollateralController;
use App\Admin\Controllers\CustomerController;
use App\Admin\Controllers\EndLoanController;
use App\Admin\Controllers\LoanController;
use App\Admin\Controllers\RepaymentScheduleController;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

Route::get('/admin', fn() => \Slowlyo\OwlAdmin\Admin::view());

Route::group([
    'domain'     => config('admin.route.domain'),
    'prefix'     => config('admin.route.prefix'),
    'middleware' => config('admin.route.middleware'),
], function (Router $router) {
    // Dashboard API路由
    $router->resource('dashboard', \App\Admin\Controllers\DashboardController::class, ['only' => ['index']]);
    $router->get('dashboard_metrics', [\App\Admin\Controllers\DashboardController::class, 'metrics']);
    $router->get('dashboard_risk-top', [\App\Admin\Controllers\DashboardController::class, 'riskTop']);
    $router->get('dashboard_channel-stats', [\App\Admin\Controllers\DashboardController::class, 'channelStats']);
    $router->get('dashboard_export', [\App\Admin\Controllers\DashboardController::class, 'export']);
    $router->resource('reports', \App\Admin\Controllers\ReportsController::class);
    $router->resource('sms_logs', \App\Admin\Controllers\SmsLogController::class);
    $router->resource('wecom_logs', \App\Admin\Controllers\WecomLogController::class);
    $router->any('system/settings/reminder', [\App\Admin\Controllers\SettingController::class, 'reminder']);
    $router->any('system/settings/wecom', [\App\Admin\Controllers\SettingController::class, 'wecom']);
    $router->any('system/settings/baidu-call', [\App\Admin\Controllers\SettingController::class, 'baiduCall']);
    $router->any('system/settings/sms', [\App\Admin\Controllers\SettingController::class, 'sms']);
    $router->get('wecom/sync-customers', [\App\Admin\Controllers\WechatController::class, 'syncWecomCustomers']);
    $router->post('wecom/{id}/bind-customer', [\App\Admin\Controllers\WechatController::class, 'bindCustomer']);
    $router->post('wecom/{id}/unbind-customer', [\App\Admin\Controllers\WechatController::class, 'unbindCustomer']);
    $router->resource('system/settings', \App\Admin\Controllers\SettingController::class);
    $router->resource('customers', CustomerController::class);
    $router->resource('collaterals', CollateralController::class);
    $router->resource('loans', LoanController::class);
    $router->resource('end_loans', EndLoanController::class);
    $router->put('loans/{id}/end', [LoanController::class, 'end']);
    $router->resource('repayment-schedules', RepaymentScheduleController::class);
    $router->resource('overdue', \App\Admin\Controllers\OverdueController::class);
    $router->resource('wechat', \App\Admin\Controllers\WechatController::class);
    $router->get('calendars', [\App\Admin\Controllers\CalendarController::class, 'index']);

    $router->get('collaterals_options/{collateral_id}', [CollateralController::class, 'options']);
    $router->get('customer_options', [CustomerController::class, 'options']);
    $router->post('repayment-schedules/{id}/mark-paid', [RepaymentScheduleController::class, 'markPaid']);
    $router->get('repayment-schedules/stats/{loanId}', [RepaymentScheduleController::class, 'repaymentStats']);
    $router->post('repayment-schedules/regenerate/{loanId}', [RepaymentScheduleController::class, 'regenerate']);

    // 客户企微绑定路由
    $router->post('customers/{id}/bind-wecom', [CustomerController::class, 'bindWecom']);
    $router->post('customers/{id}/unbind-wecom', [CustomerController::class, 'unbindWecom']);
    $router->get('customers/wecom-options', [CustomerController::class, 'wecomOptions']);

    $router->post('customers/{id}/notice/{type}', [CustomerController::class, 'notice']);


    // 逾期管理API路由
    $router->post('overdue/{id}/mark-paid', [\App\Admin\Controllers\OverdueController::class, 'markAsPaid']);

    // 报表中心API路由
    $router->get('reports_cashflow', [\App\Admin\Controllers\ReportsController::class, 'cashflow']);
    $router->get('reports_risk', [\App\Admin\Controllers\ReportsController::class, 'risk']);
    $router->get('reports_net-change', [\App\Admin\Controllers\ReportsController::class, 'netChange']);
    $router->get('reports_channel', [\App\Admin\Controllers\ReportsController::class, 'channel']);
    $router->get('reports_asset-quality', [\App\Admin\Controllers\ReportsController::class, 'assetQuality']);
    $router->get('reports_cohort', [\App\Admin\Controllers\ReportsController::class, 'cohort']);
    $router->get('reports_funnel', [\App\Admin\Controllers\ReportsController::class, 'funnel']);
    $router->get('reports_vintage', [\App\Admin\Controllers\ReportsController::class, 'vintage']);
    $router->get('reports_export_excel', [\App\Admin\Controllers\ReportsController::class, 'exportExcel']);
    $router->get('reports_export_pdf', [\App\Admin\Controllers\ReportsController::class, 'exportPDF']);
});
