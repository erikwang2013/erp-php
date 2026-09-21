<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\purchase;

use app\admin\controller\BaseController;
use app\model\PurchaseApply;
use support\Request;
use support\Response;

#[\erikwang2013\apidoc\annotation\Title('采购申请')]
#[\erikwang2013\apidoc\annotation\Group('采购管理')]

class ApplyController extends BaseController
{
    /**
     * 采购申请列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('采购申请列表')]
    #[\erikwang2013\apidoc\annotation\Desc('获取采购申请列表，支持分页、关键词搜索和状态筛选')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/purchase/apply')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('采购管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', default:1, desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', default:15, desc:'每页条数')]
    #[\erikwang2013\apidoc\annotation\Param(name:'keyword', type:'string', default:'', desc:'搜索关键词（申请单号）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', default:'', desc:'状态筛选')]
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
        [$page, $limit] = $this->pageParams($request);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');

        $query = PurchaseApply::query();
        if ($keyword) {
            // 表无 name 列（erp_purchase_apply 仅有 code/apply_user_id 等，见 install.sql），仅按申请单号搜索
            $query->where('code', 'like', "%{$keyword}%");
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray(), ['id', 'apply_user_id']));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建采购申请
     */
    #[\erikwang2013\apidoc\annotation\Title('创建采购申请')]
    #[\erikwang2013\apidoc\annotation\Desc('新增一个采购申请记录')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/purchase/apply')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('采购管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', default:'', desc:'申请单号，留空后端自生成')]
    #[\erikwang2013\apidoc\annotation\Param(name:'apply_user_id', type:'string', default:'', desc:'申请人ID（hashid 或数字），留空默认当前登录管理员')]
    #[\erikwang2013\apidoc\annotation\Param(name:'department', type:'string', default:'', desc:'申请部门')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', default:0, desc:'状态: 0=待审批 1=已批准 2=已驳回 3=已转订单')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'采购申请记录')]

    public function store(Request $request): Response
    {
        // 校验真实表列（原 name 必填校验指向不存在的列，随 fill 落入 INSERT 必 SQL 错）
        $validator = validator($request->all(), [
            'code' => 'nullable|string|max:50',
            // 申请人可缺省（缺省=当前登录管理员），且须兼容 hashid 串：
            // 列表/详情回显的 apply_user_id 就是 hashid，下拉回填后原样提交会被 integer 规则打回
            'apply_user_id' => 'nullable',
            // department/remark 上限对齐建表列宽（varchar 50 / 500）：不设上限时超长输入
            // 直落列报 1406，最终以 500「服务器内部错误」返回
            'department' => 'string|max:50',
            'remark' => 'nullable|string|max:500',
            // status 落 TINYINT UNSIGNED（0待审批/1已批准/2已驳回/3已转订单）：
            // 裸 integer 放行 -1/999 会以 1264 Out of range → 500
            'status' => 'integer|between:0,3',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        // 申请人缺省 = 当前登录管理员（中间件 AdminAuth 注入 adminId）：采购申请本就是
        // 「我提交的单子」，要求操作员手填一个雪花 ID 既不符合实际操作也无从获知
        $rawApplyUser = $request->input('apply_user_id');
        $applyUserId = ($rawApplyUser === null || $rawApplyUser === '')
            ? (int) ($request->adminId ?? 0)
            : $this->decodeFlexibleId($rawApplyUser);
        if ($applyUserId === null || $applyUserId < 1) {
            return $this->fail($this->trans('Invalid apply_user_id'), 422);
        }

        $item = new PurchaseApply();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        $item->fill(['code' => doc_code($request->input('code'), 'PA')]); // 单号缺省后端自生成
        // apply_user_id 在 $fillable 内，fill 会把 hashid 串直填 BIGINT 列（严格模式 1366），
        // 故解码结果须在 fill 之后覆写（走 fill 而非直写属性：模型无 @property，
        // 直写 $item->apply_user_id 会给 PHPStan 新增 property.notFound）
        $item->fill(['apply_user_id' => $applyUserId]);
        $item->save();

        // 与列表同一 FK 名单：apply_user_id 漏编码会把 4.1e17 的雪花 ID 原样下发，
        // 前端按数字回传即丢精度（>2^53），回写时 hashid 解码失败 → 422
        return $this->success($this->encodeIds($item->toArray(), ['id', 'apply_user_id']), $this->trans('Created successfully'));
    }

    /**
     * 采购申请详情
     */
    #[\erikwang2013\apidoc\annotation\Title('采购申请详情')]
    #[\erikwang2013\apidoc\annotation\Desc('根据ID获取采购申请详细信息')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('采购管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'采购申请hashid')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'采购申请详情')]

    public function show(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = PurchaseApply::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        return $this->success($this->encodeIds($item->toArray(), ['id', 'apply_user_id']));
    }

    /**
     * 更新采购申请
     */
    #[\erikwang2013\apidoc\annotation\Title('更新采购申请')]
    #[\erikwang2013\apidoc\annotation\Desc('根据ID更新采购申请信息')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('采购管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'采购申请hashid')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', default:'', desc:'申请单号')]
    #[\erikwang2013\apidoc\annotation\Param(name:'department', type:'string', default:'', desc:'申请部门')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', default:'', desc:'状态')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'更新后的采购申请记录')]

    public function update(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
            'code' => 'string|max:50',
            'department' => 'string|max:50',
            'remark' => 'nullable|string|max:500',
            // status 落 TINYINT UNSIGNED（0待审批/1已批准/2已驳回/3已转订单）：
            // 裸 integer 放行 -1/999 会以 1264 Out of range → 500
            'status' => 'integer|between:0,3',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = PurchaseApply::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        $this->fillModelFromRequest($item, $request);
        // apply_user_id 出参已 encode（列表 :65 / 建单 :105），回写须解码，防 hashid 串入 BIGINT 列
        $rawApplyUser = $request->input('apply_user_id');
        if ($rawApplyUser !== null && $rawApplyUser !== '') {
            $applyUserId = $this->decodeFlexibleId($rawApplyUser);
            if ($applyUserId === null || $applyUserId < 1) {
                return $this->fail($this->trans('Invalid apply_user_id'), 422);
            }
            // 同 store：走 fill 而非直写属性（模型无 @property，直写会新增 PHPStan property.notFound）
            $item->fill(['apply_user_id' => $applyUserId]);
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray(), ['id', 'apply_user_id']), $this->trans('Updated successfully'));
    }

    /**
     * 删除采购申请（软删除）
     */
    #[\erikwang2013\apidoc\annotation\Title('删除采购申请')]
    #[\erikwang2013\apidoc\annotation\Desc('根据ID软删除采购申请，需管理员密码二次确认')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('采购管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'采购申请hashid')]
    #[\erikwang2013\apidoc\annotation\Param(name:'password', type:'string', default:'', desc:'管理员密码（二次确认）')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'array', desc:'空数组')]

    public function destroy(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = PurchaseApply::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $item->delete();

        return $this->success([], $this->trans('Deleted successfully'));
    }
}
