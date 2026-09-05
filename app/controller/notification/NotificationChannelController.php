<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\controller\notification;

use app\admin\controller\BaseController;
use app\service\notification\ChannelService;
use support\Request;
use support\Response;

/**
 * 通知渠道外发（B4, P2-5）

 * 【状态】本批次仅交付控制器 + Apidoc，不注册路由：启用入口
 * （/admin/platform/notification-channel/*）由平台批次统一挂载并接入权限
 * （AdminAuth 之后）。站内通知（inapp）走既有 NotificationController。

 * 发送日志 id 为 snowflake 数值主键（本控制器无按 id 查询动作，故不作
 * hashid 编解码）。业务规则与错误消息契约见 ChannelService（消息文本为
 * 稳定契约，勿在此层改写）。
 */
class NotificationChannelController extends BaseController
{
    private ChannelService $service;

    public function __construct()
    {
        $this->service = new ChannelService();
    }

    /**
     * 发送渠道通知
     */
#[\erikwang2013\apidoc\annotation\Title("发送渠道通知")]
#[\erikwang2013\apidoc\annotation\Desc("同步外发 sms/mail（Mock 网关），成功/失败均落 erp_notification_channel_log；同 (channel,to,内容) 5 分钟成功窗口内幂等去重，dedup:true 返回既有记录")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/platform/notification-channel/send")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("平台管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"channel", type:"string", desc:"渠道: sms:短信 mail:邮件，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"to", type:"string", desc:"接收方(手机号/邮箱)，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"subject", type:"string", desc:"主题/标题，可空")]
#[\erikwang2013\apidoc\annotation\Param(name:"content", type:"string", desc:"内容，必填，超500字符驱动判失败")]
#[\erikwang2013\apidoc\annotation\Param(name:"operator_id", type:"int", desc:"操作人ID，缺省0=系统")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"data.log_id:发送日志ID; data.message_id:渠道消息ID(失败为空); data.dedup:是否幂等命中既有成功记录")]

    public function send(Request $request): Response
    {
        $result = $this->service->send(
            (string) $request->input('channel', ''),
            (string) $request->input('to', ''),
            (string) $request->input('subject', ''),
            (string) $request->input('content', ''),
            (int) $request->input('operator_id', 0)
        );
        if (!$result['success']) {
            $data = $result['log_id'] !== null ? ['log_id' => $result['log_id']] : [];

            return $this->fail($result['error'], 422, $data);
        }

        return $this->success([
            'log_id' => $result['log_id'],
            'message_id' => $result['message_id'],
            'dedup' => $result['dedup'],
        ], $result['dedup'] ? '命中幂等记录，未重复发送' : '发送成功');
    }

    /**
     * 发送日志列表
     */
#[\erikwang2013\apidoc\annotation\Title("发送日志列表")]
#[\erikwang2013\apidoc\annotation\Desc("渠道通知发送日志分页查询，倒序")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/platform/notification-channel/logs")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("平台管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"channel", type:"string", desc:"渠道过滤: sms/mail，缺省全部")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", desc:"状态过滤: 0=发送中 1=成功 2=失败，缺省全部")]
#[\erikwang2013\apidoc\annotation\Param(name:"to", type:"string", desc:"接收方模糊过滤")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", desc:"页码，缺省1")]
#[\erikwang2013\apidoc\annotation\Param(name:"page_size", type:"int", desc:"每页条数(≤100)，缺省20")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"data.list:日志行数组；data.total:总行数")]

    public function logs(Request $request): Response
    {
        $data = $this->service->sendLogs(
            [
                'channel' => (string) $request->input('channel', ''),
                'status' => $request->input('status', ''),
                'to' => (string) $request->input('to', ''),
            ],
            (int) $request->input('page', 1),
            (int) $request->input('page_size', 20)
        );

        return $this->success($data);
    }

    /**
     * 重试失败记录
     */
#[\erikwang2013\apidoc\annotation\Title("重试失败记录")]
#[\erikwang2013\apidoc\annotation\Desc("重试 status:2 且冷却(上次尝试≥60秒前)的失败记录，id 升序取前 limit 条")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/platform/notification-channel/retry")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("平台管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"channel", type:"string", desc:"渠道过滤: sms/mail，缺省全部")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", desc:"最多重试条数(≥1)，缺省50")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"data.attempted:尝试数; data.succeeded:成功数; data.failed:仍失败数")]

    public function retry(Request $request): Response
    {
        $result = $this->service->retryFailures(
            (string) $request->input('channel', ''),
            (int) $request->input('limit', 50)
        );
        if ($result['error'] !== '') {
            return $this->fail($result['error'], 422);
        }

        return $this->success([
            'attempted' => $result['attempted'],
            'succeeded' => $result['succeeded'],
            'failed' => $result['failed'],
        ], '重试完成');
    }
}
