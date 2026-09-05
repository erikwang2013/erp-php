<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\bi;

use app\admin\controller\BaseController;
use app\model\ReportDataset;
use support\Request;
use support\Response;

/**
 * 数据集管理
 */
#[\erikwang2013\apidoc\annotation\Tag("商业智能")]
#[\erikwang2013\apidoc\annotation\Title("数据集")]
#[\erikwang2013\apidoc\annotation\Group("BI看板")]

class DatasetController extends BaseController
{
    /**
     * 数据集列表
     */
#[\erikwang2013\apidoc\annotation\Title("数据集列表")]
#[\erikwang2013\apidoc\annotation\Desc("分页查询自定义报表数据集，支持按名称或查询SQL关键字筛选")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/bi/dataset")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("商业智能")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", default:"1", desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", default:"15", desc:"每页数量")]
#[\erikwang2013\apidoc\annotation\Param(name:"keyword", type:"string", desc:"数据集名称或查询SQL关键字")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"分页列表(list/total/page/limit)")]

    public function index(Request $request): Response
    {
        $page = (int)$request->input('page', 1);
        $limit = (int)$request->input('limit', 15);
        $query = ReportDataset::query();
        $keyword = $request->input('keyword', '');
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('query_sql', 'like', "%{$keyword}%");
            });
        }
        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)->limit($limit)->orderBy('id', 'desc')->get()->map(fn ($i) => $this->encodeIds($i->toArray()));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建数据集
     */
#[\erikwang2013\apidoc\annotation\Title("创建数据集")]
#[\erikwang2013\apidoc\annotation\Desc("基于报表模板新建数据集")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/bi/dataset")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("商业智能")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", require:true, desc:"数据集名称(≤200字符)")]
#[\erikwang2013\apidoc\annotation\Param(name:"template_id", type:"int", require:true, desc:"报表模板ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"数据集详情(hashid)")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'name' => 'required|string|max:200',
            'template_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $item = new ReportDataset();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 数据集详情
     */
#[\erikwang2013\apidoc\annotation\Title("数据集详情")]
#[\erikwang2013\apidoc\annotation\Desc("查看单个数据集定义")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("商业智能")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"数据集ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"数据集详情(hashid)")]

    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = ReportDataset::find($id);

        return $item ? $this->success($this->encodeIds($item->toArray())) : $this->fail('记录不存在', 404);
    }

    /**
     * 更新数据集
     */
#[\erikwang2013\apidoc\annotation\Title("更新数据集")]
#[\erikwang2013\apidoc\annotation\Desc("更新数据集名称或查询定义")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("商业智能")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"数据集ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", desc:"数据集名称")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"更新后数据集详情(hashid)")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = ReportDataset::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除数据集
     */
#[\erikwang2013\apidoc\annotation\Title("删除数据集")]
#[\erikwang2013\apidoc\annotation\Desc("删除数据集定义，需二次密码确认")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("商业智能")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"数据集ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", require:true, desc:"操作密码(二次确认)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"array", desc:"空数组")]

    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = ReportDataset::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }
        $item->delete();

        return $this->success([], '删除成功');
    }
}
