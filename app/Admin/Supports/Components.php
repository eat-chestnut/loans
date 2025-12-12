<?php

namespace App\Admin\Supports;

class Components
{
    public static function make()
    {
        return new static;
    }

    public function number($key, $label='', $prefix = '￥')
    {
        return amis()->NumberControl($key, $label)->kilobitSeparator(true)->precision(2)->prefix($prefix)->static();
    }

    public function tableNumberColumn($key, $label, $prefix = '￥')
    {
        return amis()->TableColumn($key, $label)->type('number')->kilobitSeparator(true)->prefix($prefix);
    }

    public function sendSmsButton($key)
    {
        return amis()->AjaxAction()
            ->label('短信提醒')
            ->api('post:/customers/${'.$key.'}/notice/send_sms')
            ->confirmText('确定发送短信提醒？')
            ->level('link');
    }


    public function sendWechatButton($key)
    {
        return amis()->AjaxAction()
            ->label('企微提醒')
            ->api('post:/customers/${'.$key.'}/notice/send_wechat')
            ->confirmText('确定发送企微提醒？')
            ->level('link');
    }


    public function callPhoneButton($key)
    {
        return amis()->AjaxAction()
            ->label('电话提醒')
            ->api('post:/customers/${'.$key.'}/notice/call_phone')
            ->confirmText('确定电话提醒？')
            ->level('link');
    }


    public function paidRepaymentButton($key)
    {
        return amis()->AjaxAction()
            ->level('link')
            ->className('text-success')
            ->label('标记还款')
            ->api('post:/repayment-schedules/${'.$key.'}/mark-paid')
            ->confirmText('确认标记该期已还款？');
    }
}
