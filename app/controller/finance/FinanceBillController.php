<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\controller\finance;

use app\admin\controller\BaseController;
use app\model\FinanceBill;
use app\service\finance\FinanceBillService;
use support\Container;
use support\Request;
use support\Response;

/**
 * 承兑汇票票据台账(应收/应付) — P2 F6
 * 状态机全量转换校验在 FinanceBillService：0在库 1已背书 2已贴现 3托收中 4已到期兑付 5已退票。
 * 边界：票据为资产追踪单据，不新增 ARAP 分录、不联动收付款/核销/结算。
 */
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Title("票据台账")]
#[\erikwang2013\apidoc\annotation\Group("财务管理")]

class FinanceBillController extends BaseController
{
    /** 响应中需要 hashid 化的字段 */
    private const ID_FIELDS = ['id', 'bank_account_id', 'source_id'];

    /**
     * 票据台账列表（分页，方向/类型/状态/关键词筛选）
     */
#[\erikwang2013\apidoc\annotation\Title("票据台账列表")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/finance/bill")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", default:1, desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", default:15, desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"direction", type:"int", default:0, desc:"方向(0全部 1收票 2开票)")]
#[\erikwang2013\apidoc\annotation\Param(name:"type", type:"int", default:0, desc:"类型(0全部 1银行承兑 2商业承兑)")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", default:-1, desc:"状态(-1全部 0在库 1已背书 2已贴现 3托收中 4已到期兑付 5已退票)")]
#[\erikwang2013\apidoc\annotation\Param(name:"keyword", type:"string", default:"", desc:"关键词(票号)")]

    public function index(Request $request): Response
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = (int) $request->input('limit', 15);
        $query = FinanceBill::query();
        $direction = (int) $request->input('direction', 0);
        if (in_array($direction, [1, 2], true)) {
            $query->where('direction', $direction);
        }
        $type = (int) $request->input('type', 0);
        if (in_array($type, [1, 2], true)) {
            $query->where('type', $type);
        }
        $status = (int) $request->input('status', -1);
        if ($status >= 0) {
            $query->where('status', $status);
        }
        $keyword = $request->input('keyword', '');
        if ($keyword !== '') {
            $query->where('bill_no', 'like', "%{$keyword}%");
        }
        $total = $query->count();
        $list = array_map(
            fn (array $item) => $this->encodeIds($item, self::ID_FIELDS),
            $query->offset(($page - 1) * $limit)->limit($limit)
                ->orderBy('id', 'desc')->get()->toArray()
        );

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 到期预警清单（未兑付且到期日 ≤ 今天+days，上限 200）
     */
#[\erikwang2013\apidoc\annotation\Title("到期预警清单")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/finance/bill/due-warnings")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Param(name:"days", type:"int", default:7, desc:"预警天数")]
#[\erikwang2013\apidoc\annotation\Param(name:"direction", type:"int", default:0, desc:"方向(0全部 1收票 2开票)")]

    public function dueWarnings(Request $request): Response
    {
        $days = (int) $request->input('days', 7);
        $direction = (int) $request->input('direction', 0);
        $list = array_map(
            fn (array $row) => $this->encodeIds($row, self::ID_FIELDS),
            $this->service()->dueWarnings($days, $direction)
        );

        return $this->success(['list' => $list]);
    }

    /**
     * 票据登记
     */
#[\erikwang2013\apidoc\annotation\Title("票据登记")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/finance/bill")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Param(name:"bill_no", type:"string", required:true, desc:"票号(唯一)")]
#[\erikwang2013\apidoc\annotation\Param(name:"type", type:"int", required:true, desc:"类型(1银行承兑 2商业承兑)")]
#[\erikwang2013\apidoc\annotation\Param(name:"direction", type:"int", required:true, desc:"方向(1收票 2开票)")]
#[\erikwang2013\apidoc\annotation\Param(name:"amount", type:"string", required:true, desc:"票面金额")]
#[\erikwang2013\apidoc\annotation\Param(name:"due_date", type:"string", required:true, desc:"到期日 Y-m-d")]
#[\erikwang2013\apidoc\annotation\Param(name:"issue_date", type:"string", default:"", desc:"出票日期 Y-m-d")]
#[\erikwang2013\apidoc\annotation\Param(name:"drawer", type:"string", default:"", desc:"出票人")]
#[\erikwang2013\apidoc\annotation\Param(name:"payee", type:"string", default:"", desc:"收款人")]
#[\erikwang2013\apidoc\annotation\Param(name:"acceptor", type:"string", default:"", desc:"承兑人")]
#[\erikwang2013\apidoc\annotation\Param(name:"bank_account_id", type:"string", default:0, desc:"托收账户(hashid，开票票勿传)")]
#[\erikwang2013\apidoc\annotation\Param(name:"source_type", type:"string", default:"manual", desc:"来源(manual/receipt)")]
#[\erikwang2013\apidoc\annotation\Param(name:"source_id", type:"string", default:0, desc:"来源单(hashid，关联收款单)")]
#[\erikwang2013\apidoc\annotation\Param(name:"remark", type:"string", default:"", desc:"备注")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'bill_no' => 'required|string|max:50',
            'type' => 'required|integer',
            'direction' => 'required|integer',
            'amount' => 'required',
            'due_date' => 'required|date',
            'issue_date' => 'nullable|date',
            'source_type' => 'nullable|string|max:30',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $data = $this->collectPayload($request);
        [$bill, $error] = $this->service()->store($data);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        return $this->success($this->present($bill), '登记成功');
    }

    /**
     * 票据详情
     */
#[\erikwang2013\apidoc\annotation\Title("票据详情")]
#[\erikwang2013\apidoc\annotation\Method("GET")]

    public function show(Request $request, string $id): Response
    {
        $bill = FinanceBill::find($this->decodeId($id));
        if (!$bill) {
            return $this->fail('票据不存在', 404);
        }

        return $this->success($this->present($bill));
    }

    /**
     * 更新票据（仅 在库 可改；票号/方向/来源不可改）
     */
#[\erikwang2013\apidoc\annotation\Title("更新票据")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        if (!FinanceBill::find($id)) {
            return $this->fail('票据不存在', 404);
        }
        $data = $this->collectPayload($request);
        unset($data['bill_no'], $data['type'], $data['direction'], $data['source_type'], $data['source_id']);
        if (($error = $this->service()->update($id, $data)) !== null) {
            return $this->fail($error, 422);
        }

        return $this->success($this->present(FinanceBill::find($id)), '更新成功');
    }

    /**
     * 删除票据（仅 在库，需管理员密码；软删）
     */
#[\erikwang2013\apidoc\annotation\Title("删除票据")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", required:true, desc:"管理员密码")]

    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $bill = FinanceBill::find($id);
        if (!$bill) {
            return $this->fail('票据不存在', 404);
        }
        if ((int) $bill->status !== 0) {
            return $this->fail('仅 在库 票据可删除', 422);
        }
        $adminId = $request->adminId ?? 0;
        if (($error = $this->confirmPassword($adminId, $request->input('password', ''), $request)) !== null) {
            return $this->fail($error, 422);
        }
        $bill->delete();

        return $this->success(null, '删除成功');
    }

    /**
     * 背书转让（在库→已背书，被背书人必填；应收票、未到期）
     */
#[\erikwang2013\apidoc\annotation\Title("背书转让")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Param(name:"endorsee", type:"string", required:true, desc:"被背书人")]

    public function endorse(Request $request, string $id): Response
    {
        if (($error = $this->service()->endorse($this->decodeId($id), (string) $request->input('endorsee', ''))) !== null) {
            return $this->fail($error, 422);
        }

        return $this->success(null, '背书成功');
    }

    /**
     * 贴现（在库→已贴现；记录贴现息）
     */
#[\erikwang2013\apidoc\annotation\Title("票据贴现")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Param(name:"fee", type:"string", required:true, desc:"贴现息(0~票面金额)")]

    public function discount(Request $request, string $id): Response
    {
        if (($error = $this->service()->discount($this->decodeId($id), (string) $request->input('fee', ''))) !== null) {
            return $this->fail($error, 422);
        }

        return $this->success(null, '贴现成功');
    }

    /**
     * 托收（在库→托收中；指定托收账户）
     */
#[\erikwang2013\apidoc\annotation\Title("票据托收")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Param(name:"bank_account_id", type:"string", default:"", desc:"托收账户(hashid，留空用登记账户)")]

    public function collect(Request $request, string $id): Response
    {
        $accountId = $request->input('bank_account_id', '') !== ''
            ? $this->decodeMaybe((string) $request->input('bank_account_id')) : 0;
        if (($error = $this->service()->collect($this->decodeId($id), $accountId)) !== null) {
            return $this->fail($error, 422);
        }

        return $this->success(null, '托收成功');
    }

    /**
     * 到期确认兑付/解付（收票：托收中→已到期兑付；开票：在库→已到期兑付）
     */
#[\erikwang2013\apidoc\annotation\Title("确认到期兑付")]
#[\erikwang2013\apidoc\annotation\Method("POST")]

    public function cash(Request $request, string $id): Response
    {
        if (($error = $this->service()->cash($this->decodeId($id))) !== null) {
            return $this->fail($error, 422);
        }

        return $this->success(null, '确认兑付成功');
    }

    /**
     * 退票（在库/托收中→已退票；托收被拒付退回）
     */
#[\erikwang2013\apidoc\annotation\Title("退票")]
#[\erikwang2013\apidoc\annotation\Method("POST")]

    public function reject(Request $request, string $id): Response
    {
        if (($error = $this->service()->reject($this->decodeId($id))) !== null) {
            return $this->fail($error, 422);
        }

        return $this->success(null, '退票成功');
    }

    /** 组装服务入参（source 相关 id 均走 hashid→int） */
    private function collectPayload(Request $request): array
    {
        return [
            'bill_no' => (string) $request->input('bill_no', ''),
            'type' => (int) $request->input('type', 0),
            'direction' => (int) $request->input('direction', 0),
            'amount' => (string) $request->input('amount', '0'),
            'due_date' => (string) $request->input('due_date', ''),
            'issue_date' => (string) $request->input('issue_date', ''),
            'drawer' => (string) $request->input('drawer', ''),
            'payee' => (string) $request->input('payee', ''),
            'acceptor' => (string) $request->input('acceptor', ''),
            'bank_account_id' => $request->input('bank_account_id', '') !== ''
                ? $this->decodeMaybe((string) $request->input('bank_account_id')) : 0,
            'source_type' => (string) $request->input('source_type', 'manual'),
            'source_id' => $request->input('source_id', '') !== ''
                ? $this->decodeMaybe((string) $request->input('source_id')) : 0,
            'remark' => (string) $request->input('remark', ''),
        ];
    }

    /** 票据头响应（金额字符串直出，ID hashid 化） */
    private function present(FinanceBill $bill): array
    {
        return $this->encodeIds($bill->toArray(), self::ID_FIELDS);
    }

    /** 解码可选 hashid 参数（空串/无效 → 0，与 collectPayload 的 0 哨兵一致） */
    private function decodeMaybe(string $hashid): int
    {
        return $this->decodeIdSafe($hashid) ?? 0;
    }

    /**
     * 票据服务实例
     */
    private function service(): FinanceBillService
    {
        return Container::get(FinanceBillService::class);
    }
}
