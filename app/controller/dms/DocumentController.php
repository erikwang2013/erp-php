<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\dms;

use app\admin\controller\BaseController;
use app\model\DmsCategory;
use app\model\DmsDocument;
use app\model\DmsDocumentVersion;
use support\Request;
use support\Response;

/**
 * 文档管理
 * @Apidoc\Tag("文档管理")
 */#[\erikwang2013\apidoc\annotation\Tag("文档管理")]

class DocumentController extends BaseController
{
    /**
     * 预定义文档分类
     */
    private const CATEGORIES = ['制度规范', '流程文档', '技术文档', '合同协议', '培训材料', '其他'];

    /**
     * 文档列表
     * @Apidoc\Title("文档列表")
     * @Apidoc\Desc("分页查询文档，支持标题/编码关键字、分类与状态筛选")
     * @Apidoc\Url("/admin/v1/dms/document")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("文档管理")
     * @Apidoc\Param(name="page", type="int", default="1", desc="页码")
     * @Apidoc\Param(name="limit", type="int", default="15", desc="每页数量")
     * @Apidoc\Param(name="keyword", type="string", desc="标题或文档编码关键字")
     * @Apidoc\Param(name="category", type="string", desc="文档分类")
     * @Apidoc\Param(name="status", type="int", desc="状态,0=草稿,1=发布")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="分页列表(list/total/page/limit)")
     */#[\erikwang2013\apidoc\annotation\Title("文档列表")]
#[\erikwang2013\apidoc\annotation\Desc("分页查询文档，支持标题/编码关键字、分类与状态筛选")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/dms/document")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("文档管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", default:"1", desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", default:"15", desc:"每页数量")]
#[\erikwang2013\apidoc\annotation\Param(name:"keyword", type:"string", desc:"标题或文档编码关键字")]
#[\erikwang2013\apidoc\annotation\Param(name:"category", type:"string", desc:"文档分类")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", desc:"状态,0=草稿,1=发布")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"分页列表(list/total/page/limit)")]

    public function index(Request $request): Response
    {
        $page = (int)$request->input('page', 1);
        $limit = (int)$request->input('limit', 15);
        $query = DmsDocument::query();
        $keyword = $request->input('keyword', '');
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                  ->orWhere('code', 'like', "%{$keyword}%");
            });
        }
        $category = $request->input('category');
        if ($category) {
            $query->where('category', $category);
        }
        $status = $request->input('status');
        if ($status !== null && $status !== '') {
            $query->where('status', (int)$status);
        }
        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)->limit($limit)->orderBy('id', 'desc')->get()->map(fn ($i) => $this->encodeIds($i->toArray()));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建文档
     * @Apidoc\Title("创建文档")
     * @Apidoc\Desc("新建文档，自动生成文档编码(DOC-日期-随机)并记录初始版本")
     * @Apidoc\Url("/admin/v1/dms/document")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("文档管理")
     * @Apidoc\Param(name="title", type="string", require=true, desc="文档标题(≤200字符)")
     * @Apidoc\Param(name="category", type="string", require=true, desc="文档分类(≤50字符)")
     * @Apidoc\Param(name="status", type="int", default="0", desc="状态,0=草稿,1=发布")
     * @Apidoc\Param(name="content", type="string", desc="文档内容")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="文档详情(hashid)")
     */#[\erikwang2013\apidoc\annotation\Title("创建文档")]
#[\erikwang2013\apidoc\annotation\Desc("新建文档，自动生成文档编码(DOC-日期-随机)并记录初始版本")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/dms/document")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("文档管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"title", type:"string", require:true, desc:"文档标题(≤200字符)")]
#[\erikwang2013\apidoc\annotation\Param(name:"category", type:"string", require:true, desc:"文档分类(≤50字符)")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", default:"0", desc:"状态,0=草稿,1=发布")]
#[\erikwang2013\apidoc\annotation\Param(name:"content", type:"string", desc:"文档内容")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"文档详情(hashid)")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'title' => 'required|string|max:200',
            'category' => 'required|string|max:50',
            'status' => 'nullable|integer|between:0,1',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new DmsDocument();
        $item->id = $this->generateId();
        $item->code = $this->generateDocumentCode();
        $item->version = 1;
        $item->author = $request->adminId ?? 0;
        $item->status = (int)$request->input('status', 0);
        $this->fillModelFromRequest($item, $request);
        $item->save();

        // 记录初始版本
        $this->createVersion($item, $request, '初始版本');

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 文档详情
     * @Apidoc\Title("文档详情")
     * @Apidoc\Desc("查看文档详情及其全部版本历史")
     * @Apidoc\Url("/admin/v1/dms/document/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("文档管理")
     * @Apidoc\Param(name="id", type="string", require=true, desc="文档ID(hashid)")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="文档详情,含versions版本数组")
     */#[\erikwang2013\apidoc\annotation\Title("文档详情")]
#[\erikwang2013\apidoc\annotation\Desc("查看文档详情及其全部版本历史")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("文档管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"文档ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"文档详情,含versions版本数组")]

    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = DmsDocument::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        $data = $this->encodeIds($item->toArray());
        $versions = DmsDocumentVersion::where('document_id', $id)->orderBy('id', 'desc')->get()->map(fn ($v) => $this->encodeIds($v->toArray()));
        $data['versions'] = $versions;

        return $this->success($data);
    }

    /**
     * 更新文档
     * @Apidoc\Title("更新文档")
     * @Apidoc\Desc("更新文档信息，内容变更时自动生成新版本并记录变更说明")
     * @Apidoc\Url("/admin/v1/dms/document/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("文档管理")
     * @Apidoc\Param(name="id", type="string", require=true, desc="文档ID(hashid)")
     * @Apidoc\Param(name="title", type="string", desc="文档标题")
     * @Apidoc\Param(name="category", type="string", desc="文档分类")
     * @Apidoc\Param(name="content", type="string", desc="文档内容(变更时自动生成新版本)")
     * @Apidoc\Param(name="change_note", type="string", desc="版本变更说明")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="更新后文档详情(hashid)")
     */#[\erikwang2013\apidoc\annotation\Title("更新文档")]
#[\erikwang2013\apidoc\annotation\Desc("更新文档信息，内容变更时自动生成新版本并记录变更说明")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("文档管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"文档ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"title", type:"string", desc:"文档标题")]
#[\erikwang2013\apidoc\annotation\Param(name:"category", type:"string", desc:"文档分类")]
#[\erikwang2013\apidoc\annotation\Param(name:"content", type:"string", desc:"文档内容(变更时自动生成新版本)")]
#[\erikwang2013\apidoc\annotation\Param(name:"change_note", type:"string", desc:"版本变更说明")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"更新后文档详情(hashid)")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = DmsDocument::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        // 内容变更时自动生成新版本
        $contentChanged = $request->input('content') !== null
            && $request->input('content') !== $item->content;

        $this->fillModelFromRequest($item, $request);
        if ($contentChanged) {
            $item->version = (int)$item->version + 1;
            $this->createVersion($item, $request, $request->input('change_note', '内容更新'));
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除文档
     * @Apidoc\Title("删除文档")
     * @Apidoc\Desc("删除文档并级联删除其全部版本记录，需二次密码确认")
     * @Apidoc\Url("/admin/v1/dms/document/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("文档管理")
     * @Apidoc\Param(name="id", type="string", require=true, desc="文档ID(hashid)")
     * @Apidoc\Param(name="password", type="string", require=true, desc="操作密码(二次确认)")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="array", desc="空数组")
     */#[\erikwang2013\apidoc\annotation\Title("删除文档")]
#[\erikwang2013\apidoc\annotation\Desc("删除文档并级联删除其全部版本记录，需二次密码确认")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("文档管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"文档ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", require:true, desc:"操作密码(二次确认)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"array", desc:"空数组")]

    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = DmsDocument::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }
        DmsDocumentVersion::where('document_id', $id)->delete();
        $item->delete();

        return $this->success([], '删除成功');
    }

    /**
     * 文档版本历史
     * @Apidoc\Title("文档版本历史")
     * @Apidoc\Url("/admin/v1/dms/document/{id}/versions")
     * @Apidoc\Method("GET")
     */#[\erikwang2013\apidoc\annotation\Title("文档版本历史")]
#[\erikwang2013\apidoc\annotation\Method("GET")]

    public function versions(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $versions = DmsDocumentVersion::where('document_id', $id)->orderBy('id', 'desc')->get()->map(fn ($v) => $this->encodeIds($v->toArray()));

        return $this->success(['list' => $versions]);
    }

    /**
     * 文档分类列表
     * @Apidoc\Title("文档分类列表")
     * @Apidoc\Url("/admin/v1/dms/categories")
     * @Apidoc\Method("GET")
     */#[\erikwang2013\apidoc\annotation\Title("文档分类列表")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/dms/categories")]
#[\erikwang2013\apidoc\annotation\Method("GET")]

    public function categories(Request $request): Response
    {
        $categories = DmsCategory::where('status', 1)->orderBy('sort')->pluck('name')->all();

        return $this->success(['list' => $categories ?: self::CATEGORIES]);
    }

    /**
     * 创建版本记录
     */
    private function createVersion(DmsDocument $item, Request $request, string $changeNote): void
    {
        $version = new DmsDocumentVersion();
        $version->id = $this->generateId();
        $version->document_id = $item->id;
        $version->version = (int)$item->version;
        $version->content = (string)$item->content;
        $version->changed_by = $request->adminId ?? 0;
        $version->change_note = $changeNote;
        $version->save();
    }

    /**
     * 生成文档编码: DOC-YYYYMMDD-随机4位
     */
    private function generateDocumentCode(): string
    {
        return 'DOC-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
    }
}
