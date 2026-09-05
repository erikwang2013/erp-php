<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\manufacturing;

use app\admin\controller\BaseController;
use app\service\manufacturing\MfgCapacityService;
use InvalidArgumentException;
use support\Container;
use support\Request;
use support\Response;

/**
 * 产能负荷（P1-M3）：工作站日历例外 + 粗能力负荷报表
  * @Apidoc\Tag("生产制造")
 */#[\erikwang2013\apidoc\annotation\Tag("生产制造")]

class CapacityController extends BaseController
{
    /**
     * 工作站日历（默认周一~五 8 小时 + 例外覆盖，逐日材料化）
     * @Apidoc\Title("工作站日历")
     * @Apidoc\Desc("查询工作站逐日可用工时；无例外记录按默认规则(周一~五8小时)返回")
     * @Apidoc\Url("/admin/v1/mfg/capacity/calendar")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="workstation_id", type="string", desc="工作站ID，必填")
     * @Apidoc\Param(name="from", type="string", desc="开始日期 YYYY-MM-DD，默认今天")
     * @Apidoc\Param(name="to", type="string", desc="结束日期 YYYY-MM-DD，默认+30天")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[\erikwang2013\apidoc\annotation\Title("工作站日历")]
#[\erikwang2013\apidoc\annotation\Desc("查询工作站逐日可用工时；无例外记录按默认规则(周一~五8小时)返回")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/mfg/capacity/calendar")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Param(name:"workstation_id", type:"string", desc:"工作站ID，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"from", type:"string", desc:"开始日期 YYYY-MM-DD，默认今天")]
#[\erikwang2013\apidoc\annotation\Param(name:"to", type:"string", desc:"结束日期 YYYY-MM-DD，默认+30天")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function calendar(Request $request): Response
    {
        $wsId = $this->decodeWsId($request->input('workstation_id'));
        if ($wsId === null) {
            return $this->fail('工作站ID不能为空', 422);
        }
        [$from, $to] = $this->rangeFromRequest($request);
        try {
            $rows = $this->cap()->calendar($wsId, $from, $to);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success(['list' => $rows]);
    }

    /**
     * 设置日历例外日（工作站+日期 唯一键 upsert；hours=0 表示闭厂）
     * @Apidoc\Title("设置日历例外")
     * @Apidoc\Desc("覆盖默认日历规则；同一工作站同一天重复设置即更新")
     * @Apidoc\Url("/admin/v1/mfg/capacity/calendar")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="workstation_id", type="string", desc="工作站ID，必填")
     * @Apidoc\Param(name="date", type="string", desc="日期 YYYY-MM-DD，必填")
     * @Apidoc\Param(name="hours", type="string", desc="可用工时 0~24，必填")
     * @Apidoc\Param(name="remark", type="string", desc="备注(停机/检修等)，最长200字")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[\erikwang2013\apidoc\annotation\Title("设置日历例外")]
#[\erikwang2013\apidoc\annotation\Desc("覆盖默认日历规则；同一工作站同一天重复设置即更新")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/mfg/capacity/calendar")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Param(name:"workstation_id", type:"string", desc:"工作站ID，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"date", type:"string", desc:"日期 YYYY-MM-DD，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"hours", type:"string", desc:"可用工时 0~24，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"remark", type:"string", desc:"备注(停机/检修等)，最长200字")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function setException(Request $request): Response
    {
        $wsId = $this->decodeWsId($request->input('workstation_id'));
        if ($wsId === null) {
            return $this->fail('工作站ID不能为空', 422);
        }
        $validator = validator($request->all(), [
            'date' => 'required|date_format:Y-m-d',
            'hours' => 'required',
            'remark' => 'nullable|string|max:200',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        try {
            $this->cap()->setException($wsId, (string) $request->input('date'), (string) $request->input('hours'), (string) $request->input('remark', ''));
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success([], '设置成功');
    }

    /**
     * 删除日历例外日（恢复默认规则）
     * @Apidoc\Title("删除日历例外")
     * @Apidoc\Desc("删除指定工作站指定日期的例外记录，需密码确认；无记录时幂等成功")
     * @Apidoc\Url("/admin/v1/mfg/capacity/calendar")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="workstation_id", type="string", desc="工作站ID，必填")
     * @Apidoc\Param(name="date", type="string", desc="日期 YYYY-MM-DD，必填")
     * @Apidoc\Param(name="password", type="string", desc="管理员密码")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[\erikwang2013\apidoc\annotation\Title("删除日历例外")]
#[\erikwang2013\apidoc\annotation\Desc("删除指定工作站指定日期的例外记录，需密码确认；无记录时幂等成功")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/mfg/capacity/calendar")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Param(name:"workstation_id", type:"string", desc:"工作站ID，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"date", type:"string", desc:"日期 YYYY-MM-DD，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", desc:"管理员密码")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function removeException(Request $request): Response
    {
        $wsId = $this->decodeWsId($request->input('workstation_id'));
        if ($wsId === null) {
            return $this->fail('工作站ID不能为空', 422);
        }
        $validator = validator($request->all(), ['date' => 'required|date_format:Y-m-d']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }
        try {
            $this->cap()->removeException($wsId, (string) $request->input('date'));
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success([], '删除成功');
    }

    /**
     * 粗能力负荷报表（工作站×日期 负荷/产能/负荷率）
     * @Apidoc\Title("产能负荷报表")
     * @Apidoc\Desc("未结工单剩余数量×工艺标准工时折算需求，按计划窗口产能日均摊；缺省统计全部启用工作站")
     * @Apidoc\Url("/admin/v1/mfg/capacity/report")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="workstation_id", type="string", desc="工作站ID，可选；缺省=全部启用工作站")
     * @Apidoc\Param(name="from", type="string", desc="开始日期 YYYY-MM-DD，默认今天")
     * @Apidoc\Param(name="to", type="string", desc="结束日期 YYYY-MM-DD，默认+30天")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[\erikwang2013\apidoc\annotation\Title("产能负荷报表")]
#[\erikwang2013\apidoc\annotation\Desc("未结工单剩余数量×工艺标准工时折算需求，按计划窗口产能日均摊；缺省统计全部启用工作站")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/mfg/capacity/report")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Param(name:"workstation_id", type:"string", desc:"工作站ID，可选；缺省=全部启用工作站")]
#[\erikwang2013\apidoc\annotation\Param(name:"from", type:"string", desc:"开始日期 YYYY-MM-DD，默认今天")]
#[\erikwang2013\apidoc\annotation\Param(name:"to", type:"string", desc:"结束日期 YYYY-MM-DD，默认+30天")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function report(Request $request): Response
    {
        $wsId = $this->decodeWsId($request->input('workstation_id'));   // 缺省 null=全部启用站
        [$from, $to] = $this->rangeFromRequest($request);
        try {
            $rows = $this->cap()->report($wsId, $from, $to);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }
        $rows = array_map(fn ($row) => $this->encodeIds($row, ['workstation_id']), $rows);

        return $this->success(['list' => $rows]);
    }

    // ---------- 私有辅助 ----------

    /** hashid 解码（含空值/非法值归一），供必填判空与可选缺省共用 */
    private function decodeWsId(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        return $this->decodeIdSafe((string) $raw);
    }

    /** 区间参数：缺省=今天 ~ +30 天（服务层兜底 366 天上限） */
    private function rangeFromRequest(Request $request): array
    {
        $today = date('Y-m-d');

        return [
            (string) $request->input('from', $today),
            (string) $request->input('to', date('Y-m-d', strtotime($today . ' +30 day'))),
        ];
    }

    /**
     * 产能负荷薄服务层实例（Container::get 走 class_exists 回退，见 config/dependence.php 注释）
     */
    private function cap(): MfgCapacityService
    {
        return Container::get(MfgCapacityService::class);
    }
}
