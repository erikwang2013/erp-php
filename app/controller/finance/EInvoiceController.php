<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\controller\finance;

use app\admin\controller\BaseController;
use app\model\FinanceInvoice;
use app\service\tax\EInvoiceService;
use support\Container;
use support\Request;
use support\Response;

/**
 * 数电票开票/红冲出口 — P2-2 F5
 * 幂等与状态机（none → issued → voided）在 EInvoiceService 行锁内判定，
 * 本控制器只做参数搬运与统一响应；业务错误 422、发票不存在 404。
 * 平台为适配器注入（默认 mock），切换真实开票通道不涉及本控制器。
 */
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]

class EInvoiceController extends BaseController
{
    /**
     * 开具数电票（幂等：已开具重复调用直接返回既有数电票号码，绝不重复开票）
     */
#[\erikwang2013\apidoc\annotation\Title("开具数电票")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", required:true, desc:"发票ID(hashid，须应收且已审核)")]

    public function issue(Request $request, string $id): Response
    {
        $adminId = $request->adminId ?? 0;
        $invoiceId = $this->decodeId($id);
        if (!FinanceInvoice::find($invoiceId)) {
            return $this->fail('发票不存在', 404);
        }
        $result = $this->service()->issueInvoice($invoiceId, (int) $adminId);
        if (!$result['success']) {
            return $this->fail($result['error'], 422);
        }
        $data = [
            'id' => $this->encodeId($invoiceId),
            'bill_no' => $result['bill_no'],
            'issue_status' => $result['issue_status'],
            'idempotent' => !empty($result['idempotent']),
        ];
        $message = !empty($result['idempotent']) ? '发票已开具，返回既有数电票号码' : '开票成功';

        return $this->success($data, $message);
    }

    /**
     * 数电票红冲（仅已开具可冲；冲后不可再开/再冲，electronic_no 保留供对账）
     */
#[\erikwang2013\apidoc\annotation\Title("数电票红冲")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", required:true, desc:"发票ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"reason", type:"string", required:true, desc:"红冲原因")]

    public function void(Request $request, string $id): Response
    {
        $adminId = $request->adminId ?? 0;
        $invoiceId = $this->decodeId($id);
        if (!FinanceInvoice::find($invoiceId)) {
            return $this->fail('发票不存在', 404);
        }
        $reason = (string) $request->input('reason', '');
        $result = $this->service()->voidInvoice($invoiceId, $reason, (int) $adminId);
        if (!$result['success']) {
            return $this->fail($result['error'], 422);
        }
        $data = [
            'id' => $this->encodeId($invoiceId),
            'bill_no' => $result['bill_no'],
            'issue_status' => $result['issue_status'],
        ];

        return $this->success($data, '红冲成功');
    }

    /**
     * 开票/红冲日志（平台调用轨迹，新→旧）
     */
#[\erikwang2013\apidoc\annotation\Title("数电票操作日志")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", required:true, desc:"发票ID(hashid)")]

    public function logs(Request $request, string $id): Response
    {
        $invoiceId = $this->decodeId($id);
        if (!FinanceInvoice::find($invoiceId)) {
            return $this->fail('发票不存在', 404);
        }

        return $this->success(['items' => $this->service()->issueLogs($invoiceId)]);
    }

    private function service(): EInvoiceService
    {
        return Container::get(EInvoiceService::class);
    }
}
