<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\finance;

use app\admin\controller\BaseController;
use app\model\FinanceAccount;
use app\model\FinanceSubsidiaryLedger;
use app\model\FinanceVoucher;
use support\Request;
use support\Response;

#[\erikwang2013\apidoc\annotation\Title('明细账')]
#[\erikwang2013\apidoc\annotation\Group('财务管理')]

class SubsidiaryLedgerController extends BaseController
{
    /**
     * 明细账查询
     */
    #[\erikwang2013\apidoc\annotation\Title('明细账查询')]
    #[\erikwang2013\apidoc\annotation\Desc('按科目列出每笔凭证分录明细，支持日期范围筛选')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/finance/subsidiary-ledger')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', desc:'每页条数')]
    #[\erikwang2013\apidoc\annotation\Param(name:'account_id', type:'int', desc:'科目ID')]
    #[\erikwang2013\apidoc\annotation\Param(name:'start_date', type:'string', desc:'开始日期')]
    #[\erikwang2013\apidoc\annotation\Param(name:'end_date', type:'string', desc:'结束日期')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function index(Request $request): Response
    {
        $validator = validator($request->all(), [
            'page' => 'integer',
            'limit' => 'integer',
            'account_id' => 'string',
            'start_date' => 'string',
            'end_date' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        [$page, $limit] = $this->pageParams($request);
        $accountRaw = $request->input('account_id');
        $accountId = null;
        if ($accountRaw !== null && $accountRaw !== '') {
            // 筛选收 hashid 串或原生数字（双模）：解码失败/非正数 422（与出口 encode 对称）
            $accountId = $this->decodeFlexibleId($accountRaw);
            if ($accountId === null || $accountId < 1) {
                return $this->fail($this->trans('Invalid account_id'), 422);
            }
        }
        $startDate = $request->input('start_date', '');
        $endDate = $request->input('end_date', '');

        $query = FinanceSubsidiaryLedger::query();
        if ($accountId !== null) {
            $query->where('account_id', $accountId);
        }
        if ($startDate) {
            $query->where('entry_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('entry_date', '<=', $endDate);
        }

        $total = $query->count();
        $models = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('entry_date', 'desc')->get();
        // 行补科目名/凭证号：前端 inferColumns 用 *_name、*_code 兄弟列渲染，
        // 否则这两列显示的是编码后的 hashid
        $accountNames = FinanceAccount::query()->whereIn('id', $models->pluck('account_id')->all())
            ->pluck('name', 'id')->all();
        $voucherCodes = FinanceVoucher::query()->whereIn('id', $models->pluck('voucher_id')->all())
            ->pluck('code', 'id')->all();
        $list = $models->map(function ($item) use ($accountNames, $voucherCodes) {
            $row = $this->encodeIds($item->toArray(), ['id', 'account_id', 'voucher_id', 'voucher_item_id']);
            $row['account_name'] = $accountNames[$item->account_id] ?? '';
            $row['voucher_code'] = $voucherCodes[$item->voucher_id] ?? '';

            return $row;
        });

        return $this->successPage($list, $total, $page, $limit);
    }
}
