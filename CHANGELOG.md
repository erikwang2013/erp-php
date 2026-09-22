# 更新日志

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## v1.19.8 (2026-09-22)

**审批轨迹 create 侧 + 演示数据两条残单批**：把 v1.19.7「待办」里的头部三条收口 —— `/purchase/apply` 的 `store()` 建单即 `status:1|2` 也落审批轨迹（与 update 侧对称）；演示数据两条残单：多态 `*_source_id` 改人工白名单、8 个「注释宣告码表而值是 0」的列按注释首个码归位。范围仍只含缺陷修复：**0 个新增控制器、0 个新增路由、0 个新增数据表**。

### 修复 · `/purchase/apply` 的 `store()` 也落审批轨迹
- 此前 `store()` 收 `status`（`integer|between:0,3`）却不写审批列 ⇒ 建单即 `status:1|2` 得到「已批准但没有审批人」，而经 v1.19.7 的同值守卫之后**再无旁路补救**（对已批准的记录再点一次「通过」不再写轨）。**是真实用户路径**：Flutter 建单表单的 status 下拉可手选「已批准/已驳回」（`apps/flutter/lib/app/pages/purchase/apply_list_page.dart:89`；Angular/React 建单表单无 status 字段，从未受影响）
- 修法：`$finalStatus = (int) $item->getAttribute('status')` ∈ `{1,2}` 时 `forceFill(['approved_by' => adminId, 'approved_at' => now])`，**取 `fillModelFromRequest` 之后的最终值**。**刻意不选 422 打回** —— 那会打断建单路径（Flutter 下拉默认就含这两个值）
- **`status:3`（已转订单）刻意不落**：它是订单认领的结果态（`OrderController::markApplyOrdered` 既不写审批人也不校验原状态），且 **update 侧对 3 本就不改写** ⇒ create 落而 update 不落就是同一状态两条口径。详见「待办」
- **读法坑（本仓新建模型专属）**：`store` 的 `$item` 来自 `new PurchaseApply()`（**具体类型**）⇒ 直读 `$item->status` 会让 PHPStan 报 `property.notFound`；而 `update` 的来自 `find()`，本仓无 larastan ⇒ 推成 `mixed` ⇒ 同形读**一直没报错**。**两处不可互抄**，故用 `getAttribute('status')`（仓内先例 `CrmService:107`）

### 修复 · 演示数据两条残单（`scripts/gen-demo-data.mjs` + `database/install-demo.sql`）
- **多态 `*_source_id`：拟议的通用规则被否决，改人工白名单 `POLY_FK`（11 列）**。规则「某 `*_id` 列所在表存在 `<base>_type` 即按多态填 0」命中 16 列、**误伤 2 列**（按派单阈值「误伤 > 0 不落地」否决）：
  - `erp_hr_perf_score.rater_id` —— `rater_type` 是评分人**角色快照**（1自评/2上级/3同事360）不是判别列，该列本已正确指向 `erp_hr_employee`，且它是 `uk_plan_emp_rater_indicator` 成员 ⇒ **归零会破唯一键**
  - `erp_oms_inventory_reservation.source_id` —— 注释「来源ID（OMS订单ID）」**点名了目标表** ⇒ 该**解析**不该归零（已改指 `erp_oms_order`，实测变真订单行）
  - 白名单 9 个非唯一键列填 `0`、2 个 UK 成员（`uk_source(source_type, source_id)`）落行号 1/2/3 保唯一
- **真值 12 列，不是登记单上的 7 列 —— 这条是本批最值得记的方法论结论**：登记单是**按 diff 生成的**，而「拿编造值冒充关联」的列**在手工文件里也同样是编造值** ⇒ 两版一致 ⇒ **不产生 diff ⇒ 永远不进残单**。改用**值口径**独立扫描（扫全表全列、值 ∈ `erp_finance_voucher_source` 现有 id 集）才看见：旧文件命中 **13** 列、新文件 **1** 列（那 1 列是 `erp_finance_voucher_source.id` 自己的主键、不是指针）⇒ **12**。多出的 5 列是 `erp_{inventory_flow,mfg_wip_flow,notification,quality_nonconformity,oms_inventory_reservation}.source_id`
  - **独立强证据**：手工调优文件里 `bill`/`cash_journal`/`invoice`/`invoice_match_log`/`tax_record.source_id` 本就是 `0`、`ar_ap`/`voucher_source.source_id` 本就是 `1,2,3`，与本轮输出**逐格相同**（23 格「修好」桶）⇒ 规则只是把原作者意图形式化，不是改数据
- **规则 E：注释宣告 `1=… 2=…` 而值是 `0`** —— 判为**值侧缺口**（注释是完整闭合枚举、`0` 不在域内；手工文件这 8 格也全是 `0` ⇒ 长期不一致的根源在值侧），**改值不改注释**。原始命中 133 个 INT 列，**有效改动只有 8 列 / 24 格**（133 = 125 已由既有规则给对 —— 52 个被「非空数字 DEFAULT」先返回 + 73 个首个码本就是 `0` + 8）。8 列全部 `0 → 1`：`erp_cost_record.type`、`erp_finance_ar_ap.type`、`erp_finance_bank_statement.direction`、`erp_finance_cash_journal.direction`、`erp_finance_settlement.type`、`erp_hr_leave.type`、`erp_inventory_flow.direction`、`erp_mfg_wip_flow.source_type`
  - **承重前提：首个码 ≠ 0 才采**（否则 `0=无` 这类哨兵注释会把合法 `0` 反覆盖）
  - **零 `status` 列被抢**（既有坑：`lit()` 的 INT 分支排在 `status` 规则之前）—— 用插桩（`#E# reach` / `#E# FIRE`，插桩产物与仓内生成段逐字节相同 ⇒ 无损）实测 reach 82 列、FIRE 恰 8 列；74 个未触发里 73 个首码=0、1 个（`erp_finance_cost_account_config.cost_type`）被 `!isUniq` 挡下
- 规模：本批相对上一版 **60 格 / 20 个 (表,列)**（= 12 个多态列 × 3 + 8 个规则 E 列 × 3）与 **+12 行**（生成段新增的多态提示块，落在 `install-demo.sql:102-113`）；值为 `'d'` 的格 **504 → 504 未动**。`install-demo.sql` `a7ef9ded…` → **`d4b421d3…`**（1163 行）、`gen-demo-data.mjs` `3df97b1e…` → **`5619551100…`**

### 验证（独立验证方单飞，仓库零写入）
- **头条数字 12 被值口径独立复算确认**（旧 13 − 新 1 = 12，那 1 是主键）；`16 列命中 / 2 误伤`、`133 = 125 + 8`、`60 格 / 20 列`、`+12 行` 全部逐数吻合；两个「误伤」列实测**未被归零**且值真指向目标表（`rater_id` ⊆ `erp_hr_employee.id`、`oms_inventory_reservation.source_id` ⊆ `erp_oms_order.id`）
- **`store()` 五态矩阵**（自建探针 × 夹带自报 `approved_by=999`/`approved_at='2000-01-01'`）：显式 `0`/`3`/不下发 ⇒ 两列 `0`/`NULL`；显式 `1`/`2` ⇒ `adminId` + now；**自报值一律未落库**，不符格子 = 0。**负控**用 `git show ad2e463:` 的改前控制器抢类名 ⇒ 恰在 `1`/`2` 两格为 `0`/`NULL`、其余 3 格逐格相同 ⇒ 增量精确等于「建单即 1|2 落轨迹」
- **演示数据真能装**（自建空库）：`install.sql` rc=0/0B → 落地版演示数据 rc=0 / **stderr 0 字节** / 227 张表
- **`--check` rc=0** + 四条负控（值漂移 / 删整表 / 坏哨兵 / **另加的多态回填**）各 rc=1 且点名到表，还原 `md5sum -c` 成功且与仓库文件逐字节相同；**幂等**：install-only 库与 install+演示库两次重生成 `cmp` 逐字节相同（`9ac90ec9…`）且等于仓内生成段
- **phpunit CI 等效**：`Tests: 1059, Assertions: 7151, Warnings: 2, Skipped: 9` rc=0 = 上批 `1057 / 7129` + **2 用例 + 22 断言**（跳过数零残差）；15 道 node 门禁全 rc=0；PHPStan `[OK] No errors`；CS Fixer `0 of 652`
- **`doc-stats` 红因只有本轮新增用例**：实测 **test files 113 / tests 1048 / assertions 5040**，`--check` 报 130 处不一致（65 `stats:tests` 1044→1048 + 65 `stats:assertions` 5029→5040），`stats:test_files` 零漂移。**口径提醒**：运行时断言 `+22`（两条用例各一个 foreach，`7151 − 7129 = 22`）≠ doc-stats 的**静态调用点** `+11`，两者别混。冻结时跑一次 `--fix`（触动 52 份）⇒ 复跑 `--check` **rc=0 / 338 处全一致**
- **未验证（显式列出）**：① E2E 与前端构建本轮未跑（本批未动前端；上批跑过均 rc=0）② `composer audit` 未重跑（上批的绿只在本地缓存范围成立）③ 冻结清单（manifest）本轮未核 —— 复核方只有四文件哈希 + `git status`

### 待办 / 已知遗留（本轮未修）
- **`/purchase/apply` 的 `create` 允许 `status=3`（已转订单）**：3 是订单认领的结果态（唯一写入方 `OrderController::markApplyOrdered` 既不写审批人也不校验原状态）⇒「3 ⇒ 有审批人」这条不变量在本仓本就不成立。要治得**连端一起动**：Flutter 建单/编辑**共用同一个含 0..3 的下拉**（`apply_list_page.dart:82-103`），只禁 create 会被编辑路径绕过；Angular/React 建单表单没有 status 字段 ⇒ 影响面只在 Flutter 与直连 API。单开窗
- **演示数据 8 张表的 `source_type` 仍是兜底 `'d'`**（`bill`/`cash_journal`/`invoice`/`invoice_match_log`/`tax_record`/`ar_ap`/`voucher_source` 等）—— 它们正是 `POLY_FK` 那些 `source_id` 的**判别列**，现在 `source_id` 已归位而判别列还是占位串。字符串侧同形规则（注释 `code=label` 且值兜底 `'d'`）候选只有 5 列（`invoice_match_log.result` / `member_balance_log.biz_type` / `member_point_log.biz_type` / `notification_channel_log.channel` / `tax_issue_log.action`），**接不住那 8 张表**（它们的注释没有 `code=label` 形态）⇒ 需另立规则，单独量
- **`erp_approval_instance.target_type`（`'d1'/'d2'/'d3'`）、`erp_member_balance_log.biz_type` / `erp_member_point_log.biz_type`（`'d'`）**：仍是占位串（DDL 默认值为空 ⇒ 被规则 C 的门挡在外），与上批登记的「注释宣告 ASCII 域却留 `'d'`」同类，本批未动
- **`erp_finance_budget_item.period_month` / `erp_finance_general_ledger.period_month`**：注释只写 `(0=全年)` 而值是 1/2/3（规则 D 给行号）—— 语义没错（月份），但**注释没把 1–12 宣告出来**；将来若有更严的「值 ∈ 注释宣告域」门禁，这两列会亮。属注释表达力问题
- **`ar_ap.type` 行 3 的手工值 `2` 被规则 E 统一成 `1`**：手工文件原本是 `1,1,2` 混排，看着像刻意让演示覆盖应收/应付两个分支 ⇒ 记为 **demo 质量项**（非缺陷）。要恢复得加「按注释码表轮转」的独立规则，需单独量
- **3 个中性多态列**（`erp_approval_node.approver_id`、`erp_member_balance_log.biz_id`、`erp_member_point_log.biz_id`）：已量、值为 `0` 且无编造 ⇒ **判定无需动作**，未列入 `POLY_FK`（列进去只多一行提示、不改任何值）。登记以免将来被当漏项重查
- v1.19.7 结转未修：采购金额 11 对 `0.00`（改前既有）、真库 `erp` 不会被自动更新、`docs/i18n/ar/CLAUDE.md` 围栏树 10 个模块数过期、11 份 i18n README 的 `tests/` 树行形态差异、dms `status` 空串语义、详情抽屉 `fallbackCell` 的 kd 分支、移动端 `customer_id`/`bank_account_id` 编辑态兜底

## v1.19.7 (2026-09-22)

**演示数据生成器同步 + 审批轨迹边界批**：把 v1.19.6「待办」里排第一的收口 —— 签入的演示数据生成段比它的生成器差一个版本（`6ed0772` 换过外键解析器、文件此后没再重生成），本轮补解析缺口、把 44 个字符串列从占位 `'d'` 改成 DDL 默认值、按文件自带流程重生成并入，并给生成器加 `--check`；同时收掉 `/purchase/apply` 的 PUT「同值重放也改写审批轨迹」的边界。范围仍只含缺陷修复：**0 个新增控制器、0 个新增路由、0 个新增数据表**。

### 修复 · 演示数据生成器同步（`scripts/gen-demo-data.mjs` + `database/install-demo.sql`）
- **未解析必填外键 28 → 3**，三类拆分各有依据：
  - **11 条真解决**，其中 `erp_cost_record.flow_id` 抓得最典型：注释「库存流水ID」指向 `erp_inventory_flow`，而长度兜底会误选 `erp_mfg_wip_flow`；改对后 3 行值与仓内手工值**逐字节相同**
  - **14 条「用户外键」重新归类**进「按设计填 0」块 —— 依据是本文件**设计约定第 3/6 条原文**（「本文件不建用户」「`owner_user_id`/`submitter_id`/`apply_user_id` 这类只能填 0，展示为空是预期行为，不是数据缺失」）。**是把报告对齐既有文档约定，不是新造例外**
  - **3 条真多态**留在警告块、**不猜**：`approval_instance.target_id`（`uk_target(target_type,target_id)`）、`ar_ap.partner_id`（「往来对象ID（客户/供应商）」）、`settlement.receipt_payment_id`（「收款/付款单ID」）
- **44 个「非空 DEFAULT 的字符串列」改为 DDL 默认值**（41 列旧值是占位 `'d'`/`'d1'`；另 **3 列是旧业务码归位** —— `erp_dms_document.status` `'1'`→`draft`、`erp_eam_repair_order.status` `'1'`→`open`、`erp_mfg_bom.version` `'1'/'2'/'3'`→`1.0`）。分母实测自洽：全库「字符串列 + 非空 DEFAULT」= 57，其中**在生成范围且非唯一键 = 54 ⇒ 54 = 44 改 + 10 逐格未变（零回归）**；另 2 列不在生成范围（`erp_finance_tax_rate.type` 属 SEEDED、`erp_operation_log.source` 属 SKIP）、1 列被 `!isUniq` 挡掉（`erp_system_config.group`）⇒ **56 = 54 + 2**。新值形如 `'bank'`/`'CNY'`/`'manual'`/`'pass'`/`'A4'` … —— DDL 默认值是**按构造合法**的值，`'d'` 不是。**两个守卫都做了摘除负控证明承重**：字面量守卫（非空 + 不含引号/反斜杠/制表/换行）摘掉后 **223 对变色，且变色集合 ≡ 探针拒绝集合（逐格 diff 为空）**：**222 对是空串默认值的列被清成 `''`**（`DEMO-*` 编码 / 邮箱 / 电话 / 「演示数据」全丢），**1 对**是 `erp_approval_workflow.canvas_json` —— 全 schema 唯一的表达式默认值 `DEFAULT ('')`，会被渲染成带 `_utf8mb4` 前缀的畸形串；`!isUniq` 守卫摘掉后 `erp_system_config.group` 三行塌成同一值（`uk_group_key(group,key)` 靠 `key` 仍能导入 ⇒ 它是**语义**守卫、不是硬失败守卫，如实记）
- **落地走文件自带的流程**（不跳步）：生成 → 落 `/tmp` → **空库**导入 `install.sql` → 导入产物（rc=0 / stderr 0 字节 / 227 张 `erp_` 表）→ 才 `--install` 并入。**幂等已验证**：从「install.sql + 演示数据都在」的库重生成，产物与只装 `install.sql` 的参照库**逐字节相同** ⇒ 读种子 ID 的 SELECT 不会把演示行读进来
- **新增 `--check` 模式**（真逐字节比对、漂移点名到表）：三条负控（改一个值 / 删整块 / `install.sql` 加一列）各自 rc=1 且点名到表，还原 `md5sum -c` 成功。**限制（诚实记）**：它需要活库（`TEST_DB_DATABASE` 指向已导入 `install.sql` 的库）⇒ **接不进 CI** 的 docs 作业（那里没有 MySQL），是本地/合入前守卫；不带库时 rc=1 报错退出，**不静默通过**
- 手工段 1–95 行与 HEAD **逐字节相同**；全仓消费者复查：只有 `app/controller/InstallController.php:574`（安装向导「带测试数据」）读它，测试与 CI **不依赖它的具体值**
- 规模：落地后与旧签入文件差 **606 cell / 205 个 (表,列)**，逐格分解（可独立复算）：**数值列 → DDL 默认值 276 格 / 92 列**、字符串列 → DDL 默认值 132 格 / 44 列、年度列 → `YEAR(CURDATE())` 39 格 / 13 列、外键 `0` → 真 ID 99 格 / 33 列、外键改指 51 格 / 20 列、非外键 `0` → 非 `0` 6 格 / 2 列、非 `0` → `0` 3 格 / 1 列

### 修复 · `/purchase/apply` 同值重放改写审批轨迹（v1.19.6 引入的边界）
- `update` 原先只判新值（`$newStatus === 1 || $newStatus === 2`）⇒ 对已是「已通过/已驳回」的记录**再下发一次相同 status** 也会 `forceFill`，把 `approved_by` 改成**当前编辑者**、`approved_at` 刷成此刻。两条真实路径：① Web 上对已通过的记录再点一次「通过」；② `tests/E2E/api-coverage.php:430` 的「PUT 全字段原值回写」探针
- 改为**只在状态真的变化时写**（0→1、0→2、1→2、2→1；1→1 / 2→2 / 0→0 不写）：`$newStatus` 读 fill 后的模型属性、原状态走 `getOriginal('status')`（`fillModelFromRequest` 已把属性改写成新值，读属性就晚了；`getOriginal` 不新增属性访问 ⇒ PHPStan 零新增）。fillable / 列名语义 / `forceFill` / 错误面惯例 / 两条审批路径（1 与 2 都落）全未动
- 两条新用例（+9 断言）：同值重放断言 **`approved_by` 仍是 999、`approved_at` 仍是那个明确过去时刻**（**判据刻意不用「两次调用是否落在同一秒」** —— `approved_at` 是秒级，同秒内即使没修也会绿）；另加一条正向用例证明 1→2 确实改写、守卫没把功能一起删
- 对照：`RmaService::approve`（status≠0 抛）与 `ExpenseController:216-218`（已批准 422）都**拒绝**，采购是唯一无守卫的写入方 ⇒ **单点根因**。这里选 no-op 而非拒绝：拒绝会打断 Web 上「再点一次通过」，也会让 E2E 那条断言 `bizCode===0` 的原值回写探针变红

### 验证（独立验证方单飞，全部按内容哈希 / 逐格复算，仓库零写入）
- **phpunit（CI 等效，补 `TEST_REDIS_HOST`）**：`Tests: 1057, Assertions: 7129, Warnings: 2, Skipped: 9` rc=0 ⇒ 在 v1.19.6 基线 `1055 / 7120` 之上正好 **+2 用例 / +9 断言**、跳过数零残差；两条新用例源码里的断言数 4 + 5 = 9 与增量逐数吻合
- **守卫按全矩阵验，不只跑那两条用例**：探针逐格读回三列，判据 `期望写入 = 新值∈{1,2} 且 新值≠原值` ⇒ **不符格子 = 0**（覆盖 0→1、0→2、1→2、2→1、3→1、1→1、2→2、0→0、3→3，以及不下发 status 的两种库态）。**负控**用 `git show HEAD:` 的改前控制器抢占类名跑同一探针 ⇒ **恰好 2 格不符（1→1、2→2）** —— 行为增量精确等于该格集合，不多不少
- **演示数据逐格复算**：56 / 54 / 44 / 10 / 2 的分母自洽（见上）；**606 格 / 205 列**的分解被独立算出且与正文逐字吻合；**幂等**用两个库（227 表 / 0 演示行 vs 227 表 / 有演示行）各跑一次 ⇒ 产物 `cmp` 逐字节相同（`882b6d78…`）且与仓内生成段逐字节相同 ⇒ **落盘文件不再落后于它自己的生成器**
- 15 道 node 门禁 rc=0；`check-ddl-dict` 覆盖度行与上批逐字相同；本轮 7 个申报哈希逐字吻合
- **`doc-stats` 曾红，且红因只有本轮新增用例**：实测三个真值 = **test files 113 / cases 1046 / assertions 5029**；`--check` 报 `✗ 338 处标注，130 处不一致`，其中 `stats:tests` 65 处（标注 1044 ≠ 1046）、`stats:assertions` 65 处（5020 ≠ 5029），**`stats:test_files` 零漂移**，无第三种红因。冻结时按纪律只跑一次 `--fix`（触动 52 份文档：13 语种 × 4 篇 + 根 README）⇒ 复跑 `--check` **rc=0 / 338 处全部与实测一致**
- **未验证（显式列出，未吞）**：① `composer audit --no-dev` rc=0，但 packagist 超时、回落本地缓存 ⇒ 该绿只在缓存范围内成立，不等于远端公告面干净 ② E2E 与 docs 两个 CI 作业本轮未复跑 ③ 「全仓只有 `InstallController.php:574` 读演示文件」是上批结论、本轮未重跑 ④ 覆盖率 HTML 子步骤与 pcov 驱动维度（本机环境限制，同上批）

### 待办 / 已知遗留（本轮未修）
- **`/purchase/apply` 的 `store()` 允许建单即 `status:1|2` 而 `approved_by` 恒 0** ⇒ 会出现「已批准但没有审批人」的记录，审计轨迹同款缺失。与 update 侧对称的修法是「create 时就落轨迹」而非新增 422（新增 422 会打断建单路径）；本轮按范围未开。
  - **本轮的新守卫把这个洞的影响面变硬了（实测 A/B）**：HEAD 版尚可「对那条记录再点一次通过」把 `approved_by` 由 0 补成编辑者、`approved_at` 由 NULL 补上；新版「无状态变化即非审批动作」⇒ 两者恒为 0 / NULL，**审批人永久不可考、且再无旁路补救**。语义上新版是对的，但这条待办的优先级因此上升 —— 要么在 create 侧落轨迹，要么就接受这类记录无审批人
- **演示数据 `*_source_id` 有 7 列被指到 `erp_finance_voucher_source`**（`bill`/`invoice`/`tax_record`/`cash_journal`/`invoice_match_log`/`ar_ap` 各一 + `voucher_source.source_id` 自指）——这些列与同表的 `*_type` 判别列配对，**实为多态**。建议规则：某 `*_id` 列所在表同时存在 `<base>_type` 时按多态处理（填 0 并入警告块）。改动面涉及两处调用点，单独一批更稳
- **`erp_cost_record.type` / `erp_finance_ar_ap.type`**：DDL 注释宣告的域是 `1|2`，而生成值是 `0`（域外，属「DDL 注释即字典」那类）。要修得先定口径：或让规则按注释取首个枚举值，或把这类列收进人工映射
- 采购金额 11 对（`purchase_order.total_amount`、`purchase_order_item`/`receive_item`/`return_item` 的 price/quantity/amount）生成值是 `0.00` —— **改前就是 `0.00`，非本轮引入**（在「改前差距」桶里，不在「本次改动」桶里）
- v1.19.6 结转未修：`docs/i18n/ar/CLAUDE.md` 围栏树 10 个模块数过期、11 份 i18n README 的 `tests/` 树行形态差异、dms `status` 空串语义、详情抽屉 `fallbackCell` 的 kd 分支、移动端 `customer_id`/`bank_account_id` 编辑态兜底
- **真库 `erp` 仍有 `'d'` 系统性占位**：本轮只清了 `method` 那 6 行（改 `bank`）；演示文件侧的占位已由本轮生成器改动消除，但**真库不会被自动更新** —— 要不要按新演示文件回填真库另议

## v1.19.6 (2026-09-22)

**DDL 字典对差门禁 + 操作人关联名批**：把 v1.19.5「待办」里的三条逐条收口 —— 新增第 15 道门禁 `check-ddl-dict.mjs`（`install.sql` 列注释/默认值 ↔ 词典对差，B5）、dms `update` 补 `status` 值域、`method` 由裸 `string` 收紧为 `in:`；同时补 10 个 index 端点的操作人关联名（B7/B7b）与 `/purchase/apply` 的审批轨迹（B8）。范围仍只含缺陷修复：**0 个新增控制器、0 个新增路由、0 个新增数据表**。

### 新增 · DDL 字典对差门禁（B5）
- `scripts/check-ddl-dict.mjs`：三条判据 **A 字典宣告 ⊆ DDL 值域 / B DDL 值域 ⊆ 字典 / C DDL 默认值 ∈ 字典**。输入面只有四处：`install.sql` 列注释、`config/route.php` 的 `controller@method`（**只用于把 `use app\model\X` 解析成 `$table`，不读 validator 规则**）、模型 `$table`、Angular `domains/*.ts` 字典
- 首跑 `rc=1`、恰好 2 处 FAIL：`/finance/{receipt,payment}` 的字典宣告了 `other` 而列注释值域里没有它（覆盖口径与白名单段 PASS）。**逐字节对 HEAD 版本复跑同样 2 处 FAIL ⇒ 既有、非本批引入**，不塞白名单
- **已知盲区**：只读 Angular 侧字典 ⇒ React 侧漂移对这道门禁不可见
- 跳过面已逐条复核（13 处跳过 + 1 条白名单）：**零隐藏缺陷** —— 3 处「候选表不唯一」（保守跳过是必需的：`/workflow/my .target_type` 是「字典 = registry ∪ 注释遗留」的刻意并集，任取一个候选即产假阳）、3 处「本控制器表里无此列」、7 处「端点未解析出表」（这些控制器的模型只在 service 里 `use app\model\X`，而门禁只扫控制器 import ⇒ 该页**每一个**键永久跳过，与注释写得多可读无关）
- 按门禁结果修 3 处 `install.sql` 注释：`target_type` 补 `sales_order`、`gender` 补 `0=未知`、`erp_hr_perf_plan.created_by` 由 `erp_hr_employee.id` 改 `erp_admin_user.id`。**修注释那侧、不塞白名单**；`gender` 连带两端 `EMPLOYEE_DICTS` 加 `0:'未知'` 键（补码而非加表单字段 —— 该列 `DEFAULT 0`，原注释却从 1 起）
- **枚举注释解析面补齐（两个新解析器）**：斜杠形（`状态: 0草稿/1上架/2下架`，只按 `/`、`|` 切 —— 标签本身含数字，按「下一个数字即下一个码」切会把 `3同事360` 切错）与连写形（`0草稿1待审批2已审批…`）。回归前这两类注释**既不解析也不计数** ⇒ 对应断言全是空转。第 5 个解析器的标签字符类 `[^\d\s=,，、;；/|()（）]+` **不含数字** ⇒ 标签一含数字整条返回 null、**引擎无从猜切点**（判不可解析至少是响的，猜错切点产的是假绿）；`looksEnumerable` 同步加两条对偶分支把被拒注释计入 `enumUnparsed` —— 否则「按约束拒掉」会重新变成静默
- 覆盖度 `keysWithDomain` 144 → **165**：相对前三解析器基线共激活 **21 键**（斜杠 7 + 连写 14）、`enumUnparsed` 0。**「解析面 ≠ 断言面」**：另有 6 列命中但页面无字典 ⇒ 只解析不断言，别拿 165 当断言数
- **自检上限由 `enumUnparsed <= 30` 收成 `<= 5`**：塌陷签名是 7 / 14 / 21（斜杠坏 / 连写坏 / 两者坏），原上限三者全在界内 ⇒ 解析面塌一半只多打印一行、照样绿；`keysWithDomain >= 80` 的地板也兜不住（165 → 144 仍在地板之上）—— **两个计数器互相兜不住**，是典型假绿形状
- 已接 CI：`docs` 作业末尾 append 两个 step（`actions/setup-node@v4` 锁 `22.x` + 门禁本体），**不动 `release.needs`**（它已在 needs 里 ⇒ 门禁红照样断发版），接线以 rc=0 为前提。锁 `22.x` 而非「改写成低版本也能跑」：`module.registerHooks` 比 `--experimental-strip-types` 更晚才有、Node 20 根本没有类型擦除，改代码救不了，且要动 6 个门禁脚本共用的自举头（5 个不属本批）。**改这个 pin 的人需要重跑本门禁**

### 新增 · 门禁自证升级
- `check-column-titles.mjs` 的产出方断言由裸 `'must'` 升为 `['must', N]` 计数 pin（14 键）：**裸 `'must'` 会被静默跳过 ⇒ 此前是假绿**；现在别的车道新增产出方会红到必须改 pin 行
- `gen-column-titles.mjs --check` 改为真逐字节比对：此前只打印计数、不读被检查的文件，三种漂移全 rc=0（假绿）。现在 `rc=0` 的含义是两端落盘物逐字节一致（543 个新键 / 543 条落盘条目 / 2 片）
- 两端手写标题档补 `order_code: '工单编码'`（非 DB 列：mfg 三页 index 按 `order_id` 反查 `mfg_production_order.code` 带出；`purchase/receive`、`sales/delivery`、`oms/rma` 的值侧别名同为该键）—— 措辞沿用词典既有「工单编码」，不另造第二条

### 修复 · 操作人关联名产出方（10 个 index 端点）
- 4 个新键：`assigned_name`（`/wms/{pick,pack,putaway}`，`wms_*_task.assigned_to`）、`approved_name`（`/oms/rma`、`/finance/expense`、`/purchase/apply`）、`audited_name`（`/finance/invoice`）、`created_name`（`/admin/openapi`、`/admin/webhook`、`/hr/perf-plan`）
- `/finance/expense` 另补 `apply_user_name`/`account_name`；`/admin/webhook` 的 `app_name` 同批落地
- 口径沿用 v1.19.4：名称按**裸 ID** 查、一次 `whereIn(...)->pluck('real_name','id')` 铺行、不新增模型属性读
- `/hr/perf-plan`（B7b）：`appendCreatorName()` 与其余端点同手法；连带修正该列注释（`created_by` 实为 `erp_admin_user.id`）

### 修复 · `/purchase/apply` 审批轨迹（B8）
- `index` 补 `leftJoin('admin_user as approver', 'approver.id', '=', 'purchase_apply.approved_by')`，行出 `apply_user_name`/`approved_name`
- `update` 在 `status` 落到 **1 已通过 / 2 已驳回**时 `forceFill(['approved_by' => adminId, 'approved_at' => now])` —— 此前审批人/审批时间**永不落库**，列表也就恒空；不带 `status` 的 PUT 不触碰这两个字段（用例钉住）
- `PurchaseApply` 模型注释同步：`approved_by`/`approved_at` 刻意留在 `$fillable` 之外 ⇒ 只能 `forceFill`（`fill()` 会静默丢弃）

### 修复 · 值域收紧（两项后端裁决落地）
- `/finance/{receipt,payment}` 的 store+update：`method` 由裸 `string` 改为 `string|in:cash,bank,wechat,alipay,other`
- dms `/dms/document` 的 update：`status` 补 `nullable|integer|between:0,1`（此前客户端可 PUT 任意串、影响筛选）
- **两条都只收紧写入**；`method` 的存量数据同批（用户裁决）：`install.sql:1202/:1221` 列注释补 `other`、`install-demo.sql` 6 条 INSERT 与真库 `erp_finance_{receipt,payment}` 各 3 行由 `'d'` 改 `'bank'`（= 该列 DDL 默认值）—— 不放行则两端编辑弹窗把存量值原样回送即 422。修完 `check-ddl-dict` 此前仅剩的 2 处 FAIL 归零。范围证明：两文件「反向替换后哈希 == 改前基线」；真库 `ROW_COUNT()` 各 3、表内余 `'d'` 为 0；只 UPDATE 这两张表，全库另 327 处 `'d'` 系统性占位未清

### 修复 · E2E token 刷新（B3）
- `tests/E2E/api-coverage.php` 的 `$auth` 由箭头函数改闭包 `use (&$token)`：箭头函数**按值捕获**，刷新回写的新 token 对已定义的闭包不可见 ⇒ 第 7 步起继续携带旧 token，而并发会话上限（3 个）踢出的可能正是它 ⇒ 整片 401
- 刷新成功后采纳新 token；`usleep(1.1s)` 把本次签发推到严格更晚的一秒 —— app 侧 `score = time()+7200` 是秒级，同分时 `trackSession` 踢的是 member 的 md5 字典序最小者（**可能是刚签发的新 token**）。**这是挡在本作业之外的护栏，不是修复 app 侧 `refresh` 会话计数缺陷**
- 本机未实跑：`.env` 与 `.env.example` 都**没有** `E2E_*` 任何键（旁路条件在 `app/api/v1/controller/AuthController.php:70-72`），且 8788 上那个服务读共享 `.env` ⇒ `DB_DATABASE=erp`（真库），而 E2E 会写真 ⇒ 只做静态复核。（**「8788 未监听」这条理由已作废**：21:09:49 起 WorkerMan 已在该端口拉起 31 个 worker，写下这句时尚未起。）

### 修复 · 测试基建
- `DetailContractRegressionTest.php` 的 `$targetId` 由写死 `7777` 改 `SnowflakeService::generate()`：`erp_approval_instance` 带 `uk_target=(target_type,target_id)`，**两条车道同时在真库跑本文件时后插者必 1062**（实测一条 rc=2、一条 rc=0）。判据：串行 `--filter testApprovalShow` rc=0、全仓 `grep 7777 tests/` 仅此一处；改后全量串行 `OK (30 tests, 273 assertions)`，断言数与改前一致
- 新用例 7 条：`testListRowsCarryActorForeignKeyNames`、`testApplyIndexExposesApproverNameAfterApproval`、`testApplyUpdateStampsApproverOnApproveAndReject`、`testApplyUpdateWithoutStatusLeavesApprovalTraceUntouched`、`testApplyModelKeepsApprovalColumnsOutOfFillable`、`testFinanceMethodValidatorRejectsOutOfDomainValues`、`testDocumentUpdateRejectsOutOfDomainStatus`

### 修复 · 文档统计数字
- `docs/i18n/{ar,pt}/README.md` 的控制器数 `139 → 159`：把 `controllers_business` 误抄进了全仓口径的句子（zh 与其他 10 语种同位置本为 159）。只换数字、句子未重写、未翻译；围栏树内的 `139` 是合法异值，保留未动
- v1.19.5 待办里登记的「20 处夹文字陈旧数字」已由 `4cb37c6` 覆盖，本轮实测只剩上述 2 处

### 验证
- **15 道 node 门禁全绿**（rc=0 逐条，两轮一致）；`check-ddl-dict` 覆盖度行逐字：`140 页解析出表 135 页；词典键 191 个落到列 177 个、其中 165 个有 DDL 值域可比、0 个枚举形注释未解析（上限 5）`、白名单 1 条全命中；13 处跳过的构成复核为 3（候选表不唯一）+ 3（本控制器表无此列）+ 7（端点未解析出表）
- **冻结完整性**：88 路径逐个 md5 对拍 **88/88**；**跑完 15 道门禁后再核一遍仍 88/88** ⇒ 门禁对共享树零写入
- **PHP 全量**：`Tests: 1055, Assertions: 7120, Warnings: 2, Skipped: 9` rc=0（Unit `635/3398/9S` + Integration `420/3722`）；PHPStan `[OK] No errors`；CS Fixer `Found 0 of 652`；`php -l` 483 文件无错；`composer validate --strict` / `composer audit` 均过
- **本批真实增量**：7 条新用例在 HEAD 静态全不存在（`tests/` 的 `public function test` 585 → 592），`--exclude-filter` 复跑得 `1048 tests / 7042 assertions` ⇒ **+7 tests / +78 assertions、只增不减**。注意 `1055/7120` 是**含**这 7 条之后的数，**不是改前基线**
- **覆盖率**：整体 **32.46%**（7924/24412，门槛 4%）/ 业务层 **77.63%**（5635/7259，门槛 10%）⇒ `check-coverage.php` PASS
- **前端**：Angular `typecheck` + `build` rc=0（Initial total 1.23 MB）；React `typecheck` + `build` rc=0
- `bash scripts/doc-stats.sh --check` rc=0 / 338 处（独立复跑，未跑 `--fix`）；并确认 **`doc-stats.sh` 不读 CHANGELOG**（`grep -c CHANGELOG` = 0）⇒ 本段后改不影响其结论
- `ci.yml` 只审未改：PyYAML 可解析、jobs 5 个、`release.needs = [docs, e2e, php]` 未动、改动全部落在 docs 作业内（审计时为 30 行单一 hunk；**同批的注释订正之后 `git diff HEAD -- .github/workflows/ci.yml` 为 36/2 —— 新增 20 行注释、删除 2 行注释，其余 16 行非注释新增与改前逐字节相同**）、该作业 grep 不到 composer/npm/php/mysql、新 step 的 `set +e` / `code=$?` 捕获写法正确；门禁在本机默认 v22.17.0 下同样 rc=0，且**不需 node_modules**（`NODE_OPTIONS=--preserve-symlinks` 下亦 rc=0）
- **活体探针有效性**（`check-fe-endpoints` 打 127.0.0.1:8788）：在跑的进程起于 21:09:49，而被改的 14 个 `app/*.php` 最新 mtime 为 20:19:12 ⇒ 该进程就是本批冻结代码，148/148 命中是对本批的实测、不是旧进程的绿
- 真库只读对拍：`receipt`/`payment` 各 `bank=3 / d=0`，**跑全量 phpunit 前后一致** ⇒ 反证测试全程没落到真库
- **未验证（显式列出，未吞）**：① E2E 作业（`tests/E2E/*.php` 会写目标库，超出只读授权；且 `.env` 与 `.env.example` 都没有 `E2E_*` 键，旁路条件在 `AuthController.php:70-72`；8788 上那个服务读共享 `.env` ⇒ 指向真库 `erp`）② flutter 作业（`continue-on-error`）③ 覆盖率 HTML 子步骤（本机 `memory_limit=128M` OOM —— **clover 先写成功才 OOM**，门禁读的就是 clover，故门禁结论有效）④ pcov 驱动的覆盖数值（本机只有 xdebug）⑤ 覆盖度地板「页 ≥100」无法单独触发（`DOMAINS` 硬编码 8 项）

### 待办 / 已知遗留（本轮未修）
- **`install-demo.sql` 的生成器未同步（真遗留）**：该文件是 `scripts/gen-demo-data.mjs` 的产物（`:96` 哨兵「生成段开始，勿手工编辑」），`'d'` 出自 `:177` 末臂兜底。实测**签入的生成段与当前生成器产物差 280 cell / 95 个 (表,列)**（行数差**净 +24** = 生成器警告块 **+29 行** − 签入文件里三段手写注释 **−5 行**；两侧均 207 段、无结构差异，且 0 张表有多个 INSERT 块）⇒ **重生成今天就不是 no-op**：签入段带着旧版生成器/手工调过的痕迹（`from_currency_id` 种子 id、`total_amount=1200.00`、`status='draft'`）。候选补丁（非空 DEFAULT 的字符串列取列默认值、带 `!isUniq` 守卫）收敛到 147 cell / 49 列（含预期的 6 个 `method`，且 `erp_crm_follow_record.method` 得 `'phone'` 而非 `'bank'` —— 证明形状对、按列名的裸规则错），但走 `--install` 的残差仍 385 cell / 130 列。**唯一落地通道会整段重写生成段 ⇒ 只修 `method` 一处救不了**，故按「超阈值不落地」裁决：生成器与 `--install` 都不动，本轮 6 行手改在「没人跑 `--install`」下稳定（门禁不读该文件、生成器不自动跑、CI 不跑生成器）。**差值里恰好含本轮手改的那 6 格、方向是回退**（`erp_finance_{receipt,payment}.method`: `'bank' → 'd'`，各 3 格），而三个门禁都看不见它 ⇒ 下次谁跑一次 `--install`，这 6 格就静默回退成域外值
  - 落地时要先收三个陷阱：`erp_approval_workflow.canvas_json` 是表达式默认值 `DEFAULT ('')`，MySQL 8.4.4 的 `information_schema.column_default` 回 `_utf8mb4\\'\\'` 畸形串 ⇒ 必须加「只取普通字面量」守卫；`:74` 的 SELECT 只取了 `column_default IS NOT NULL` 布尔、**没取默认值文本**（要动 SELECT + 解构 + schema 映射）；兜底对唯一键列现在走 `d1/d2/d3`，换常量默认值会三行同值撞 `uk_*`
- dms `status` 的空串语义：`''` 会被 Laravel 的 non-implicit 规则跳过（`nullable|integer|between:0,1` 不拦空串）⇒ 空串写入仍可能发生
- **`/purchase/apply` 的 PUT 回写不再无副作用（B8 引入的边界）**：带 `status∈{1,2}` 的 PUT 一律 `forceFill(approved_by / approved_at)`，**即便状态没发生变化** ⇒ 对已通过的记录再点一次「通过」（或 E2E 的「原值回写」探针）也会把 `approved_by` 改成**当前编辑者**、`approved_at` 刷成当前时刻。正解是「只在状态**真的发生变化**时写审批轨迹」，但那要动控制器逻辑 + 补用例 ⇒ 冻结后不做；已在 `ci.yml` 的 api-coverage 注释里点名（该探针会对目标库**写真**，别指向生产库）
- `docs/i18n/ar/CLAUDE.md` 围栏树内 10 个按模块控制器数过期（product 7→8、purchase 5→8、inventory 5→6、finance 20→28、workflow 2→3、notification 1→2、project 3→4、hr 5→9、manufacturing 5→13、eam 4→5）并缺 open/platform/print/retail 四行 —— **已实测背书为真过期**（活树 137 个模块文件 + 2 顶层 = 139 = `controllers_business`，23/23 逐项吻合 zh 树），非口径差异；待开窗修（围栏内改动纪律）
- 11 份 i18n README 的 `tests/` 树行把数字写在围栏内 HTML 注释里（`<!-- stats:test_files=113 -->`）⇒ 渲染出来只有注释原文、无可读计数；`de` 与 zh 根是「可见数字 + 标注」形态。属**形态差异，注释里的数均为真值**（门禁可校验），无用户可见后果，不修
- 详情抽屉 `fallbackCell` 的 kd 分支先于 isStatus（v1.19.5 起既有，未动）
- 移动端 `customer_id`/`bank_account_id` 编辑态兜底（v1.19.5 起既有，未动）
## v1.19.5 (2026-09-22)

**状态与字典口径统一批**：把 v1.19.4「待办」里的字典/状态条目逐条收口 —— 两端状态文案兜底口径统一（命中出译文、表外值直出原值）、删除三处编造的前缀状态字典与 `COMMON_STATUS`、`erp_dms_document.status` 补 DDL 默认值键、`/finance/{receipt,payment}` 表单选项补齐到五值（含 Flutter 编辑态兜底）、文档正文数字机械对齐。范围仍只含缺陷修复：**0 个新增控制器、0 个新增路由、0 个新增数据表**。

### 修复 · 两端状态文案口径统一（★契约变化：未命中不再编造）
- **canonical：字典命中 → `tr(文案)`；表外值 / 无字典 → 原值直出**（真实数据优先）。两端删掉三条编造分支：按 endpoint 段猜的 `purchase/sales/crm` 前缀档、`COMMON_STATUS` 通用档、`状态N` / `Status N`
- **Angular `strStatus`**（`/finance/invoice`、`/eam/repair` 的字符串状态）col `kind:'rel'` → `kind:'map'` + `dict`：表外值由关联名的 `-` 兜底改为直出原值、命中同样过 `tr`、空值由 `-` 改为空文本（三档均与 React 对齐）。全页对差（同一次运行内换参数的 A/B，各 11372 行）**253 对变化只落 `/finance/invoice`（166）与 `/eam/repair`（87）**，变化键只有 `status` / `状态`；域内值前后不变（`draft → 开票申请`、`open → 待处理`）。色带不统一（Angular 纯文本 / React Badge+tone）＝已上报的观感差异，本次只统语义
- **React `strStatus` 命中补 `tr`**：8 个标签（开票申请/已提交审核/已审核入账/已作废/待处理/维修中/已完成/已取消）在两端 12 个语种译文表里都有，不 `tr` 则 en 等语种下两端分叉（**改前两端都不翻 —— 一致但都漏翻**）；改后与各自 `mapText`/`statusText` 的命中口径一致
- 连带如实上报：真库 6 行表外值（`erp_hr_employee.status=0` ×3、`erp_hr_attendance.status=0` ×3，DDL 值域 1..3 / 1..6）由「待处理」改为显示 `0`。**代码侧维持删除不回退**（不把表外值藏回中文里）；**数据侧按用户裁决修复**：查证这 6 行是早前探针在真库上的残渣（`install-demo.sql` 给这三员工/三考勤写的是 `status=1`，全仓无任何代码路径写 0），已按种子原值与 DDL 默认值还原为 `1`，三条考勤行的 `employee_id=0` 一并按种子配对还原为员工 id（改前快照 `/tmp/hr6-{employee,attendance}-before.sql`，复验三张 `status=0`/`employee_id=0` 计数均为 0）
- 别误修：**筛选胶囊 label 两端都在渲染期翻译**（Angular `{{ o.label | tr }}`、React `t(o.label)`），不存在「列表翻、筛选不翻」的分叉

### 修复 · 不可达/编造的字典
- 三处前缀档 + `COMMON_STATUS[2]='处理中'`：可达性探针证不可达（全库码 2 出现 58 次、无一处该文案；前缀档在 140 页里一次也没被查到）后删除；`check-fe-detail-items.mjs`（2 条）与 `check-fe-enum-text.mjs`（1 条）的**故意断言同步改写** —— 负控证明改写是必需的（HEAD 版断言跑新源码恰好那 3 条红，即那三条本身在把编造当契约）
- **G4「表单/筛选声明的码都渲染成文案」下的第三处假绿**：`/oms/order` ×5 是合成行的**幻 status 键**（`erp_oms_order` 无 status 列，筛选打的是 `sales_order.status`）→ 该页补 `dicts.status`（本页筛选同源枚举）；`/hr/employee` ×1 是真阳性 → 筛选项从 `ST_FILTER`（宣告 `0=禁用`，而 DDL 无码 0）改为按页字典派生的 `EMPLOYEE_STATUS_FILTER`（不给字典造 `0:'禁用'` —— 那会与 DDL 对差门禁打架）
- **覆盖漏洞补守**：React 前缀档加回去两个门禁都不红（门禁从不 import React 引擎）→ 补静态守，负控在场 rc=1

### 修复 · 字典与 DDL 对齐
- `erp_dms_document.status`（`VARCHAR(20) DEFAULT 'draft'`）补 `'draft': '草稿'` 键 —— **必须带引号**：G2 只比对字典字面量里的引号串集合，两端一致的裸键增删对它不可见
- `/finance/{receipt,payment}` 表单 `method` options 3→5（现金/银行转账/微信/支付宝/其他，值与词典同源）；Flutter 五值 + `financeMethodOther`/`financeMethodBank` 两词条 × zh/en + `gen-l10n` 产物（重生成 md5 逐字节一致）
- **证伪原判据**：「数值字典挂 VARCHAR 列永不命中」不成立 —— 两端 `mapText`/`statusText` 取的是 JS 对象键（恒为字符串），`{0:'草稿'}` 对 VARCHAR `'0'`/`'1'` 照样命中
- Flutter 编辑态兜底：`dropdownOptionsWithCurrent` + `_edit`/`_formFields`（词表外历史值前置占位项，不被 FormDialog 置 null、不被 `_buildPayload` 的空值改写链吞成 `bank`）

### 修复 · `_by` 外键与静态分析
- `DatasetController::update` 的 `template_id` 直写改 `fill`（与同文件 `store` 同款）。该改动**对整个门禁不可见**（`find()` 走 PHPStan baseline，直写也不报）—— 不补用例：加 1 个 PHP 用例＝36 份文档重同步（`stats:tests/assertions` 只数 `tests/**/*Test.php`），而失败形态是响的（hashid 直填 BIGINT → 1366 → 500）

### 修复 · 文档正文数字对齐
- `stats:` 门禁只查「数字 + 仅空白 + 标注」严格相邻的展示值，**夹了文字**的正文数字不会被 `--fix` 改：机械对齐 49 行 / 36 份（`111→113` ×15、`1001→1037` ×49、`4726→4962` ×49），判据是「非数字字符逐字节不变」（数字归一化后 49 对全等）；复验 `--check` 仍 259 处全绿

### 新增
- 门禁断言：`check-fe-enum-text.mjs` 新增 strStatus 命中/表外值两条 + React 形状（含 `tr`）一条、G4 幻键修补、React 前缀档静态守；`check-fe-detail-items.mjs` 两处故意断言改写
- Flutter 用例 +3（`enum_text_test.dart`：列表出「其他」、`wechat` 行预填「微信」、词表外历史值前置占位项回存原样）；`wechat` 那条证的是**下标 zip 不串位**，兜底的真证人是词表外值那条

### 验证
- 14 道 node 门禁全绿；`bash scripts/doc-stats.sh --check` **259 处标注一致**（`docs` 作业，发版阻断级）；React `tsc --noEmit` rc=0；Angular `ng build` rc=0
- Flutter：`flutter analyze` rc=0（`No issues found!`）、`flutter test` **195 通过**（基线 192 + 3）
- 负控（均实测变红于对应断言、逐字节还原后复绿）：strStatus 改回 `rel` → 恰好 1 条红；React 摘 `tr` → 恰好 1 条红；G4 删 `status: OMS_STATUS.dict` → 恰好那 5 条红；B2 三组还原 → 各自中靶；React 前缀档还原 → 静态守红

### 待办 / 已知遗留（本轮未修）
- **引擎级统一「枚举只声明在 `filters` 的页不必再写 `cfg.dicts`」**：探针实测零用户可见收益（140 页 × status 键 × 12 探针值 → 0 变化），且要改 `inferDetailItems` 签名 + 同步 G4 调用点 ⇒ 单开窗口
- dms `update` 不校验 `status` 且 `status` 在 `$fillable`（客户端可 PUT 任意串，影响筛选）
- `method` validator 是裸 `string`、不收值域（加 `in:` 前先探线上 `DISTINCT`，可能 422 掉既有客户端）
- 移动端 `customer_id`/`bank_account_id` 未加同款编辑态兜底（影响有界：空 `bank_account_id` 不上送、`customer_id` 必填拦截，非数据丢失）
- 详情抽屉 `fallbackCell` 的 kd 分支**先于** isStatus（字典覆盖的 status 形键退化成纯文本、丢徽标；45 对 (页,键)、35 个真列，既有行为）
- **DDL 通道扫描未做**（B5）：字典文案仍是逐页人工抄 `install.sql` 列注释，没有「DDL 注释/默认值 ↔ 词典」的自动对差门禁

## v1.19.4 (2026-09-22)

**后端关联名收尾批**：v1.19.3 遗留的「后端外键名缺口 8 处」逐条查证 —— 7 处补齐产出方、1 处（`/sales/settlement`）查证为误报。一并修掉上一批写路径引入的 3 处 PHPStan `property.notFound`（**`main` 的静态分析此前是红的**）。范围仍只含缺陷修复：**0 个新增控制器、0 个新增路由、0 个新增数据表**。

### 修复 · 后端关联名产出方（7 处，全部只补 `index`）
- **`/finance/bill`、`/finance/payment`**：行补 `bank_account_name`（`erp_finance_bank_account.name`）。票据页的 `bank_account_id` 表单字段没有 `source`（裸 hashid 输入框），只有这个兄弟键能让详情抽屉显出账户名 —— 缺它就只剩「托收账户 -」
- **`/inventory/transfer`**：行补 `from_warehouse_name`/`to_warehouse_name`（`erp_warehouse.name`，一次 `whereIn` 覆盖两列）
- **`/tms/freight-invoice`**：行补 `carrier_name`（`tms_carrier.name`）与 `shipment_code`（`tms_shipment.code`，键名与 `tms/TrackingController:68` 既有产出方一致）
- **`/system/permission`**：行补 `parent_name` —— 权限是自引用树，父节点就在同一结果集里，`array_column` 零查询解出（顶级 `parent_id=0` 留空串）
- **两端别名表**新增 `shipment_id → shipment_code`（Angular `NAME_ALIAS` + React `REL_ALIAS`，键序一致）：运单表无 `name` 列，默认兄弟 `shipment_name` 全仓零产出方，别名指过去才有值；`check-column-titles.mjs` 的 `EXTRA_KEYS` 早有 `shipment_code`，标题不受影响
- 三条口径：名称一律按**裸 ID** 查（`encodeIds` 之后同键已是 hashid，误用即整列为空）；新查询走 `Model::query()->whereIn()`（`Model::whereIn()` 是本仓 PHPStan baseline 逐类登记的 `staticMethod.notFound`，新类新写点会直接红）；不新增模型属性读（`$model->xxx_id` 同样计入 baseline 的 `property.notFound` 计数，超一条即红）

### 查证 · `/sales/settlement` 的 `receipt_payment_id` 是误报（不补）
`receipt_payment_id` 是 `erp_finance_settlement`（核销记录）的列（install.sql:1237），**不在** `/sales/settlement` 返回的 `erp_finance_ar_ap` 行上；前端那一处是**核销弹窗的入参**（`fields` + `source: /admin/v1/finance/receipt`），不是展示列。唯一会列出核销记录行的 `/finance/settlement` 在两端 Web 里**零消费者**（`grep -rn finance/settlement apps/` 命中 0），抽屉永远不会渲染它。补 `receipt_payment_name` 等于产一个没有读方的键。

### 修复 · CI 静态分析（上一批写路径引入，HEAD 即红）
- `app/controller/bi/WidgetController.php:108-109`（`dashboard_id`/`dataset_id`）、`app/controller/bi/DatasetController.php:101`（`template_id`）：直写模型属性 → 3 处 `property.notFound`。改为 `$item->fill([...])`（三键都在各自 `$fillable` 里；`BiModuleTest` 的落库断言覆盖 `template_id`）

### 新增
- 测试：`tests/DetailContractRegressionTest.php` +1（`testListRowsCarryRemainingForeignKeyNames`，22 断言：5 个页面/7 个键 + 外键仍是 hashid + 既有 `supplier_name` 不回退 + 调拨两键不互换 + 顶级 `parent_name` 空串）
- 负控 5 组变异（票据账户名、付款单账户名、调拨 from/to 互换、运费发票承运商名、权限父级名）逐组**变红于对应断言**（断言数 3/7/11/15/19 依次中靶）

### 修复 · 文档统计标注漂移（`docs` 作业连续红，阻断发版）
- `scripts/doc-stats.sh --check` 的 `stats:tests` / `stats:assertions` 标注自 v1.19.3 批起未随用例增长同步（标注 1025/4827 ≠ 实测 1037/4962），`docs` 作业在 8c882bb、0429d75 两次 push 上连续失败；`release` 作业 `needs: [docs, e2e, php]`，因此 **v1.19.3 与 v1.19.4 两批一直没能发出 tag / Release**
- 按仓库自带的 `bash scripts/doc-stats.sh --fix` 自愈：26 份文档各 1 行（`docs/` + 11 国语言镜像的 README/CLAUDE/FUNCTIONS/EDITIONS），同步标注与紧邻展示值；复验 259 处标注全部一致。前几批的清单里有这一步（v1.19.0 批即「doc-stats 统计标注自愈」），最近两批漏跑

### 验证
- CI 等效双跑（`VERIFY_DB=erp_verify` 临时库 + `TEST_DB_*` 驱动集成测试）：**1048 用例 / 7046 断言 / 2 warning / 8 skipped / 0 失败**，两遍逐字一致（含覆盖采集那一遍，三遍同数字）；相对上一批的**差异恰为本次新增用例**（+1 用例 +22 断言，同环境基线 `/tmp/relcheck.suite.log` 为 1047/7024/8，skipped 数随共享验证库的历史残留浮动、与本次改动无关）
- 覆盖率门禁：整体 **31.47%**（门槛 30，上批 30.91%）、业务层 `app/service` **77.59%**（门槛 40），另按 CI 现行 4/10 阈值复算同样 PASS
- PHPStan `--memory-limit=1G` **0 错误**（改前 HEAD 即红的 3 处已清）、PHP CS Fixer dry-run 0 文件可修
- 14 道 node 门禁全绿（含 `check-fe-detail-items.mjs` 的两端别名表同源比对、`check-column-titles.mjs` 的两端标题一致）、Angular `ng build` 0 warning、React `tsc --noEmit` 干净

### 待办 / 已知遗留（本轮未修）
- **`/finance/receipt`、`/finance/payment` 的 `method` 表单只给 `bank/cash/other` 三值**，DDL 注释是 `cash/bank/wechat/alipay`、本页词典是五值（含微信/支付宝）—— 后端不校验该字段，属选项缺口
- **`erp_dms_document.status` 是 `VARCHAR(20) DEFAULT 'draft'`，页面 dict 却写 `{0:'草稿',1:'发布'}`**：数值字典落在字符串列上永不命中，列表显示 `draft` 原文（`domains/mgmt.ts:418`）
- 三处**编造的前缀状态字典**（`purchase`/`sales`/`crm` 模块通用档）在 140 页里一次也没被查到（不可达），删除前要先改 `check-fe-detail-items.mjs:96/:122` 的故意断言；`COMMON_STATUS[2]='处理中'` 同被 `check-fe-enum-text.mjs:325` 锁住（全库码 2 出现 58 次、无一处该文案）
- **DDL 通道扫描未做**：字典文案是逐页人工抄 `install.sql` 列注释，没有「DDL 注释 ↔ 词典」的自动对差门禁
- **`approved_by`/`assigned_to` 这类 `_by` 外键**：前端已跳过裸编码值（落「-」），后端仍无姓名产出方 —— 补产出方还是接受「-」未裁决
- `DatasetController::update` 的 `template_id` 仍是直写（同文件 `store` 已改 `fill`）：PHPStan 当前未报（`$item` 来自 `find()` 的推断路径不同），本批未动，留给下一轮修静态分析时一并收口
- React `strStatus` 与 Angular 的状态文案兜底口径有观感差异（未统一）

## v1.19.3 (2026-09-22)

**枚举文案 · 关联名收尾批（v1.19.2 续批）**：上一批把「状态列的取值来源」「外键兜底值」定死，这一批把同两类问题在**另外三处出口**补齐 —— Web 两端的**详情抽屉 / 动作结果面板**（列里翻了、抽屉里还是码）、**移动端列表**（Flutter / HarmonyOS 的枚举列仍是裸码、关联列仍是裸 hashid）、以及**派生非 DB 列**（`items_count`/`quotes_count` 这类 `withCount` 键此前驼峰化上屏，即用户报的 `itemsCount`、`quotesCount`；`awarded` 中标标记、`issue_status` 发票开具状态同属这一类）。Web 两端各补 78 处 `cfg.dicts`（48 页单行形态 + 4 处多行块，文案逐字抄自 `install.sql` 该列注释，两端逐页对齐）；后端补 20 余处关联名产出方与 6 类双模外键解码；新增 4 道门禁、扩写 3 道，其中 `check-fe-detail-items.mjs` 新增 **140 页全页面扫**（真配置 × 真引擎渲染，喂 DDL 全量数值型外键列的超集哨兵，共 19320 个哨兵）。范围仍只含缺陷修复与既有能力接线：**0 个新增控制器、0 个新增路由、0 个新增数据表**。

### 修复 · 两端的枚举文案（★契约变化：字典挂法）
- **`cfg.dicts` 对「已显式声明 columns 的键」静默失效**：显式列走 `cellOf`/`columnCell`，字典根本没被查 —— 于是「列表列是文案、详情抽屉是码」。改法两条：字典挂列上（Angular `kind:'map'` + `dict`、React `render: mapText`），`cfg.dicts` 继续供详情抽屉与动作结果面板；两端共 78 处新增（`apps/angular/src/app/config/domains/*.ts`、`apps/react/src/config/domains/*.ts`）
- **`textCol('xxx_id')` 一律按关联列渲染**（`kind: '_id' 结尾 → rel`）：显式声明成文本列的外键此前把裸 hashid 贴上列表
- **`enabled`/`is_*` 17 列按列名识别布尔**（DDL 注释常为空，`is_bool` 只能按名判）：`0/1` → 否/是，语义特异的表（`is_read` 未读/已读）由页面 dicts 覆盖
- **`awarded` 中标标记**（`erp_purchase_rfq_quote.awarded`）改走 `未中标/已中标`；**`issue_status` 发票开具状态**（`erp_finance_invoice`）补 `未开具/已开具/已红冲`

### 修复 · 派生非 DB 列的标题（用户报的 `itemsCount` / `quotesCount`）
- 两端标题表补 **38 个**非 DB 键（两端逐键一致），漏登记时的上屏形状正是驼峰化：`items_count` 明细行数、`quotes_count` 报价数、`users_count` 用户数、`voucher_code` 凭证号、`receiving_code` 收货单号、`production_order_code` 工单编码、`order_channel_no` 渠道订单号、`carrier_service_code` 服务编码、`recipients_names` 接收人、比价回包块（`matrix`/`rfq`/`target_amount`/`target_total`）等，各条注释都标了产出方与依据列（Angular `column-titles.ts` + 两端 `column-titles-extra/part*.ts`，React 的非 DB 键在 `lib/defaults.tsx`；`check-column-titles.mjs` 守两端一致）

### 修复 · 关联名（后端补产出方）
- **品质五页**：IQC `receiving_code`/`product_name`/`standard_name`、IPQC `production_order_code`/`workstation_name`/…、OQC `delivery_code`/…、检验标准 `product_name`、不良品 `product_name`（`source_id` 是多态外键，无法 leftJoin，前端落「-」）
- **采购**：询价单 index 补 `buyer_real_name`（`real_name` 原先只在 `::compare` 产；键名避开 `buyer_name` —— 那在标题表里是税票的「购买方名称」）、报价/明细补 `supplier_name`/`product_name`、结算补 `supplier_name`、收货与销售发货及 RMA 的「关联订单」以 `order_code` leftJoin 带出、制造三页（领料/报工/成本录入）按页内 `order_id` 反查同款别名
- **商品**：补 `category_name`

### 修复 · 移动端（Flutter / HarmonyOS）
- **Flutter 50 页**：枚举列从裸码改文案（每页一个 `_xLabel` switch，末臂 `'$v'` 兜底），外键列改名称（`_warehouseName`/`_productName`/`_bomName`…），l10n 新增 73 条 × zh/en 两份
- **HarmonyOS 21 文件**：`string.json` 两份各 +162 键（`receiving_status_*`/`putaway_status_*`/`shipment_status_*` 等）；新增 `common/RefDialog.ets`（引用只读弹层 + 表单外键选择三件套）；WMS/TMS/OMS/mfg 各页接上

### 修复 · 后端双模外键（hashid / 数字一律收口）
- **BI 三页写路径**（看板/数据集/图表）与 **EAM 三页**：外键出参是 hashid 串，直填 BIGINT 列报 1366 —— `store`/`update` 统一走 `decodeIdFields`（缺省/空串＝不改动或不指定），必填外键解不出即 422（不套 `decodeIdFields`，否则会静默写出 `template_id=0` 的孤儿行）
- **不再用 `required|string` 卡 ID**：`is_string()` 会把数字形态的 ID 判成 422，双模判定统一在 `decodeFlexibleId` 收口；`decodeIdFields` 上提到 `BaseController` 供 bi 复用

### 新增
- 门禁（新增 4 道）：`scripts/check-fe-enum-text.mjs`（132 断言：两端真引擎渲染枚举文案 + 抽屉 + React 真身 import）、`check-fe-captcha-single-submit.mjs`（验证码一次作答只提交一次）、`check-flutter-quality-fk.mjs`（5 个质量页：后端解码 + join 关联键 + 前端下拉/前置）、`check-harmonyos-enum.mjs`（字面量引用的 526 键两边都有）
- 门禁（扩写 3 道）：`check-fe-detail-items.mjs` 新增 I 段 140 页全页面扫、`check-flutter-settlement-fields.mjs` 加两页收集/上送/后端认三方一致与判别子自检、`check-column-titles.mjs` 覆盖新增的 38 个非 DB 键
- 测试：`tests/DetailContractRegressionTest.php` 28 例（详情契约、双模外键、`order_code` 别名、BOM 嵌套编码）、`BiModuleTest`/`EamModuleTest`/`Unit/SourcingTest` 各 +1（双模外键、不卡 `string`、比价面板带名）、Flutter 三个测试文件共 16 例

### 待办 / 已知遗留（本轮未修）
- **后端外键名缺口 8 处**（形状都是「index 里补一行 map 查询」、键名落默认兄弟）：`/finance/bill`、`/finance/payment` 的 `bank_account_id`（无 `bank_account_name` 产出方）、`/inventory/transfer` 的 `from_/to_warehouse_id`、`/sales/settlement` 的 `receipt_payment_id`、`/system/permission` 的 `parent_id`（index 的 select 是列子集，补 `parent_name` 即被默认兄弟接上）、`/tms/freight-invoice` 的 `carrier_id`/`shipment_id`（`shipment_code` 只在 `tms/TrackingController:68` 产，还需登记别名 `shipment_id → shipment_code`）
- **`erp_dms_document.status` 是 `VARCHAR(20) DEFAULT 'draft'`，页面 dict 却写 `{0:'草稿',1:'发布'}`**：数值字典落在字符串列上永不命中，列表显示 `draft` 原文（`domains/mgmt.ts:418`）
- **`/finance/receipt`、`/finance/payment` 的 `method` 表单只给 `bank/cash/other` 三值**，而 DDL 注释是 `cash/bank/wechat/alipay`、本页词典是五值（含微信/支付宝）—— 后端不校验该字段（只 `string`），故不报错，属选项缺口
- 三处**编造的前缀状态字典**（`purchase`/`sales`/`crm` 模块通用档）在 140 页里一次也没被查到（不可达），删除前要先改 `check-fe-detail-items.mjs:96/:122` 的故意断言
- `COMMON_STATUS[2]='处理中'` 的裁决未落：全库码 2 出现 58 次、无一处该文案，被 `check-fe-enum-text.mjs:325` 锁住
- **DDL 通道扫描未做**：本批字典文案逐页人工抄 `install.sql` 列注释，没有「DDL 注释 ↔ 词典」的自动对差门禁
- **`approved_by`/`assigned_to` 这类 `_by` 外键**：前端已跳过裸编码值（落「-」），后端仍无姓名产出方 —— 补产出方还是接受「-」未裁决
- React `strStatus` 与 Angular 的状态文案兜底口径有观感差异（未统一）

## v1.19.2 (2026-09-22)

**状态与关联批**：从界面往回查，把「状态显示成数字/错文案」和「关联显示成裸 hashid」两类展示问题，以及「状态列有、改状态的入口没有」的死胡同补齐。**55 个列表页带状态筛选、其中 23 个的推断状态列此前吃的是按域粗分的兜底字典**（`mfg`/`hr`/`oms` 等一律落 `0待处理 1已生效 2处理中`），改为一律取本资源自己的状态筛选项——筛选下拉里写着「已失效」、列里却写「处理中」的错档至此消失（另 32 个已显式声明状态列、2 个非数字档，本就不受影响）；详情抽屉与列表同源，一并修好。流转入口补了 6 处（采购申请 批准/驳回、录用 Offer 接受/拒绝、招聘候选人 推进/淘汰、费用报销 批准、记账凭证 审核、BOM 生效），并把采购订单 ↔ 采购申请这条断掉的关联接通。范围只含缺陷修复与既有能力的接线：**0 个新增控制器、0 个新增路由、0 个新增数据表**；另修两处「本机必现、CI 不现」的坑：验证码内存 fatal（背景图目录指向 20MP 原图，约半数请求 500，见「验证码背景图内存」）与集成测试隔离（同一测试库复跑必红，见「集成测试隔离」），未修项见文末。

### 修复 · 状态文案（★契约变化：状态列取值来源）
- **状态字典改为「本资源的状态筛选项即字典」**（`dictFromFilter`）：列表/详情里 `status` 的文案一律与筛选下拉逐字一致，域级兜底字典仅在资源没有状态筛选时生效 —— `apps/react/src/lib/defaults.tsx:246`、`apps/angular/src/app/pages/resource-page/columns.ts:73`，两端各在 `inferColumns` 收 `filter` 入参（`ResourcePage` 传 `cfg.filters`）
- **受影响的 23 个叶子页**（推断状态列 + 有状态筛选）：如 `hr_leave.status=2` 曾显示「处理中」（真值「已驳回」）、`erp_mfg_bom.status=2` 曾显示「处理中」（真值「已失效」）、`erp_hr_candidate.status=1` 曾显示「已生效」（真值「初筛通过」）、`erp_project.status=2` 曾显示「处理中」（真值「已延期」）、`erp_quality_ipqc.status=1` 曾显示「已生效」（真值「已完成」）
- **`apply_id → apply_code` 别名**补进关联解析表（采购订单的申请单号），与既有的 `supplier_id → supplier_name` 同机制

### 修复 · 关联展示（★契约变化：外键兜底值）
- **外键三条解析途径全落空时落「-」占位，不再贴裸 hashid**：`*_name` 兄弟 / `with` 关系对象 / `fields.source` 选项任一命中即出名称；三条都没有（孤儿外键、选项未加载）时贴出来的雪花编码在界面上无处可用（`defaults.tsx` 的 `fallbackValue`、`columns.ts` 的 `fallbackCell`）
- **`/admin/v1/mfg/bom` 列表带出产品名**：`index` 加 `with => ['product']`（`BomController.php:81`），前端按关系对象列出产品名；`encodeIds` 递归，嵌套 `product.id` 同样是 hashid

### 修复 · 状态流转入口（原本有状态列、界面上却无路可走）
- **采购申请**：补「批准」(0→1) /「驳回」(0→2)，按状态显隐（`trade.ts:84`）；**状态 3「已转订单」不设按钮**，由采购订单创建时回写
- **采购订单 ↔ 采购申请接通**：`store`/`update` 解码并落 `apply_id`，落库后把该申请置 3（`OrderController.php:391` 的 `markApplyOrdered`）；出参经 `leftJoin` 补 `apply_code`（`OrderController.php:62`），表单新增「采购申请」选择器（可空）
- **录用 Offer**：原只有「发出」，发出(1)之后界面无任何出口 —— 补「接受」(1→2，候选人 3→4 入职) /「拒绝」(1→3，候选人退回 2 面试中)，三个动作各按 from 状态显隐（`RecruitService::sendOffer/acceptOffer/rejectOffer` 三处守卫）
- **招聘候选人**：补「推进下一级」/「淘汰」，与 `RecruitService::canAdvanceCandidateStatus` 的允许集对齐（推进仅 status<4、淘汰仅当前非 5）
- **费用报销**：补「批准」(0→1)，与 `ExpenseController::update` 只接受 `status===1` 一致
- **BOM 管理**：补状态筛选（0草稿/1已生效/2已失效）与「生效」动作；生效的副作用是同产品其它已生效 BOM 转失效，故不提供「失效」按钮（由新版本取代，`ManufacturingService::BOM_STATUS_FLOW`）
- **记账凭证**：补「审核」(0→1)（`finance.ts:33`）—— `VoucherController::update` 只放行 `status` 0→1（`:211`，已审核不可再改、期间已结账 422），但界面既无状态项也无按钮，草稿凭证永远审不了

### 修复 · 验证码背景图内存（本机必现，CI 无关）
- **`background_dir` 指原图目录 ⇒ 约半数验证码请求 fatal**：poster-php 的 `AbstractCaptcha` 对目录内图片**无尺寸守卫**，`array_rand` 选中后整张解码；上游 `GdDriver::MAX_PIXELS = 40000000` 是**像素口径**（20MP 通过校验，折算却要 ~170MB），128M 上限下永不触发。实测一张 5472×3648（20MP）照片解码增量 **85.5MB** ⇒ `/api/v1/captcha/generate` 500、`phpunit` 全套 fatal（`Allowed memory size ... exhausted in GdDriver.php:35`）
- **改法：背景目录指向压缩副本**（`config/poster.php` → `public/img/captcha`），新增 `scripts/gen-captcha-bg.php` 把原图压到长边 ≤800px（原图一张不动）。实测单张解码 85.5MB → **~2MB**，30 次 random 验证码在**默认 128M** 下峰值 20.4MB
- 影响面：CI 与任何未准备该目录的环境不受影响 —— 目录不存在时按上游默认回退程序化背景（不报错）。`public/img` 及其副本目录均已被 `.gitignore` 整目录忽略，故**不需要**为部署准备背景图
- 新增照片后重跑：`php scripts/gen-captcha-bg.php`（提示已写进 `config/poster.php` 注释）

### 修复 · 集成测试隔离（同一测试库复跑必红）
- **脚手架在回退路径下只清 setUp、不清 tearDown**：`database/h34_hr.sql` 不在仓内（从未交付），H3/H4 脚手架走「表已在位」分支 —— setUp 用 `truncate` 保证空表起步，而 tearDown 的 `dropTableIfCreated` 对**本来就存在**的真表是空操作（2026-09-14 误删 13 张 HR 真表之后立的规矩：只删自己建的表），于是 `H4SocialTest` 种下的 3 条社保规则留在库尾
- **后果**：`H34AdversarialIntegrationTest` 是不继承脚手架的对抗性用例（按 id 自清自己的行），却断言**全表计数**（`total==3`，`H34AdversarialIntegrationTest.php:692`）⇒ 同一测试库跑第二遍读到 6（`Failed asserting that 6 is identical to 3`）。CI 每次新库只跑一遍故为绿，**本机复跑必踩**，且失败点离真因很远（看着像业务缺陷）
- 改法：回退路径打标（`$fallbackSchema`），tearDown 复用 setUp 的同一段 `truncateH34Tables()` —— 对抗性两类本就自清（`H34PayslipAdversarialTest` 同款 `deleteOwnRows`），闭环后同一库连跑两遍均绿（本机实测两轮各 1036 用例 0 失败）
- 注：`H1H2Scaffold` 结构相同，但仓内没有任何外类断言其表族计数（仅自身子类引用），本轮不动

### 新增
- `scripts/gen-captcha-bg.php`：背景图压缩副本生成（用法与「为什么需要」写在文件头；输出目录缺失时验证码回退程序化背景）
- 测试：`tests/PurchaseModuleTest.php` +2（订单 store 回写关联申请为 3、申请 update 只带 `status` 不丢其余列）、`tests/DetailContractRegressionTest.php` +1（BOM 列表带出产品名 + 嵌套 `product.id` 编码，去掉 `with` 即失败）
- `scripts/check-fe-detail-items.mjs` +5 断言：状态筛选即字典（`hr_leave` 2 出「已驳回」而非通用档文案）、详情条目同源、无筛选时按前缀回退、孤儿外键落「-」、关系对象在**列表列**上也改名取名称（BOM 那条路径）

### 待办 / 已知遗留（本轮未修）
- **状态由别的模块流程驱动、界面上没有也不该有按钮**（加按钮会双驱状态机），已逐条核对写入者：采购收货→采购订单 3 已收货/2 部分收货（`ReceiveController.php:317-351`）、销售发货→销售订单 3 已发货（`DeliveryController.php:320`）、WMS 出库→履约管理（`WmsOutboundService.php:137`，`lockForUpdate`）、核销→应收应付（`SettlementController.php:90-132`）、委外发料/收货→委外加工 1 已发料/3 已核销（`SubcontractService.php:130/225`）
- **状态只有泛型 `PUT` 一条写入路径**（`status` 在 `$fillable` 内、无守卫路由、界面也无入口）：`sales_quotation`、`transfer`、`check_task`、`project`/`project_task`、`crm_opportunity`、`finance_cost_center`/`finance_profit_center` —— 属**界面缺口**（补状态项或动作即可，后端不用动），本轮未逐页补
- **销售发货的 0「待出库」在 `store` 同一请求内即置 1**（`DeliveryController.php:183 → 276`，创建即出库并生成应收）：界面上没有独立出库步骤，该状态实际看不到，是否拆分属流程设计问题
- **费用科目无列表接口**：`erp_finance_account` 有表有模型、无控制器/路由，费用报销表单仍要求按 ID 填写
- **培训课程 报名/取消/完成**（`/hr/course/{id}/enroll|cancel|complete`）按员工维度操作选课记录、非课程行操作，前端仍无入口（两端注释已标「留待员工学习记录页」）
- 新增的动作文案（`推进下一级`/`淘汰`/`已批准`/`已驳回` 等）未进 i18n 词典，与既有 81/610 的缺口同类

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
