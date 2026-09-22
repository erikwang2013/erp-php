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

        // 申请人姓名经 leftJoin 带出：apply_user_id 落在 erp_admin_user（store 缺省取当前登录管理员），
        // 只下发 hashid 的话列表/详情只能显示一串雪花编码，代理不了「谁提的单」。
        // 审批人姓名同法：approved_by 存的是管理员雪花ID（update 的批准/驳回分支写中间件注入的
        // adminId），键名必须叫 approved_name —— 前端取名的规则是「键名切掉末 3 字符 + _name」
        // （approved_by → approved_name，见 check-fe-enum-text.mjs:531），写成 approved_by_name 无人消费。
        // 未审批的行 approved_by=0（列 NOT NULL DEFAULT 0），leftJoin 落空 → approved_name 为 null，
        // 前端按空值显示「-」，与「没审批过」同义。
        // 注意 admin_user 与 purchase_apply 都有 status/created_at/deleted_at，join 后这些列必须带表名前缀
        $query = PurchaseApply::query()
            ->leftJoin('admin_user', 'admin_user.id', '=', 'purchase_apply.apply_user_id')
            ->leftJoin('admin_user as approver', 'approver.id', '=', 'purchase_apply.approved_by')
            ->select('purchase_apply.*', 'admin_user.real_name as apply_user_name', 'approver.real_name as approved_name');
        if ($keyword) {
            // 表无 name 列（erp_purchase_apply 仅有 code/apply_user_id 等，见 install.sql），仅按申请单号搜索
            $query->where('purchase_apply.code', 'like', "%{$keyword}%");
        }
        if ($status !== null && $status !== '') {
            $query->where('purchase_apply.status', (int) $status);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('purchase_apply.id', 'desc')
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
        // 与 index 同一 join：apply_user_id 是 erp_admin_user 的外键，出参补申请人姓名（口径见 index）
        $item = PurchaseApply::query()
            ->leftJoin('admin_user', 'admin_user.id', '=', 'purchase_apply.apply_user_id')
            ->where('purchase_apply.id', $id)
            ->select('purchase_apply.*', 'admin_user.real_name as apply_user_name')
            ->first();
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
        // 审批轨迹：两端「批准/驳回」行内按钮只下发 {status:1|2}（前端 trade.ts），不改这里的话
        // approved_by 恒 0、approved_at 恒 NULL —— 审批人不可考。驳回也是审批动作，两条路径都落。
        // 两列刻意不在 $fillable（防客户端伪造审批人，见模型注释），fill() 会静默丢弃，故 forceFill
        // 显式绕白名单；审批人取中间件注入的 adminId，同 oms RmaService::approve 的取法。
        // 只在状态**真的变化**时写（0→1、0→2、1→2、2→1；1→1 / 2→2 不写）——不是遗漏：本接口是通用
        // PUT，同一个 status 会被再下发一遍的真实路径有两条：① Web 对已通过的记录再点一次「通过」；
        // ② E2E 的「PUT 全字段原值回写」探针（tests/E2E/api-coverage.php:430）。不比较原状态的话，
        // 这两次都会把 approved_by 改成当前编辑者、approved_at 刷成此刻，「最后一次审批决策」被改写。
        $newStatus = (int) $item->status;
        // 原状态只能取 getOriginal：上面 fillModelFromRequest 已把 $item->status 改写成新值，读属性就晚了
        $oldStatus = (int) $item->getOriginal('status');
        if ($newStatus !== $oldStatus && ($newStatus === 1 || $newStatus === 2)) {
            $item->forceFill([
                'approved_by' => (int) ($request->adminId ?? 0),
                'approved_at' => date('Y-m-d H:i:s'),
            ]);
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
