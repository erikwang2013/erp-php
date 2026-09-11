<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\crm;

use app\admin\controller\BaseController;
use app\model\CrmFunnelStage;
use app\model\CrmOpportunity;
use app\model\Customer;
use app\service\crm\CrmService;
use support\Container;
use support\Request;
use support\Response;

#[\erikwang2013\apidoc\annotation\Title('商机')]
#[\erikwang2013\apidoc\annotation\Group('CRM')]

class OpportunityController extends BaseController
{
    /**
     * 商机列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('商机列表')]
    #[\erikwang2013\apidoc\annotation\Desc('分页查询商机记录')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/crm/opportunity')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('CRM')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', desc:'每页条数')]
    #[\erikwang2013\apidoc\annotation\Param(name:'keyword', type:'string', desc:'关键词')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', desc:'状态')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function index(Request $request): Response
    {
        $validator = validator($request->all(), [
            'page' => 'integer',
            'limit' => 'integer',
            'keyword' => 'string',
            'status' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');

        $result = $this->crm()->list(CrmOpportunity::class, [
            'keyword' => $keyword,
            'status' => $status,
        ], $page, $limit, [
            // 表无 code 列（erp_crm_opportunity：customer_id/stage_id/name/estimated_amount 等）
            'searchFields' => ['name'],
            'eqFilters' => ['status'],
        ]);
        // FK 编码为 hashid（与客户/漏斗下拉选项同源，供编辑弹窗回填）+ 引用名展示
        $list = array_map(fn ($item) => $this->encodeIds($item, ['id', 'customer_id', 'stage_id']), $result['list']);
        $list = $this->enrichNames($list);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    /**
     * 列表行补引用名：客户名称/阶段名称（表无名称类列，仅 FK 展示用）
     */
    private function enrichNames(array $list): array
    {
        $customerIds = array_values(array_unique(array_map(static fn ($r) => (int) ($r['customer_id'] ?? 0), $list)));
        $customerNames = Customer::whereIn('id', $customerIds)->pluck('name', 'id');
        $stageIds = array_values(array_unique(array_map(static fn ($r) => (int) ($r['stage_id'] ?? 0), $list)));
        $stageNames = CrmFunnelStage::whereIn('id', $stageIds)->pluck('name', 'id');

        return array_map(function ($row) use ($customerNames, $stageNames) {
            $row['customer_name'] = (string) ($customerNames[(int) ($row['customer_id'] ?? 0)] ?? '');
            $row['stage_name'] = (string) ($stageNames[(int) ($row['stage_id'] ?? 0)] ?? '');

            return $row;
        }, $list);
    }

    /**
     * 创建商机
     */
    #[\erikwang2013\apidoc\annotation\Title('创建商机')]
    #[\erikwang2013\apidoc\annotation\Desc('新增商机记录')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/crm/opportunity')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('CRM')]
    #[\erikwang2013\apidoc\annotation\Param(name:'name', type:'string', desc:'商机名称，必填')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function store(Request $request): Response
    {
        // 校验真实表列（表无 code/amount/stage 列，页面幻键经 $fillable 静默过滤）
        $data = $request->all();
        $validator = validator($data, ['name' => 'required|string|max:200']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        // customer_id/stage_id 均 NOT NULL 无默认：hashid/原生数字双模解码，垃圾串 422 拒绝
        foreach (['customer_id' => '客户ID', 'stage_id' => '漏斗阶段ID'] as $field => $label) {
            $decoded = $this->decodeFlexibleId((string) ($data[$field] ?? ''));
            if ($decoded === null || $decoded < 1) {
                return $this->fail($label . '无效', 422);
            }
            $data[$field] = $decoded;
        }
        // 可空/可缺省列：空串按缺省处理（避免 '' 直插 DATE/DECIMAL 触发严格模式 500）
        foreach (['estimated_amount', 'probability', 'expected_close_date', 'remark', 'owner_user_id'] as $field) {
            if (isset($data[$field]) && $data[$field] === '') {
                unset($data[$field]);
            }
        }

        $item = $this->crm()->create(CrmOpportunity::class, $data);

        return $this->success($this->encodeIds($item->toArray(), ['id', 'customer_id', 'stage_id']), '创建成功');
    }

    /**
     * 商机详情
     */
    #[\erikwang2013\apidoc\annotation\Title('商机详情')]
    #[\erikwang2013\apidoc\annotation\Desc('查看商机详细信息')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('CRM')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'商机ID')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function show(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = $this->crm()->find(CrmOpportunity::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray(), ['id', 'customer_id', 'stage_id']));
    }

    /**
     * 更新商机
     */
    #[\erikwang2013\apidoc\annotation\Title('更新商机')]
    #[\erikwang2013\apidoc\annotation\Desc('修改商机信息')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('CRM')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'商机ID')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function update(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = $this->crm()->find(CrmOpportunity::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $data = $request->all();
        foreach (['customer_id' => '客户ID', 'stage_id' => '漏斗阶段ID'] as $field => $label) {
            if (isset($data[$field]) && $data[$field] !== '') {
                $decoded = $this->decodeFlexibleId((string) $data[$field]);
                if ($decoded === null || $decoded < 1) {
                    return $this->fail($label . '无效', 422);
                }
                $data[$field] = $decoded;
            } else {
                unset($data[$field]);
            }
        }
        foreach (['estimated_amount', 'probability', 'expected_close_date', 'remark', 'owner_user_id'] as $field) {
            if (isset($data[$field]) && $data[$field] === '') {
                unset($data[$field]);
            }
        }

        $item = $this->crm()->update(CrmOpportunity::class, $id, $data);

        return $this->success($this->encodeIds($item->toArray(), ['id', 'customer_id', 'stage_id']), '更新成功');
    }

    /**
     * 删除商机
     */
    #[\erikwang2013\apidoc\annotation\Title('删除商机')]
    #[\erikwang2013\apidoc\annotation\Desc('删除商机记录，需密码确认')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('CRM')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'商机ID')]
    #[\erikwang2013\apidoc\annotation\Param(name:'password', type:'string', desc:'管理员密码')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function destroy(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = $this->crm()->find(CrmOpportunity::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $this->crm()->delete(CrmOpportunity::class, $id);

        return $this->success([], '删除成功');
    }

    /**
     * CRM 薄服务层实例（Container::get 走 class_exists 回退，见 config/dependence.php 注释）
     */
    private function crm(): CrmService
    {
        return Container::get(CrmService::class);
    }
}
