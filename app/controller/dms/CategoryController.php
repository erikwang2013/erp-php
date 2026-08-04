<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\dms;

use app\admin\controller\BaseController;
use support\Request;
use support\Response;

/**
 * 文档分类管理
 * @Apidoc\Tag("文档管理")
 */
class CategoryController extends BaseController
{
    private const CATEGORIES = ['制度规范', '流程文档', '技术文档', '合同协议', '培训材料', '其他'];

    public function index(Request $request): Response
    {
        return $this->success(['list' => self::CATEGORIES]);
    }
}
