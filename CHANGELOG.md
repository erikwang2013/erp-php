# 更新日志

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## v1.19.1 (2026-09-22)

**收尾批**：把上一轮审计遗留的「用户报得出、代码查不到」的毛病修完——明细（`items`）在编辑态写不回去、列表脱敏值被回写覆盖真值、`mfg` 领料/委外发料 update 半写（表头已改、明细校验失败却回 422）、发货/收货编辑态字段静默丢失；同时收口错误面（唯一键冲突/超长输入/非法 hashid 由 500 改 422）、全仓分页参数归一，并把四端「用户分配角色、角色多选权限、权限树形展示」这条链路接到可用。范围只含缺陷修复与既有能力的接线：**0 个新增控制器、0 个新增路由、0 个新增数据表**（仅 +1 列 +16 条权限种子），未修项见文末。

### 修复 · 错误面与状态码（客户端可见契约变化）
- **唯一键冲突（1062）由 500 改 422 并点名重复单号** —— `app/exception/ApiHandler.php:42`（必须排在 debug 分支之前：1062 的 `getCode()` 也是 500，放后面会被吞掉）
- **输入超长（SQLSTATE 22001 / MySQL 1406）由 500 改 422**，含表名/列名的 MySQL 原文只进日志（部分控制器 `max:` 比真实列宽大，超长输入过得了校验、到 MySQL 才炸）
- **hashid 解码失败等 `InvalidArgumentException` 由 500 改 422**，消息原样回客户端（旧书签、被截断的串、扫描器批量探路径不再只看到「服务器内部错误」）
- **收货/发货：业务拒绝 422、SQL/连接故障仍 500**（显式排除 `PDOException`，原先一律 500）
- **mfg 领料/委外发料 update 不再半写**：明细校验全部前移到任何写库动作之前，表头与明细同一事务 —— 原先表头先落库、明细校验失败回 422，用户以为没保存而实际已改（`MaterialIssueController.php:245`、`SubcontractIssueController.php:247`，各配一条回归用例）

### 修复 · 入参 / 出参形状（★契约变化）
- **`/admin/v1/user` 新增 `role_ids`**（hashid 数组；**缺省=关联不动，`[]`=清空**），store/update 都在触库前归一，非法即 422；**用户列表/详情新增 `roles`（hashid 数组）**，依赖 `with('roles')`（注释已标「勿删」）
- **用户 update 丢弃脱敏值**：phone/email 含 `***` 一律当「未改动」（列表下发 `138****8888`，客户端拿列表行回存会覆盖真值且不可恢复）
- **采购申请 `apply_user_id` 缺省=当前登录管理员**，入参兼容 hashid、出参补编码；**采购订单 `ordered_at=''` 归一 NULL**（否则 1292 → 500）、FK 出参补 `apply_id`/`warehouse_id`
- **收货/发货 `items[].order_item_id` 由 `required` 改 `nullable`**：缺省时按 `product_id` 在本单反查，本单该商品非唯一才 422；**明细 `product_id` 缺失从「静默落 0」改「拒绝」**
- **RFQ 报价列表新增 `supplier_name` / `rfq_no`**（`RfqQuoteController.php:54`）
- **分页参数全仓归一**：`page≥1`、`1≤limit≤500`（`BaseController::pageParams:171` + ~120 处 `list()` 改用）——负 `limit` 曾被编译成 `LIMIT -5` 直接 500，`?limit=100000` 曾无上限拉取

### 修复 · 后端逻辑
- **`decodeFlexibleId` 加往返校验**（`encode(decode(x))===x` 才采信）：纯数字串曾被静默解成 `PHP_INT_MAX` 写进无 FK 约束的列（`BaseController.php:92`）
- **`validator()` 入口把 JSON float 规范成十进制串**：brick/math ≥0.14 对 float 发 `E_DEPRECATED`，webman 升级成 `ErrorException` → `quantity=1.5` / `price=12.34` 必 500（`app/functions.php:33`）
- **供应商评估 `dimensions` 加 `array` 强转**：缺则把 `"Array"` 写进 json 列报 3140，建评分接口传什么入参都 500
- **采购申请模型 `$fillable` 去掉 `approved_at`/`approved_by`**（客户端可自造「已审批」记录）；**采购退货 destroy 加「已出库不可删」守卫 + 启用 SoftDeletes**，`status` 仅 0→1
- **寻源链路（RFQ/报价）整条修通**：`DB` 门面改 Capsule（原「A facade root has not been set.」必失败）、加 `lockForUpdate` + 状态守卫、仅草稿可改、报价行支持按 `product_id` 匹配、单价/行金额/中标总额上限守卫
- **WMS ASN `store` 补 `warehouse_id`/`supplier_id` required**（NOT NULL 无默认列 → 1364 500）；**列宽校验收紧到真实列宽**（brand 200→100、category/contact/funnel/hr-candidate 200→50、warehouse/location 200→100、shipment code 200→50）
- **`ChannelService` 重发 `limit` 封顶 500**（`?limit=100000` 单请求串行发完积压）；**采购结算 `receipt_payment_id` 先解码并拒绝**（原垃圾串在 int 形参上抛 TypeError，被 catch 成业务文案回给用户）
- **角色权限归一函数上提到 `BaseController::normalizeIdArray`**（角色/用户共用同一套判定顺序）

### 修复 · 前端（Angular / React，同构改动）
- **编辑弹框先拉详情再挂载**：列表 phone/email 是脱敏值，直接拿列表行保存会把打码串写回真值且不可恢复；详情失败（无路由/无权限/网络错）静默回落列表行，由后端 `***` 护栏兜底 —— `mergeEditRow()` 抽成纯函数（`apps/*/lib|pages/edit-row.ts`），两端逐字节同语义
- **新增序号守卫**：连点两行编辑时迟到的详情响应作废，避免「显示的值」与「提交用的 id」来自两条记录（`apps/react/src/lib/seq.ts`、Angular `editSeq`）
- **表单初值优先级反转**：编辑态**行值优先**于 `defaultValue`（原顺序让 `status`/`type`/`sort` 类字段永远改不动）
- **编辑态 `items` 免必填 + 编辑前摘除 `items`**：明细写不回去（更新接口要么不处理 `items`、改了不生效却提示成功，要么按 `(int) $row['sku_id']` 查 SKU → 哈希串落 0 → 422 整单存不了）
- **发货/收货的 `order_id`/`supplier_id`/`warehouse_id`/`items` 标 `createOnly`**（更新接口只写 `remark`，原先四个字段静默丢失）
- 采购申请去掉「申请人ID」、采购订单去掉「编号」录入（后端生成 / 缺省当前管理员）；**询价单页补 fields + 「发布」动作**（原先无入口，新单永停草稿，整条寻源在界面上走不通）、供应商报价页补 `supplier_name`/`rfq_no` 列
- 新增 `type:'tree'` 字段类型（角色权限复选、权限「父级」单选）+ React 列级缩进（`DataTable` + `app.css` 的 `.tree-box`/`.tree-node`）

### 修复 · 前端（Flutter / HarmonyOS）
- **Flutter 采购申请/订单/退货不再前端自造单号**（原 `PA`/`PO`/`PRN` + 秒级时间戳，同秒两次提交撞 `uk_code`），`_p2()` 辅助函数一并删除
- **Flutter 采购退货新增「出库确认」**（二次确认 → `PUT status=1`；`status=1` 不展示按钮）；用户弹框补角色多选（复用 `PermissionTreePicker` 渲染扁平角色清单）、编辑先拉详情取明文，`roles` 未下发则不提交 `role_ids`；补 BI「数据集」、EAM「备件管理」菜单
- **HarmonyOS 采购订单**：删 `code` 录入（8 字段→7）、`apply_id`/`warehouse_id` 撤 `InputType.Number`（hashid 可含字母）、金额改 `NUMBER_DECIMAL`、状态改 0..4 码表；详情页 hashid 由 `num()` 改 `hint()`（走 `num()` 恒 NaN 显示 `-`）
- **HarmonyOS 模型**：`User.roles?`、`UserCreateForm/UserUpdateForm.role_ids?`、`PermissionNode.parent_id` 改 `string | number`（后端已改 hashid 下发）；工作台新增 7 组业务宫格（29 个入口）

### 新增
- 前端自检脚本 3 个（**均未接入 CI**）：`scripts/check-fe-items-strip.mjs`（明细摘除，两端夹具逐字节对比）、`check-fe-edit-seq.mjs`（序号守卫）、`check-fe-tree.mjs`（树形权限）—— 走 node `--experimental-strip-types` 直接 import 真 TS 模块（被 import 的模块只允许 `import type`）
- 测试：`tests/FieldContractRegressionTest.php`（+320：用户角色/权限、WMS 六表 `warehouse_id` 必填、采购越界输入、`dimensions` json 落库、FK hashid 出参、`order_item_id` 反查）、`tests/PurchaseModuleTest.php`（+588 真库：询价/报价/中标、超收、退货出库守卫，事务内自造行+回滚）、`tests/FakeRequest.php` 补 `__isset`（缺它 `$request->adminId ?? 0` 恒落默认值，测到的分支与线上不同）、`apps/flutter/test/routes/page_reachability_test.dart`（`_pageBuilders` 每键必须在 `getPages` 注册）
- `database/install.sql`：新增 **16 条权限种子**（采购结算/询价单/供应商报价/供应商评估 4 条菜单 + 12 条动作）+ `erp_purchase_return.deleted_at`
- Flutter l10n 新增 2 键（zh/en arb + 生成物同步）；HarmonyOS 两语种各补 12 键（键集一致、无删键）

### 配置
- `config/poster.php`：`background_dir` 由 `null` 改指 `public/img`（本机素材目录，见升级须知的内存警告）；`.gitignore` 增加 `public/img/`，该文件与该处的过时注释一并改写
- `resource/translations/*/common.php` 12 语种各 +2 文案（重复单号、已出库不可删）、`*/install.php` 各 +1（AES-256 密钥长度）
- `database/install-demo.sql`：采购 demo 的 `order_id`/`receive_id` 原指向全库不存在的行（收货按订单挑明细必空）→ 改指真实单据并补数量/金额
- `scripts/`：`check-endpoints.php` 按 HarmonyOS `ApiService` 还原 `/admin`→`/admin/v1`；`doc-stats.sh` 新增「展示值≠标注」校验
- `README.md`（+78/-43）与 `docs/` 31 个文件：事实校正（`/v1` 前缀、ApiVersion 中间件已删、L0–L12、227 表/159 控制器/224 模型/23 模块、Flutter 102 路由、HarmonyOS 41 页、Node ≥22.22.3）+ 统计标注自愈；`docs/i18n/` 252 个文件（12 语种 × 21）为**跟随 zh 源的镜像重建**

### 升级须知
- **已部署的库需补 16 条权限种子**（id `31000000000000165`–`168` 四条菜单、`169`/`170`/`172`–`180`/`198` 十二条动作），否则询价/报价/供应商评估/采购结算菜单对已有角色不可见；**`erp_purchase_return` 需补 `deleted_at` 列**（本轮启用了 SoftDeletes，缺列会让该表的删除与查询报 1054）
- **错误面变化**：唯一键冲突、输入超长、非法 hashid 现在是 **422 而不是 500**，收货/发货的业务拒绝也由 500 改 422（SQL/连接故障仍是 500）。外部客户端若按 500 分支处理错误需同步
- **`items[].order_item_id` 由必填放宽为可选**：缺省时后端按 `product_id` 在本单反查，本单该商品不唯一才 422
- **分页 `limit` 上限 500、`page` 最小 1**：`?limit=100000` 不再全量拉取
- **`/admin/v1/user` 的 `role_ids` 缺省=不动，`[]`=清空**（想清空角色必须显式传空数组）
- **`config/poster.php` 的 `background_dir` 现指向 `public/img`**：该目录是本机个人素材目录（已 gitignore，不入库）。**部署环境请自备已压到画布尺寸（300×200 上下）的小图**——目录里放 ~30MP 原图会让每次验证码请求吃掉几十 MB，128M 上限下直接 fatal（本机跑全量 phpunit 就挂在 `CaptchaTest` 上）
- 移动端本轮有表单字段变化（去掉前端自造单号、hashid 字段撤数字键盘），需重新构建安装包

### 待办 / 已知遗留（本轮未修）
- `docs/coverage-priority-2026-08-27.md` 列出的 15 项动作端点仍无前端入口（CRM 公海认领/释放、工单指派/解决、分析生成/指标、HR 请假审批、财务期末等；Flutter 页面存在但不调该端点）
- `app/` 内 `max:200` 类校验宽度不符仍有 **51 处**（本轮只清了 9 处已知必现的）
- 供应商评估 `dimensions`(JSON) 仍无评分维度编辑器（`trade.ts:289` 注释「暂不提供入口」）；询价/报价明细行同商品多行时仍要求调用方显式传 `rfq_item_id`
- 三个前端自检脚本未接入 CI；`docker*` / `.github/workflows/ci.yml` 本轮未改
- `ReportScheduleController` 仍只有模型+控制器、无任何执行者（补表单会让用户创建永不触发的调度）
- 多租户 `TenantScope` 仍故意未注册（`X-Tenant-Code` 可由客户端伪造，且缺 admin↔tenant 绑定表与鉴权）
- 本机「浏览器点击链路 / 真机」仍未实跑：两端 Web 前端无 DOM 测试框架，行为验证走 `scripts/check-*.mjs`（已跑）；Flutter/HarmonyOS 仅静态构建与用例

## v1.19.0 (2026-09-21)

**审计轮**：后端↔前端契约与「页面实际操作可达性」全量核对后的缺陷修复批次（154 个文件，其中 15 份为文档统计标注自愈）。重点是三类**必现故障**——创建即 500、明细行 ID 未解码（静默丢数据）、登录人机验证链（正确答案也判错）；另修掉一处 500 响应把原始异常（含库名与整条 SQL）回给客户端的错误处理死代码。范围只含缺陷，不含新功能开发，未修项见文末。

### 修复 · 创建即 500（校验规则与真实列不符）
- 真实 **`NOT NULL` 无默认列**无人提供 → MySQL 严格模式 1364：库位（`location_id`/`zone_id`）、库区（`warehouse_id`/`code`）、调拨（`from`/`to_warehouse_id`）、盘点（`code`/`warehouse_id`）、运输服务（`carrier_id`）、运费发票（`carrier_id`/`shipment_id`）、RMA（`customer_id`）——后端补 `required`，双端表单补必填项与下拉数据源
- **幻键静默丢弃**：`fillModelFromRequest` 实为 `fill($request->only($model->getFillable()))` ⇒ 前端提交的幻列（如供应商 `credit_limit`，`erp_supplier` 无此列）既不报错也不落库，用户白填；已按真实列删幻列（`erp_customer.credit_limit` 是真列，保留）
- **校验宽度大于真实列**：`code` 实列 `VARCHAR(50)` 而校验写 `max:200`，51–200 字符放过校验去撞 1406/500 —— 6 个 WMS `store` 改 `max:50`
- **唯一键重复裸 500**：`POST /oms/channel`（`code NOT NULL` + `uk_code`，而双端表单**没有该字段**）、`POST /tms/carrier`（`code` 可选，留空入库 `''`，第二条撞 1062）——后端 `code` 改 `required|string|max:30`（真实列宽），双端表单补必填
- **成本中心 / 利润中心列表必 500（内存耗尽）**：`index()` 先 `encodeIds` 再 `buildTree`，而 `buildTree` 用 `(int)$item['id']` 下钻、hashid 转 int 恒为 0 ⇒ 同一批顶层行无限自调用（实测 32M 限制必 OOM）；改为先建树后编码
- **前端在调、后端未注册的路由**：`/inventory/{id}`、`/inventory/flow/{id}`、`/finance/settlement`、`/finance/cash-journal/{id}` ——补注册（变量路由必须排在 `/inventory/<静态段>` 之后，否则遮蔽静态路由触发 FastRoute 异常）

### 修复 · 契约与交互不兼容
- **明细行 ID 未解码（静默丢数据）**：`receiving/{id}/complete`、`pick/{id}/confirm`、`wave/{id}/release` 的 `items[].product_id`/`sku_id`/`location_id` 由前端下拉下发 **hashid**，后端直接进 SQL ⇒ 作 WHERE 时数字比较恒不命中（UPDATE 影响 0 行**且不报错**）、作 INSERT 值在严格模式 1366 → 500。新增 `BaseController::decodeItemIds()`（批量双模解码，任一行任一字段非法即 422），三个端点接入
- **hashid 0 哨兵**：递归编码把 `*_id = 0`（318 个 `NOT NULL DEFAULT 0` 列的零值哨兵）编成真值串，破坏前端「falsy = 未选」契约 —— 哨兵保持原值
- **外键裸 ID 泄给客户端（写响应与部分列表）**：`encodeIds()` 旧默认名单只有 `id` 本身，`supplier_id`/`customer_id` 这类外键在下单/改单响应里直出雪花 ID —— 既与「所有 ID 经 hashids 加密传输」（`docs/FEATURE_DESIGN.md`）相悖，也让前端「行内 FK ↔ 下拉选项（hashid）」对不上（前端的下拉 `source` 拿到的都是 hashid）。改为默认递归编码任意层级的 `id` / `*_id`（显式名单仍可收窄），并加防二次编码；**客户端可见的契约变更**，写响应此后与 list/show 同形（详见升级须知）
- **422 消息把规则键甩给用户**：`resource/translations/zh_CN/validation.php` 只有扁平 `min`/`max`，而 illuminate 取 `validation.<规则>.<类型>`（**无扁平键回退**）⇒ 实测客户端看到的就是 `validation.max.string`。按 8 条带参规则 × 4 类型重写（影响 171 处 `max:` / 40 `min:` / 40 `size:` / 6 `between:` / 3 `gt:`）
- **状态字典张冠李戴**：Angular `crm.ts` 全文件只有一个 `STAGE` 字典，却被商机/合同/报价/工单四张 status 域**各不相同**的表共用（工单 `status=3` 真实语义是「已关闭」，页面显示「赢单」）→ 拆成 OPP/CONTRACT/QUOT/TICKET 四份（对齐 React）
- **表单字段键错**（必 422 或静默不落库）：`stage`→`stage_id`、`amount`→`estimated_amount`、`sign_date`→`signed_at`
- **永久空列**：质量 IQC 的 `supplier_name`/`submit_quantity`/`pass_quantity`、NCR 的 `product_name`/`amount`（真列分别是 `inspected_qty`/`passed_qty`/`defect_qty`）、供应商 `credit_limit`；Flutter 侧 WMS `receiving`/`putaway`/`pick`/`pack`/`wave` 五页与 `asn_list_page` 渲染 `r['name']`，而这 6 张表无 `name` 列（真列 `code`）→ 全部按真实列改写
- **登录失败的 401 被当成会话过期**：两端 API 层会「续期 + 重放」，密码错误既被伪装成「登录已过期」，又会累加后端失败计数（有锁号风险）→ 登录调用改 `noRetry`
- 字符串状态（发票 `draft`/`audited`、工单 `open`/…）无处渲染 → `statusText` 兼容字符串键

### 修复 · 安全与错误面
- **500 把原始异常回给客户端**：`ApiHandler` 判 debug 写的是 `method_exists($this, 'debug')`，而框架 `$debug` 是**属性**不是方法 ⇒ 恒为 false，「非 debug 回通用文案」整段成了死代码 —— 任何 500 都把原始异常（SQLSTATE、库名、整条 SQL）原样回给客户端。现按 `$this->debug` 判定：500 回「服务器内部错误，请稍后重试（TraceId: …）」，完整异常进日志并补一条带 TraceId 的摘要（500 事件不过 TracingId 中间件，响应拿不到 `X-Trace-Id` 头）；`debug=true` 行为不变
- **apidoc 文档站口令/密钥硬编码**（`'123456'` / `'apidoc#erik'`）→ 改为 `env('APIDOC_PASSWORD')` / `env('APIDOC_SECRET_KEY')` 并开启鉴权，两个 env 模板给 `CHANGE_ME_*` 占位
- **信任边界未校验写**：`BudgetController` 明细 `foreach ($it as $k => $v) $detail->$k = $v`（客户端任意键直写模型）→ 白名单化

### 修复 · 登录 / 人机验证链
- **放行凭证与挑战不同源（正确答案也 500）**：`CaptchaController::verify` 硬编码 `Redis::setex`，而挑战按 `captcha.storage` 存盘（本机 auto→文件、Redis 未运行）⇒ 校验通过也写不进凭证，消息还误导为「验证码校验失败」（用户会以为点歪反复重试）。改为双方共用 `captcha_pass_store()`；写失败 fail-closed 且如实报「验证码服务异常，请稍后重试」
- **rotate 方向反了（怎么摆正都判错）**：`GdDriver::rotate(+A)` 内部 `imagerotate(-A)`，实测把内容**顺时针**转 A°（4×4 标记：左上→右上）；四端前端旋钮读数顺时针递增并**直接提交读数**（摆正时读数 ≡ 360−A），服务端却按 A 比对 ⇒ 无解。修：`rotatePayload()` 取负（−(360−A) ≡ A mod 360）；`config/poster.php` 的 `image.driver` 由 `auto` **钉死 `gd`** —— 否则换台装了 imagick 的机器整套旋转几何换一套
- **安装器口令口径与登录不一致（装完即锁死）**：`InstallController::validateAdmin` 用 `strlen`（字节）且只卡下界，登录是 `min:6|max:32`（字符，`getSize()` 走 `mb_strlen`）⇒ ①3–5 个汉字的密码安装期放行、登录恒 422；②33+ 字符同理；③2 个汉字的用户名亦然。改 `mb_strlen` 双向 3–50 / 6–32，`step4.php` 补 `maxlength`，12 语言文案改「6-32」「3-50」

### 新增
- `app/queue/redis/WebhookTask.php` —— Webhook 异步投递任务（`WebhookService` 此前 `use` 的类**文件不存在**，入队必致命错误）；因消费进程 `consumer_dir` 非递归扫描，故必须直接落在 `app/queue/redis/`
- `app/service/workflow/ApprovalNotifier.php` —— 审批旁路副作用（站内通知 + Webhook 事件），失败只记日志、不影响主流程
- 配置驱动表单新构件（双端同源）：`items`（明细行编辑器：`itemFields` 子字段 + 增删行）、`bodyFields`（动作执行前弹表单收集参数）、`showResult`（把返回数据渲染进弹窗）；React 侧抽出 `components/FormFields.tsx`
- `database/install.sql` 补 19 条权限种子（WMS 作业闭环 + `mfg`/`hr`/`report` 三个此前无权限节点的模块顶级菜单）

### 配置
- `config/poster.php`：`image.driver` 由 `auto` 改 `gd`（rotate 几何随驱动变，四端读数只有一套）；`background_dir` 由不存在的 `assets/backgrounds` 改为 `null`（程序化背景 —— 上游对目录内图片**无尺寸守卫**，本机 `public/img` 里 3 张 ~30MP 照片单张解码峰值即 ~90MB，128M 上限下验证码请求直接 fatal）
- `composer.lock`：依赖补丁级更新（`doctrine/lexer` 3.0.1→3.0.2、`symfony/*` v7.4.18→v7.4.19、`phpunit/phpunit` 12.5.34→12.5.35 等）
- `resource/translations/*/install.php` 12 语种文案同步（密码/用户名口径）

### 升级须知
- **已部署的库需补 19 条权限种子**（`install.sql` 只覆盖全新安装），否则新端点对已有角色不可见；幂等 SQL 的 id 为 `31000000000000751`–`31000000000000769`
- **apidoc 文档站已开启鉴权**：须在 `.env` 设 `APIDOC_PASSWORD` / `APIDOC_SECRET_KEY`，留空则文档站拒绝访问（不影响应用启动）
- 新增的 `required` 校验会把此前「静默丢数据」变为明确 422 —— 第三方客户端若未带这些字段需同步
- **写接口响应中的外键 ID 改为 hashid**（原先直出雪花 ID，如 `POST/PUT /admin/v1/purchase/order` 的 `supplier_id`）：仓库内四端前端都不读写响应（提交后重载列表），故不受影响；若有外部集成按整数解析这类响应，需同步改为 hashid（`HashidsService::decode()` 可还原）
- `config/poster.php` 的 `background_dir` 留 `null`（程序化背景）。要换真实背景图请另建目录并**先把图压到验证码画布尺寸**（300×200 上下）——指向 `public/img` 这类原图目录会让每次验证码请求吃掉几十 MB 内存

### 待办 / 已知遗留（本轮未修）
- `max:200` 类宽度不符仍有 44 处；仓库内 30+ 处 `save()` 无唯一键冲突（1062）捕获 —— 建议加全局唯一键冲突处理器，而非逐处 `try/catch`
- 6 个 WMS `store` 的 `warehouse_id`（`NOT NULL` 无默认）未加 `required`：桌面端无可达创建入口、Flutter 表单必填，仅手工构造请求可触发（加固项）
- `WebhookService::dispatchAsync` 的队列往返（投递 + 退避重试）本机无 Redis，**未做端到端验证**
- 多租户 `TenantScope` 故意未注册（`X-Tenant-Code` 可由客户端伪造，且缺 admin↔tenant 绑定表与鉴权）
- `/purchase/rfq`、`/purchase/rfq-quote` 缺整页（主从录入，声明式表单表达不了）；`/report/schedule` 无任何执行者（补表单会让用户创建永不触发的调度）；供应商评估 `dimensions` 评分维度编辑器；`inventory/flow` 等 4 个无路由动作是否对外暴露需产品裁决
- 移动端（Flutter）仍接不了需 `items` 非空的三条 WMS 动作（无明细数据源，属新功能）

## v1.18.9 (2026-09-21)

资源页推断列表头按 `install.sql` 列注释补齐，两端各 698 键。

### 功能

- 新增生成物 `apps/{angular/src/app,react/src}/config/column-titles-extra/{index,part1,part2}.ts`（522 条，
  按 500 行上限切 2 片），数据源为 `database/install.sql` 的列注释；合并顺序保证人工档优先 —— Angular 是
  `{ ...COLUMN_TITLES_EXTRA, ...BASE }`，React 在 `TITLES` 里先铺 `...COLUMN_TITLES_EXTRA`，撞键人工档胜出
- 新增 `scripts/gen-column-titles.mjs`（读 `install.sql` 生成；`--check` 只比对不写，差异落 `/tmp/new_titles.txt`）
  与 `scripts/check-column-titles.mjs`（页面实际展示的列必须命中标题表；本地闸门，尚未接入 CI）

### 待办

- 522 条里约 423 条中文标题在 12 个非中文语种词典中还没有条目，这些表头在任何语种下都回退中文原文；
  修法是把 `/tmp/new_titles.txt` 并进源词典后 `node scripts/gen-fe-locales.mjs --app angular|react --all`

## v1.18.8 (2026-09-17)

启动端口全部收进 `.env`：后端监听、两端 dev server、docker 发布端口各归一处。

### 配置

- `.env` / `.env.example` 新增「启动端口」小节：`APP_HTTP_PORT` / `APP_WS_PORT`（后端监听）、`ANGULAR_DEV_PORT` /
  `REACT_DEV_PORT`（两端 `npm run dev`）、`NGINX_PORT` / `NGINX_SSL_PORT` / `MYSQL_PORT` / `ES_PORT`（docker 发布端口）
- **React**：`vite.config.ts` 改 `defineConfig(({ mode }) => ...)` + `loadEnv(mode, rootDir, '')`（空前缀读全部键），
  dev server 端口与 `/api` 等代理目标后端地址都由 `.env` 决定
- **Angular**：`proxy.conf.json` → `proxy.conf.js`（CJS，`process.loadEnvFile` 读根 `.env` 后拼代理目标；用 `.js`
  是因为 `@angular/build` 内部走 `require()`）；新增 `scripts/serve.mjs`，`npm run dev` 走它把 `ANGULAR_DEV_PORT`
  映射成 `PORT` 再拉起 ng —— 顺序上必须如此：`@angular/build` 的 `normalizeOptions` 在加载代理配置**之前**
  就用 `process.env.PORT` 定了端口，代理配置里再设 PORT 已经晚了
- `tests/EnvConfigTest.php` 新增 `startup_ports_declared_in_env_and_example`：8 个端口键在 `.env` 与
  `.env.example` 里都必须以 `键=数字` 存在；README「关键配置项」补 3 行，`docs/INSTALL.md` 端口句改指 `APP_HTTP_PORT`
- 已知边界：docker 编排的 Nginx 反代写死 `app:8788`（`docs/nginx-default.conf`），容器内改后端端口须同步改 upstream

## v1.18.7 (2026-09-16)

修复：平台自定义字段列表接口只要表里有数据就报错。

### 修复

- 根因是元组解包喂错了类型：`CustomFieldService::list()` 返回定义模型数组，控制器却按 create/update 那种
  `[data, err]` 元组解包 —— ≥2 行时 `isset($result[1])` 成立，把第 2 行的模型当错误消息返回 422；恰好 1 行时
  模型喂给 `encodeIds(array)` 触发 TypeError。即 `erp_custom_field_definition` 只要有一条定义该接口必挂，只有空表才正常
- 改为直接遍历模型、逐行 `toArray()` 后再编码 id

## v1.18.6 (2026-09-16)

修复资源页两个用户报告的缺陷（表头不随语种切换、外键 ID 原样外泄），根因都在「配置驱动资源页引擎」自身。

### 前端（两端同构，逐键逐值对齐）

- **表头**：`cols = cfg.columns ?? inferColumns(...)` 直接拿字段名当标题，而标题表只有 32 条、兜底是驼峰英文；
  词典又以中文为 key（查不到回原文），非中文标题于是在任何语种下都显示原文。标题表扩到 **176 键**，来源按优先级
  合并（① `config/domains` 全量 `{key,label}`，同 key 取众数 ② 通用档 ③ 补充档），
  `keyTitle` 解析顺序 = `fields.label` → 标题表 → 驼峰兜底
- **关联列**：`HIDDEN` 不覆盖 `*_id`，单元格 `String(v)` 直出；hashid 编码按控制器白名单逐处开启，604 处调用点
  只有约 32 处带外键 —— 雪花 ID 因此原样外泄。新增 `rel` 列，解析顺序：兄弟 `*_name` → 嵌套关系对象
  `name/title/label/code` → 选项源 → 原值（无名称可用时保留该列并渲染原值，不隐藏）；新增共享选项加载器
  （表单/表格共用，endpoint 缓存 + in-flight 去重 + `?limit=100`）；词典补 BOM / SKU ID / 删除时间 / 规格属性，
  22 个生成词典重出（各语种缺失 0）

### 后端

- 62 个控制器、66 处 `encodeIds` 白名单追加表格可见外键（共 134 个字段，已逐一对回 `install.sql` 真列）；
  另补 inventory/TraceController 服务层拼行的外键。4 处**故意不加**并写明理由：cost-center / profit-center 的
  `parent_id`（编码会触发既有 `buildTree` 死循环）、voucher `ledger_id`、project/task `parent_id`（前端契约即数字）
- 验证：双端自检 PASS（Angular 18 例 / React 9 checks）、两端构建通过、`php -l` 62/62、phpstan 0 错误、
  Unit 569 测试 0 失败、doc-stats 202 处标注一致

## v1.18.5 (2026-09-16)

一键安装向导 13 语种 + 演示数据勾选；安装写 `.env` 口令被模板残留拼接；SSRF 检测器降为 log；doc-stats 自愈。

### 功能与修复

- **一键安装向导 13 语种**（`acfbafb`）：新增 13 个语种的 `resource/translations/*/install.php` 词典，`I18n`
  注册 `install` 域，向导 step0~step5 文案全改走 `t()` 随请求语种切换；数据库步骤补「同时导入演示数据」勾选
  （默认不勾，生产保持不勾），`scripts/gen-be-locales.mjs` 支持 install 域
- **安装写 `.env` 的口令被 `.env.example` 残留值拼接**（`646c9f3`，表现为登录 1045）：`writeEnv()` 五条替换里只有
  口令用裸 `DB_PASSWORD=` 当搜索串，`=` 后面的模板值原封不动留着，于是「填的口令 + 模板残留」一起写进 `.env`
  （实测 20 位输入写成 40 位）。改为整行替换，并用 `preg_replace_callback` 保证字面量写入，避免口令里的
  `$1` / `\1` 被当反向引用吃掉；新增 `EnvConfigTest::installer_writes_db_password_verbatim` 反射驱动真实 `writeEnv`
- **SSRF 检测器降为 log**（`6c106f0`）：它对**所有字段**跑内网 URL 正则、无字段作用域，而 `_server.HTTP_ORIGIN`
  与 `headers.Referer` 在前后端同机时必然是 `http://localhost:4200`，命中后后台 POST 一律 403 并经累计封禁
  127.0.0.1 900 秒；降为 log 后仍进日志、不再计入封禁。`tests/SecurityFilterLoopbackTest.php` 固化契约
  （回环不拦 / log 仍可检出 / 不升级封禁 / block 模式阴性对照 / 真域名 Origin 不误判）。顺带 `.gitignore` 加 `.env.back`
- **doc-stats 标注自愈**：上一提交新增 `tests/SecurityFilterLoopbackTest.php` 后实测计数上移
  （test_files 110→111 / tests 972→978 / assertions 4626→4636），12 语种 FUNCTIONS.md + 根 `docs/FUNCTIONS.md` /
  `docs/CLAUDE.md` / `README.md` 共 20 处 `<!-- stats:key=value -->` 仍是旧值，CI docs 作业红、release 被 `needs`
  挡下未打 tag；用 `bash scripts/doc-stats.sh --fix` 就地改写，复验 202 处一致

## v1.18.4 (2026-09-15)

安全：升级 `erikwang2013/security-php` 到 v1.3.3，改用插件的冲突合并 API，删掉自研那份。

### 安全

- 上游 v1.3.3 是实质更新，修的正是 v1.18.3 报的那个洞：`SecurityGuard::mergeRequestSources()` 把被同名顶掉的旧值
  改挂到 `_<来源>.<键>`（如 `_cookie.evil`）继续进扫描面，值相同或键在白名单里时不重复拷贝；插件自家 Webman
  中间件中过同一个洞一并修了，顺带修掉 `file` 直接赋值覆盖（同款缺陷第三处）
- 合并职责既已归插件，本仓 v1.3.3 之前自研的 `SecurityFilter::merge()` 即删除 —— 同一件事不留两份互相打架的。
  调用位置留在 `boot()` 之后，注释写明理由：`mergeRequestSources()` 内部会 `getConfig()`，未初始化时兜底
  `init(插件默认配置)`，会把本仓这 135 键的配置静默换成默认值
- `composer.lock` 此前已是 v1.3.3 而 `vendor/` 仍停在 v1.3.2（锁定版与实装版脱节），本次 `composer install` 一并对齐
- **更正一处此前记错的功劳**：v1.3.3 修好了 `UploadDetector` 一个从未生效过的检测器（其注释原文
  `no upload was ever blocked there`），所以上轮夹逼里「multipart 恶意文件名 `.env` → 403」是本仓 `GAP_PATTERNS`
  的 `.env` 规则拦下的，不是插件的上传检测 —— 该检测器现已真正生效
- 回归：集成夹逼 19/19（v1.3.3 上重跑，遮蔽攻击仍 403）、负向对照确认测试有牙（临时退回 `array_merge` → 2 例红，
  还原 → 全绿）；`tests/SecurityFilterPayloadTest.php` 改为驱动本仓装配线（反射调 `payload()`），断言只看值在不在、
  不钉插件的 `_<来源>.<键>` 命名

## v1.18.3 (2026-09-15)

安全：修扫描面同名遮蔽绕过（cookie/get/post 同名值互相顶掉）。

### 安全

- 根因：`payload()` 用 `array_merge` 拼扫描面，后一来源覆盖前一来源的同名值，而扫描面是**按值**扫的 —— 攻击者
  只要在 query 里放一个与恶意 cookie 同名的无害值，恶意值就整个离开扫描面。集成实测（真实 `Request` 过中间件）：
  `Cookie: evil=<script>alert(1)</script>` → 403；同一请求再加 `?evil=1` → 200（对照与攻击各一例，两次测得）
- 读插件 v1.3.1 的修复时发现：那笔只修了身份维度，检测面没修而本仓照搬了同一套；插件自带的
  `Webman\SecurityMiddleware` 也是同一套 `array_merge`（cookies 单独传进 meta 修掉身份维度，`$data` 那份没动）。
  vendor 改不得，在适配层修
- 改法：`merge()` 冲突时把两个值并列成**数组**而不是改键名 —— `SecurityGuard::flattenData()` 会递归展开每个叶子
  （撞键另有 `uniqueKey()` 兜底），两个值都进扫描面；键名不变，以免影响 `whitelist_fields` 的按名匹配（本仓配置
  `_token` / `_method` / `csrf_token`）。用 `array_key_exists` 而非 `isset`，空串值同样参与冲突判定；`merge()`
  公开的理由同 `resolveIp()`：纯函数形态便于直接夹逼
- 新增 `tests/SecurityFilterPayloadTest.php` 8 例（同名 cookie+get / query+post / 三来源 / 无冲突原样 / 单来源数组
  不包装 / 上传结构保持 / 来源顺序 / 空值判定）；集成夹逼 19/19 未回归

## v1.18.2 (2026-09-15)

安全：接入 security-php 插件并消除来源 IP 可伪造；`dns_rebinding` 检测器降为 log（裸 IP / 内网访问不再整站 403）。

### 安全

- **接入 security-php 插件**（`fbe3374`）：`SecurityFilter` 重写为插件适配层，不注册 vendor 现成的
  `Webman\SecurityMiddleware`，两个本项目特有的原因 —— ① 它用 `$request->getRealIp()`，对端是内网时取
  `X-Forwarded-For` 的**最左**值，而 nginx 用追加式 `$proxy_add_x_forwarded_for` 写入，最左值由客户端自己塞，
  等于来源 IP 可任意伪造、会污染插件黑名单与登录地点基线，改为 `resolveIp()` 取最右一跳（控制器侧记录来源统一走
  `clientIp()`）；② storage 需长期持有 Redis 句柄，而 `support\Redis` 是协程连接池、连接会被 `Context::onDestroy`
  回收复用，改为按 `config/redis.php` 参数每进程自建独占连接
- 补齐插件未覆盖的载荷模式（grep `vendor/Detector` 确认无命中而旧实现是拦的）：`.env`/`.git`/`WEB-INF`/`proc/self`/
  `boot.ini` 路径、危险 DDL（`drop|alter|truncate`）、裸命令注入（`;rm|;ls|;cmd|;powershell`），复用
  `Guard::blockDecision()` 通道、状态码与文案仍取自插件配置；含 URL 归一化 —— `path()` 返回未解码原始路径，
  `/%2Egit/config` 否则逃得掉匹配。登录侧改用 `SecurityFilter::clientIp()` 并接入 `recordLogin`/`recordFailedLogin`；
  刻意不叠加 `SecurityGuard::isLockedOut()`（插件按 user_id 计数、本处 `account_lock` 按 username 计数，叠加等于同一策略数两遍）
- **`dns_rebinding` 降为 log**（`1e27053`）：上一提交后 CI 的 E2E 仍红，根因不是健康检查而是全站 —— smoke 以
  `--base-url=http://127.0.0.1:8788` 发请求，Host 全是裸 IP，被该检测器判 critical 且它是 block 模式。它只对 Host
  做字符串匹配、全程不做 DNS 解析，测不到真正的 rebinding（真实攻击送的是 `Host: evil.com`，九条规则一条都不命中），
  命中的恰好相反是客户端主动用 IP 访问 —— block 换不到真实防护，只换来可用性损失（内网 ERP `192.168.x.x` 整站 403）。
  连带删掉 `/health` 的放行例外（其唯一依据就是该检测器），并更正该处注释：原文写「精确匹配而非前缀」，实为前缀匹配
- **CI 暴露的三处一并修**（`5334829`）：① `SecurityFilter::files()` 调了不存在的 `UploadFile::getUploadTmpPath()`
  （`UploadFile` 继承 `SplFileInfo`，临时路径由父类持有，应取 `getPathname()`）—— 运行时真缺陷，`payload()` 在
  try 之外，任何真实文件上传都会抛未捕获 Error 变 500；此前没暴露是因为夹逼里那条「upload .env」实际发的是 JSON body，
  已补真实 multipart 用例；② `/health` 被检测器拦死（见上）；③ 新增测试文件使 doc-stats 漂移，按 `--fix` 对齐
- 验证：Unit 559 例 / 2739 断言全绿；集成夹逼 16/16 → 19/19（路径·查询串·body 三类载体，含 `.gitignore`、`drop-off`、
  伪造 XFF、真实 multipart、`/health`）；`composer validate --strict` 与 `composer audit --no-dev` 干净

## v1.18.1 (2026-09-15)

文档：核准 ARCHITECTURE §20 / FUNCTIONS §19 计数并按 `install.sql` 重生成 INSTALL.md 表清单；php 作业四笔测试债清零，发版门禁加回 php。

### 文档

- **ARCHITECTURE §20**：Controllers 列 16 行复核无误（脚注 23 目录 / 139 控制器亦证实）；表数列 8/16 与实际不符，
  合计 131 → **179** —— 按 `install.sql` 的 227 张表以表名前缀归属唯一模块实测，并在节内写入口径与重算命令
- **ARCHITECTURE §20.1**：「抽取后 = 0」经 `1051d83` 复放属实，但 HEAD 上直查已回流（CRM 6 / 制造 39 / 商品 2，
  人力仍 0）。数字一字未改（改了即篡改历史记录），段末加带日期的复测注 + 复测命令
- **FUNCTIONS §19**：合并重复的「多租户」两行（取 ⚠️ 版，依统计口径注「未达双 ✅ 即计骨架」并点名多租户 B5），
  模块行 45 → 44，统计表 / v1.17.0 / v1.4.0 批次注 / EDITIONS 现役指针逐格重算
- **INSTALL.md 表清单**：标题称 227 张、实际只列 163 张（分模块计数亦陈旧），按 §20 同口径重生成 26 行 / 227 张，
  双向集合差为零 —— 这正是 §20 表数走样的源头

### CI

- **php 作业长期红，根因是 CI 专属的集成测试债**（本地无 `TEST_DB_*` 时整套跳过，故本地全绿瞒得住 CI 红）。四笔：
  ① scout 同步打到 CI 不存在的 ES（`Prepare .env` 加 `SCOUT_DRIVER=null`，且用 `^SCOUT_DRIVER=.*` 通配替换 ——
  该键默认值改过名，字面量 sed 换名后会静默不匹配）；② F12 scaffold 守卫漏前缀致重复建列（改用
  `Capsule::schema()->hasColumn/hasIndex`，让 Laravel 施加连接前缀）；③ 并发子进程 Capsule 漏 prefix；④ B4 用例向
  共享表泄漏行（补整表清理，与 setUp 对称）
- 验证：本机 CI 等价跑（`erp_ci` + `install.sql`，同库连跑两轮不重置）971 测试 / 6027 断言 / exit 0，对照修复前
  基线 234E/54F；`release.needs` 加回 php，发版门禁重新生效

## v1.18.0 (2026-09-15)

文档全量对齐 + CI 发版链打通（顺带修掉 Release 自 v1.9.2 断档的真正根因）。

### 文档

- **README 项目功能与项目结构对齐现状**：`apps/` 补上 Angular 22（`apps/angular/`）与 React 19 + Vite
  （`apps/react/`）两端（此前只列 Flutter / HarmonyOS）；业务控制器 136→139（总 159）、模型 223→224、
  表 226→227、`install.sql` 表清单 163→227；域子计数订正（product 8 / purchase 8 / inventory 6），
  并补上漏列的 `open/` 域（23 域齐）
- **国际化口径统一为 13 语种**（README / CLAUDE / ARCHITECTURE / FUNCTIONS / API / EDITIONS /
  INSTALL / TEAM 八份）：后端词典 11 个语种各 542 条、`zh_CN` 533、`en` 30 —— **口径为「叶子条目」**，
  `validation.php` 的 `attributes` 是分组容器不计入（箭头口径会得 543/535/31，两者都是实测真值）；
  Angular 源词典 1453 键 / React 1447 键，按语种懒加载成独立 chunk
- **纠正三处与代码不符的陈述**：① ARCHITECTURE / TEAM 声称存在「Locale 中间件」——实测全仓无此类
  （`config/middleware.php` 全局链仅 Cors→SecurityFilter→RateLimit→TracingId），语种解析实际在
  `app/common/I18n.php` 的 `getLocale()`，由 `I18n::trans()` 调用，共改写 10 处；② `FUNCTIONS.md`
  首段同行自相矛盾（文字 19 域/163 表 vs 标注 23/227）；③ INSTALL / TEAM 陈旧计数
- EDITIONS 补记 v1.17.0 与 v1.18.0 变更段

### CI 与发版

- **release 作业不再被常红的 php 作业卡死**：`needs` 由 `[php, docs, e2e]` 改为 `[docs, e2e]`
- **修复「只打 tag、不建 Release」的真正根因**（v1.9.2 ~ v1.17.0 期间 GitHub Release 一直断档）：
  `gh` CLI 在 Actions 里必须显式注入 `GH_TOKEN`；`permissions: contents: write` 只是授权，
  不会把 token 放进环境变量，缺它直接 `exit 4`
- **修复「一次 run 打两个 tag」**：Release 步骤重复调用 `bump-version.sh --create`（按「最新 tag +1」
  计算），导致 step3 建 vN.1、step4 又建 vN.2 且只给后者建 Release；改为 step3 产出 `TAG_NAME`
  交 step4 复用，缺则报错退出
- 新增防重守卫：同一 commit 已有版本 tag 即跳过创建，并把该 tag 交给 Release 步骤补建（幂等自愈）
- 自动 Release 标题统一为纯 `x.y.z`（不带 `v` 前缀、不带附加文字）
- 现状：docs / e2e / flutter 三作业绿；php 作业仍红（CI 专属集成测试历史债 —— 建表 `ledger_id`
  重复、测试里 `tenant` 表漏 `erp_` 前缀、发票用例依赖 CI 未提供的 Elasticsearch 等），
  但已不再拦发版

## v1.17.0 (2026-09-15)

**全平台 13 语种**（后端响应消息 + Angular 管理端 + React 管理端），并补齐两端词典漏收文案。

### 国际化

- **后端响应消息 12 个语种词典全量生成**（与 zh_CN 合计 13 语种）：common 428 / modules 84 /
  validation 20 + attributes 块，键集与 zh_CN 严格对齐；新增 `scripts/gen-be-locales.mjs`
  （「英文即 key」与框架规则名两套逻辑并存、截断自愈二分重试、断点续跑）
- **Angular 管理端 13 语种**：11 个新语种词典全量生成（源词典 1453 键），语言切换常驻顶栏 +
  个人中心下拉，`acceptLanguage` 随语种声明
- **React 管理端 13 语种**：11 个新语种词典全量生成（源词典 1447 键），切换器与 Angular 同源
  （同一份 `LOCALES` 清单，label 用各语种自称、不翻译）
- **词典按语种懒加载**：每个语种各自成一个 chunk，只有真正切到该语种才下载 —— 否则 12 份词典
  会把首屏包撑爆（实测主包 343KB 未增长，12 个语种 chunk 独立产出）
- **新增 `scripts/gen-fe-locales.mjs`**：`--app angular|react` 双端一套生成器，译文**经英文中转**
  （英文词典已在手，作中间语质量更好）。缓存是「中文原文 → 译文」映射、**与端无关**，所以换产物
  路径这类改动零 API 调用即可重出；只有新增中文键才真正请求网关。断点续跑
- **质量口径（实测）**：键集与源词典缺 0 / 多 0、57 条带 `{占位符}` 的词条占位符全保留、
  除日语汉字外语种**零残留中文**、两端 config 中文覆盖率 **100%**
- **补齐漏收文案**：React 6 条、Angular 10 条（含本端独有的权限树父级 help、JSON 属性帮助串等）；
  另有 2 条斜杠空格漂移（`搜索用户名 / 姓名`）——config 里带空格仅 5 处、不带 933 处，按多数风格
  对齐为无空格，沿用既有译文零新增翻译

### 界面调整

- **语言切换改为顶栏独立图标入口**（两端）：原先藏在用户菜单里 —— 功能页面上看不见；现在
  globe 图标点开即是 13 语种清单（当前项打勾），与用户菜单互斥打开、路由变化自动收起。
  用户菜单回归「个人中心 / 退出登录」两项

### 文档

- README 增加 Angular / React 管理端启动方式（含 Node ≥ 22.22.3 的 npx 绕法、dev 代理说明）
  与 apidoc 访问/标注用法
- 统计标注按实测对齐（tests 943→960、assertions 4585→4608）

## v1.16.0 (2026-09-14)

安装可选演示数据 + CI php 作业去红 + 三处用户可见缺陷修复。

### 平台与架构

- **安装向导新增「带测试数据」选项**：勾选后追加执行 `database/install-demo.sql`；默认**不勾选**（生产安全）。
  该文件**只有数据、没有 DDL** —— schema 的唯一事实源仍是 `install.sql`，避免两份 DDL 漂移；
  演示数据 ID 统一在 `41xxxxxxxxxxxxxxx` 段，可按段整段清理。覆盖 **227 张表**（9 段手工精写 +
  207 段生成）
- **`scripts/gen-demo-data.mjs` 生成器**：**以 `information_schema` 为结构源**（不解析建表语句文本，
  早期正则版本反复漏列）；按列类型/列名/唯一键/列长出值，保证 NOT NULL 列有值、唯一键列逐行互异、
  字符串按列上限截断。**两段式**：只写 `/tmp` → 导入验证通过 → `--install` 才并入正式文件
  （曾直接覆盖，既删了手工段又因唯一键撞车导不进去）
- **CI php 作业 PHPUnit 大幅去红**：集成测试**只在 CI 真跑**（本地无 `TEST_DB_*` 时跳过），
  长期 **349 errors / 14 failures**。根因：**五处自建 Eloquent Capsule 都写着 `'prefix' => ''`**，
  注释理由是「各模型已显式声明 `$table = 'erp_xxx'`」——**实测 224 个模型无一例外都是无前缀表名**。
  前缀与 app 对齐后 → **9 errors / 4 failures**

### 其他

- **数据破坏性缺陷修复（重要）**：测试 tearDown 原先直接 `dropTableIfExists`。前缀对齐后，
  脚手架声明的 `hr_job` 会解析成**真实表 `erp_hr_job`** 并被 DROP —— 实际删除了 13 张 HR 表
  （此前该调用是空操作，因为 `hr_job` 这个无前缀表名根本不存在）。现改为 `dropTableIfCreated`：
  **建表时记账、删表时核对**，只删本次用例自己建的表，本来就存在的表永不删除
- **Angular 资源页「刷新」按钮无效**：`effect` **只跟踪同步执行期间被读取的 signal**，而 `refresh()`
  写入的 `tick` signal **从未被读取** → Angular 不会因此重跑 effect，点击毫无反应。改为 `refresh()`
  直接调 `reload()`，并删除多余的 `tick` signal
- **Webhook 订阅创建必 422「event 不能为空」**：后端要求 `event`（数组，必填），而 **Angular 与 React
  两端的 Webhook 表单都没有该字段**（同一份平行副本，两端一起缺）→ 从界面创建必然失败。后端放宽为
  也接受逗号/分号/换行分隔的字符串（**事件名白名单校验一字未动**），两端表单补上 `event` 字段
- 质量门：Angular `ng build` 退出 0 且 **0 warning**；React `tsc --noEmit` 干净；
  `phpunit 971 tests / 2728 assertions / 0 failure`；`phpstan [OK]`；`php-cs-fixer 0/644`
- 遗留：CI php 作业仍红在 PHPUnit 的 **9 errors + 4 failures**（`ledger_id` 的 ALTER 不幂等、
  `member`/`tenant` 两表在全新库中缺），另需给该作业加 `SCOUT_DRIVER=null` 清 ES 连接错误


---

> 本文件保留 v1.16.0 及以后（v1.15.0 及更早见 [docs/CHANGELOG-archive.md](docs/CHANGELOG-archive.md)）——
> 体例不变，最新在前；总行数接近 500 行时按同一规则继续前移归档边界（整段搬入，不重写历史条目）。
