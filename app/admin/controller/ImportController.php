<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * @Apidoc\Tag("导入管理")
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\model\AdminUser;
use PhpOffice\PhpSpreadsheet\IOFactory;
use support\Log;
use support\Request;
use support\Response;

class ImportController extends BaseController
{
    /**
     * Excel导入用户
     * @Apidoc\Title("Excel导入用户")
     * @Apidoc\Desc("上传Excel文件批量导入用户，支持xlsx/xls格式，必需列为username/password/real_name")
     * @Apidoc\Url("/admin/import/users")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("导入管理")
     * @Apidoc\Param(name="file", type="file", require=true, desc="Excel文件(.xlsx/.xls)")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="导入结果", children={
     *     @Apidoc\Returned("total", type="int", desc="总行数"),
     *     @Apidoc\Returned("success", type="int", desc="成功数"),
     *     @Apidoc\Returned("failed", type="int", desc="失败数"),
     *     @Apidoc\Returned("errors", type="array", desc="错误详情"),
     * })
     */
    public function users(Request $request): Response
    {
        $file = $request->file('file');
        if (!$file || !$file->isValid()) {
            return $this->fail('请上传 Excel 文件', 422);
        }

        $ext = strtolower($file->getUploadExtension() ?: '');
        if (!in_array($ext, ['xlsx', 'xls'], true)) {
            return $this->fail('仅支持 .xlsx 或 .xls 文件', 422);
        }

        $tmpPath = $file->getRealPath();
        $spreadsheet = IOFactory::load($tmpPath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        if (count($rows) < 2) {
            return $this->fail('Excel 文件无数据', 422);
        }

        $headers = array_map('strtolower', array_map('trim', $rows[0]));
        $colMap = array_flip($headers);

        $required = ['username', 'password', 'real_name'];
        foreach ($required as $col) {
            if (!isset($colMap[$col])) {
                return $this->fail("缺少必填列: {$col}", 422);
            }
        }

        $total = 0;
        $success = 0;
        $failed = 0;
        $errors = [];

        // 一次取回全部已存在的用户名（与原逐行 exists() 一致：不含软删除）
        $usernames = [];
        foreach ($rows as $idx => $row) {
            if ($idx === 0) {
                continue;
            }
            $username = trim((string) ($row[$colMap['username']] ?? ''));
            if ($username !== '') {
                $usernames[] = $username;
            }
        }
        $existing = AdminUser::whereIn('username', array_unique($usernames))->pluck('username')->flip();

        $seen = [];
        foreach ($rows as $idx => $row) {
            if ($idx === 0) {
                continue;
            }
            $total++;

            $username = trim((string) ($row[$colMap['username']] ?? ''));
            $password = trim((string) ($row[$colMap['password']] ?? ''));
            $realName = trim((string) ($row[$colMap['real_name']] ?? ''));
            $phone = trim((string) ($row[$colMap['phone']] ?? ''));
            $email = trim((string) ($row[$colMap['email']] ?? ''));
            $status = isset($colMap['status']) ? (int) ($row[$colMap['status']] ?? 1) : 1;

            if (empty($username)) {
                $failed++;
                $errors[] = ['row' => $idx + 1, 'reason' => '用户名为空'];
                continue;
            }

            if (isset($existing[$username]) || isset($seen[$username])) {
                $failed++;
                $errors[] = ['row' => $idx + 1, 'reason' => "用户名 {$username} 已存在"];
                continue;
            }
            $seen[$username] = true;

            try {
                $user = new AdminUser();
                $user->id = $this->generateId();
                $user->username = $username;
                $user->password = password_hash($password, PASSWORD_BCRYPT);
                $user->real_name = $realName;
                $user->status = in_array($status, [0, 1], true) ? $status : 1;
                $user->phone = $phone;
                $user->email = $email;
                $user->save();
                $success++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = ['row' => $idx + 1, 'reason' => $e->getMessage()];
                // 行级失败已回显给客户端，但需留日志便于批量问题排查
                Log::warning('导入用户：第 ' . ($idx + 1) . ' 行失败: ' . $e->getMessage() . ' | TraceId: ' . trace_id());
            }
        }

        return $this->success([
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
        ], '导入完成');
    }
}
