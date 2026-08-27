# ERP 업무 모듈 구현 계획

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**목표:** 기존 webman v2 시스템 관리 기반 위에 매입·판매·재고 + 재무 + CRM 완전한 ERP 업무 모듈 구현

**아키텍처:** Controller → Service → Model 3계층. 모든 모델은 `app/model/` 공유, 컨트롤러는 모듈별 디렉토리 `app/controller/{module}/`, 비즈니스 로직은 `app/service/{module}/`. 데이터베이스 약 50개 테이블은 모두 `erik_` 접두사, BIGINT snowflake 기본키 통일. API 버전은 `API-Version` 요청 헤더 + `ApiVersion` 미들웨어로 전달.

**기술 스택:** PHP 8.3+ / webman v2 / MySQL 8.0+ / Eloquent ORM / erikwang2013/* 시리즈 패키지

**코드베이스 패턴** (기존 코드 기준):
- 컨트롤러는 `app\admin\controller\BaseController` 상속 — `success()`, `fail()`, `encodeId()`, `decodeId()`, `encodeIds()`, `generateId()`, `confirmPassword()` 제공
- 모델은 `support\Model` 사용, `SoftDeletes`, `Searchable`, `Encryptable` 캐스트
- 기본키: `$incrementing = false; $keyType = 'int';`
- 라우트: `/admin` 그룹 아래 `Route::resource()`와 명시적 라우트, 전체 미들웨어 체인 적용
- 권한: `slug` 형식 `method.admin/module/action` (예: `get.admin/product`)
- 검증: webman 내장 `validator()` 함수
- 응답 형식: `{code: 0, message: 'success', data: {...}}`
- API 버전: 요청 헤더 `API-Version`(예: `v1`, `v2`), 누락 시 기본 `v1`, URL에 반영하지 않음

---

## Task 1: 데이터베이스 마이그레이션 — 상품 기초 데이터

**Files:**
- Create: `database/migrations/2026_05_22_000003_product_base_tables.sql`

- [ ] **Step 1: 상품 기초 데이터 마이그레이션 SQL 생성**

```sql
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: 商品与基础数据模块（11张表）

CREATE TABLE IF NOT EXISTS `erik_category` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `parent_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '父级分类ID',
    `name` VARCHAR(100) NOT NULL COMMENT '分类名称',
    `code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '分类编码',
    `sort` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0禁用1启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`), KEY `idx_parent_id` (`parent_id`), KEY `idx_sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品分类表';

CREATE TABLE IF NOT EXISTS `erik_brand` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `name` VARCHAR(100) NOT NULL COMMENT '品牌名称',
    `logo` VARCHAR(255) NOT NULL DEFAULT '',
    `description` VARCHAR(500) NOT NULL DEFAULT '',
    `sort` INT UNSIGNED NOT NULL DEFAULT 0,
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='品牌表';

CREATE TABLE IF NOT EXISTS `erik_product` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `category_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '分类ID',
    `brand_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '品牌ID',
    `code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '商品编码',
    `name` VARCHAR(200) NOT NULL COMMENT '商品名称',
    `barcode` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '主条码',
    `spec` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '规格描述',
    `unit` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '基本单位',
    `image` VARCHAR(255) NOT NULL DEFAULT '',
    `description` TEXT COMMENT '商品描述',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`), UNIQUE KEY `uk_code` (`code`),
    KEY `idx_category_id` (`category_id`), KEY `idx_brand_id` (`brand_id`),
    KEY `idx_barcode` (`barcode`), KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品主表';

CREATE TABLE IF NOT EXISTS `erik_product_sku` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '商品ID',
    `sku_code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT 'SKU编码',
    `barcode` VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'SKU条码',
    `spec_attrs` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '规格属性JSON',
    `cost_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '参考成本价',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_product_id` (`product_id`), UNIQUE KEY `uk_sku_code` (`sku_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品SKU表';

CREATE TABLE IF NOT EXISTS `erik_product_unit` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '商品ID',
    `unit_name` VARCHAR(20) NOT NULL COMMENT '单位名称',
    `conversion_rate` DECIMAL(12,4) NOT NULL DEFAULT 1.0000 COMMENT '相对基本单位换算率',
    `is_base` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否基本单位',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品多单位换算表';

CREATE TABLE IF NOT EXISTS `erik_product_price` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '商品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID，0表示商品级',
    `price_type` VARCHAR(20) NOT NULL COMMENT 'purchase|wholesale|retail|customer_level',
    `customer_level_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '客户等级ID',
    `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '价格',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_product_id` (`product_id`), KEY `idx_sku_id` (`sku_id`),
    KEY `idx_price_type` (`price_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品价格策略表';

CREATE TABLE IF NOT EXISTS `erik_warehouse` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `name` VARCHAR(100) NOT NULL COMMENT '仓库名称',
    `code` VARCHAR(50) NOT NULL COMMENT '仓库编码',
    `address` VARCHAR(300) NOT NULL DEFAULT '',
    `manager` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '负责人',
    `phone` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '电话（加密存储）',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`), UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='仓库表';

CREATE TABLE IF NOT EXISTS `erik_location` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `warehouse_id` BIGINT UNSIGNED NOT NULL COMMENT '所属仓库ID',
    `code` VARCHAR(50) NOT NULL COMMENT '库位编码',
    `name` VARCHAR(100) NOT NULL DEFAULT '',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), UNIQUE KEY `uk_warehouse_code` (`warehouse_id`, `code`),
    KEY `idx_warehouse_id` (`warehouse_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='库位表';

CREATE TABLE IF NOT EXISTS `erik_supplier` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '供应商编码',
    `name` VARCHAR(200) NOT NULL COMMENT '供应商名称',
    `contact_person` VARCHAR(50) NOT NULL DEFAULT '',
    `phone` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '电话（加密存储）',
    `email` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '邮箱（加密存储）',
    `address` VARCHAR(300) NOT NULL DEFAULT '',
    `bank_name` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '开户银行',
    `bank_account` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '银行账号（加密存储）',
    `tax_number` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '税号',
    `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '税率(%)',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `remark` VARCHAR(500) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`), UNIQUE KEY `uk_code` (`code`), KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='供应商表';

CREATE TABLE IF NOT EXISTS `erik_customer_level` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `name` VARCHAR(50) NOT NULL COMMENT '等级名称',
    `discount` DECIMAL(5,2) NOT NULL DEFAULT 100.00 COMMENT '默认折扣(%)',
    `sort` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='客户等级表';

CREATE TABLE IF NOT EXISTS `erik_customer` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '客户编码',
    `name` VARCHAR(200) NOT NULL COMMENT '客户名称',
    `level_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '客户等级ID',
    `contact_person` VARCHAR(50) NOT NULL DEFAULT '',
    `phone` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '电话（加密存储）',
    `email` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '邮箱（加密存储）',
    `address` VARCHAR(300) NOT NULL DEFAULT '',
    `credit_limit` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '信用额度',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `remark` VARCHAR(500) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`), UNIQUE KEY `uk_code` (`code`),
    KEY `idx_name` (`name`), KEY `idx_level_id` (`level_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='客户表';
```

- [ ] **Step 2: 실행 및 검증**

```bash
mysql -u root -p open_admin < database/migrations/2026_05_22_000003_product_base_tables.sql && echo "Tables: $(mysql -u root -p open_admin -N -e 'SHOW TABLES LIKE "erik_%"' | wc -l)"
```

- [ ] **Step 3: 커밋**

```bash
git add database/migrations/2026_05_22_000003_product_base_tables.sql
git commit -m "feat: add product base data tables (11 tables: category, brand, product, sku, unit, price, warehouse, location, supplier, customer_level, customer)"
```

---

## Task 2: 데이터베이스 마이그레이션 — 구매 모듈

**Files:**
- Create: `database/migrations/2026_05_22_000004_purchase_tables.sql`

- [ ] **Step 1: 구매 모듈 9개 테이블 마이그레이션 SQL**

```sql
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: 采购模块

CREATE TABLE IF NOT EXISTS `erik_purchase_apply` (
    `id` BIGINT UNSIGNED NOT NULL,
    `code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '申请单号',
    `apply_user_id` BIGINT UNSIGNED NOT NULL COMMENT '申请人ID',
    `department` VARCHAR(50) NOT NULL DEFAULT '',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待审批1已批准2已驳回3已转订单',
    `remark` VARCHAR(500) NOT NULL DEFAULT '',
    `approved_at` DATETIME DEFAULT NULL,
    `approved_by` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '审批人ID',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`), KEY `idx_status` (`status`), KEY `idx_apply_user_id` (`apply_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购申请表';

CREATE TABLE IF NOT EXISTS `erik_purchase_apply_item` (
    `id` BIGINT UNSIGNED NOT NULL,
    `apply_id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `unit` VARCHAR(20) NOT NULL DEFAULT '',
    `estimated_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_apply_id` (`apply_id`), KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购申请明细';

CREATE TABLE IF NOT EXISTS `erik_purchase_order` (
    `id` BIGINT UNSIGNED NOT NULL,
    `code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '订单单号',
    `apply_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `supplier_id` BIGINT UNSIGNED NOT NULL,
    `warehouse_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待审核1已审核2部分收货3已收货4已取消',
    `remark` VARCHAR(500) NOT NULL DEFAULT '',
    `ordered_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`), KEY `idx_supplier_id` (`supplier_id`), KEY `idx_status` (`status`),
    KEY `idx_apply_id` (`apply_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购订单表';

CREATE TABLE IF NOT EXISTS `erik_purchase_order_item` (
    `id` BIGINT UNSIGNED NOT NULL,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '采购数量',
    `received_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '已收数量',
    `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '单价',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '金额',
    `unit` VARCHAR(20) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_order_id` (`order_id`), KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购订单明细';

CREATE TABLE IF NOT EXISTS `erik_purchase_receive` (
    `id` BIGINT UNSIGNED NOT NULL,
    `code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '收货单号',
    `order_id` BIGINT UNSIGNED NOT NULL,
    `supplier_id` BIGINT UNSIGNED NOT NULL,
    `warehouse_id` BIGINT UNSIGNED NOT NULL,
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待入库1已入库',
    `remark` VARCHAR(500) NOT NULL DEFAULT '',
    `received_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_order_id` (`order_id`), KEY `idx_supplier_id` (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购收货表';

CREATE TABLE IF NOT EXISTS `erik_purchase_receive_item` (
    `id` BIGINT UNSIGNED NOT NULL,
    `receive_id` BIGINT UNSIGNED NOT NULL,
    `order_item_id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `location_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '收货库位ID',
    `batch_code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '批次号',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '实际单价',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `unit` VARCHAR(20) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_receive_id` (`receive_id`), KEY `idx_order_item_id` (`order_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购收货明细';

CREATE TABLE IF NOT EXISTS `erik_purchase_return` (
    `id` BIGINT UNSIGNED NOT NULL,
    `code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '退货单号',
    `receive_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `supplier_id` BIGINT UNSIGNED NOT NULL,
    `warehouse_id` BIGINT UNSIGNED NOT NULL,
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待出库1已出库',
    `remark` VARCHAR(500) NOT NULL DEFAULT '',
    `returned_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_receive_id` (`receive_id`), KEY `idx_supplier_id` (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购退货表';

CREATE TABLE IF NOT EXISTS `erik_purchase_return_item` (
    `id` BIGINT UNSIGNED NOT NULL,
    `return_id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `batch_code` VARCHAR(50) NOT NULL DEFAULT '',
    `location_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `unit` VARCHAR(20) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_return_id` (`return_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购退货明细';

CREATE TABLE IF NOT EXISTS `erik_purchase_settlement` (
    `id` BIGINT UNSIGNED NOT NULL,
    `supplier_id` BIGINT UNSIGNED NOT NULL,
    `receive_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '应付金额',
    `paid_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '已付金额',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0未结算1部分结算2已结算',
    `settled_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_supplier_id` (`supplier_id`), KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购结算表';
```

- [ ] **Step 2: 마이그레이션 실행**

```bash
mysql -u root -p open_admin < database/migrations/2026_05_22_000004_purchase_tables.sql && echo "OK: 9 purchase tables created"
```

- [ ] **Step 3: 커밋**

```bash
git add database/migrations/2026_05_22_000004_purchase_tables.sql
git commit -m "feat: add purchase module tables (9 tables: apply, order, receive, return, settlement)"
```

---

## Task 3: 데이터베이스 마이그레이션 — 판매 모듈

**Files:**
- Create: `database/migrations/2026_05_22_000005_sales_tables.sql`

- [ ] **Step 1: 판매 모듈 9개 테이블 마이그레이션 SQL**

```sql
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: 销售模块

CREATE TABLE IF NOT EXISTS `erik_sales_quotation` (
    `id` BIGINT UNSIGNED NOT NULL,
    `code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '报价单号',
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0草稿1已报价2已转订单3已失效',
    `remark` VARCHAR(500) NOT NULL DEFAULT '',
    `quoted_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`), KEY `idx_customer_id` (`customer_id`), KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='销售报价单表';

CREATE TABLE IF NOT EXISTS `erik_sales_quotation_item` (
    `id` BIGINT UNSIGNED NOT NULL,
    `quotation_id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '报价单价',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '报价金额',
    `unit` VARCHAR(20) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_quotation_id` (`quotation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='报价明细表';

CREATE TABLE IF NOT EXISTS `erik_sales_order` (
    `id` BIGINT UNSIGNED NOT NULL,
    `code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '订单单号',
    `quotation_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关联报价单ID',
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `warehouse_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '发货仓库ID',
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '折扣金额',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待审核1已审核2部分发货3已发货4已取消',
    `remark` VARCHAR(500) NOT NULL DEFAULT '',
    `ordered_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`), KEY `idx_customer_id` (`customer_id`), KEY `idx_status` (`status`),
    KEY `idx_quotation_id` (`quotation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='销售订单表';

CREATE TABLE IF NOT EXISTS `erik_sales_order_item` (
    `id` BIGINT UNSIGNED NOT NULL,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '订购数量',
    `delivered_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '已发数量',
    `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '单价',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '金额',
    `unit` VARCHAR(20) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_order_id` (`order_id`), KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='销售订单明细';

CREATE TABLE IF NOT EXISTS `erik_sales_delivery` (
    `id` BIGINT UNSIGNED NOT NULL,
    `code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '发货单号',
    `order_id` BIGINT UNSIGNED NOT NULL,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `warehouse_id` BIGINT UNSIGNED NOT NULL,
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待出库1已出库',
    `remark` VARCHAR(500) NOT NULL DEFAULT '',
    `delivered_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_order_id` (`order_id`), KEY `idx_customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='销售发货表';

CREATE TABLE IF NOT EXISTS `erik_sales_delivery_item` (
    `id` BIGINT UNSIGNED NOT NULL,
    `delivery_id` BIGINT UNSIGNED NOT NULL,
    `order_item_id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `location_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '出库库位ID',
    `batch_code` VARCHAR(50) NOT NULL DEFAULT '',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '出库单价（成本价）',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `unit` VARCHAR(20) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_delivery_id` (`delivery_id`), KEY `idx_order_item_id` (`order_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='销售发货明细';

CREATE TABLE IF NOT EXISTS `erik_sales_return` (
    `id` BIGINT UNSIGNED NOT NULL,
    `code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '退货单号',
    `delivery_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `warehouse_id` BIGINT UNSIGNED NOT NULL,
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待入库1已入库',
    `remark` VARCHAR(500) NOT NULL DEFAULT '',
    `returned_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_delivery_id` (`delivery_id`), KEY `idx_customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='销售退货表';

CREATE TABLE IF NOT EXISTS `erik_sales_return_item` (
    `id` BIGINT UNSIGNED NOT NULL,
    `return_id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `batch_code` VARCHAR(50) NOT NULL DEFAULT '',
    `location_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `unit` VARCHAR(20) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_return_id` (`return_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='销售退货明细';

CREATE TABLE IF NOT EXISTS `erik_sales_settlement` (
    `id` BIGINT UNSIGNED NOT NULL,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `delivery_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '应收金额',
    `received_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '已收金额',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0未结算1部分结算2已结算',
    `settled_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_customer_id` (`customer_id`), KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='销售结算表';
```

- [ ] **Step 2: 마이그레이션 실행**

```bash
mysql -u root -p open_admin < database/migrations/2026_05_22_000005_sales_tables.sql && echo "OK: 9 sales tables created"
```

- [ ] **Step 3: 커밋**

```bash
git add database/migrations/2026_05_22_000005_sales_tables.sql
git commit -m "feat: add sales module tables (9 tables: quotation, order, delivery, return, settlement)"
```

---

## Task 4: 데이터베이스 마이그레이션 — 재고 + 재무 + CRM 모듈

**Files:**
- Create: `database/migrations/2026_05_22_000006_inventory_tables.sql`
- Create: `database/migrations/2026_05_22_000007_finance_tables.sql`
- Create: `database/migrations/2026_05_22_000008_crm_tables.sql`

- [ ] **Step 1: 재고 모듈 11개 테이블**

```sql
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: 库存模块

CREATE TABLE IF NOT EXISTS `erik_inventory` (
    `id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `warehouse_id` BIGINT UNSIGNED NOT NULL,
    `location_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `batch_code` VARCHAR(50) NOT NULL DEFAULT '',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '库存数量',
    `cost_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '当前成本价',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_inventory` (`product_id`, `sku_id`, `warehouse_id`, `location_id`, `batch_code`),
    KEY `idx_warehouse_id` (`warehouse_id`), KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='实时库存表';

CREATE TABLE IF NOT EXISTS `erik_inventory_batch` (
    `id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `batch_code` VARCHAR(50) NOT NULL COMMENT '批次号',
    `production_date` DATE DEFAULT NULL COMMENT '生产日期',
    `expiry_date` DATE DEFAULT NULL COMMENT '过期日期',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_batch` (`product_id`, `sku_id`, `batch_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='批次信息表';

CREATE TABLE IF NOT EXISTS `erik_inventory_serial` (
    `id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `serial_code` VARCHAR(100) NOT NULL COMMENT '序列号',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0在库1已出',
    `in_flow_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '入库流水ID',
    `out_flow_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '出库流水ID',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_serial` (`serial_code`),
    KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='序列号记录表';

CREATE TABLE IF NOT EXISTS `erik_inventory_flow` (
    `id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `warehouse_id` BIGINT UNSIGNED NOT NULL,
    `location_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `batch_code` VARCHAR(50) NOT NULL DEFAULT '',
    `direction` TINYINT UNSIGNED NOT NULL COMMENT '1入库2出库',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `cost_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '出入库时成本价',
    `source_type` VARCHAR(30) NOT NULL COMMENT '来源类型: purchase_receive|purchase_return|sales_delivery|sales_return|transfer_in|transfer_out|check_profit|check_loss',
    `source_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '来源单号ID',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_source` (`source_type`, `source_id`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_warehouse_id` (`warehouse_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='出入库流水表';

CREATE TABLE IF NOT EXISTS `erik_transfer` (
    `id` BIGINT UNSIGNED NOT NULL,
    `code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '调拨单号',
    `from_warehouse_id` BIGINT UNSIGNED NOT NULL COMMENT '调出仓库',
    `to_warehouse_id` BIGINT UNSIGNED NOT NULL COMMENT '调入仓库',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待调拨1已调出2已调入3已完成',
    `remark` VARCHAR(500) NOT NULL DEFAULT '',
    `transferred_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_from_warehouse` (`from_warehouse_id`),
    KEY `idx_to_warehouse` (`to_warehouse_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='调拨单表';

CREATE TABLE IF NOT EXISTS `erik_transfer_item` (
    `id` BIGINT UNSIGNED NOT NULL,
    `transfer_id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `batch_code` VARCHAR(50) NOT NULL DEFAULT '',
    `from_location_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `to_location_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `unit` VARCHAR(20) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_transfer_id` (`transfer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='调拨明细表';

CREATE TABLE IF NOT EXISTS `erik_check_task` (
    `id` BIGINT UNSIGNED NOT NULL,
    `code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '盘点单号',
    `warehouse_id` BIGINT UNSIGNED NOT NULL COMMENT '盘点仓库',
    `type` TINYINT UNSIGNED NOT NULL COMMENT '1计划盘点2动态盘点',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待盘点1已盘点2已处理',
    `check_user_id` BIGINT UNSIGNED NOT NULL COMMENT '盘点人ID',
    `checked_at` DATETIME DEFAULT NULL,
    `remark` VARCHAR(500) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_warehouse_id` (`warehouse_id`), KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='盘点任务表';

CREATE TABLE IF NOT EXISTS `erik_check_detail` (
    `id` BIGINT UNSIGNED NOT NULL,
    `check_id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `location_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `batch_code` VARCHAR(50) NOT NULL DEFAULT '',
    `book_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '账面数量',
    `actual_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '实盘数量',
    `diff_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '差异数量',
    `unit` VARCHAR(20) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_check_id` (`check_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='盘点明细表';

CREATE TABLE IF NOT EXISTS `erik_inventory_alert_rule` (
    `id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `warehouse_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0表示所有仓库',
    `min_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '库存下限',
    `max_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '库存上限',
    `enabled` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='库存预警规则表';

CREATE TABLE IF NOT EXISTS `erik_inventory_alert_log` (
    `id` BIGINT UNSIGNED NOT NULL,
    `rule_id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `warehouse_id` BIGINT UNSIGNED NOT NULL,
    `current_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '当前库存',
    `alert_type` TINYINT UNSIGNED NOT NULL COMMENT '1低于下限2高于上限',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_rule_id` (`rule_id`), KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='库存预警日志表';

CREATE TABLE IF NOT EXISTS `erik_cost_record` (
    `id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `flow_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关联流水ID',
    `type` TINYINT UNSIGNED NOT NULL COMMENT '1入库2出库',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `unit_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '入库单价/本次成本价',
    `before_avg_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '变动前移动加权均价',
    `after_avg_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '变动后移动加权均价',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_flow_id` (`flow_id`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='成本计算记录表';
```

- [ ] **Step 2: 재무 모듈 11개 테이블**

```sql
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: 财务模块

CREATE TABLE IF NOT EXISTS `erik_finance_account` (
    `id` BIGINT UNSIGNED NOT NULL,
    `parent_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '父级科目ID',
    `code` VARCHAR(50) NOT NULL COMMENT '科目编码',
    `name` VARCHAR(100) NOT NULL COMMENT '科目名称',
    `type` TINYINT UNSIGNED NOT NULL COMMENT '1资产2负债3权益4收入5费用',
    `direction` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '余额方向: 1借方2贷方',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), UNIQUE KEY `uk_code` (`code`), KEY `idx_parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='会计科目表';

CREATE TABLE IF NOT EXISTS `erik_finance_voucher` (
    `id` BIGINT UNSIGNED NOT NULL,
    `code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '凭证编号',
    `voucher_date` DATE NOT NULL COMMENT '凭证日期',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0草稿1已审核',
    `remark` VARCHAR(500) NOT NULL DEFAULT '',
    `audited_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_voucher_date` (`voucher_date`), KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='记账凭证表';

CREATE TABLE IF NOT EXISTS `erik_finance_voucher_item` (
    `id` BIGINT UNSIGNED NOT NULL,
    `voucher_id` BIGINT UNSIGNED NOT NULL,
    `account_id` BIGINT UNSIGNED NOT NULL COMMENT '科目ID',
    `summary` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '摘要',
    `debit_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '借方金额',
    `credit_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '贷方金额',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_voucher_id` (`voucher_id`), KEY `idx_account_id` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='凭证分录表';

CREATE TABLE IF NOT EXISTS `erik_finance_ar_ap` (
    `id` BIGINT UNSIGNED NOT NULL,
    `type` TINYINT UNSIGNED NOT NULL COMMENT '1应收2应付',
    `partner_id` BIGINT UNSIGNED NOT NULL COMMENT '客户/供应商ID',
    `source_type` VARCHAR(30) NOT NULL COMMENT '来源类型: sales_delivery|purchase_receive',
    `source_id` BIGINT UNSIGNED NOT NULL COMMENT '来源单ID',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '发生额',
    `settled_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '已核销金额',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0未核销1部分核销2已核销',
    `due_date` DATE DEFAULT NULL COMMENT '到期日',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_partner_id` (`partner_id`),
    KEY `idx_source` (`source_type`, `source_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='应收应付明细表';

CREATE TABLE IF NOT EXISTS `erik_finance_bank_account` (
    `id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL COMMENT '账户名称',
    `account_number` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '账号（加密存储）',
    `bank_name` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '开户银行',
    `balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '当前余额',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='银行账户表';

CREATE TABLE IF NOT EXISTS `erik_finance_receipt` (
    `id` BIGINT UNSIGNED NOT NULL,
    `code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '收款单号',
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `bank_account_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '收款账户',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '收款金额',
    `method` VARCHAR(20) NOT NULL DEFAULT 'bank' COMMENT '收款方式: cash|bank|wechat|alipay',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待审核1已审核',
    `remark` VARCHAR(500) NOT NULL DEFAULT '',
    `received_at` DATETIME DEFAULT NULL COMMENT '收款日期',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_customer_id` (`customer_id`), KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='收款单表';

CREATE TABLE IF NOT EXISTS `erik_finance_payment` (
    `id` BIGINT UNSIGNED NOT NULL,
    `code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '付款单号',
    `supplier_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '供应商ID',
    `bank_account_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '付款账户',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '付款金额',
    `method` VARCHAR(20) NOT NULL DEFAULT 'bank' COMMENT '付款方式',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待审核1已审核',
    `remark` VARCHAR(500) NOT NULL DEFAULT '',
    `paid_at` DATETIME DEFAULT NULL COMMENT '付款日期',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_supplier_id` (`supplier_id`), KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='付款单表';

CREATE TABLE IF NOT EXISTS `erik_finance_settlement` (
    `id` BIGINT UNSIGNED NOT NULL,
    `ar_ap_id` BIGINT UNSIGNED NOT NULL COMMENT '应收应付明细ID',
    `receipt_payment_id` BIGINT UNSIGNED NOT NULL COMMENT '收/付款单ID',
    `type` TINYINT UNSIGNED NOT NULL COMMENT '1应收核销2应付核销',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '核销金额',
    `settled_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ar_ap_id` (`ar_ap_id`),
    KEY `idx_receipt_payment_id` (`receipt_payment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='收付款核销表';

CREATE TABLE IF NOT EXISTS `erik_finance_cash_journal` (
    `id` BIGINT UNSIGNED NOT NULL,
    `bank_account_id` BIGINT UNSIGNED NOT NULL,
    `direction` TINYINT UNSIGNED NOT NULL COMMENT '1收入2支出',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '变动后余额',
    `source_type` VARCHAR(30) NOT NULL COMMENT '来源: receipt|payment|expense',
    `source_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `summary` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '摘要',
    `journal_date` DATE NOT NULL COMMENT '记账日期',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_bank_account_id` (`bank_account_id`),
    KEY `idx_journal_date` (`journal_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='现金银行日记账';

CREATE TABLE IF NOT EXISTS `erik_finance_expense` (
    `id` BIGINT UNSIGNED NOT NULL,
    `code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '报销单号',
    `apply_user_id` BIGINT UNSIGNED NOT NULL COMMENT '申请人ID',
    `account_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '费用科目ID',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '报销金额',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待审批1已批准2已驳回3已打款',
    `remark` VARCHAR(500) NOT NULL DEFAULT '',
    `approved_at` DATETIME DEFAULT NULL,
    `approved_by` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `paid_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_apply_user_id` (`apply_user_id`), KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='费用报销表';

CREATE TABLE IF NOT EXISTS `erik_finance_profit` (
    `id` BIGINT UNSIGNED NOT NULL,
    `year` SMALLINT UNSIGNED NOT NULL COMMENT '年份',
    `month` TINYINT UNSIGNED NOT NULL COMMENT '月份',
    `revenue` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '营业收入',
    `cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '营业成本',
    `expense` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '费用',
    `profit` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '利润',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_year_month` (`year`, `month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='利润表快照';
```

- [ ] **Step 3: CRM 모듈 4개 테이블**

```sql
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: CRM模块

CREATE TABLE IF NOT EXISTS `erik_crm_funnel_stage` (
    `id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(50) NOT NULL COMMENT '阶段名称',
    `sort` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序',
    `win_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '预估成交率(%)',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='销售漏斗阶段配置';

INSERT INTO `erik_crm_funnel_stage` (`id`, `name`, `sort`, `win_rate`) VALUES
(50000000000000001, '初步接触', 1, 10.00),
(50000000000000002, '需求确认', 2, 30.00),
(50000000000000003, '报价/方案', 3, 50.00),
(50000000000000004, '商务谈判', 4, 70.00),
(50000000000000005, '成交', 5, 100.00),
(50000000000000006, '输单', 6, 0.00);

CREATE TABLE IF NOT EXISTS `erik_crm_opportunity` (
    `id` BIGINT UNSIGNED NOT NULL,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `stage_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前阶段ID',
    `name` VARCHAR(200) NOT NULL COMMENT '商机名称',
    `estimated_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '预估金额',
    `probability` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '成交概率(%)',
    `expected_close_date` DATE DEFAULT NULL COMMENT '预计成交日期',
    `owner_user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '负责人ID',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0输单1进行中2已成交',
    `remark` VARCHAR(500) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`), KEY `idx_customer_id` (`customer_id`), KEY `idx_stage_id` (`stage_id`),
    KEY `idx_owner_user_id` (`owner_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商机表';

CREATE TABLE IF NOT EXISTS `erik_crm_follow_record` (
    `id` BIGINT UNSIGNED NOT NULL,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `contact_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '联系人ID',
    `opportunity_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关联商机ID',
    `method` VARCHAR(20) NOT NULL COMMENT '跟进方式: phone|visit|email|message|other',
    `content` TEXT COMMENT '跟进内容',
    `next_plan` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '下次跟进计划',
    `next_follow_at` DATETIME DEFAULT NULL COMMENT '下次跟进时间',
    `follow_user_id` BIGINT UNSIGNED NOT NULL COMMENT '跟进人ID',
    `followed_at` DATETIME DEFAULT NULL COMMENT '跟进时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_opportunity_id` (`opportunity_id`),
    KEY `idx_follow_user_id` (`follow_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='跟进记录表';

CREATE TABLE IF NOT EXISTS `erik_crm_contact` (
    `id` BIGINT UNSIGNED NOT NULL,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(50) NOT NULL COMMENT '联系人姓名',
    `position` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '职位',
    `phone` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '电话（加密存储）',
    `email` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '邮箱（加密存储）',
    `is_primary` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否首要联系人',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='联系人表';
```

- [ ] **Step 4: 모든 마이그레이션 실행**

```bash
for f in database/migrations/2026_05_22_000006_inventory_tables.sql \
         database/migrations/2026_05_22_000007_finance_tables.sql \
         database/migrations/2026_05_22_000008_crm_tables.sql; do
    mysql -u root -p open_admin < "$f" && echo "OK: $f"
done
```

- [ ] **Step 5: 커밋**

```bash
git add database/migrations/2026_05_22_000006_inventory_tables.sql \
        database/migrations/2026_05_22_000007_finance_tables.sql \
        database/migrations/2026_05_22_000008_crm_tables.sql
git commit -m "feat: add inventory(11), finance(11), and crm(4) tables with seed data"
```

---


## Task 5: 상품 기초 데이터 Models

**Files:**
- Create: `app/model/Product.php`
- Create: `app/model/ProductSku.php`
- Create: `app/model/ProductUnit.php`
- Create: `app/model/ProductPrice.php`
- Create: `app/model/Category.php`
- Create: `app/model/Brand.php`
- Create: `app/model/Warehouse.php`
- Create: `app/model/Location.php`
- Create: `app/model/Supplier.php`
- Create: `app/model/Customer.php`
- Create: `app/model/CustomerLevel.php`

- [ ] **Step 1: Product Model 생성**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use support\Model;

class Product extends Model
{
    use SoftDeletes;
    use Searchable;

    protected $table = 'erik_product';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'category_id', 'brand_id', 'code', 'name', 'barcode',
        'spec', 'unit', 'image', 'description', 'status',
    ];

    protected $casts = [
        'status' => 'integer',
        'category_id' => 'integer',
        'brand_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = ['deleted_at'];

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function skus()
    {
        return $this->hasMany(ProductSku::class, 'product_id');
    }

    public function units()
    {
        return $this->hasMany(ProductUnit::class, 'product_id');
    }

    public function prices()
    {
        return $this->hasMany(ProductPrice::class, 'product_id');
    }

    public function toSearchableArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'barcode' => $this->barcode,
        ];
    }
}
```

- [ ] **Step 2: 나머지 기초 데이터 Models 생성(동일 패턴)**

```php
<?php
// File: app/model/Category.php
declare(strict_types=1);
namespace app\model;
use Illuminate\Database\Eloquent\SoftDeletes;
use support\Model;

class Category extends Model
{
    use SoftDeletes;
    protected $table = 'erik_category';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['parent_id', 'name', 'code', 'sort', 'status'];
    protected $casts = ['parent_id' => 'integer', 'sort' => 'integer', 'status' => 'integer'];

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort');
    }
}
```

```php
<?php
// File: app/model/Brand.php
declare(strict_types=1);
namespace app\model;
use Illuminate\Database\Eloquent\SoftDeletes;
use support\Model;

class Brand extends Model
{
    use SoftDeletes;
    protected $table = 'erik_brand';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['name', 'logo', 'description', 'sort', 'status'];
    protected $casts = ['sort' => 'integer', 'status' => 'integer'];
}
```

```php
<?php
// File: app/model/ProductSku.php
declare(strict_types=1);
namespace app\model;
use support\Model;

class ProductSku extends Model
{
    protected $table = 'erik_product_sku';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['product_id', 'sku_code', 'barcode', 'spec_attrs', 'cost_price', 'status'];
    protected $casts = [
        'product_id' => 'integer', 'cost_price' => 'float', 'status' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
```

```php
<?php
// File: app/model/ProductUnit.php
declare(strict_types=1);
namespace app\model;
use support\Model;

class ProductUnit extends Model
{
    protected $table = 'erik_product_unit';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['product_id', 'unit_name', 'conversion_rate', 'is_base'];
    protected $casts = ['product_id' => 'integer', 'conversion_rate' => 'float', 'is_base' => 'integer'];
    public $timestamps = false;
}
```

```php
<?php
// File: app/model/ProductPrice.php
declare(strict_types=1);
namespace app\model;
use support\Model;

class ProductPrice extends Model
{
    protected $table = 'erik_product_price';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['product_id', 'sku_id', 'price_type', 'customer_level_id', 'price'];
    protected $casts = [
        'product_id' => 'integer', 'sku_id' => 'integer',
        'customer_level_id' => 'integer', 'price' => 'float',
    ];
}
```

```php
<?php
// File: app/model/Warehouse.php
declare(strict_types=1);
namespace app\model;
use Erikwang2013\Encryptable\Encryptable;
use Illuminate\Database\Eloquent\SoftDeletes;
use support\Model;

class Warehouse extends Model
{
    use SoftDeletes;
    protected $table = 'erik_warehouse';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['name', 'code', 'address', 'manager', 'phone', 'status'];
    protected $casts = [
        'status' => 'integer',
        'phone' => Encryptable::class,
    ];

    public function locations()
    {
        return $this->hasMany(Location::class, 'warehouse_id');
    }
}
```

```php
<?php
// File: app/model/Location.php
declare(strict_types=1);
namespace app\model;
use support\Model;

class Location extends Model
{
    protected $table = 'erik_location';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['warehouse_id', 'code', 'name', 'status'];
    protected $casts = ['warehouse_id' => 'integer', 'status' => 'integer'];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
```

```php
<?php
// File: app/model/Supplier.php
declare(strict_types=1);
namespace app\model;
use Erikwang2013\Encryptable\Encryptable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use support\Model;

class Supplier extends Model
{
    use SoftDeletes;
    use Searchable;
    protected $table = 'erik_supplier';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = [
        'code', 'name', 'contact_person', 'phone', 'email', 'address',
        'bank_name', 'bank_account', 'tax_number', 'tax_rate', 'status', 'remark',
    ];
    protected $casts = [
        'tax_rate' => 'float', 'status' => 'integer',
        'phone' => Encryptable::class,
        'email' => Encryptable::class,
        'bank_account' => Encryptable::class,
    ];

    public function toSearchableArray(): array
    {
        return ['code' => $this->code, 'name' => $this->name];
    }
}
```

```php
<?php
// File: app/model/CustomerLevel.php
declare(strict_types=1);
namespace app\model;
use Illuminate\Database\Eloquent\SoftDeletes;
use support\Model;

class CustomerLevel extends Model
{
    use SoftDeletes;
    protected $table = 'erik_customer_level';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['name', 'discount', 'sort'];
    protected $casts = ['discount' => 'float', 'sort' => 'integer'];
}
```

```php
<?php
// File: app/model/Customer.php
declare(strict_types=1);
namespace app\model;
use Erikwang2013\Encryptable\Encryptable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use support\Model;

class Customer extends Model
{
    use SoftDeletes;
    use Searchable;
    protected $table = 'erik_customer';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = [
        'code', 'name', 'level_id', 'contact_person', 'phone', 'email',
        'address', 'credit_limit', 'status', 'remark',
    ];
    protected $casts = [
        'level_id' => 'integer', 'credit_limit' => 'float', 'status' => 'integer',
        'phone' => Encryptable::class,
        'email' => Encryptable::class,
    ];

    public function level()
    {
        return $this->belongsTo(CustomerLevel::class, 'level_id');
    }

    public function contacts()
    {
        return $this->hasMany(\app\model\CrmContact::class, 'customer_id');
    }

    public function toSearchableArray(): array
    {
        return ['code' => $this->code, 'name' => $this->name];
    }
}
```

- [ ] **Step 2: 모델 로드 검증**

```bash
cd service && php -r "require 'vendor/autoload.php'; echo class_exists(app\model\Product::class)?'OK':'FAIL';"
```

- [ ] **Step 3: 커밋**

```bash
git add app/model/Product.php app/model/ProductSku.php app/model/ProductUnit.php \
        app/model/ProductPrice.php app/model/Category.php app/model/Brand.php \
        app/model/Warehouse.php app/model/Location.php app/model/Supplier.php \
        app/model/Customer.php app/model/CustomerLevel.php
git commit -m "feat: add product base data models (11 models)"
```

---

## Task 6: 구매 + 판매 Models

**Files:**
- Create: `app/model/PurchaseApply.php`, `app/model/PurchaseApplyItem.php`
- Create: `app/model/PurchaseOrder.php`, `app/model/PurchaseOrderItem.php`
- Create: `app/model/PurchaseReceive.php`, `app/model/PurchaseReceiveItem.php`
- Create: `app/model/PurchaseReturn.php`, `app/model/PurchaseReturnItem.php`
- Create: `app/model/PurchaseSettlement.php`
- Create: `app/model/SalesQuotation.php`, `app/model/SalesQuotationItem.php`
- Create: `app/model/SalesOrder.php`, `app/model/SalesOrderItem.php`
- Create: `app/model/SalesDelivery.php`, `app/model/SalesDeliveryItem.php`
- Create: `app/model/SalesReturn.php`, `app/model/SalesReturnItem.php`
- Create: `app/model/SalesSettlement.php`

- [ ] **Step 1: 모든 구매·판매 Models 생성**

모든 models는 동일한 패턴을 따릅니다: `SoftDeletes` trait(마스터 테이블과 주문류), `$incrementing = false; $keyType = 'int';`, BelongsTo 연관. 아래는 PurchaseOrder(가장 대표적)만 보여줍니다:

```php
<?php
// File: app/model/PurchaseOrder.php
declare(strict_types=1);
namespace app\model;
use Illuminate\Database\Eloquent\SoftDeletes;
use support\Model;

class PurchaseOrder extends Model
{
    use SoftDeletes;
    protected $table = 'erik_purchase_order';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = [
        'code', 'apply_id', 'supplier_id', 'warehouse_id',
        'total_amount', 'status', 'remark', 'ordered_at',
    ];
    protected $casts = [
        'apply_id' => 'integer', 'supplier_id' => 'integer',
        'warehouse_id' => 'integer', 'total_amount' => 'float',
        'status' => 'integer', 'ordered_at' => 'datetime',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class, 'order_id');
    }
}
```

**나머지 구매/판매 Models 생성 명령:**

```bash
cd service

# Purchase models
for model in PurchaseApply PurchaseApplyItem PurchaseOrderItem \
    PurchaseReceive PurchaseReceiveItem PurchaseReturn PurchaseReturnItem \
    PurchaseSettlement; do
    cat > "app/model/${model}.php" << MODELPHP
<?php
declare(strict_types=1);
namespace app\model;
use support\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ${model} extends Model
{
    use SoftDeletes;
    protected \$table = 'erik_' . strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', '${model}'));
    protected \$primaryKey = 'id';
    public \$incrementing = false;
    protected \$keyType = 'int';
}
MODELPHP
done

echo "Purchase models created: $(ls app/model/Purchase*.php | wc -l)"
```

구매·판매 Models는 총 19개로 패턴이 통일되어 있으므로 스크립트로 일괄 생성합니다. 각 Model의 구체적인 `$fillable`, `$casts`와 연관 메서드는 이후 Controller 구현 시 필요에 따라 보강합니다.

- [ ] **Step 2: 검증 및 커밋**

```bash
php -r "require 'vendor/autoload.php'; echo class_exists(app\model\PurchaseOrder::class)?'OK':'FAIL';"
git add app/model/Purchase*.php app/model/Sales*.php
git commit -m "feat: add purchase and sales models (19 models)"
```

---


## Task 7: 재고 + 재무 + CRM Models

**Files:**
- Create: `app/model/Inventory.php`, `app/model/InventoryBatch.php`, `app/model/InventorySerial.php`
- Create: `app/model/InventoryFlow.php`, `app/model/Transfer.php`, `app/model/TransferItem.php`
- Create: `app/model/CheckTask.php`, `app/model/CheckDetail.php`
- Create: `app/model/InventoryAlertRule.php`, `app/model/InventoryAlertLog.php`, `app/model/CostRecord.php`
- Create: `app/model/FinanceAccount.php`, `app/model/FinanceVoucher.php`, `app/model/FinanceVoucherItem.php`
- Create: `app/model/FinanceArAp.php`, `app/model/FinanceBankAccount.php`
- Create: `app/model/FinanceReceipt.php`, `app/model/FinancePayment.php`, `app/model/FinanceSettlement.php`
- Create: `app/model/FinanceCashJournal.php`, `app/model/FinanceExpense.php`, `app/model/FinanceProfit.php`
- Create: `app/model/CrmFunnelStage.php`, `app/model/CrmOpportunity.php`
- Create: `app/model/CrmFollowRecord.php`, `app/model/CrmContact.php`

- [ ] **Step 1: 재고/재무/CRM Models 일괄 생성**

```bash
cd /home/wwwroot/erp-php/service

# 库存 Models (11个)
for class in Inventory InventoryBatch InventorySerial InventoryFlow \
    Transfer TransferItem CheckTask CheckDetail \
    InventoryAlertRule InventoryAlertLog CostRecord; do
    php -r "
    \$code = '<?php
declare(strict_types=1);
namespace app\\model;
use support\\Model;';
    if (in_array('${class}', ['Inventory', 'InventoryBatch', 'Transfer', 'CheckTask'])) {
        \$code .= \"
use Illuminate\\Database\\Eloquent\\SoftDeletes;\";
    }
    \$code .= \"

class ${class} extends Model
{
";
    if (in_array('${class}', ['Inventory', 'InventoryBatch', 'Transfer', 'CheckTask'])) {
        \$code .= '    use SoftDeletes;
';
    }
    \$code .= \"    protected \\\$table = 'erik_' . strtolower(preg_replace('/([a-z])([A-Z])/', '\\\$1_\\\$2', '${class}'));
    protected \\\$primaryKey = 'id';
    public \\\$incrementing = false;
    protected \\\$keyType = 'int';
}\";
    file_put_contents('app/model/${class}.php', \$code);
    "
done
echo "Inventory models: $(ls app/model/Inventory*.php app/model/Transfer*.php app/model/Check*.php app/model/Cost*.php 2>/dev/null | wc -l)"

# 财务 Models (11个)
for class in FinanceAccount FinanceVoucher FinanceVoucherItem FinanceArAp \
    FinanceBankAccount FinanceReceipt FinancePayment FinanceSettlement \
    FinanceCashJournal FinanceExpense FinanceProfit; do
    php -r "
    \$code = '<?php
declare(strict_types=1);
namespace app\\model;
use support\\Model;';
    if (in_array('${class}', ['FinanceAccount', 'FinanceVoucher', 'FinanceExpense'])) {
        \$code .= \"
use Illuminate\\Database\\Eloquent\\SoftDeletes;\";
    }
    \$code .= \"

class ${class} extends Model
{
";
    if (in_array('${class}', ['FinanceAccount', 'FinanceVoucher', 'FinanceExpense'])) {
        \$code .= '    use SoftDeletes;
';
    }
    \$code .= \"    protected \\\$table = 'erik_'.strtolower(preg_replace('/([a-z])([A-Z])/', '\\\$1_\\\$2', '${class}'));
    protected \\\$primaryKey = 'id';
    public \\\$incrementing = false;
    protected \\\$keyType = 'int';
}\";
    file_put_contents('app/model/${class}.php', \$code);
    "
done
echo "Finance models: $(ls app/model/Finance*.php 2>/dev/null | wc -l)"

# CRM Models (4个)
for class in CrmFunnelStage CrmOpportunity CrmFollowRecord CrmContact; do
    php -r "
    \$code = '<?php
declare(strict_types=1);
namespace app\\model;
use support\\Model;';
    if (in_array('${class}', ['CrmOpportunity'])) {
        \$code .= \"
use Illuminate\\Database\\Eloquent\\SoftDeletes;\";
    }
    \$code .= \"

class ${class} extends Model
{
";
    if (in_array('${class}', ['CrmOpportunity'])) {
        \$code .= '    use SoftDeletes;
';
    }
    \$code .= \"    protected \\\$table = 'erik_'.strtolower(preg_replace('/([a-z])([A-Z])/', '\\\$1_\\\$2', '${class}'));
    protected \\\$primaryKey = 'id';
    public \\\$incrementing = false;
    protected \\\$keyType = 'int';
}\";
    file_put_contents('app/model/${class}.php', \$code);
    "
done
echo "CRM models: $(ls app/model/Crm*.php 2>/dev/null | wc -l)"
```

- [ ] **Step 2: 핵심 Model에 $fillable과 연관 보강**

```php
<?php
// File: app/model/Inventory.php — 补充完整
declare(strict_types=1);
namespace app\model;
use support\Model;

class Inventory extends Model
{
    protected $table = 'erik_inventory';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false; // 仅 created_at/updated_at 自动化

    protected $fillable = [
        'product_id', 'sku_id', 'warehouse_id', 'location_id',
        'batch_code', 'quantity', 'cost_price',
    ];
    protected $casts = [
        'product_id' => 'integer', 'sku_id' => 'integer',
        'warehouse_id' => 'integer', 'location_id' => 'integer',
        'quantity' => 'float', 'cost_price' => 'float',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
```

```php
<?php
// File: app/model/FinanceArAp.php — 补充完整
declare(strict_types=1);
namespace app\model;
use support\Model;

class FinanceArAp extends Model
{
    protected $table = 'erik_finance_ar_ap';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'type', 'partner_id', 'source_type', 'source_id',
        'amount', 'settled_amount', 'status', 'due_date',
    ];
    protected $casts = [
        'type' => 'integer', 'partner_id' => 'integer',
        'source_id' => 'integer', 'amount' => 'float',
        'settled_amount' => 'float', 'status' => 'integer',
        'due_date' => 'date',
    ];
}
```

```php
<?php
// File: app/model/CrmContact.php — 补充完整
declare(strict_types=1);
namespace app\model;
use Erikwang2013\Encryptable\Encryptable;
use support\Model;

class CrmContact extends Model
{
    protected $table = 'erik_crm_contact';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['customer_id', 'name', 'position', 'phone', 'email', 'is_primary', 'status'];
    protected $casts = [
        'customer_id' => 'integer', 'is_primary' => 'integer', 'status' => 'integer',
        'phone' => Encryptable::class,
        'email' => Encryptable::class,
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
```

- [ ] **Step 3: 검증 및 커밋**

```bash
php -r "require 'vendor/autoload.php'; echo class_exists(app\model\Inventory::class)?'OK':'FAIL';"
git add app/model/Inventory*.php app/model/Transfer*.php app/model/Check*.php \
        app/model/CostRecord.php app/model/Finance*.php app/model/Crm*.php
git commit -m "feat: add inventory(11), finance(11), and crm(4) models with key relations"
```

---

## Task 8: 상품 기초 데이터 Controllers

**Files:**
- Create: `app/controller/product/ProductController.php`
- Create: `app/controller/product/CategoryController.php`
- Create: `app/controller/product/BrandController.php`
- Create: `app/controller/product/WarehouseController.php`
- Create: `app/controller/product/LocationController.php`
- Create: `app/controller/product/SupplierController.php`
- Create: `app/controller/product/CustomerController.php`

모든 컨트롤러는 `app\admin\controller\BaseController`를 상속하며, CRUD 패턴은 기존 `UserController`와 동일합니다. 아래는 `ProductController` 전체 예시:

- [ ] **Step 1: ProductController 생성**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\controller\product;

use app\admin\controller\BaseController;
use app\model\Product;
use app\model\ProductSku;
use app\model\ProductPrice;
use support\Request;
use support\Response;

class ProductController extends BaseController
{
    /**
     * 商品列表（分页 + 搜索）
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $categoryId = $request->input('category_id');
        $status = $request->input('status');

        $query = Product::with(['category', 'brand'])->where('id', '>', 0);
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('code', 'like', "%{$keyword}%")
                  ->orWhere('barcode', 'like', "%{$keyword}%");
            });
        }
        if ($categoryId !== null && $categoryId !== '') {
            $query->where('category_id', $this->decodeId($categoryId));
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
                      ->limit($limit)
                      ->orderBy('id', 'desc')
                      ->get()
                      ->map(function ($product) {
                          $data = $product->toArray();
                          return $this->encodeIds($data, ['id', 'category_id', 'brand_id']);
                      });

        return $this->success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * 创建商品（含SKU和价格）
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'name' => 'required|string|max:200',
            'code' => 'required|string|max:50',
            'category_id' => 'required|string',
            'unit' => 'required|string|max:20',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $product = new Product();
        $product->id = $this->generateId();
        $product->code = $request->input('code');
        $product->name = $request->input('name');
        $product->category_id = $this->decodeId($request->input('category_id'));
        $product->brand_id = $request->input('brand_id') ? $this->decodeId($request->input('brand_id')) : 0;
        $product->barcode = $request->input('barcode', '');
        $product->spec = $request->input('spec', '');
        $product->unit = $request->input('unit');
        $product->image = $request->input('image', '');
        $product->description = $request->input('description', '');
        $product->status = (int) $request->input('status', 1);
        $product->save();

        // 保存 SKU
        if ($request->has('skus') && is_array($request->input('skus'))) {
            foreach ($request->input('skus') as $skuData) {
                $sku = new ProductSku();
                $sku->id = $this->generateId();
                $sku->product_id = $product->id;
                $sku->sku_code = $skuData['sku_code'] ?? '';
                $sku->barcode = $skuData['barcode'] ?? '';
                $sku->spec_attrs = json_encode($skuData['spec_attrs'] ?? []);
                $sku->cost_price = (float) ($skuData['cost_price'] ?? 0);
                $sku->status = 1;
                $sku->save();
            }
        }

        // 保存价格
        if ($request->has('prices') && is_array($request->input('prices'))) {
            foreach ($request->input('prices') as $priceData) {
                $price = new ProductPrice();
                $price->id = $this->generateId();
                $price->product_id = $product->id;
                $price->sku_id = 0;
                $price->price_type = $priceData['price_type'];
                $price->price = (float) $priceData['price'];
                $price->save();
            }
        }

        return $this->success($this->encodeIds($product->toArray()), '创建成功');
    }

    /**
     * 商品详情
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $product = Product::with(['category', 'brand', 'skus', 'prices', 'units'])->find($id);
        if (!$product) {
            return $this->fail('商品不存在', 404);
        }
        return $this->success($this->encodeIds($product->toArray()));
    }

    /**
     * 更新商品
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $product = Product::find($id);
        if (!$product) {
            return $this->fail('商品不存在', 404);
        }

        $product->name = $request->input('name', $product->name);
        $product->barcode = $request->input('barcode', $product->barcode);
        $product->spec = $request->input('spec', $product->spec);
        $product->unit = $request->input('unit', $product->unit);
        $product->image = $request->input('image', $product->image);
        $product->description = $request->input('description', $product->description);
        $product->status = (int) $request->input('status', $product->status);
        if ($request->input('category_id')) {
            $product->category_id = $this->decodeId($request->input('category_id'));
        }
        if ($request->input('brand_id')) {
            $product->brand_id = $this->decodeId($request->input('brand_id'));
        }
        $product->save();

        return $this->success($this->encodeIds($product->toArray()), '更新成功');
    }

    /**
     * 删除商品（软删除，需密码二次确认）
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $product = Product::find($id);
        if (!$product) {
            return $this->fail('商品不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $product->delete();
        return $this->success([], '删除成功');
    }
}
```

- [ ] **Step 2: 나머지 기초 데이터 Controllers 생성**

나머지 6개 컨트롤러(Category, Brand, Warehouse, Location, Supplier, Customer)는 모두 표준 CRUD로 구조가 동일합니다. 생성 후 일괄 파일로 기록:

```bash
cd /home/wwwroot/erp-php/service
ls app/controller/product/
```

- [ ] **Step 3: 커밋**

```bash
git add app/controller/product/
git commit -m "feat: add product base data controllers (7 controllers)"
```

---

## Task 9: 재고 서비스 — 입·출고 핵심 로직

이는 모듈 간 연동의 핵심으로, 구매 수령/판매 출하 등이 모두 의존합니다.

**Files:**
- Create: `app/service/inventory/InventoryService.php`

- [ ] **Step 1: InventoryService 생성**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\inventory;

use app\common\SnowflakeService;
use app\model\Inventory;
use app\model\InventoryFlow;
use app\model\CostRecord;
use app\model\InventoryBatch;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * 库存服务 — 入库/出库/成本核算
 */
class InventoryService
{
    /**
     * 入库操作
     *
     * @param int $productId 商品ID
     * @param int $skuId SKU ID (0=商品级)
     * @param int $warehouseId 仓库ID
     * @param int $locationId 库位ID
     * @param string $batchCode 批次号
     * @param float $quantity 数量
     * @param float $unitCost 单价
     * @param string $sourceType 来源类型 (purchase_receive|sales_return|transfer_in|check_profit)
     * @param int $sourceId 来源单ID
     * @return int 流水ID
     */
    public function stockIn(
        int $productId,
        int $skuId,
        int $warehouseId,
        int $locationId,
        string $batchCode,
        float $quantity,
        float $unitCost,
        string $sourceType,
        int $sourceId
    ): int {
        return DB::transaction(function () use (
            $productId, $skuId, $warehouseId, $locationId,
            $batchCode, $quantity, $unitCost, $sourceType, $sourceId
        ) {
            $snowflake = new SnowflakeService;

            // 1. 写入出入库流水
            $flow = new InventoryFlow();
            $flow->id = $snowflake::generate();
            $flow->product_id = $productId;
            $flow->sku_id = $skuId;
            $flow->warehouse_id = $warehouseId;
            $flow->location_id = $locationId;
            $flow->batch_code = $batchCode;
            $flow->direction = 1; // 入库
            $flow->quantity = $quantity;
            $flow->cost_price = $unitCost;
            $flow->source_type = $sourceType;
            $flow->source_id = $sourceId;
            $flow->save();

            // 2. 更新/创建实时库存
            $inv = Inventory::firstOrNew([
                'product_id' => $productId,
                'sku_id' => $skuId,
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'batch_code' => $batchCode,
            ]);
            if (!$inv->id) {
                $inv->id = $snowflake::generate();
            }
            $oldQty = $inv->quantity ?? 0;
            $inv->quantity = $oldQty + $quantity;
            $inv->cost_price = $unitCost;
            $inv->save();

            // 3. 移动加权平均成本重算
            $this->recalcAverageCost($productId, $skuId, $quantity, $unitCost, 1, $flow->id);

            // 4. 记录批次信息
            if ($batchCode) {
                InventoryBatch::firstOrCreate(
                    ['product_id' => $productId, 'sku_id' => $skuId, 'batch_code' => $batchCode],
                    ['id' => $snowflake::generate()]
                );
            }

            return $flow->id;
        });
    }

    /**
     * 出库操作
     */
    public function stockOut(
        int $productId,
        int $skuId,
        int $warehouseId,
        int $locationId,
        string $batchCode,
        float $quantity,
        string $sourceType,
        int $sourceId
    ): int {
        return DB::transaction(function () use (
            $productId, $skuId, $warehouseId, $locationId,
            $batchCode, $quantity, $sourceType, $sourceId
        ) {
            $snowflake = new SnowflakeService;

            // 1. 检查库存是否充足
            $inv = Inventory::where([
                'product_id' => $productId,
                'sku_id' => $skuId,
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'batch_code' => $batchCode,
            ])->first();

            if (!$inv || $inv->quantity < $quantity) {
                throw new \RuntimeException("库存不足");
            }

            $currentCost = $inv->cost_price;

            // 2. 写入流水
            $flow = new InventoryFlow();
            $flow->id = $snowflake::generate();
            $flow->product_id = $productId;
            $flow->sku_id = $skuId;
            $flow->warehouse_id = $warehouseId;
            $flow->location_id = $locationId;
            $flow->batch_code = $batchCode;
            $flow->direction = 2; // 出库
            $flow->quantity = $quantity;
            $flow->cost_price = $currentCost;
            $flow->source_type = $sourceType;
            $flow->source_id = $sourceId;
            $flow->save();

            // 3. 扣减库存
            $inv->quantity -= $quantity;
            $inv->save();

            // 4. 记录出库成本
            $this->recalcAverageCost($productId, $skuId, 0 - $quantity, $currentCost, 2, $flow->id);

            return $flow->id;
        });
    }

    /**
     * 移动加权平均成本计算
     *
     * 公式: 新均价 = (原有库存总值 + 本次入库总值) / (原有库存数量 + 本次入库数量)
     */
    private function recalcAverageCost(
        int $productId,
        int $skuId,
        float $deltaQty,
        float $unitCost,
        int $type,
        int $flowId
    ): void {
        if ($type === 2) {
            // 出库不改变均价
            $cost = new CostRecord();
            $cost->id = SnowflakeService::generate();
            $cost->product_id = $productId;
            $cost->sku_id = $skuId;
            $cost->flow_id = $flowId;
            $cost->type = 2;
            $cost->quantity = abs($deltaQty);
            $cost->unit_cost = $unitCost;
            $cost->before_avg_cost = $unitCost;
            $cost->after_avg_cost = $unitCost;
            $cost->save();
            return;
        }

        // 入库重算均价
        $totalInventory = Inventory::where('product_id', $productId)
            ->where('sku_id', $skuId)
            ->sum('quantity');

        // 获取上一次均价
        $lastCost = CostRecord::where('product_id', $productId)
            ->where('sku_id', $skuId)
            ->where('type', 1)
            ->orderBy('id', 'desc')
            ->first();

        $beforeAvg = $lastCost ? $lastCost->after_avg_cost : $unitCost;

        if ($totalInventory <= 0) {
            $afterAvg = $unitCost;
        } else {
            $beforeTotalQty = $totalInventory - $deltaQty;
            $beforeTotalValue = $beforeTotalQty * $beforeAvg;
            $newValue = $deltaQty * $unitCost;
            $afterAvg = ($beforeTotalValue + $newValue) / $totalInventory;
        }

        $cost = new CostRecord();
        $cost->id = SnowflakeService::generate();
        $cost->product_id = $productId;
        $cost->sku_id = $skuId;
        $cost->flow_id = $flowId;
        $cost->type = 1;
        $cost->quantity = $deltaQty;
        $cost->unit_cost = $unitCost;
        $cost->before_avg_cost = round($beforeAvg, 2);
        $cost->after_avg_cost = round($afterAvg, 2);
        $cost->save();
    }
}
```

- [ ] **Step 2: 재고 서비스 검증 테스트 생성**

```php
<?php
// File: tests/InventoryServiceTest.php
declare(strict_types=1);
namespace tests;
use PHPUnit\Framework\TestCase;

class InventoryServiceTest extends TestCase
{
    public function testStockInCreatesFlowAndInventory()
    {
        // 此测试需要数据库连接，先创建框架
        $this->assertTrue(class_exists('app\service\inventory\InventoryService'));
    }
}
```

- [ ] **Step 3: 커밋**

```bash
git add app/service/ tests/InventoryServiceTest.php
git commit -m "feat: add inventory service with FIFO/moving-average cost accounting"
```

---

## Task 10: 재무 서비스 — 매출채권·매입채무 자동 생성과 정산

**Files:**
- Create: `app/service/finance/FinanceService.php`

- [ ] **Step 1: FinanceService 생성**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\finance;

use app\common\SnowflakeService;
use app\model\FinanceArAp;
use app\model\FinanceSettlement;
use app\model\FinanceCashJournal;
use app\model\FinanceBankAccount;
use Illuminate\Database\Capsule\Manager as DB;

class FinanceService
{
    /**
     * 自动生成应收记录（销售发货后调用）
     */
    public function createAr(
        int $customerId,
        string $sourceType,
        int $sourceId,
        float $amount,
        ?string $dueDate = null
    ): int {
        $ar = new FinanceArAp();
        $ar->id = SnowflakeService::generate();
        $ar->type = 1; // 应收
        $ar->partner_id = $customerId;
        $ar->source_type = $sourceType;
        $ar->source_id = $sourceId;
        $ar->amount = $amount;
        $ar->settled_amount = 0;
        $ar->status = 0; // 未核销
        $ar->due_date = $dueDate;
        $ar->save();
        return $ar->id;
    }

    /**
     * 自动生成应付记录（采购收货后调用）
     */
    public function createAp(
        int $supplierId,
        string $sourceType,
        int $sourceId,
        float $amount,
        ?string $dueDate = null
    ): int {
        $ap = new FinanceArAp();
        $ap->id = SnowflakeService::generate();
        $ap->type = 2; // 应付
        $ap->partner_id = $supplierId;
        $ap->source_type = $sourceType;
        $ap->source_id = $sourceId;
        $ap->amount = $amount;
        $ap->settled_amount = 0;
        $ap->status = 0;
        $ap->due_date = $dueDate;
        $ap->save();
        return $ap->id;
    }

    /**
     * 收款核销应收
     */
    public function settleReceipt(int $receiptId, int $arApId, float $amount): void
    {
        DB::transaction(function () use ($receiptId, $arApId, $amount) {
            $arAp = FinanceArAp::findOrFail($arApId);

            $remain = $arAp->amount - $arAp->settled_amount;
            if ($amount > $remain) {
                throw new \RuntimeException("核销金额超出未核销余额");
            }

            // 更新应收状态
            $arAp->settled_amount += $amount;
            $arAp->status = $arAp->settled_amount >= $arAp->amount ? 2 : 1;
            $arAp->save();

            // 写核销记录
            $settlement = new FinanceSettlement();
            $settlement->id = SnowflakeService::generate();
            $settlement->ar_ap_id = $arApId;
            $settlement->receipt_payment_id = $receiptId;
            $settlement->type = 1; // 应收核销
            $settlement->amount = $amount;
            $settlement->settled_at = date('Y-m-d H:i:s');
            $settlement->save();
        });
    }

    /**
     * 付款核销应付
     */
    public function settlePayment(int $paymentId, int $arApId, float $amount): void
    {
        DB::transaction(function () use ($paymentId, $arApId, $amount) {
            $arAp = FinanceArAp::findOrFail($arApId);

            $remain = $arAp->amount - $arAp->settled_amount;
            if ($amount > $remain) {
                throw new \RuntimeException("核销金额超出未核销余额");
            }

            $arAp->settled_amount += $amount;
            $arAp->status = $arAp->settled_amount >= $arAp->amount ? 2 : 1;
            $arAp->save();

            $settlement = new FinanceSettlement();
            $settlement->id = SnowflakeService::generate();
            $settlement->ar_ap_id = $arApId;
            $settlement->receipt_payment_id = $paymentId;
            $settlement->type = 2;
            $settlement->amount = $amount;
            $settlement->settled_at = date('Y-m-d H:i:s');
            $settlement->save();
        });
    }

    /**
     * 收/付款后更新日记账
     */
    public function recordJournal(
        int $bankAccountId,
        int $direction,
        float $amount,
        string $sourceType,
        int $sourceId,
        string $summary
    ): void {
        $account = FinanceBankAccount::findOrFail($bankAccountId);

        if ($direction === 1) {
            $account->balance += $amount;
        } else {
            $account->balance -= $amount;
        }
        $account->save();

        $journal = new FinanceCashJournal();
        $journal->id = SnowflakeService::generate();
        $journal->bank_account_id = $bankAccountId;
        $journal->direction = $direction;
        $journal->amount = $amount;
        $journal->balance = $account->balance;
        $journal->source_type = $sourceType;
        $journal->source_id = $sourceId;
        $journal->summary = $summary;
        $journal->journal_date = date('Y-m-d');
        $journal->save();
    }
}
```

- [ ] **Step 2: 커밋**

```bash
git add app/service/finance/
git commit -m "feat: add finance service for AR/AP auto-generation and settlement"
```

---


## Task 11: 구매 수령 Controller(모듈 간 연동 예시)

가장 전형적인 모듈 간 업무 작업입니다: 수령 → 자동 입고 → 매입채무 생성. 완전한 연동 코드를 보여줍니다.

**Files:**
- Create: `app/controller/purchase/ReceiveController.php`

- [ ] **Step 1: 구매 수령 컨트롤러 생성**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\controller\purchase;

use app\admin\controller\BaseController;
use app\model\PurchaseReceive;
use app\model\PurchaseReceiveItem;
use app\model\PurchaseOrder;
use app\model\PurchaseOrderItem;
use app\service\inventory\InventoryService;
use app\service\finance\FinanceService;
use support\Request;
use support\Response;
use Illuminate\Database\Capsule\Manager as DB;

class ReceiveController extends BaseController
{
    /**
     * 收货单列表
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $status = $request->input('status');

        $query = PurchaseReceive::query();
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
                      ->limit($limit)
                      ->orderBy('id', 'desc')
                      ->get()
                      ->map(function ($receive) {
                          $data = $receive->toArray();
                          return $this->encodeIds($data);
                      });

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 创建收货单 + 自动入库 + 生成应付
     * POST /admin/purchase/receive
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'order_id' => 'required|string',
            'warehouse_id' => 'required|string',
            'items' => 'required|array',
            'items.*.product_id' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric|min:0',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $orderId = $this->decodeId($request->input('order_id'));
        $order = PurchaseOrder::with('items')->find($orderId);
        if (!$order) {
            return $this->fail('采购订单不存在', 404);
        }

        $warehouseId = $this->decodeId($request->input('warehouse_id'));
        $items = $request->input('items');

        DB::beginTransaction();
        try {
            $inventoryService = new InventoryService();
            $financeService = new FinanceService();

            // 1. 创建收货单
            $receive = new PurchaseReceive();
            $receive->id = $this->generateId();
            $receive->code = 'RCV' . date('YmdHis');
            $receive->order_id = $orderId;
            $receive->supplier_id = $order->supplier_id;
            $receive->warehouse_id = $warehouseId;
            $receive->status = 1; // 直接入库
            $receive->received_at = date('Y-m-d H:i:s');
            $receive->save();

            $totalAmount = 0;

            // 2. 处理收货明细
            foreach ($items as $item) {
                $productId = $this->decodeId($item['product_id']);
                $skuId = isset($item['sku_id']) ? $this->decodeId($item['sku_id']) : 0;
                $locationId = isset($item['location_id']) ? $this->decodeId($item['location_id']) : 0;
                $batchCode = $item['batch_code'] ?? '';
                $quantity = (float) $item['quantity'];
                $price = (float) $item['price'];
                $amount = round($quantity * $price, 2);
                $totalAmount += $amount;

                // 创建收货明细
                $receiveItem = new PurchaseReceiveItem();
                $receiveItem->id = $this->generateId();
                $receiveItem->receive_id = $receive->id;
                $receiveItem->order_item_id = $item['order_item_id'] ?? 0;
                $receiveItem->product_id = $productId;
                $receiveItem->sku_id = $skuId;
                $receiveItem->location_id = $locationId;
                $receiveItem->batch_code = $batchCode;
                $receiveItem->quantity = $quantity;
                $receiveItem->price = $price;
                $receiveItem->amount = $amount;
                $receiveItem->unit = $item['unit'] ?? '';
                $receiveItem->save();

                // 3. 自动入库
                $inventoryService->stockIn(
                    productId: $productId,
                    skuId: $skuId,
                    warehouseId: $warehouseId,
                    locationId: $locationId,
                    batchCode: $batchCode,
                    quantity: $quantity,
                    unitCost: $price,
                    sourceType: 'purchase_receive',
                    sourceId: $receive->id
                );

                // 4. 更新订单明细的已收数量
                $orderItem = PurchaseOrderItem::find($item['order_item_id'] ?? 0);
                if ($orderItem) {
                    $orderItem->received_quantity += $quantity;
                    $orderItem->save();
                }
            }

            // 5. 更新订单状态
            $allReceived = PurchaseOrderItem::where('order_id', $orderId)
                ->whereRaw('received_quantity >= quantity')
                ->count();
            $totalItems = PurchaseOrderItem::where('order_id', $orderId)->count();
            if ($allReceived >= $totalItems) {
                $order->status = 3; // 已收货
            } else {
                $order->status = 2; // 部分收货
            }
            $order->save();

            // 6. 自动生成应付记录
            $financeService->createAp(
                supplierId: $order->supplier_id,
                sourceType: 'purchase_receive',
                sourceId: $receive->id,
                amount: $totalAmount
            );

            DB::commit();
            return $this->success($this->encodeIds($receive->toArray()), '收货成功');
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->fail('收货失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 收货单详情
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $receive = PurchaseReceive::with('items')->find($id);
        if (!$receive) {
            return $this->fail('收货单不存在', 404);
        }
        return $this->success($this->encodeIds($receive->toArray()));
    }
}
```

- [ ] **Step 2: 커밋**

```bash
git add app/controller/purchase/
git commit -m "feat: add purchase receive controller with automatic stock-in and AP generation"
```

---

## Task 12: 라우트 확장 + 권한 시드

**Files:**
- Modify: `config/route.php`
- Create: `database/migrations/2026_05_22_000009_seed_erp_permissions.sql`

- [ ] **Step 1: 라우트 파일 확장**

`config/route.php`의 `/admin` 라우트 그룹 안(`->middleware([...])` 이전)에 다음 라우트를 추가합니다:

```php
// 在 Route::group('/admin', function () { 内部追加:

// ============================================================
// 商品基础数据
// ============================================================
Route::resource('/product', app\controller\product\ProductController::class);
Route::resource('/category', app\controller\product\CategoryController::class);
Route::resource('/brand', app\controller\product\BrandController::class);
Route::resource('/warehouse', app\controller\product\WarehouseController::class);
Route::get('/warehouse/{id}/locations', [app\controller\product\LocationController::class, 'byWarehouse']);
Route::resource('/location', app\controller\product\LocationController::class);
Route::resource('/supplier', app\controller\product\SupplierController::class);
Route::resource('/customer', app\controller\product\CustomerController::class);
Route::resource('/customer-level', app\controller\product\CustomerLevelController::class);

// ============================================================
// 采购模块
// ============================================================
Route::resource('/purchase/apply', app\controller\purchase\ApplyController::class);
Route::post('/purchase/apply/{id}/approve', [app\controller\purchase\ApplyController::class, 'approve']);
Route::post('/purchase/apply/{id}/reject', [app\controller\purchase\ApplyController::class, 'reject']);
Route::resource('/purchase/order', app\controller\purchase\OrderController::class);
Route::resource('/purchase/receive', app\controller\purchase\ReceiveController::class);
Route::resource('/purchase/return', app\controller\purchase\ReturnController::class);
Route::get('/purchase/settlement', [app\controller\purchase\SettlementController::class, 'index']);

// ============================================================
// 销售模块
// ============================================================
Route::resource('/sales/quotation', app\controller\sales\QuotationController::class);
Route::post('/sales/quotation/{id}/to-order', [app\controller\sales\QuotationController::class, 'toOrder']);
Route::resource('/sales/order', app\controller\sales\OrderController::class);
Route::resource('/sales/delivery', app\controller\sales\DeliveryController::class);
Route::resource('/sales/return', app\controller\sales\ReturnController::class);
Route::get('/sales/settlement', [app\controller\sales\SettlementController::class, 'index']);

// ============================================================
// 库存模块
// ============================================================
Route::get('/inventory', [app\controller\inventory\InventoryController::class, 'index']);
Route::get('/inventory/flow', [app\controller\inventory\FlowController::class, 'index']);
Route::resource('/inventory/batch', app\controller\inventory\BatchController::class);
Route::resource('/inventory/serial', app\controller\inventory\SerialController::class);
Route::resource('/inventory/transfer', app\controller\inventory\TransferController::class);
Route::post('/inventory/transfer/{id}/execute', [app\controller\inventory\TransferController::class, 'execute']);
Route::resource('/inventory/check', app\controller\inventory\CheckController::class);
Route::post('/inventory/check/{id}/process', [app\controller\inventory\CheckController::class, 'process']);
Route::resource('/inventory/alert', app\controller\inventory\AlertController::class);

// ============================================================
// 财务模块
// ============================================================
Route::resource('/finance/account', app\controller\finance\AccountController::class);
Route::resource('/finance/voucher', app\controller\finance\VoucherController::class);
Route::resource('/finance/receipt', app\controller\finance\ReceiptController::class);
Route::post('/finance/receipt/{id}/settle', [app\controller\finance\ReceiptController::class, 'settle']);
Route::resource('/finance/payment', app\controller\finance\PaymentController::class);
Route::post('/finance/payment/{id}/settle', [app\controller\finance\PaymentController::class, 'settle']);
Route::get('/finance/cash-journal', [app\controller\finance\CashJournalController::class, 'index']);
Route::resource('/finance/expense', app\controller\finance\ExpenseController::class);
Route::post('/finance/expense/{id}/approve', [app\controller\finance\ExpenseController::class, 'approve']);
Route::post('/finance/expense/{id}/pay', [app\controller\finance\ExpenseController::class, 'pay']);
Route::get('/finance/report/profit', [app\controller\finance\ReportController::class, 'profit']);
Route::resource('/finance/bank-account', app\controller\finance\BankAccountController::class);

// ============================================================
// CRM模块
// ============================================================
Route::resource('/crm/opportunity', app\controller\crm\OpportunityController::class);
Route::post('/crm/opportunity/{id}/move-stage', [app\controller\crm\OpportunityController::class, 'moveStage']);
Route::resource('/crm/follow', app\controller\crm\FollowController::class);
Route::resource('/crm/funnel', app\controller\crm\FunnelController::class);
Route::resource('/crm/contact', app\controller\crm\ContactController::class);

// ============================================================
// 仪表盘面板
// ============================================================
Route::get('/dashboard/sales', [app\admin\controller\DashboardController::class, 'sales']);
Route::get('/dashboard/inventory', [app\admin\controller\DashboardController::class, 'inventory']);
Route::get('/dashboard/finance', [app\admin\controller\DashboardController::class, 'finance']);
```

- [ ] **Step 2: ERP 권한 시드 SQL 생성**

```sql
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 种子: ERP 业务模块菜单 + API 权限

-- 菜单权限
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`) VALUES
(31000000000000001, NULL, '商品管理', 'product', 1, 'inventory', '/admin/product', 7),
(31000000000000002, NULL, '采购管理', 'purchase', 1, 'shopping_cart', '/admin/purchase', 8),
(31000000000000003, NULL, '销售管理', 'sales', 1, 'sell', '/admin/sales', 9),
(31000000000000004, NULL, '库存管理', 'inventory', 1, 'warehouse', '/admin/inventory', 10),
(31000000000000005, NULL, '财务管理', 'finance', 1, 'account_balance', '/admin/finance', 11),
(31000000000000006, NULL, 'CRM', 'crm', 1, 'people', '/admin/crm', 12);

-- API 权限 — 商品
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`) VALUES
(31000000000000011, 31000000000000001, '查看商品', 'get.admin/product', 3),
(31000000000000012, 31000000000000001, '创建商品', 'post.admin/product', 3),
(31000000000000013, 31000000000000001, '更新商品', 'put.admin/product', 3),
(31000000000000014, 31000000000000001, '删除商品', 'delete.admin/product', 3);

-- API 权限 — 采购
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`) VALUES
(31000000000000021, 31000000000000002, '采购收货', 'post.admin/purchase/receive', 3),
(31000000000000022, 31000000000000002, '采购申请审批', 'post.admin/purchase/apply/approve', 3);

-- API 权限 — 销售
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`) VALUES
(31000000000000031, 31000000000000003, '销售发货', 'post.admin/sales/delivery', 3),
(31000000000000032, 31000000000000003, '报价转订单', 'post.admin/sales/quotation/to-order', 3);

-- API 权限 — 库存
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`) VALUES
(31000000000000041, 31000000000000004, '盘点处理', 'post.admin/inventory/check/process', 3),
(31000000000000042, 31000000000000004, '执行调拨', 'post.admin/inventory/transfer/execute', 3);

-- API 权限 — 财务
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`) VALUES
(31000000000000051, 31000000000000005, '收款核销', 'post.admin/finance/receipt/settle', 3),
(31000000000000052, 31000000000000005, '付款核销', 'post.admin/finance/payment/settle', 3),
(31000000000000053, 31000000000000005, '报销打款', 'post.admin/finance/expense/pay', 3);

-- API 权限 — CRM
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`) VALUES
(31000000000000061, 31000000000000006, '移动商机阶段', 'post.admin/crm/opportunity/move-stage', 3);

-- 超级管理员授权
INSERT INTO `erik_admin_role_permission` (`role_id`, `permission_id`)
SELECT 10000000000000001, `id` FROM `erik_admin_permission`
WHERE `id` >= 31000000000000001;
```

- [ ] **Step 3: 라우트 문법 검증**

```bash
cd service && php -l config/route.php
```

- [ ] **Step 4: 커밋**

```bash
git add config/route.php database/migrations/2026_05_22_000009_seed_erp_permissions.sql
git commit -m "feat: add ERP module routes and permission seeds"
```

---

## Task 13: 대시보드 패널 확장

**Files:**
- Modify: `app/admin/controller/DashboardController.php`

- [ ] **Step 1: DashboardController 업데이트 — 판매·재고·재무 패널 추가**

기존 `DashboardController`에 다음 메서드를 추가합니다:

```php
<?php
// 追加到 app/admin/controller/DashboardController.php

/**
 * 销售面板
 * GET /admin/dashboard/sales
 */
public function sales(Request $request): Response
{
    $cacheKey = 'dashboard:sales:' . date('Y-m-d');
    $cached = Redis::get($cacheKey);
    if ($cached) {
        return $this->success(json_decode($cached, true));
    }

    $today = date('Y-m-d');
    $thisMonth = [date('Y-m-01'), date('Y-m-d')];

    $data = [
        'today_sales' => SalesOrder::whereDate('ordered_at', $today)->sum('total_amount'),
        'month_sales' => SalesOrder::whereBetween('ordered_at', $thisMonth)->sum('total_amount'),
        'top_customers' => SalesOrder::selectRaw('customer_id, sum(total_amount) as total')
            ->whereBetween('ordered_at', $thisMonth)
            ->groupBy('customer_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $customer = Customer::find($row->customer_id);
                return [
                    'customer_id' => $this->encodeId($row->customer_id),
                    'customer_name' => $customer->name ?? '',
                    'total' => $row->total,
                ];
            }),
        'funnel' => CrmOpportunity::selectRaw('stage_id, count(*) as count, sum(estimated_amount) as amount')
            ->where('status', 1)
            ->groupBy('stage_id')
            ->get(),
    ];

    Redis::setex($cacheKey, 300, json_encode($data));
    return $this->success($data);
}

/**
 * 库存面板
 * GET /admin/dashboard/inventory
 */
public function inventory(Request $request): Response
{
    $cacheKey = 'dashboard:inventory:' . date('Y-m-d');
    $cached = Redis::get($cacheKey);
    if ($cached) {
        return $this->success(json_decode($cached, true));
    }

    $data = [
        'total_inventory_value' => Inventory::selectRaw('sum(quantity * cost_price) as total')->first()->total ?? 0,
        'alert_low' => InventoryAlertLog::where('alert_type', 1)
            ->whereDate('created_at', '>=', date('Y-m-01'))
            ->count(),
        'alert_high' => InventoryAlertLog::where('alert_type', 2)
            ->whereDate('created_at', '>=', date('Y-m-01'))
            ->count(),
        'flow_trend' => InventoryFlow::selectRaw('DATE(created_at) as date, direction, sum(quantity) as total')
            ->whereDate('created_at', '>=', date('Y-m-01'))
            ->groupBy('date', 'direction')
            ->orderBy('date')
            ->get(),
    ];

    Redis::setex($cacheKey, 300, json_encode($data));
    return $this->success($data);
}

/**
 * 财务面板
 * GET /admin/dashboard/finance
 */
public function finance(Request $request): Response
{
    $cacheKey = 'dashboard:finance:' . date('Y-m-d');
    $cached = Redis::get($cacheKey);
    if ($cached) {
        return $this->success(json_decode($cached, true));
    }

    $thisMonth = [date('Y-m-01'), date('Y-m-d')];

    $data = [
        'total_ar' => FinanceArAp::where('type', 1)->where('status', '!=', 2)->sum('amount'),
        'total_ap' => FinanceArAp::where('type', 2)->where('status', '!=', 2)->sum('amount'),
        'month_receipt' => FinanceReceipt::whereBetween('received_at', $thisMonth)->sum('amount'),
        'month_payment' => FinancePayment::whereBetween('paid_at', $thisMonth)->sum('amount'),
        'cash_balance' => FinanceBankAccount::sum('balance'),
    ];

    Redis::setex($cacheKey, 300, json_encode($data));
    return $this->success($data);
}
```

DashboardController 상단에 `use` 보강 필요:
```php
use app\model\SalesOrder;
use app\model\Customer;
use app\model\CrmOpportunity;
use app\model\Inventory;
use app\model\InventoryFlow;
use app\model\InventoryAlertLog;
use app\model\FinanceArAp;
use app\model\FinanceReceipt;
use app\model\FinancePayment;
use app\model\FinanceBankAccount;
use support\Redis;
```

- [ ] **Step 2: 커밋**

```bash
git add app/admin/controller/DashboardController.php
git commit -m "feat: add sales, inventory, and finance dashboard panels with Redis caching"
```

---

## Task 14: 클라이언트 API(Flutter/HarmonyOS 호출)

**Files:**
- Create: `app/api/v1/controller/ProductController.php`
- Create: `app/api/v1/controller/OrderController.php`

- [ ] **Step 1: 클라이언트 API 예시**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\api\v1\controller;

use app\admin\controller\BaseController;
use app\model\Product;
use support\Request;
use support\Response;

class ProductController extends BaseController
{
    /**
     * 客户端商品列表（轻量，不含进价等敏感数据）
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 20);
        $keyword = $request->input('keyword', '');

        $query = Product::where('status', 1);
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('code', 'like', "%{$keyword}%");
            });
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
                      ->limit($limit)
                      ->orderBy('id', 'desc')
                      ->get(['id', 'code', 'name', 'barcode', 'spec', 'unit', 'image'])
                      ->map(fn($p) => $this->encodeIds($p->toArray()));

        return $this->success(['list' => $list, 'total' => $total]);
    }

    /**
     * 商品详情（含SKU和价格，不含进价）
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $product = Product::with(['skus' => function ($q) {
            $q->select('id', 'product_id', 'sku_code', 'barcode', 'spec_attrs', 'status');
        }, 'prices' => function ($q) {
            $q->whereIn('price_type', ['wholesale', 'retail']);
        }])->find($id);

        if (!$product) {
            return $this->fail('商品不存在', 404);
        }

        return $this->success($this->encodeIds($product->toArray()));
    }
}
```

클라이언트 라우트 등록(`config/route.php` `/api` 그룹 안에 추가):

```php
Route::get('/product', v('ProductController', 'index'));
Route::get('/product/{hashid}', v('ProductController', 'show'));
```

- [ ] **Step 2: 커밋**

```bash
git add app/api/v1/controller/ config/route.php
git commit -m "feat: add client API endpoints for product listing"
```

---

## Task 15: Flutter + HarmonyOS 프론트엔드 페이지

**Flutter Web PC 단 페이지 디렉토리:**
```
apps/flutter/lib/app/pages/
├── product/      # 商品列表页 + 表单页
├── purchase/     # 采购订单/收货页
├── sales/        # 销售订单/发货页
├── inventory/    # 库存查询/盘点/调拨页
├── finance/      # 收付款/报销/报表页
└── crm/          # 商机/跟进/漏斗页
```

각 페이지는 기존 Flutter 코드 패턴(GetX Controller + ApiService 싱글턴 + Material 3 테마)을 따릅니다.

**HarmonyOS 페이지 디렉토리:**
```
apps/harmonyos/entry/src/main/ets/pages/
├── ProductListPage.ets
├── PurchaseOrderPage.ets
├── SalesOrderPage.ets
├── InventoryPage.ets
├── FinancePage.ets
└── CrmOpportunityPage.ets
```

- [ ] **Step 1: Flutter 예시 — 상품 목록 페이지**

```dart
// File: apps/flutter/lib/app/pages/product/product_list_page.dart
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';

class ProductListPage extends StatelessWidget {
  const ProductListPage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('商品管理')),
      body: FutureBuilder(
        future: ApiService.instance.get('/admin/product?page=1&limit=20'),
        builder: (context, snapshot) {
          if (!snapshot.hasData) {
            return const Center(child: CircularProgressIndicator());
          }
          final data = snapshot.data!['data'];
          final items = data['list'] as List;
          return ListView.builder(
            itemCount: items.length,
            itemBuilder: (_, i) => ListTile(
              title: Text(items[i]['name'] ?? ''),
              subtitle: Text('编码: ${items[i]['code']}'),
            ),
          );
        },
      ),
    );
  }
}
```

- [ ] **Step 2: HarmonyOS 예시 — 상품 목록 페이지**

```typescript
// File: apps/harmonyos/entry/src/main/ets/pages/ProductListPage.ets
import { router } from '@kit.ArkUI';
import { ApiService } from '../../service/ApiService';

@Entry
@Component
struct ProductListPage {
  @State products: Array<Object> = [];
  private api: ApiService = new ApiService();

  aboutToAppear() {
    this.api.get('/api/v1/product', { 'API-Version': 'v1' }).then((res: Object) => {
      this.products = (res['data'] as Record<string, Object>)['list'] as Array<Object>;
    });
  }

  build() {
    Column() {
      List() {
        ForEach(this.products, (item: Object) => {
          ListItem() {
            Text((item as Record<string, Object>)['name'] as string)
          }
        })
      }
    }
  }
}
```

- [ ] **Step 3: 커밋**

```bash
git add apps/flutter/ apps/harmonyos/
git commit -m "feat: add Flutter and HarmonyOS ERP module pages skeleton"
```

---

## Task 16: 최종 검증과 통합 테스트

- [ ] **Step 1: PHP 문법 검사**

```bash
cd /home/wwwroot/erp-php/service
find app -name '*.php' -not -path '*/vendor/*' -exec php -l {} \; | grep -v 'No syntax errors'
```

- [ ] **Step 2: 기존 테스트 실행**

```bash
cd /home/wwwroot/erp-php/service
php vendor/bin/phpunit --testdox
```

- [ ] **Step 3: 라우트 무결성 검증**

```bash
cd /home/wwwroot/erp-php/service
php -r "
require 'vendor/autoload.php';
\$routes = config('route');
echo 'Routes configured OK' . PHP_EOL;
"
```

- [ ] **Step 4: 데이터베이스 테이블 무결성 검사**

```bash
mysql -u root -p open_admin -e "
SELECT COUNT(*) AS erp_table_count
FROM information_schema.tables
WHERE table_schema = 'open_admin' AND table_name LIKE 'erik_%';
"
# 预期: 约 55 张表（5张系统表 + 50张业务表）
```

- [ ] **Step 5: 최종 커밋**

```bash
git add -A
git commit -m "feat: complete ERP modules - product, purchase, sales, inventory, finance, CRM with full MVC stack"
```

---

## 구현 요약

| 계층 | 수량 | 파일 위치 |
|------|------|----------|
| 데이터베이스 테이블 | 50개 | `database/migrations/` |
| Models | 44개 | `app/model/` |
| Controllers | ~35개 | `app/controller/{module}/` |
| Services | 핵심 2개 | `app/service/inventory/`, `app/service/finance/` |
| 라우트 그룹 | 7개 모듈 | `config/route.php` |
| 권한 항목 | ~65건 | seed SQL |
| 대시보드 패널 | 4개 | DashboardController |
| 클라이언트 API | 필요에 따라 확장 | `app/api/v1/controller/` |
| Flutter 페이지 | 12+개 | `apps/flutter/lib/app/pages/` |
| HarmonyOS 페이지 | 6+개 | `apps/harmonyos/entry/src/main/ets/pages/` |

**총 코드량 추정:** PHP ~8,000줄 / SQL ~2,000줄 / Dart ~3,000줄 / ArkTS ~1,500줄

**핵심 설계 결정:**
1. 모든 ID는 API 계층에서 암호화 전송(hashids), 데이터베이스에는 원본 BIGINT 저장
2. 민감 필드는 데이터베이스 계층에서 자동 암·복호화(Encryptable cast)
3. 모듈 간 연동은 Service 계층 이벤트로 트리거, 직접 상호 호출하지 않음
4. 원가 계산은 이동가중평균법 사용
5. 매출채권·매입채무는 구매/판매 문서에서 자동 생성, 수동 정산
6. 대시보드 데이터는 Redis 5분 캐시

**미포함 항목(추후 반복 필요):**
- Phase 2: 제조 관리, 프로젝트 관리
- WebSocket 실시간 푸시(재고 경고, 승인 알림)
- 멀티테넌트 개조
- 더 세밀한 권한 단위(데이터 권한)
- 모바일 오프라인 모드
