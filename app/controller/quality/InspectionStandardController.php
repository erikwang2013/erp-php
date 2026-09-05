<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\quality;

use app\admin\controller\BaseController;
use app\model\QualityInspectionStandard;
use support\Request;
use support\Response;

/**
 * 检验标准管理
 */
#[\erikwang2013\apidoc\annotation\Tag("质量管理")]
#[\erikwang2013\apidoc\annotation\Title("检验标准")]
#[\erikwang2013\apidoc\annotation\Group("质量管理QMS")]

class InspectionStandardController extends BaseController
{
    /**
     * 检验标准列表（分页）
     */
#[\erikwang2013\apidoc\annotation\Title("检验标准列表")]
#[\erikwang2013\apidoc\annotation\Desc("获取检验标准列表，支持分页、名称/编码关键词搜索")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/quality/standard")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("质量管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", default:1, desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", default:15, desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"keyword", type:"string", default:"", desc:"搜索关键词（名称/编码）")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"检验标准列表数据")]

    public function index(Request $request): Response
    {
        $page = (int)$request->input('page', 1);
        $limit = (int)$request->input('limit', 15);
        $query = QualityInspectionStandard::query();
        $keyword = $request->input('keyword', '');
        if ($keyword) {
            $query->where('name', 'like', "%{$keyword}%")->orWhere('code', 'like', "%{$keyword}%");
        }
        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)->limit($limit)->orderBy('id', 'desc')->get()->map(fn ($i) => $this->encodeIds($i->toArray()));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建检验标准
     */
#[\erikwang2013\apidoc\annotation\Title("创建检验标准")]
#[\erikwang2013\apidoc\annotation\Desc("新增一条检验标准，标准名称必填")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/quality/standard")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("质量管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", default:"", desc:"标准名称（必填）")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"创建的检验标准记录")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['name' => 'required|string|max:200']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $item = new QualityInspectionStandard();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 检验标准详情
     */
#[\erikwang2013\apidoc\annotation\Title("检验标准详情")]
#[\erikwang2013\apidoc\annotation\Desc("根据ID获取检验标准详细信息")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("质量管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", default:"", desc:"检验标准hashid")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"检验标准详情")]

    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = QualityInspectionStandard::find($id);

        return $item ? $this->success($this->encodeIds($item->toArray())) : $this->fail('记录不存在', 404);
    }

    /**
     * 更新检验标准
     */
#[\erikwang2013\apidoc\annotation\Title("更新检验标准")]
#[\erikwang2013\apidoc\annotation\Desc("根据ID更新检验标准信息")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("质量管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", default:"", desc:"检验标准hashid")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"更新后的检验标准记录")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = QualityInspectionStandard::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除检验标准（软删除）
     */
#[\erikwang2013\apidoc\annotation\Title("删除检验标准")]
#[\erikwang2013\apidoc\annotation\Desc("根据ID软删除检验标准，需管理员密码二次确认")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("质量管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", default:"", desc:"检验标准hashid")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", default:"", desc:"管理员密码（二次确认）")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"array", desc:"空数组")]

    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = QualityInspectionStandard::find($id);
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
