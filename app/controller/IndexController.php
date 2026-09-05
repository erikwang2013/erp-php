<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\controller;

use erikwang2013\apidoc\annotation as Apidoc;

use support\Request;

class IndexController
{
    /**
     * 首页(iframe 官方欢迎页)
     * @Apidoc\Title("首页")
     * @Apidoc\Desc("webman 默认首页，内嵌官方欢迎页 iframe，返回原始 HTML")
     * @Apidoc\Url("/")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("系统")
     */
    public function index(Request $request)
    {
        return <<<EOF
<style>
  * {
    padding: 0;
    margin: 0;
  }
  iframe {
    border: none;
    overflow: scroll;
  }
</style>
<iframe
  src="https://www.workerman.net/wellcome"
  width="100%"
  height="100%"
  allow="clipboard-write"
  sandbox="allow-scripts allow-same-origin allow-popups allow-downloads"
></iframe>
EOF;
    }

    /**
     * 欢迎视图页
     * @Apidoc\Title("欢迎视图页")
     * @Apidoc\Desc("渲染 index/view 欢迎视图(Blade 模板)")
     * @Apidoc\Url("/index/view")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("系统")
     */
    public function view(Request $request)
    {
        return view('index/view', ['name' => 'webman']);
    }

    /**
     * 健康检查 JSON
     * @Apidoc\Title("健康检查")
     * @Apidoc\Desc("返回固定 JSON 探活响应")
     * @Apidoc\Url("/index/json")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("系统")
     * @Apidoc\Returned("code", type="int", desc="业务代码,恒为0")
     * @Apidoc\Returned("msg", type="string", desc="提示信息")
     */
    public function json(Request $request)
    {
        return json(['code' => 0, 'msg' => 'ok']);
    }

}
