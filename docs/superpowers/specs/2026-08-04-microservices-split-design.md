# 微服务拆分架构设计

> 日期：2026-08-04 | 状态：已确认

## 概述

将当前 webman v2 单体应用拆分为 9 个独立服务，通过 gRPC 通信。分两阶段执行：
- **Phase 1**：PHP 拆分 + gRPC 通信（保持 PHP 技术栈）
- **Phase 2**：PHP → Go 渐进迁移（核心服务逐个重写）

## 决策记录

| 决策点 | 选择 | 理由 |
|--------|------|------|
| 拆分粒度 | 全部业务模块独立（9个服务） | 最大化独立部署和团队自治 |
| 技术栈 | Phase 1 PHP, Phase 2 PHP→Go | 先验证架构，再优化性能 |
| 数据库 | 共享 MySQL，代码层隔离 | 降低初期风险，后续可拆分 |
| 通信协议 | gRPC (protobuf) | 强类型契约、高性能二进制、跨语言支持 |

## 服务划分

| 服务 | 端口(gRPC/HTTP) | 模型数 | 核心职责 |
|------|----------------|--------|---------|
| ERP Core | 50051/8787 | ~35 | 认证授权、商品主数据、采购/销售、审批、通知、报表 |
| OMS | 50052/8788 | 6 | 订单接入、库存预占/分配、履约编排、RMA |
| WMS | 50053/8789 | 10 | 收货、上架、波次、拣货、打包、发货确认 |
| TMS | 50054/8790 | 6 | 承运商、运费报价、运单、轨迹追踪、运费结算 |
| Finance | 50055/8791 | 22 | 应收应付、凭证、收付款、总账/明细账、三表、固定资产、税务 |
| CRM | 50056/8792 | 15 | 商机/漏斗、联系人、公海池、报价/合同、营销、工单 |
| HR | 50057/8793 | 8 | 部门/员工/职位、考勤、请假、薪资 |
| Manufacturing | 50058/8794 | 6 | BOM、生产订单、工艺路线、工作站、MRP |
| Project | 50059/8795 | 3 | 项目管理、任务分解、工时 |

## gRPC 通信设计

### Proto 文件组织

```
proto/
├── common/                    # 共享类型
│   ├── types.proto           # 通用类型（分页、排序、响应格式）
│   ├── money.proto           # 金额类型
│   └── address.proto         # 地址类型
├── erp/
│   ├── auth.proto            # Token验证、权限校验
│   ├── product.proto         # 商品/SKU/分类/品牌查询
│   ├── inventory.proto       # 库存查询、成本查询
│   ├── supplier.proto        # 供应商查询
│   └── customer.proto        # 客户查询
├── oms/
│   ├── order.proto           # 订单创建、状态查询、取消
│   ├── fulfillment.proto     # 履约状态更新
│   └── rma.proto             # 退换货状态
├── wms/
│   ├── inbound.proto         # 收货通知、上架确认
│   ├── outbound.proto        # 波次创建、拣货/打包/发货状态
│   └── inventory.proto       # 库存变动通知
├── tms/
│   ├── shipment.proto        # 运单创建、状态更新
│   ├── tracking.proto        # 轨迹查询、Webhook回调
│   └── rate.proto            # 运费查询
├── finance/
├── crm/
├── hr/
├── manufacturing/
└── project/
```

### 核心流程：发货 (OMS → WMS → TMS → Finance)

```
OMS:创建履约 → WMS:创建波次 → WMS:拣货 → WMS:打包
    → TMS:创建运单 → TMS:发货确认
                        ↓ gRPC
                   WMS:消耗库存+更新履约
                        ↓ gRPC
                   OMS:订单状态→已发货
                        ↓ gRPC
                   Finance:生成应收账款
```

### 认证传递

- JWT Token 通过 gRPC metadata 跨服务传递
- ERP Core `AuthService.ValidateToken()` 作为统一认证入口
- 每个服务可缓存权限校验结果（Redis 60s）

### 错误处理

- 统一 gRPC 状态码映射（NotFound→5, InvalidArgument→3, Internal→13）
- 标准错误格式：`{ code: int32, message: string, details: []Any }`

## 项目结构

```
~/wwwroot/
├── erp-core/              # ERP 核心（当前仓库改名）
├── erp-oms/               # OMS 订单管理
├── erp-wms/               # WMS 仓储管理
├── erp-tms/               # TMS 运输管理
├── erp-finance/           # 财务服务
├── erp-crm/               # CRM 服务
├── erp-hr/                # 人力资源服务
├── erp-manufacturing/     # 生产制造服务
├── erp-project/           # 项目管理服务
├── proto/                 # 共享 protobuf 定义（独立仓库）
└── erp-common/            # 共享 PHP Composer 包
    ├── src/
    │   ├── GrpcClient.php       # gRPC 客户端基类
    │   ├── GrpcServer.php       # gRPC 服务端基类
    │   ├── ServiceRegistry.php  # 服务地址注册表
    │   └── GrpcException.php    # gRPC 错误标准化
    ├── snowflake/
    ├── hashids/
    └── encryption/
```

每个服务标准化结构：

```
erp-<name>/
├── app/
│   ├── controller/       # HTTP 控制器
│   ├── service/          # 业务逻辑
│   ├── model/            # 仅本服务职责的表
│   ├── grpc/
│   │   ├── Server/       # gRPC 服务实现
│   │   └── Client/       # 调用其他服务的 gRPC 客户端
│   └── middleware/
├── config/
├── proto/                # 本服务 proto（或引用共享 proto）
├── database/migrations/
├── tests/
├── .env
├── composer.json
├── Dockerfile
└── README.md
```

## Phase 1 实施计划

| Sprint | 内容 | 产出 |
|--------|------|------|
| Sprint 1: 基础设施 | proto 仓库 + erp-common 包 + PHP gRPC stub | proto 定义、公共包可用 |
| Sprint 2: OMS | 提取 OMS，跨模块调用改 gRPC | OMS 独立运行 |
| Sprint 3: WMS+TMS | 提取 WMS/TMS，改写跨服务调用 | WMS/TMS 独立运行 |
| Sprint 4: Finance+CRM | 提取 Finance/CRM | 财务/CRM 独立运行 |
| Sprint 5: HR+Mfg+Project | 提取剩余模块，全链路测试，部署更新 | 9 服务全部独立运行 |

## 风险与缓解

| 风险 | 缓解措施 |
|------|---------|
| gRPC 延迟增加 | keep-alive 连接池、热点数据 Redis 缓存、共享 DB 减少跨服务查询 |
| 分布式事务 | Phase 1 共享 DB 可用本地事务；跨服务操作用 Saga 补偿 |
| Proto 版本兼容 | 遵循 protobuf 向后兼容规则，不删字段不改编号 |
| 开发效率下降 | erp-common 统一基类、Docker Compose 一键启动 |

## 服务依赖图

```
ERP Core ←── OMS ←── WMS ←── TMS
    ↑          ↑       ↑       ↑
    └──────────┼───────┼───────┤
               ↓       ↓       ↓
           Finance  CRM  HR  Manufacturing  Project
              ↑       ↑   ↑        ↑           ↑
              └───────┴───┴────────┴───────────┘
                       (都依赖 ERP Core 认证)
```
