<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\service\notification;

class TemplateRenderer
{
    /**
     * Render a template with variable substitution.
     * Variables in templates use {var_name} syntax.
     */
    public function render(string $template, array $variables): string
    {
        $result = $template;
        foreach ($variables as $key => $value) {
            $result = str_replace('{' . $key . '}', (string)$value, $result);
        }

        return $result;
    }

    public function renderNotification(string $templateCode, array $variables): array
    {
        $templates = [
            'approval_pending' => ['title' => '{applicant}的审批待处理', 'content' => '您有一个来自{applicant}的审批待处理: {title}'],
            'approval_approved' => ['title' => '审批已通过', 'content' => '您的申请「{title}」已通过审批'],
            'approval_rejected' => ['title' => '审批已驳回', 'content' => '您的申请「{title}」已被驳回，原因: {reason}'],
            'inventory_alert' => ['title' => '库存预警', 'content' => '商品「{product}」库存{level}，当前库存: {qty}'],
            'task_assigned' => ['title' => '任务分配', 'content' => '您有一个新任务「{title}」来自 {assigner}'],
            'order_shipped' => ['title' => '订单已发货', 'content' => '订单「{order}」已通过{carrier}发货，运单号: {tracking}'],
        ];

        $tpl = $templates[$templateCode] ?? ['title' => '通知', 'content' => '{message}'];

        return [
            'title' => $this->render($tpl['title'], $variables),
            'content' => $this->render($tpl['content'], $variables),
        ];
    }
}
