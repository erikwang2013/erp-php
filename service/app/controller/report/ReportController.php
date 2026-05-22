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
 * 自定义报表管理 — 模板CRUD + 字段/筛选管理 + 执行查询 + 查看结果
 */
class ReportController extends BaseController
{
    // ============================================================
    // 报表模板 CRUD
    // ============================================================

    /**
     * 报表模板列表（分页）
     * GET /admin/report
     */
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
            ->get()->map(fn($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 创建报表模板
     * POST /admin/report
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:200',
            'module' => 'required|string|max:50',
        ]);
        if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);

        $item = new ReportTemplate();
        $item->id = $this->generateId();
        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 报表模板详情（含字段与筛选条件）
     * GET /admin/report/{id}
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = ReportTemplate::with(['fields', 'filters'])->find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $data = $item->toArray();
        if (isset($data['fields'])) {
            $data['fields'] = array_map(fn($f) => $this->encodeIds($f), $data['fields']);
        }
        if (isset($data['filters'])) {
            $data['filters'] = array_map(fn($f) => $this->encodeIds($f), $data['filters']);
        }
        return $this->success($this->encodeIds($data));
    }

    /**
     * 更新报表模板
     * PUT /admin/report/{id}
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = ReportTemplate::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除报表模板（软删除）
     * DELETE /admin/report/{id}
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = ReportTemplate::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) return $this->fail($error, 422);

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
     * 获取模板的所有字段
     * GET /admin/report/{id}/fields
     */
    public function fields(Request $request, string $hashid): Response
    {
        $templateId = $this->decodeId($hashid);
        $fields = ReportField::where('template_id', $templateId)
            ->orderBy('sort_order', 'asc')
            ->get()->map(fn($item) => $this->encodeIds($item->toArray()));
        return $this->success(['list' => $fields]);
    }

    /**
     * 添加字段
     * POST /admin/report（通过 action=add_field 判断）
     */
    public function addField(Request $request): Response
    {
        $validator = validator($request->all(), [
            'template_id' => 'required|integer',
            'name' => 'required|string|max:100',
            'field' => 'required|string|max:100',
            'label' => 'required|string|max:100',
        ]);
        if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);

        $item = new ReportField();
        $item->id = $this->generateId();
        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->created_at = date('Y-m-d H:i:s');
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '字段添加成功');
    }

    /**
     * 删除字段
     * DELETE /admin/report/field/{id}
     */
    public function deleteField(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = ReportField::find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        $item->delete();
        return $this->success([], '删除成功');
    }

    // ============================================================
    // 报表筛选条件管理
    // ============================================================

    /**
     * 获取模板的所有筛选条件
     * GET /admin/report/{id}/filters
     */
    public function filters(Request $request, string $hashid): Response
    {
        $templateId = $this->decodeId($hashid);
        $filters = ReportFilter::where('template_id', $templateId)
            ->orderBy('id', 'asc')
            ->get()->map(fn($item) => $this->encodeIds($item->toArray()));
        return $this->success(['list' => $filters]);
    }

    /**
     * 添加筛选条件
     * POST /admin/report（通过 action=add_filter 判断）
     */
    public function addFilter(Request $request): Response
    {
        $validator = validator($request->all(), [
            'template_id' => 'required|integer',
            'name' => 'required|string|max:100',
            'field' => 'required|string|max:100',
        ]);
        if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);

        $item = new ReportFilter();
        $item->id = $this->generateId();
        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->created_at = date('Y-m-d H:i:s');
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '筛选条件添加成功');
    }

    /**
     * 删除筛选条件
     * DELETE /admin/report/filter/{id}
     */
    public function deleteFilter(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = ReportFilter::find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        $item->delete();
        return $this->success([], '删除成功');
    }

    // ============================================================
    // 执行查询与查看结果
    // ============================================================

    /**
     * 执行报表查询
     * POST /admin/report/{id}/execute
     */
    public function execute(Request $request, string $hashid): Response
    {
        $templateId = $this->decodeId($hashid);
        $template = ReportTemplate::with(['fields', 'filters'])->find($templateId);
        if (!$template) return $this->fail('报表模板不存在', 404);

        $queryConfig = $template->query_config;
        if (!$queryConfig || empty($queryConfig['table'])) {
            return $this->fail('报表查询配置不完整，缺少table定义', 422);
        }

        // 构建SQL查询
        $table = $queryConfig['table'];
        $select = [];
        $groupBy = $queryConfig['group_by'] ?? null;

        // Whitelist allowed table names (prevent SQL injection via config)
        $allowedTables = [];
        foreach (glob(base_path('database/migrations/*.sql')) as $f) {
            preg_match_all('/`(erik_\w+)`/', file_get_contents($f), $m);
            $allowedTables = array_merge($allowedTables, $m[1]);
        }
        $allowedTables = array_unique($allowedTables);

        if (!in_array($table, $allowedTables, true)) {
            return $this->fail('不允许的表名: ' . $table, 422);
        }

        if ($template->fields->isNotEmpty()) {
            foreach ($template->fields as $field) {
                $fieldExpr = '`' . str_replace('`', '``', $field->field) . '`';
                if ($field->aggregator && $field->aggregator !== 'none') {
                    $fieldExpr = strtoupper($field->aggregator) . '(' . $fieldExpr . ')';
                }
                $select[] = $fieldExpr . ' AS `' . str_replace('`', '``', ($field->name ?? 'value')) . '`';
            }
        } else {
            $select[] = '*';
        }

        $sql = "SELECT " . implode(', ', $select) . " FROM {$table}";

        // JOIN
        if (!empty($queryConfig['joins'])) {
            foreach ($queryConfig['joins'] as $join) {
                $joinType = $join['type'] ?? 'LEFT';
                $joinTable = $join['table'];
                $joinOn = $join['on'];
                if (!in_array($joinTable, $allowedTables, true)) {
                    return $this->fail('不允许的表名: ' . $joinTable, 422);
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
                    switch ($filter->filter_type) {
                        case 'text':
                            $whereClauses[] = "{$filter->field} LIKE ?";
                            $params[] = "%{$value}%";
                            break;
                        case 'date_range':
                            if (is_array($value) || str_contains($value, ',')) {
                                $dates = is_array($value) ? $value : explode(',', $value);
                                if (!empty($dates[0])) {
                                    $whereClauses[] = "{$filter->field} >= ?";
                                    $params[] = $dates[0];
                                }
                                if (!empty($dates[1])) {
                                    $whereClauses[] = "{$filter->field} <= ?";
                                    $params[] = $dates[1];
                                }
                            }
                            break;
                        case 'number_range':
                            if (is_array($value) || str_contains($value, ',')) {
                                $nums = is_array($value) ? $value : explode(',', $value);
                                if (!empty($nums[0])) {
                                    $whereClauses[] = "{$filter->field} >= ?";
                                    $params[] = $nums[0];
                                }
                                if (!empty($nums[1])) {
                                    $whereClauses[] = "{$filter->field} <= ?";
                                    $params[] = $nums[1];
                                }
                            }
                            break;
                        case 'select':
                        default:
                            $whereClauses[] = "{$filter->field} = ?";
                            $params[] = $value;
                            break;
                    }
                }
            }
        }

        // 额外的where条件
        if (!empty($queryConfig['where'])) {
            foreach ($queryConfig['where'] as $w) {
                $whereClauses[] = $w;
            }
        }

        if (!empty($whereClauses)) {
            $sql .= ' WHERE ' . implode(' AND ', $whereClauses);
        }

        // GROUP BY
        if ($groupBy) {
            $sql .= ' GROUP BY ' . $groupBy;
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
            return $this->fail('查询执行失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 查看报表执行结果
     * GET /admin/report/{id}/result
     */
    public function result(Request $request, string $hashid): Response
    {
        // 支持按模板ID或数据集ID查看
        $id = $this->decodeId($hashid);
        $datasetId = $request->input('dataset_id');

        if ($datasetId) {
            $dataset = ReportDataset::find($this->decodeId($datasetId));
        } else {
            // 获取该模板最新的数据集
            $dataset = ReportDataset::where('template_id', $id)
                ->orderBy('id', 'desc')->first();
        }

        if (!$dataset) return $this->fail('未找到报表数据，请先执行查询', 404);

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
