<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * @Apidoc\Tag("上传管理")
 */

declare(strict_types=1);

namespace app\admin\controller;

use support\Request;
use support\Response;

class UploadController extends BaseController
{
    private array $allowExts = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'xlsx', 'docx'];
    private int $maxSize = 10 * 1024 * 1024;

    /**
     * 文件上传
     * @Apidoc\Title("文件上传")
     * @Apidoc\Desc("上传文件到服务器，支持jpg/jpeg/png/gif/pdf/xlsx/docx格式，最大10MB")
     * @Apidoc\Url("/admin/upload")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("上传管理")
     * @Apidoc\Param(name="file", type="file", require=true, desc="上传文件(最大10MB)")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="上传结果", children={
     *     @Apidoc\Returned("url", type="string", desc="文件访问相对路径"),
     * })
     */
    public function upload(Request $request): Response
    {
        $file = $request->file('file');
        if (!$file) {
            return $this->fail('请选择文件', 422);
        }

        if (!$file->isValid()) {
            return $this->fail('文件上传失败', 500);
        }

        $ext = strtolower($file->getUploadExtension() ?: 'bin');
        if (!in_array($ext, $this->allowExts, true)) {
            return $this->fail('不支持的文件类型: .' . $ext, 422);
        }

        if ($file->getSize() > $this->maxSize) {
            return $this->fail('文件大小不能超过 10MB', 422);
        }

        $dateDir  = date('Y-m-d');
        $filename = md5(uniqid((string) mt_rand(), true)) . '.' . $ext;
        $relativePath = "/upload/{$dateDir}/{$filename}";
        $absoluteDir  = public_path() . "/upload/{$dateDir}";

        if (!is_dir($absoluteDir)) {
            mkdir($absoluteDir, 0755, true);
        }

        $file->move($absoluteDir . '/' . $filename);

        return $this->success(['url' => $relativePath], '上传成功');
    }
}
