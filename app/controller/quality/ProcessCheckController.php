<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\quality;

use app\admin\controller\BaseController;
use app\model\QualityIpcqRecord;
use support\Request;
use support\Response;

/**
 * 过程检验 (IPQC)
 * @Apidoc\Tag("质量管理")
 */
class ProcessCheckController extends BaseController
{
    public function index(Request $request): Response
    {
        $page = (int)$request->input('page', 1);
        $limit = (int)$request->input('limit', 15);
        $query = QualityIpcqRecord::query();
        $keyword = $request->input('keyword', '');
        if ($keyword) $query->where('code', 'like', "%{$keyword}%");
        $result = $request->input('result', '');
        if ($result !== '') $query->where('result', $result);
        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)->limit($limit)->orderBy('id', 'desc')->get()->map(fn($i) => $this->encodeIds($i->toArray()));
        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'inspected_qty' => 'required|integer|min:0',
            'result' => 'required|in:pass,reject',
        ]);
        if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);
        $item = new QualityIpcqRecord();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = QualityIpcqRecord::find($id);
        return $item ? $this->success($this->encodeIds($item->toArray())) : $this->fail('记录不存在', 404);
    }

    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = QualityIpcqRecord::find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        $this->fillModelFromRequest($item, $request);
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = QualityIpcqRecord::find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) return $this->fail($error, 422);
        $item->delete();
        return $this->success([], '删除成功');
    }
}
