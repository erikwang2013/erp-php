<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);
namespace app\common\controller;

/**
 * 通用数据结构定义
 */
class Definitions
{
    /**
     * 分页参数
     * @Apidoc\Definition("page", type="int", default=1, desc="页码")
     * @Apidoc\Definition("limit", type="int", default=15, desc="每页条数")
     * @Apidoc\Definition("keyword", type="string", default="", desc="搜索关键词")
     * @Apidoc\Definition("status", type="int", default="", desc="状态筛选")
     */
    public function pagination() {}

    /**
     * 商品对象
     * @Apidoc\Definition("id", type="string", desc="商品ID(hashid)")
     * @Apidoc\Definition("code", type="string", desc="商品编码")
     * @Apidoc\Definition("name", type="string", desc="商品名称")
     * @Apidoc\Definition("barcode", type="string", desc="条码")
     * @Apidoc\Definition("spec", type="string", desc="规格")
     * @Apidoc\Definition("unit", type="string", desc="单位")
     * @Apidoc\Definition("image", type="string", desc="图片URL")
     * @Apidoc\Definition("status", type="int", desc="状态:0禁用1启用")
     */
    public function product() {}

    /**
     * 分页列表响应
     * @Apidoc\Definition("list", type="array", desc="数据列表")
     * @Apidoc\Definition("total", type="int", desc="总条数")
     * @Apidoc\Definition("page", type="int", desc="当前页码")
     * @Apidoc\Definition("limit", type="int", desc="每页条数")
     */
    public function pageList() {}
}
