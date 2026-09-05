<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\dms;

use app\admin\controller\BaseController;
use app\model\DmsCategory;
use support\Request;
use support\Response;

/**
 * 文档分类管理
 */#[\erikwang2013\apidoc\annotation\Tag("文档管理")]

class CategoryController extends BaseController
{
    private const CATEGORIES = ['制度规范', '流程文档', '技术文档', '合同协议', '培训材料', '其他'];

#[\erikwang2013\apidoc\annotation\Title("文档分类列表")]
#[\erikwang2013\apidoc\annotation\Desc("返回启用的文档分类名称列表，无自定义分类时回退内置默认分类")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/dms/categories")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("文档管理")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"list(分类名称数组)")]

    public function index(Request $request): Response
    {
        $categories = DmsCategory::where('status', 1)->orderBy('sort')->pluck('name')->all();

        return $this->success(['list' => $categories ?: self::CATEGORIES]);
    }
}
