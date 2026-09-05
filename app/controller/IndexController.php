<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\controller;

use support\Request;

class IndexController
{
    /**
     * 首页(iframe 官方欢迎页)
     */
#[\erikwang2013\apidoc\annotation\Title("首页")]
#[\erikwang2013\apidoc\annotation\Desc("webman 默认首页，内嵌官方欢迎页 iframe，返回原始 HTML")]
#[\erikwang2013\apidoc\annotation\Url("/")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("系统")]

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
     */
#[\erikwang2013\apidoc\annotation\Title("欢迎视图页")]
#[\erikwang2013\apidoc\annotation\Desc("渲染 index/view 欢迎视图(Blade 模板)")]
#[\erikwang2013\apidoc\annotation\Url("/index/view")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("系统")]

    public function view(Request $request)
    {
        return view('index/view', ['name' => 'webman']);
    }

    /**
     * 健康检查 JSON
     */
#[\erikwang2013\apidoc\annotation\Title("健康检查")]
#[\erikwang2013\apidoc\annotation\Desc("返回固定 JSON 探活响应")]
#[\erikwang2013\apidoc\annotation\Url("/index/json")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("系统")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,恒为0")]
#[\erikwang2013\apidoc\annotation\Returned("msg", type:"string", desc:"提示信息")]

    public function json(Request $request)
    {
        return json(['code' => 0, 'msg' => 'ok']);
    }

}
