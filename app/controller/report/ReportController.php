<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\report;

use app\admin\controller\BaseController;
use app\model\ReportDataset;
use app\model\ReportField;
use app\model\ReportFilter;
use app\model\ReportTemplate;
use support\Db;
use support\Request;
use support\Response;

/**
 * 自定义报表管理
 */
#[\erikwang2013\apidoc\annotation\Tag("自定义报表")]
#[\erikwang2013\apidoc\annotation\Title("报表模板")]
#[\erikwang2013\apidoc\annotation\Group("自定义报表")]

class ReportController extends BaseController
{
    // ============================================================
    // 报表模板 CRUD
    // ============================================================

    /**
     * 报表模板列表（分页）
     * })
     */
#[\erikwang2013\apidoc\annotation\Title("报表模板列表")]
#[\erikwang2013\apidoc\annotation\Desc("获取报表模板分页列表，支持关键字和模块筛选")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/report")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("自定义报表")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", default:1, desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", default:15, desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"keyword", type:"string", default:"", desc:"搜索关键词(名称/编码)")]
#[\erikwang2013\apidoc\annotation\Param(name:"module", type:"string", default:"", desc:"模块筛选")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("list", type:"array", desc:"模板列表")]
#[\erikwang2013\apidoc\annotation\Returned("total", type:"int", desc:"总条数")]
#[\erikwang2013\apidoc\annotation\Returned("page", type:"int", desc:"当前页码")]
#[\erikwang2013\apidoc\annotation\Returned("limit", type:"int", desc:"每页条数")]

    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $module = $request->input('module', '');

        $query = ReportTemplate::query();
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('code', 'like', "%{$keyword}%");
            });
        }
        if ($module) {
            $query->where('module', $module);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建报表模板
     */
#[\erikwang2013\apidoc\annotation\Title("创建报表模板")]
#[\erikwang2013\apidoc\annotation\Desc("创建一个新的报表模板")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/report")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("自定义报表")]
#[\erikwang2013\apidoc\annotation\Param(name:"code", type:"string", require:true, desc:"模板编码")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", require:true, desc:"模板名称")]
#[\erikwang2013\apidoc\annotation\Param(name:"module", type:"string", require:true, desc:"所属模块")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"模板信息")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:200',
            'module' => 'required|string|max:50',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new ReportTemplate();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 报表模板详情
     */
#[\erikwang2013\apidoc\annotation\Title("报表模板详情")]
#[\erikwang2013\apidoc\annotation\Desc("获取指定报表模板的详细信息，包含字段和筛选条件")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("自定义报表")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"模板ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"模板详情(含字段/筛选)")]

    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail('无效ID', 400);
        }
        $item = ReportTemplate::with(['fields', 'filters'])->find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $data = $item->toArray();
        if (isset($data['fields'])) {
            $data['fields'] = array_map(fn ($f) => $this->encodeIds($f), $data['fields']);
        }
        if (isset($data['filters'])) {
            $data['filters'] = array_map(fn ($f) => $this->encodeIds($f), $data['filters']);
        }

        return $this->success($this->encodeIds($data));
    }

    /**
     * 更新报表模板
     */
#[\erikwang2013\apidoc\annotation\Title("更新报表模板")]
#[\erikwang2013\apidoc\annotation\Desc("更新指定报表模板的信息")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("自定义报表")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"模板ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"更新后的模板信息")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail('无效ID', 400);
        }
        $item = ReportTemplate::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除报表模板
     */
#[\erikwang2013\apidoc\annotation\Title("删除报表模板")]
#[\erikwang2013\apidoc\annotation\Desc("软删除指定报表模板及其关联字段、筛选条件和数据集，需要密码二次确认")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("自定义报表")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"模板ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", require:true, desc:"当前管理员密码(二次确认)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"array", desc:"空数组")]

    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail('无效ID', 400);
        }
        $item = ReportTemplate::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        // 清理关联数据
        ReportField::where('template_id', $id)->delete();
        ReportFilter::where('template_id', $id)->delete();
        ReportDataset::where('template_id', $id)->delete();

        $item->delete();

        return $this->success([], '删除成功');
    }

    // ============================================================
    // 报表字段管理
    // ============================================================

    /**
     * 获取模板字段列表
     * })
     */
#[\erikwang2013\apidoc\annotation\Title("模板字段列表")]
#[\erikwang2013\apidoc\annotation\Desc("获取指定报表模板的所有字段")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("自定义报表")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"模板ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("list", type:"array", desc:"字段列表")]

    public function fields(Request $request, string $id): Response
    {
        $templateId = $this->decodeIdSafe($id);
        if (!$templateId) {
            return $this->fail('无效ID', 400);
        }
        $fields = ReportField::where('template_id', $templateId)
            ->orderBy('sort_order', 'asc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $fields]);
    }

    /**
     * 添加字段
     */
#[\erikwang2013\apidoc\annotation\Title("添加报表字段")]
#[\erikwang2013\apidoc\annotation\Desc("向指定模板添加一个报表字段")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/report/field")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("自定义报表")]
#[\erikwang2013\apidoc\annotation\Param(name:"template_id", type:"int", require:true, desc:"模板ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", require:true, desc:"字段名")]
#[\erikwang2013\apidoc\annotation\Param(name:"field", type:"string", require:true, desc:"数据库字段")]
#[\erikwang2013\apidoc\annotation\Param(name:"label", type:"string", require:true, desc:"显示标签")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"字段信息")]

    public function addField(Request $request): Response
    {
        $validator = validator($request->all(), [
            'template_id' => 'required|integer',
            'name' => 'required|string|max:100',
            'field' => 'required|string|max:100',
            'label' => 'required|string|max:100',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new ReportField();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        $item->created_at = date('Y-m-d H:i:s');
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '字段添加成功');
    }

    /**
     * 删除字段
     */
#[\erikwang2013\apidoc\annotation\Title("删除报表字段")]
#[\erikwang2013\apidoc\annotation\Desc("删除指定的报表字段")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("自定义报表")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"字段ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"array", desc:"空数组")]

    public function deleteField(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail('无效ID', 400);
        }
        $item = ReportField::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        $item->delete();

        return $this->success([], '删除成功');
    }

    // ============================================================
    // 报表筛选条件管理
    // ============================================================

    /**
     * 获取模板筛选条件列表
     * })
     */
#[\erikwang2013\apidoc\annotation\Title("模板筛选条件列表")]
#[\erikwang2013\apidoc\annotation\Desc("获取指定报表模板的所有筛选条件")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("自定义报表")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"模板ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("list", type:"array", desc:"筛选条件列表")]

    public function filters(Request $request, string $id): Response
    {
        $templateId = $this->decodeIdSafe($id);
        if (!$templateId) {
            return $this->fail('无效ID', 400);
        }
        $filters = ReportFilter::where('template_id', $templateId)
            ->orderBy('id', 'asc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $filters]);
    }

    /**
     * 添加筛选条件
     */
#[\erikwang2013\apidoc\annotation\Title("添加筛选条件")]
#[\erikwang2013\apidoc\annotation\Desc("向指定模板添加一个筛选条件")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/report/filter")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("自定义报表")]
#[\erikwang2013\apidoc\annotation\Param(name:"template_id", type:"int", require:true, desc:"模板ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", require:true, desc:"筛选条件名")]
#[\erikwang2013\apidoc\annotation\Param(name:"field", type:"string", require:true, desc:"数据库字段")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"筛选条件信息")]

    public function addFilter(Request $request): Response
    {
        $validator = validator($request->all(), [
            'template_id' => 'required|integer',
            'name' => 'required|string|max:100',
            'field' => 'required|string|max:100',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new ReportFilter();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        $item->created_at = date('Y-m-d H:i:s');
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '筛选条件添加成功');
    }

    /**
     * 删除筛选条件
     */
#[\erikwang2013\apidoc\annotation\Title("删除筛选条件")]
#[\erikwang2013\apidoc\annotation\Desc("删除指定的报表筛选条件")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("自定义报表")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"筛选条件ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"array", desc:"空数组")]

    public function deleteFilter(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail('无效ID', 400);
        }
        $item = ReportFilter::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        $item->delete();

        return $this->success([], '删除成功');
    }

    // ============================================================
    // 执行查询与查看结果
    // ============================================================

    /**
     * 执行报表查询
     * })
     */
#[\erikwang2013\apidoc\annotation\Title("执行报表查询")]
#[\erikwang2013\apidoc\annotation\Desc("根据模板配置和筛选参数执行SQL查询，结果保存为数据集。支持text/date_range/number_range/select筛选类型。最多返回1000行。")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("自定义报表")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"模板ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("dataset_id", type:"string", desc:"数据集ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Returned("rows_count", type:"int", desc:"结果行数")]
#[\erikwang2013\apidoc\annotation\Returned("query_sql", type:"string", desc:"执行的SQL")]

    public function execute(Request $request, string $id): Response
    {
        $templateId = $this->decodeIdSafe($id);
        if (!$templateId) {
            return $this->fail('无效ID', 400);
        }
        $template = ReportTemplate::with(['fields', 'filters'])->find($templateId);
        if (!$template) {
            return $this->fail('报表模板不存在', 404);
        }

        $queryConfig = $template->query_config;
        if (!$queryConfig || empty($queryConfig['table'])) {
            return $this->fail('报表查询配置不完整，缺少table定义', 422);
        }

        // 构建SQL查询
        $table = $queryConfig['table'];
        $select = [];
        $groupBy = $queryConfig['group_by'] ?? null;

        // Whitelist allowed table names (prevent SQL injection via config)
        preg_match_all('/`(erp_\w+)`/', file_get_contents(base_path('database/install.sql')), $m);
        $allowedTables = array_unique($m[1]);

        if (!in_array($table, $allowedTables, true)) {
            return $this->fail('不允许的表名: ' . $table, 422);
        }

        // 校验字段/聚合函数/筛选字段/group_by/join 标识符（白名单，拒绝任何函数/表达式片段）
        if ($template->fields->isNotEmpty()) {
            foreach ($template->fields as $field) {
                if (($err = $this->validateIdentifier($field->field, '字段名')) !== null) {
                    return $this->fail($err, 422);
                }
                if ($field->aggregator && $field->aggregator !== 'none'
                    && ($err = $this->validateIdentifier($field->aggregator, '聚合函数')) !== null) {
                    return $this->fail($err, 422);
                }
            }
        }
        foreach ($template->filters as $filter) {
            if (($err = $this->validateIdentifier($filter->field, '筛选字段')) !== null) {
                return $this->fail($err, 422);
            }
        }

        if ($template->fields->isNotEmpty()) {
            foreach ($template->fields as $field) {
                $fieldExpr = $this->quoteColumn($field->field);
                if ($field->aggregator && $field->aggregator !== 'none') {
                    $fieldExpr = strtoupper($field->aggregator) . '(' . $fieldExpr . ')';
                }
                $select[] = $fieldExpr . ' AS `' . str_replace('`', '``', ($field->name ?? 'value')) . '`';
            }
        } else {
            $select[] = '*';
        }

        $sql = 'SELECT ' . implode(', ', $select) . " FROM {$table}";

        // JOIN（on 仅允许 "列 [=|<|>|<=|>=|!=] 列" 形式，列名白名单校验）
        if (!empty($queryConfig['joins'])) {
            foreach ($queryConfig['joins'] as $join) {
                $joinType = strtoupper((string) ($join['type'] ?? 'LEFT'));
                $joinTable = (string) ($join['table'] ?? '');
                $joinOn = (string) ($join['on'] ?? '');
                if (!in_array($joinType, ['LEFT', 'RIGHT', 'INNER'], true)) {
                    return $this->fail('不支持的 JOIN 类型: ' . $joinType, 422);
                }
                if (!in_array($joinTable, $allowedTables, true)) {
                    return $this->fail('不允许的表名: ' . $joinTable, 422);
                }
                if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?\s*(=|<=>|<|>|<=|>=|!=)\s*[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $joinOn)) {
                    return $this->fail('JOIN ON 条件非法：仅支持 "列 操作符 列"', 422);
                }
                $sql .= " {$joinType} JOIN {$joinTable} ON {$joinOn}";
            }
        }

        // WHERE from request filters
        $whereClauses = [];
        $params = [];
        if ($template->filters->isNotEmpty()) {
            foreach ($template->filters as $filter) {
                $value = $request->input($filter->name, $filter->default_value);
                if ($filter->required && ($value === null || $value === '')) {
                    return $this->fail("筛选条件「{$filter->name}」为必填", 422);
                }
                if ($value !== null && $value !== '') {
                    $filterRef = $this->quoteColumn($filter->field);
                    switch ($filter->filter_type) {
                        case 'text':
                            $whereClauses[] = "{$filterRef} LIKE ?";
                            $params[] = "%{$value}%";
                            break;
                        case 'date_range':
                            if (is_array($value) || str_contains($value, ',')) {
                                $dates = is_array($value) ? $value : explode(',', $value);
                                if (!empty($dates[0])) {
                                    $whereClauses[] = "{$filterRef} >= ?";
                                    $params[] = $dates[0];
                                }
                                if (!empty($dates[1])) {
                                    $whereClauses[] = "{$filterRef} <= ?";
                                    $params[] = $dates[1];
                                }
                            }
                            break;
                        case 'number_range':
                            if (is_array($value) || str_contains($value, ',')) {
                                $nums = is_array($value) ? $value : explode(',', $value);
                                if (!empty($nums[0])) {
                                    $whereClauses[] = "{$filterRef} >= ?";
                                    $params[] = $nums[0];
                                }
                                if (!empty($nums[1])) {
                                    $whereClauses[] = "{$filterRef} <= ?";
                                    $params[] = $nums[1];
                                }
                            }
                            break;
                        case 'select':
                        default:
                            $whereClauses[] = "{$filterRef} = ?";
                            $params[] = $value;
                            break;
                    }
                }
            }
        }

        // 额外的where条件（仅接受结构化 field/op/value，值一律参数绑定，拒绝裸 SQL）
        if (!empty($queryConfig['where'])) {
            foreach ($queryConfig['where'] as $w) {
                if (!is_array($w)) {
                    return $this->fail('where 条件必须为结构化字段条件（field/op/value）', 422);
                }
                $clause = $this->buildWhereClause($w);
                if ($clause === null) {
                    return $this->fail('where 条件非法：仅支持白名单字段与 eq/neq/gt/gte/lt/lte/like/between/in 操作符', 422);
                }
                $whereClauses[] = $clause[0];
                array_push($params, ...$clause[1]);
            }
        }

        if (!empty($whereClauses)) {
            $sql .= ' WHERE ' . implode(' AND ', $whereClauses);
        }

        // GROUP BY（仅允许单个/逗号分隔的列名，拒绝函数/表达式；每段可带 alias. 前缀）
        if ($groupBy) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?(\s*,\s*[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?)*$/', $groupBy)) {
                return $this->fail('GROUP BY 字段非法', 422);
            }
            $groupBySql = implode(', ', array_map(fn (string $g) => $this->quoteColumn(trim($g)), explode(',', $groupBy)));
            $sql .= ' GROUP BY ' . $groupBySql;
        }

        // ORDER BY
        $sql .= ' ORDER BY 1 DESC';

        // 限制行数（安全）
        $sql .= ' LIMIT 1000';

        // 执行查询
        try {
            $rows = Db::select($sql, $params);
            $rowCount = count($rows);

            // 保存数据集
            $dataset = new ReportDataset();
            $dataset->id = $this->generateId();
            $dataset->template_id = $templateId;
            $dataset->name = $template->name . ' - ' . date('Y-m-d H:i:s');
            $dataset->query_sql = $sql;
            $dataset->data = json_encode($rows, JSON_UNESCAPED_UNICODE);
            $dataset->rows_count = $rowCount;
            $dataset->generated_at = date('Y-m-d H:i:s');
            $dataset->parameters = json_encode($params, JSON_UNESCAPED_UNICODE);
            $dataset->created_at = date('Y-m-d H:i:s');
            $dataset->save();

            return $this->success([
                'dataset_id' => $this->encodeId($dataset->id),
                'rows_count' => $rowCount,
                'query_sql' => $sql,
            ], '查询执行成功');
        } catch (\Throwable $e) {
            // 不回显原始异常信息（可能泄露表结构/SQL），详情记录服务端日志
            $this->logError('执行报表查询', $e);

            return $this->fail('查询执行失败，请查看服务端日志', 500);
        }
    }

    /**
     * 校验拼入 SQL 的标识符（列名/字段名/聚合函数），非法返回错误信息，合法返回 null。
     * 允许 [A-Za-z_][A-Za-z0-9_]* 或别名点号形式 alias.col（JOIN 模板用），拒绝任何函数/表达式/字符串片段。
     */
    protected function validateIdentifier(mixed $value, string $context): ?string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $value)) {
            return "{$context} 非法: " . (is_scalar($value) ? (string) $value : gettype($value));
        }

        return null;
    }

    /**
     * 将已通过白名单校验的标识符渲染为安全 SQL 列引用：alias.col → `alias`.`col`。
     * 调用方必须先经 validateIdentifier 校验，此处不再重复校验。
     */
    protected function quoteColumn(string $identifier): string
    {
        return implode('.', array_map(fn (string $part) => '`' . $part . '`', explode('.', $identifier)));
    }

    /**
     * 将结构化 where 条件（field/op/value）映射为参数化 SQL 片段 [sql, params]，非法返回 null。
     * 支持 op: eq/neq/gt/gte/lt/lte/like/between/in；field 白名单校验，值一律参数绑定。
     */
    protected function buildWhereClause(array $w): ?array
    {
        $field = (string) ($w['field'] ?? '');
        $op = (string) ($w['op'] ?? '');
        $value = $w['value'] ?? null;

        if ($this->validateIdentifier($field, 'where 字段') !== null) {
            return null;
        }

        $fieldRef = $this->quoteColumn($field);

        switch ($op) {
            case 'eq':
                return ["{$fieldRef} = ?", [$value]];
            case 'neq':
                return ["{$fieldRef} <> ?", [$value]];
            case 'gt':
                return ["{$fieldRef} > ?", [$value]];
            case 'gte':
                return ["{$fieldRef} >= ?", [$value]];
            case 'lt':
                return ["{$fieldRef} < ?", [$value]];
            case 'lte':
                return ["{$fieldRef} <= ?", [$value]];
            case 'like':
                return ["{$fieldRef} LIKE ?", [$value]];
            case 'between':
                if (!is_array($value) || count($value) !== 2) {
                    return null;
                }
                $bounds = array_values($value);

                return ["{$fieldRef} BETWEEN ? AND ?", [$bounds[0], $bounds[1]]];
            case 'in':
                if (!is_array($value) || $value === []) {
                    return null;
                }
                $values = array_values($value);

                return ["{$fieldRef} IN (" . implode(', ', array_fill(0, count($values), '?')) . ')', $values];
            default:
                return null;
        }
    }

    /**
     * 查看报表执行结果
     */
#[\erikwang2013\apidoc\annotation\Title("查看报表结果")]
#[\erikwang2013\apidoc\annotation\Desc("查看最近一次执行结果，或通过dataset_id查看指定数据集")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("自定义报表")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"模板ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"dataset_id", type:"string", default:"", desc:"数据集ID(hashid)，不传则取最新")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"数据集详情(含查询结果)")]

    public function result(Request $request, string $id): Response
    {
        // 支持按模板ID或数据集ID查看
        $id = $this->decodeIdSafe($id);
        $datasetId = $request->input('dataset_id');

        if ($datasetId) {
            $datasetId = $this->decodeIdSafe($datasetId);
            if (!$datasetId) {
                return $this->fail('无效ID', 400);
            }
            $dataset = ReportDataset::find($datasetId);
        } else {
            if (!$id) {
                return $this->fail('无效ID', 400);
            }
            // 获取该模板最新的数据集
            $dataset = ReportDataset::where('template_id', $id)
                ->orderBy('id', 'desc')->first();
        }

        if (!$dataset) {
            return $this->fail('未找到报表数据，请先执行查询', 404);
        }

        $data = $dataset->toArray();
        // 解析JSON数据
        if (is_string($data['data'])) {
            $data['data'] = json_decode($data['data'], true);
        }
        if (is_string($data['parameters'])) {
            $data['parameters'] = json_decode($data['parameters'], true);
        }

        return $this->success($this->encodeIds($data));
    }
}
