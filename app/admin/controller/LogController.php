<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * @Apidoc\Tag("操作日志")
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\model\OperationLog;
use support\Request;
use support\Response;

class LogController extends BaseController
{
    /**
     * 操作日志列表
     * @Apidoc\Title("操作日志列表")
     * @Apidoc\Desc("获取操作日志分页列表，支持按用户、操作动作、请求路径、日期范围筛选")
     * @Apidoc\Url("/admin/v1/log")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("操作日志")
     * @Apidoc\Param(name="page", type="int", default=1, desc="页码")
     * @Apidoc\Param(name="limit", type="int", default=15, desc="每页条数")
     * @Apidoc\Param(name="user_id", type="int", default="", desc="用户ID筛选")
     * @Apidoc\Param(name="action", type="string", default="", desc="操作动作筛选")
     * @Apidoc\Param(name="path", type="string", default="", desc="请求路径模糊筛选")
     * @Apidoc\Param(name="start_date", type="string", default="", desc="开始日期")
     * @Apidoc\Param(name="end_date", type="string", default="", desc="结束日期")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据", children={
     *     @Apidoc\Returned("list", type="array", desc="日志列表"),
     *     @Apidoc\Returned("total", type="int", desc="总条数"),
     *     @Apidoc\Returned("page", type="int", desc="当前页码"),
     *     @Apidoc\Returned("limit", type="int", desc="每页条数"),
     * })
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $userId = $request->input('user_id');
        $action = $request->input('action');
        $path = $request->input('path');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = OperationLog::with('user');

        if ($userId) {
            $query->where('user_id', $userId);
        }
        if ($action) {
            $query->where('action', $action);
        }
        if ($path) {
            $query->where('path', 'like', "%{$path}%");
        }
        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
                       ->limit($limit)
                       ->orderBy('id', 'desc')
                       ->get()
                       ->map(function ($log) {
                           $data = $log->toArray();
                           $data['id'] = $this->encodeId($data['id']);
                           $data['user_name'] = $log->user->username ?? '系统';
                           unset($data['user'], $data['user_id']);

                           return $data;
                       });

        return $this->success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);
    }
}
