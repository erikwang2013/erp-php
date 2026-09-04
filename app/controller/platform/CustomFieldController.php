<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\controller\platform;

use app\admin\controller\BaseController;
use app\service\platform\CustomFieldService;
use InvalidArgumentException;
use support\Container;
use support\Request;
use support\Response;

/**
 * 表单自定义字段（P2-B7）：先导实体 sales_order/purchase_order/customer/supplier。
 * 定义 CRUD + 动态 schema（applySchema 供前端渲染）+ 值校验（validate 由单据
 * 服务在保存 custom_fields 前调用）。
 */
class CustomFieldController extends BaseController
{
    /**
     * 字段定义列表
     * @Apidoc\Title("自定义字段定义列表")
     * @Apidoc\Desc("按实体类型与启用状态查询字段定义")
     * @Apidoc\Url("/admin/platform/custom-field")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("低代码")
     * @Apidoc\Param(name="entity_type", type="string", desc="实体类型，空=全部")
     * @Apidoc\Param(name="status", type="int", desc="1=仅启用")
     */
    public function list(Request $request): Response
    {
        $result = $this->customField()->list(
            $request->input('entity_type') !== null ? (string) $request->input('entity_type') : null,
            $request->input('status') !== null ? (int) $request->input('status') : null
        );
        if (isset($result[1])) {
            return $this->fail((string) $result[1], 422);
        }

        return $this->success($this->encodeIds($result[0] ?? []));
    }

    /**
     * 新建字段定义
     * @Apidoc\Title("新建自定义字段定义")
     * @Apidoc\Desc("entity_type/field_key 白名单；同实体同 key 唯一")
     * @Apidoc\Url("/admin/platform/custom-field")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("低代码")
     */
    public function create(Request $request): Response
    {
        try {
            [$data, $err] = $this->customField()->create($request->all());
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }
        if ($err !== null) {
            return $this->fail((string) $err, 422);
        }

        return $this->success($data, '创建成功');
    }

    /**
     * 更新字段定义
     * @Apidoc\Title("更新自定义字段定义")
     * @Apidoc\Url("/admin/platform/custom-field/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("低代码")
     */
    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        try {
            [$data, $err] = $this->customField()->update($id, $request->all());
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }
        if ($err !== null) {
            return $this->fail((string) $err, 422);
        }

        return $this->success($data, '更新成功');
    }

    /**
     * 删除字段定义
     * @Apidoc\Title("删除自定义字段定义")
     * @Apidoc\Url("/admin/platform/custom-field/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("低代码")
     */
    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        [$data, $err] = $this->customField()->delete($id);
        if ($err !== null) {
            return $this->fail((string) $err, 422);
        }

        return $this->success($data, '删除成功');
    }

    /**
     * 实体自定义字段校验
     * @Apidoc\Title("校验实体自定义字段值")
     * @Apidoc\Desc("单据保存前调用：按启用定义校验并返回归一化值；未知 key 宽容忽略")
     * @Apidoc\Url("/admin/platform/custom-field/validate")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("低代码")
     */
    public function validate(Request $request): Response
    {
        $entityType = (string) $request->input('entity_type', '');
        $values = $request->input('custom_fields', []);
        if ($entityType === '' || !is_array($values)) {
            return $this->fail('entity_type 与 custom_fields 必填', 422);
        }

        $errors = $this->customField()->validate($entityType, $values);
        if ($errors !== []) {
            return $this->fail(implode('; ', $errors), 422);
        }

        return $this->success([], '校验通过');
    }

    /**
     * 动态表单 schema
     * @Apidoc\Title("实体自定义字段动态表单")
     * @Apidoc\Desc("返回实体全部启用定义 + 既有值合并，供前端渲染")
     * @Apidoc\Url("/admin/platform/custom-field/schema")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("低代码")
     */
    public function schema(Request $request): Response
    {
        $entityType = (string) $request->input('entity_type', '');
        $values = $request->input('custom_fields', []);
        if ($entityType === '' || !is_array($values)) {
            return $this->fail('entity_type 与 custom_fields 必填', 422);
        }

        [$normalized, $errors] = $this->customField()->applySchema($entityType, $values);
        if ($errors !== []) {
            return $this->fail(implode('; ', $errors), 422);
        }

        return $this->success($normalized);
    }

    private function customField(): CustomFieldService
    {
        return Container::get(CustomFieldService::class);
    }
}
