<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * @Apidoc\Tag("库存管理")
 */

declare(strict_types=1);

namespace app\controller\inventory;

use app\admin\controller\BaseController;
use app\service\inventory\TraceService;
use support\Container;
use support\Request;
use support\Response;

/**
 * 追溯链报表（P1-M6）：批次正反向追溯 / 序列号链 / 近效期预警
 *
 * 路由（由 lead 注册，本控制器不自行注册）：
 *   GET /trace/forward/{batchCode}
 *   GET /trace/backward/{batchCode}
 *   GET /trace/serial/{serialCode}
 *   GET /trace/expiry?days=N
 *
 * 注意：追溯接口返回原始 snowflake id 与 source_id（不 hashid 编码），
 * 便于前端直接以 source_id 定位上游/下游业务单据。
 */
class TraceController extends BaseController
{
    /**
     * 正向追溯：该批次全部流水按方向分组，出库侧展开下游去向
     * @Apidoc\Title("批次正向追溯")
     * @Apidoc\Desc("按批次号查询全部出入库流水，出库侧展开下游去向（单据类型/source_id）")
     * @Apidoc\Url("/trace/forward/{batchCode}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("库存管理")
     * @Apidoc\Param(name="batchCode", type="string", require=true, desc="批次号")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="批次追溯结果")
     */
    public function forward(Request $request, string $batchCode): Response
    {
        return $this->run(fn (): array => $this->trace()->forward(trim((string) $batchCode)));
    }

    /**
     * 反向追溯：该批次入库流水的来源 → 上游单据
     * @Apidoc\Title("批次反向追溯")
     * @Apidoc\Desc("按批次号查询入库来源链（来源单据类型/source_id）")
     * @Apidoc\Url("/trace/backward/{batchCode}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("库存管理")
     * @Apidoc\Param(name="batchCode", type="string", require=true, desc="批次号")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="批次来源链")
     */
    public function backward(Request $request, string $batchCode): Response
    {
        return $this->run(fn (): array => $this->trace()->backward(trim((string) $batchCode)));
    }

    /**
     * 序列号链：入库/出库两端流水明细
     * @Apidoc\Title("序列号追溯")
     * @Apidoc\Desc("按序列号查询入出库两端流水明细")
     * @Apidoc\Url("/trace/serial/{serialCode}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("库存管理")
     * @Apidoc\Param(name="serialCode", type="string", require=true, desc="序列号")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="序列号追溯结果")
     */
    public function serial(Request $request, string $serialCode): Response
    {
        return $this->run(fn (): array => $this->trace()->serial(trim((string) $serialCode)));
    }

    /**
     * 近效期预警：expiry_date 非空且 <= 今天+days，且批次仍有在库
     * @Apidoc\Title("近效期预警")
     * @Apidoc\Desc("查询未来 N 天内到期且有在库的批次")
     * @Apidoc\Url("/trace/expiry")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("库存管理")
     * @Apidoc\Param(name="days", type="int", default=90, desc="预警窗口天数")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="array", desc="近效期批次列表")
     */
    public function expiry(Request $request): Response
    {
        $days = (int) $request->input('days', 90);

        return $this->run(fn (): array => $this->trace()->expiryAlert($days));
    }

    // ---------- 私有辅助 ----------

    /** 追溯服务实例（Container::get 走 class_exists 回退，同既有控制器约定） */
    private function trace(): TraceService
    {
        return Container::get(TraceService::class);
    }

    /** 统一执行 + 参数校验错误转 422 */
    private function run(callable $fn): Response
    {
        try {
            return $this->success($fn());
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }
}
