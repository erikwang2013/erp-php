<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\controller\print;

use erikwang2013\apidoc\annotation as Apidoc;

use app\admin\controller\BaseController;
use app\service\print\PrintTemplateService;
use InvalidArgumentException;
use support\Container;
use support\Request;
use support\Response;

/**
 * 单据打印模板（P1-B1）
 *
 * 模板模型 + 占位符渲染（dompdf 出 PDF）+ 二维码出图（poster，{{qr:}} 占位符）。
 * CRUD 走 hashid 路由；render/pdf 用模板 code 直调（单据侧集成按 code 引用模板）。
 */
class PrintTemplateController extends BaseController
{
    /**
     * 模板列表（分页）
     * @Apidoc\Title("打印模板列表")
     * @Apidoc\Desc("分页查询打印模板，支持关键词与单据类型过滤")
     * @Apidoc\Url("/admin/v1/print/template")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("打印模板")
     * @Apidoc\Param(name="page", type="int", desc="页码")
     * @Apidoc\Param(name="limit", type="int", desc="每页条数")
     * @Apidoc\Param(name="keyword", type="string", desc="关键词(code/name)")
     * @Apidoc\Param(name="target_type", type="string", desc="适用单据类型")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     */#[Apidoc\Title("打印模板列表")]
#[Apidoc\Desc("分页查询打印模板，支持关键词与单据类型过滤")]
#[Apidoc\Url("/admin/v1/print/template")]
#[Apidoc\Method("GET")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("打印模板")]
#[Apidoc\Param(name:"page", type:"int", desc:"页码")]
#[Apidoc\Param(name:"limit", type:"int", desc:"每页条数")]
#[Apidoc\Param(name:"keyword", type:"string", desc:"关键词(code/name)")]
#[Apidoc\Param(name:"target_type", type:"string", desc:"适用单据类型")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]

    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $result = $this->printService()->listTemplates(
            (string) $request->input('keyword', ''),
            (string) $request->input('target_type', ''),
            $page,
            $limit
        );

        return $this->successPage(
            array_map(fn (array $t) => $this->encodeIds($t), $result['list']),
            $result['total'],
            $page,
            $limit
        );
    }

    /**
     * 新建模板
     * @Apidoc\Title("新建打印模板")
     * @Apidoc\Desc("code/name/content 必填；code 全局唯一（含软删）")
     * @Apidoc\Url("/admin/v1/print/template")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("打印模板")
     * @Apidoc\Param(name="code", type="string", require=true, desc="模板编码")
     * @Apidoc\Param(name="name", type="string", require=true, desc="模板名称")
     * @Apidoc\Param(name="content", type="string", require=true, desc="HTML模板体")
     * @Apidoc\Param(name="target_type", type="string", desc="适用单据类型")
     * @Apidoc\Param(name="paper_size", type="string", desc="纸张，默认A4")
     * @Apidoc\Param(name="orientation", type="string", desc="portrait/landscape")
     */#[Apidoc\Title("新建打印模板")]
#[Apidoc\Desc("code/name/content 必填；code 全局唯一（含软删）")]
#[Apidoc\Url("/admin/v1/print/template")]
#[Apidoc\Method("POST")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("打印模板")]
#[Apidoc\Param(name:"code", type:"string", require:true, desc:"模板编码")]
#[Apidoc\Param(name:"name", type:"string", require:true, desc:"模板名称")]
#[Apidoc\Param(name:"content", type:"string", require:true, desc:"HTML模板体")]
#[Apidoc\Param(name:"target_type", type:"string", desc:"适用单据类型")]
#[Apidoc\Param(name:"paper_size", type:"string", desc:"纸张，默认A4")]
#[Apidoc\Param(name:"orientation", type:"string", desc:"portrait/landscape")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:100',
            'content' => 'required|string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            $tpl = $this->printService()->createTemplate($request->all());
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($tpl->toArray()), '创建成功');
    }

    /**
     * 模板详情（含占位符清单）
     * @Apidoc\Title("打印模板详情")
     * @Apidoc\Desc("返回模板字段与 content 中的占位符 token 清单，供前端设计器提示")
     * @Apidoc\Url("/admin/v1/print/template/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("打印模板")
     */#[Apidoc\Title("打印模板详情")]
#[Apidoc\Desc("返回模板字段与 content 中的占位符 token 清单，供前端设计器提示")]
#[Apidoc\Method("GET")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("打印模板")]

    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $tpl = $this->printService()->getById($id);
        if (!$tpl) {
            return $this->fail('模板不存在', 404);
        }

        $data = $this->encodeIds($tpl->toArray());
        $data['placeholders'] = $this->printService()->parsePlaceholders((string) $tpl->content);

        return $this->success($data);
    }

    /**
     * 更新模板
     * @Apidoc\Title("更新打印模板")
     * @Apidoc\Desc("部分更新：仅提交的字段生效")
     * @Apidoc\Url("/admin/v1/print/template/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("打印模板")
     */#[Apidoc\Title("更新打印模板")]
#[Apidoc\Desc("部分更新：仅提交的字段生效")]
#[Apidoc\Method("PUT")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("打印模板")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        try {
            $tpl = $this->printService()->updateTemplate($id, $request->all());
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($tpl->toArray()), '更新成功');
    }

    /**
     * 删除模板（需密码确认）
     * @Apidoc\Title("删除打印模板")
     * @Apidoc\Desc("软删除；uk_code 唯一性保留，删除后同编码需换码")
     * @Apidoc\Url("/admin/v1/print/template/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("打印模板")
     */#[Apidoc\Title("删除打印模板")]
#[Apidoc\Desc("软删除；uk_code 唯一性保留，删除后同编码需换码")]
#[Apidoc\Method("DELETE")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("打印模板")]

    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        try {
            $this->printService()->deleteTemplate($id);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success([], '删除成功');
    }

    /**
     * 渲染模板为 HTML（预览）
     * @Apidoc\Title("渲染打印模板")
     * @Apidoc\Desc("按模板 code 渲染：占位符替换 + 缺失键清单；不落盘不生成 PDF")
     * @Apidoc\Url("/admin/v1/print/template/render")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("打印模板")
     * @Apidoc\Param(name="code", type="string", require=true, desc="模板编码")
     * @Apidoc\Param(name="data", type="object", desc="渲染数据(支持点路径占位符)")
     */#[Apidoc\Title("渲染打印模板")]
#[Apidoc\Desc("按模板 code 渲染：占位符替换 + 缺失键清单；不落盘不生成 PDF")]
#[Apidoc\Url("/admin/v1/print/template/render")]
#[Apidoc\Method("POST")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("打印模板")]
#[Apidoc\Param(name:"code", type:"string", require:true, desc:"模板编码")]
#[Apidoc\Param(name:"data", type:"object", desc:"渲染数据(支持点路径占位符)")]

    public function render(Request $request): Response
    {
        $code = (string) $request->input('code', '');
        $data = $request->input('data', []);
        if ($code === '') {
            return $this->fail('模板编码不能为空', 422);
        }
        if (!is_array($data)) {
            return $this->fail('data 必须是对象', 422);
        }

        try {
            $result = $this->printService()->render($code, $data);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($result);
    }

    /**
     * 渲染并下载 PDF
     * @Apidoc\Title("下载打印 PDF")
     * @Apidoc\Desc("按模板 code 渲染并输出 PDF 文件（纸张/方向取模板配置）")
     * @Apidoc\Url("/admin/v1/print/template/pdf")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("打印模板")
     * @Apidoc\Param(name="code", type="string", require=true, desc="模板编码")
     * @Apidoc\Param(name="data", type="object", desc="渲染数据")
     */#[Apidoc\Title("下载打印 PDF")]
#[Apidoc\Desc("按模板 code 渲染并输出 PDF 文件（纸张/方向取模板配置）")]
#[Apidoc\Url("/admin/v1/print/template/pdf")]
#[Apidoc\Method("POST")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("打印模板")]
#[Apidoc\Param(name:"code", type:"string", require:true, desc:"模板编码")]
#[Apidoc\Param(name:"data", type:"object", desc:"渲染数据")]

    public function pdf(Request $request): Response
    {
        $code = (string) $request->input('code', '');
        $data = $request->input('data', []);
        if ($code === '') {
            return $this->fail('模板编码不能为空', 422);
        }
        if (!is_array($data)) {
            return $this->fail('data 必须是对象', 422);
        }

        try {
            $binary = $this->printService()->renderPdf($code, $data);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        $filename = sprintf('print_%s_%s.pdf', $code, date('YmdHis'));
        $tmpFile = runtime_path() . '/tmp/' . $filename;
        $dir = dirname($tmpFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($tmpFile, $binary);

        return response()->download($tmpFile, $filename);
    }

    // ---------- 私有辅助 ----------

    private function printService(): PrintTemplateService
    {
        return Container::get(PrintTemplateService::class);
    }
}
