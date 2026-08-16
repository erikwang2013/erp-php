<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service;

use app\common\SnowflakeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * 通用薄服务基类（P2-F2 服务层轻量提取）
 *
 * 提供各业务模块 Service 共享的通用 CRUD 骨架：
 *  - list()：分页查询，支持关键词 / 等值 / 真值过滤、关联预加载、排序；
 *  - find() / create() / update() / delete()：单记录读写；
 *  - deleteWhere()：条件批量删除（配合级联清理）。
 *
 * 设计约定：
 *  - 数据访问与查询构建下沉到 Service，控制器仅负责 HTTP 参数解析、
 *    hashid 编解码、权限校验与响应包装，不再直接调用模型查询方法；
 *  - 返回原始数组/模型对象，hashid 编码等"展示层"逻辑仍留在控制器；
 *  - 纯逻辑助手（normalizePageParams / canTransition 等）不依赖数据库，
 *    可直接编写单元测试。
 *
 * 注意：本类及其子类必须保持无参构造（或没有构造函数）。
 * 容器解析走 Webman\Container::get() 的 class_exists 回退（new $name()），
 * config/dependence.php 为 dead config（未被 addDefinitions 加载），
 * 任何带参构造都会导致容器实例化失败。
 */
abstract class AbstractCrudService
{
    /**
     * 分页参数归一化（纯逻辑，可单测）
     * 页码最小为 1，每页条数限制在 [1, 100] 区间。
     *
     * @return array{0:int, 1:int} [page, limit]
     */
    public function normalizePageParams(int $page, int $limit): array
    {
        return [
            max(1, $page),
            min(max(1, $limit), 100),
        ];
    }

    /**
     * 状态流转校验（纯逻辑，可单测）
     * 依据状态流转图判断当前状态是否允许流转到目标状态。
     *
     * @param int $from 当前状态
     * @param int $to   目标状态
     * @param array<int, int[]> $flow 状态流转图：from => [允许的 to 列表]
     */
    public function canTransition(int $from, int $to, array $flow): bool
    {
        return isset($flow[$from]) && in_array($to, $flow[$from], true);
    }

    /**
     * 分页查询
     *
     * @param string $model 模型类名（如 app\model\CrmContact::class）
     * @param array<string, mixed> $filters 过滤条件（keyword / 各字段值）
     * @param array<string, mixed> $options 查询选项：
     *   - searchFields: 关键词模糊匹配字段列表（name/code 等）
     *   - eqFilters:    等值过滤字段（值非 null 且非 '' 时生效，int 强转）
     *   - stringEqFilters: 等值过滤字段（不转 int，适用于 type/category 等字符串）
     *   - truthyFilters: 真值过滤字段（值非空时生效，int 强转）
     *   - baseOrWhere:  基础 orWhere 组，如 [['status', 0], ['owner_user_id', 0]]
     *   - with:         关联预加载列表
     *   - orderBy:      排序字段（string 或 [['field','dir'], ...] 数组）
     *   - orderDir:     排序方向（orderBy 为 string 时生效）
     *
     * @return array{list: array, total: int, page: int, limit: int}
     */
    public function list(string $model, array $filters = [], int $page = 1, int $limit = 15, array $options = []): array
    {
        $query = $model::query();
        if (!empty($options['with'])) {
            $query->with($options['with']);
        }
        $this->applyListFilters($query, $filters, $options);

        $total = (int) $query->count();
        $query->offset(($page - 1) * $limit)->limit($limit);
        $this->applyListOrder($query, $options);

        $list = $query->get()->map(static fn (Model $item): array => $item->toArray())->all();

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /**
     * 全量查询（不分页，返回原始数组列表）
     *
     * @param string $model 模型类名
     * @param array<string, mixed> $filters 过滤条件
     * @param array<string, mixed> $options 同 list() 的 options
     * @return array<int, array>
     */
    public function all(string $model, array $filters = [], array $options = []): array
    {
        $query = $model::query();
        if (!empty($options['with'])) {
            $query->with($options['with']);
        }
        $this->applyListFilters($query, $filters, $options);
        $this->applyListOrder($query, $options);

        return $query->get()->map(static fn (Model $item): array => $item->toArray())->all();
    }

    /**
     * 按主键查询（可带关联预加载）
     *
     * @param string $model 模型类名
     * @param array<int, string> $with 关联预加载列表
     */
    public function find(string $model, int $id, array $with = []): ?Model
    {
        $query = $model::query();
        if ($with !== []) {
            $query->with($with);
        }

        return $query->find($id);
    }

    /**
     * 创建记录
     * 生成 snowflake 主键；请求数据仅填充模型 $fillable 字段（与原控制器
     * BaseController::fillModelFromRequest() 语义完全一致：
     * 无 $fillable 的模型不持久化请求字段）。
     *
     * @param string $model 模型类名
     * @param array<string, mixed> $data 待写入数据（已做 fillable 过滤）
     * @param array<string, mixed> $defaults 默认值（如 status => 0）
     * @param bool $defaultsOverride 默认值是否覆盖请求数据：
     *   - true（默认）：先填充请求数据再应用默认值（默认值强制生效，
     *     如请假/生产工单的 status=0 必须覆盖请求传入的 status）；
     *   - false：先应用默认值再填充请求数据（请求数据可覆盖默认值）。
     */
    public function create(string $model, array $data = [], array $defaults = [], bool $defaultsOverride = true): Model
    {
        /** @var Model $item */
        $item = new $model();
        $item->id = $this->generateId();
        if (!$defaultsOverride) {
            foreach ($defaults as $field => $value) {
                $item->$field = $value;
            }
        }
        $item->fill($this->fillableOnly($item, $data));
        if ($defaultsOverride) {
            foreach ($defaults as $field => $value) {
                $item->$field = $value;
            }
        }
        $item->save();

        return $item;
    }

    /**
     * 更新记录
     * 请求数据仅填充 $fillable 字段；preserve 中的字段在填充后恢复原值，
     * 防止请求覆盖业务字段（如 status、completed_quantity）。
     *
     * @param string $model 模型类名
     * @param array<string, mixed> $data 待写入数据（已做 fillable 过滤）
     * @param array<int, string> $preserve 需要保留原值的字段列表
     * @return Model|null 记录不存在时返回 null
     */
    public function update(string $model, int $id, array $data = [], array $preserve = []): ?Model
    {
        $item = $model::find($id);
        if (!$item) {
            return null;
        }
        $keep = [];
        foreach ($preserve as $field) {
            $keep[$field] = $item->$field;
        }
        $item->fill($this->fillableOnly($item, $data));
        foreach ($keep as $field => $value) {
            $item->$field = $value;
        }
        $item->save();

        return $item;
    }

    /**
     * 按主键删除
     *
     * @param string $model 模型类名
     */
    public function delete(string $model, int $id): bool
    {
        $item = $model::find($id);
        if (!$item) {
            return false;
        }

        return (bool) $item->delete();
    }

    /**
     * 条件批量删除（返回影响行数）
     *
     * @param string $model 模型类名
     * @param array<string, mixed> $conditions 等值条件，如 ['ticket_id' => 12]
     */
    public function deleteWhere(string $model, array $conditions): int
    {
        $query = $model::query();
        foreach ($conditions as $field => $value) {
            $query->where($field, $value);
        }

        return $query->delete();
    }

    /**
     * 构建列表过滤条件（查询构建逻辑，可单测）
     *
     * @param Builder $query 查询构建器
     * @param array<string, mixed> $filters 过滤条件
     * @param array<string, mixed> $options 查询选项（同 list()）
     */
    public function applyListFilters(Builder $query, array $filters = [], array $options = []): void
    {
        // 关键词模糊匹配（name/code 等）
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        $searchFields = $options['searchFields'] ?? [];
        if ($keyword !== '' && $searchFields !== []) {
            $query->where(function (Builder $q) use ($keyword, $searchFields): void {
                foreach ($searchFields as $i => $field) {
                    if ($i === 0) {
                        $q->where($field, 'like', "%{$keyword}%");
                    } else {
                        $q->orWhere($field, 'like', "%{$keyword}%");
                    }
                }
            });
        }

        // 基础 orWhere 组（如公海池：status=0 或 无归属人）
        $baseOrWhere = $options['baseOrWhere'] ?? [];
        if ($baseOrWhere !== []) {
            $query->where(function (Builder $q) use ($baseOrWhere): void {
                foreach ($baseOrWhere as $i => [$field, $value]) {
                    if ($i === 0) {
                        $q->where($field, $value);
                    } else {
                        $q->orWhere($field, $value);
                    }
                }
            });
        }

        // 等值过滤（int 强转，值非 null 且非 '' 时生效）
        foreach ($options['eqFilters'] ?? [] as $field) {
            if (isset($filters[$field]) && $filters[$field] !== '') {
                $query->where($field, (int) $filters[$field]);
            }
        }

        // 字符串等值过滤（不转 int）
        foreach ($options['stringEqFilters'] ?? [] as $field) {
            if (isset($filters[$field]) && $filters[$field] !== '') {
                $query->where($field, (string) $filters[$field]);
            }
        }

        // 真值过滤（int 强转，值非空时生效）
        foreach ($options['truthyFilters'] ?? [] as $field) {
            if (!empty($filters[$field])) {
                $query->where($field, (int) $filters[$field]);
            }
        }

        // 字符串真值过滤（不转 int，值非空时生效）
        foreach ($options['stringTruthyFilters'] ?? [] as $field) {
            if (!empty($filters[$field])) {
                $query->where($field, (string) $filters[$field]);
            }
        }
    }

    /**
     * 仅保留模型 $fillable 中的字段
     * 语义与 BaseController::fillModelFromRequest() 一致：
     *  - 模型声明了 $fillable 时，只取 $fillable 内的键（且忽略 null 值）；
     *  - 模型未声明 $fillable 时返回空数组（原控制器经 Request::only([])
     *    后同样不会持久化任何请求字段）。
     *
     * @param array<string, mixed> $data 原始请求数据
     * @return array<string, mixed>
     */
    protected function fillableOnly(Model $model, array $data): array
    {
        $fillable = $model->getFillable();
        if ($fillable === []) {
            return [];
        }
        $result = [];
        foreach ($fillable as $key) {
            if (isset($data[$key])) {
                $result[$key] = $data[$key];
            }
        }

        return $result;
    }

    /**
     * 生成 snowflake 主键
     */
    protected function generateId(): int
    {
        return SnowflakeService::generate();
    }

    /**
     * 应用排序
     *
     * @param Builder $query 查询构建器
     * @param array<string, mixed> $options 查询选项
     */
    protected function applyListOrder(Builder $query, array $options): void
    {
        $orderBy = $options['orderBy'] ?? 'id';
        if (is_array($orderBy)) {
            foreach ($orderBy as [$field, $dir]) {
                $query->orderBy($field, $dir);
            }

            return;
        }
        $query->orderBy($orderBy, $options['orderDir'] ?? 'desc');
    }
}
