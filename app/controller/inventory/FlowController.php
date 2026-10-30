<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\inventory;

use app\admin\controller\BaseController;
use app\model\InventoryFlow;
use support\Request;
use support\Response;

#[\erikwang2013\apidoc\annotation\Title('库存流水')]
#[\erikwang2013\apidoc\annotation\Group('库存管理')]

class FlowController extends BaseController
{
    /**
     * 库存流水列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('库存流水列表')]
    #[\erikwang2013\apidoc\annotation\Desc('获取库存流水列表，支持分页、关键词搜索')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/inventory/flow')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('库存管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', default:1, desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', default:15, desc:'每页条数')]
    #[\erikwang2013\apidoc\annotation\Param(name:'keyword', type:'string', default:'', desc:'搜索关键词（批次号/来源单据类型）')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function index(Request $request): Response
    {
        $validator = validator($request->all(), [
            'page' => 'integer',
            'limit' => 'integer',
            'keyword' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        [$page, $limit] = $this->pageParams($request);
        $keyword = $request->input('keyword', '');

        $query = InventoryFlow::query();
        if ($keyword) {
            // 表无 name/code/status 列（真实列见 store 注释）：原三处引用都会 1054，
            // 关键词改搜真实列 batch_code/source_type；status 筛选整段删除（FE 那页无 filters，
            // 方向列是 direction，需要筛选时再加）
            $query->where(function ($q) use ($keyword) {
                $q->where('batch_code', 'like', "%{$keyword}%")
                  ->orWhere('source_type', 'like', "%{$keyword}%");
            });
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray(), ['id', 'product_id', 'sku_id', 'warehouse_id', 'location_id', 'source_id']));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 库存流水详情
     */
    #[\erikwang2013\apidoc\annotation\Title('库存流水详情')]
    #[\erikwang2013\apidoc\annotation\Desc('根据ID获取库存流水详细信息')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('库存管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'库存流水hashid')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'库存流水详情')]

    public function show(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = InventoryFlow::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }
}
