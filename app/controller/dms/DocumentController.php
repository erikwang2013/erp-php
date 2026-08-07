<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\dms;

use app\admin\controller\BaseController;
use app\model\DmsDocument;
use app\model\DmsDocumentVersion;
use support\Request;
use support\Response;

/**
 * 文档管理
 * @Apidoc\Tag("文档管理")
 */
class DocumentController extends BaseController
{
    /**
     * 预定义文档分类
     */
    private const CATEGORIES = ['制度规范', '流程文档', '技术文档', '合同协议', '培训材料', '其他'];

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

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

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

    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = DmsDocument::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        $data = $this->encodeIds($item->toArray());
        $versions = DmsDocumentVersion::where('document_id', $id)->orderBy('id', 'desc')->get()->map(fn ($v) => $this->encodeIds($v->toArray()));
        $data['versions'] = $versions;

        return $this->success($data);
    }

    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
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

    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
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
     * @Apidoc\Url("/admin/dms/document/{id}/versions")
     * @Apidoc\Method("GET")
     */
    public function versions(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $versions = DmsDocumentVersion::where('document_id', $id)->orderBy('id', 'desc')->get()->map(fn ($v) => $this->encodeIds($v->toArray()));

        return $this->success(['list' => $versions]);
    }

    /**
     * 文档分类列表
     * @Apidoc\Title("文档分类列表")
     * @Apidoc\Url("/admin/dms/categories")
     * @Apidoc\Method("GET")
     */
    public function categories(Request $request): Response
    {
        return $this->success(['list' => self::CATEGORIES]);
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
