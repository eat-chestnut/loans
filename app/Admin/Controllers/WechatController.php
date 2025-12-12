<?php

namespace App\Admin\Controllers;

use App\Services\Wecom\WecomService;
use Illuminate\Support\Facades\Log;

/**
 * 客户表
 *
 * @property WecomService $service
 */
class WechatController extends AdminController
{
    protected string $serviceName = WecomService::class;

    public function list()
    {
        $crud = $this->baseCRUD()
            ->headerToolbar([
                ...$this->baseHeaderToolBar(),
                amis()->AjaxAction()
                    ->api('get:/wecom/sync-customers')
                    ->label('同步客户')
                    ->icon('fa fa-refresh')
                    ->level('success')
                    ->confirmText('确定要从企业微信同步客户信息吗？')
            ])
            ->filterTogglable()
            ->columns([
                amis()->TableColumn('id', 'ID')->sortable(),
                amis()->TableColumn('name', '企微名称'),
                amis()->TableColumn('wechat_id', '企微ID')->copyable(),
                amis()->TableColumn('mobile', '手机号码'),
                amis()->TableColumn('customer.name', '绑定客户'),
                amis()->TableColumn('created_at', '创建时间')->sortable(),
            ])
            ->filter(
                amis()->Form()->wrapWithPanel(false)->body([
                    amis()->TextControl('name', '企微名称')->clearable(),
                    amis()->TextControl('mobile', '手机号')->clearable(),
                    amis()->SelectControl('has_customer', '绑定状态')
                        ->clearable()
                        ->options([
                            ['label' => '已绑定', 'value' => 1],
                            ['label' => '未绑定', 'value' => 0],
                        ]),
                ])
            );

        return $this->baseList($crud);
    }

    /**
     * 同步企微客户信息
     */
    public function syncWecomCustomers()
    {
        try {
            if (!$this->service->enabled()) {
                return $this->response()->fail('企业微信未启用，请先在系统设置中配置');
            }

            $result = $this->service->syncCustomers();

            return $this->response()->successMessage(
                "同步完成！新增 {$result['created']} 个客户，更新 {$result['updated']} 个客户"
            );
        } catch (\Exception $e) {
            Log::error('同步企微客户失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->response()->fail('同步失败：' . $e->getMessage());
        }
    }

    /**
     * 绑定系统客户
     */
    public function bindCustomer($id)
    {
        $contact = \App\Models\WecomContact::findOrFail($id);
        $customerId = request('customer_id');

        if (!$customerId) {
            return $this->response()->fail('请选择要绑定的客户');
        }

        $customer = \App\Models\Customer::findOrFail($customerId);

        // 检查该企微客户是否已绑定其他客户
        if ($contact->customer_id && $contact->customer_id != $customerId) {
            return $this->response()->fail('该企微客户已绑定其他系统客户');
        }

        // 绑定关系
        $contact->customer_id = $customerId;
        $contact->save();

        Log::info('企微客户绑定成功', [
            'wecom_contact_id' => $contact->id,
            'wecom_name' => $contact->name,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
        ]);

        return $this->response()->successMessage(
            "已将企微客户 \"{$contact->name}\" 绑定到系统客户 \"{$customer->name}\""
        );
    }

    /**
     * 解绑系统客户
     */
    public function unbindCustomer($id)
    {
        $contact = \App\Models\WecomContact::findOrFail($id);

        if (!$contact->customer_id) {
            return $this->response()->fail('该企微客户未绑定任何系统客户');
        }

        $customerName = $contact->customer->name ?? '';

        Log::info('企微客户解绑', [
            'wecom_contact_id' => $contact->id,
            'wecom_name' => $contact->name,
            'customer_id' => $contact->customer_id,
            'customer_name' => $customerName,
        ]);

        // 解除绑定
        $contact->customer_id = null;
        $contact->save();

        return $this->response()->successMessage(
            "已解绑企微客户 \"{$contact->name}\" 与系统客户 \"{$customerName}\" 的绑定关系"
        );
    }
}
