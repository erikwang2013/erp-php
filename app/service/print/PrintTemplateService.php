<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\print;

use app\common\SnowflakeService;
use app\model\PrintTemplate;
use Dompdf\Dompdf;
use Erikwang2013\Poster\Qrcode\QrcodeGenerator;
use InvalidArgumentException;

/**
 * 单据打印模板引擎（P1-B1）
 *
 * 模板模型 = erp_print_template 单表（content 存 HTML 模板体）。占位符约定：
 *   {{key}}          扁平取值
 *   {{a.b.c}}        点路径穿透嵌套 data
 *   {{date}}         Y-m-d（渲染时刻）
 *   {{datetime}}     Y-m-d H:i:s（渲染时刻）
 *   {{qr:文本}}      poster QrcodeGenerator 出 PNG，内嵌 base64 data URI；
 *                    驱动不可用时渲染空串并计入 missing（不阻断整单打印）
 *
 * 安全与渲染语义：
 *  - 值原样插入，不做 HTML 转义 —— 模板由管理员自维护属可信内容；
 *  - 缺失键渲染为空串，随返回值给出 missing 清单（供预览提示，不抛异常）；
 *  - 金额/数量等字符串值字节级直通（调用方负责 bcmath 规范化，本引擎无算术）。
 *  - 中文打印：dompdf 默认字体无 CJK 字形，模板 content 需自行 @font-face
 *    声明项目/系统字体（与既有 ExportController::pdf 同约束）。
 *
 * 无算术运算 → 无需 bcmath（旁路数据仅透传）。
 */
class PrintTemplateService
{
    /** token 可为任意非空白非花括号字符（含 CJK，如 {{qr:批次A}}）；/u 保证 UTF-8 语义 */
    private const PLACEHOLDER_RE = '/\{\{\s*([^\s{}]+)\s*\}\}/u';

    /** 模板分页列表（软删排除，keyword 命中 code/name） */
    public function listTemplates(string $keyword = '', string $targetType = '', int $page = 1, int $limit = 15): array
    {
        $query = PrintTemplate::query();
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword): void {
                $q->where('name', 'like', "%{$keyword}%")->orWhere('code', 'like', "%{$keyword}%");
            });
        }
        if ($targetType !== '') {
            $query->where('target_type', $targetType);
        }
        $total = $query->count();

        return [
            'total' => $total,
            'list' => $query->orderBy('id', 'desc')
                ->offset(($page - 1) * $limit)->limit($limit)->get()->toArray(),
        ];
    }

    public function getById(int $id): ?PrintTemplate
    {
        return PrintTemplate::find($id);
    }

    public function getByCode(string $code): ?PrintTemplate
    {
        return PrintTemplate::where('code', $code)->first();
    }

    public function createTemplate(array $d): PrintTemplate
    {
        $this->assertContent($d['content'] ?? null);
        if (PrintTemplate::withTrashed()->where('code', $d['code'] ?? '')->exists()) {
            throw new InvalidArgumentException('模板编码已存在（含软删记录）');
        }
        $tpl = new PrintTemplate();
        $tpl->id = SnowflakeService::generate();
        $this->apply($tpl, $d);
        $tpl->save();

        return $tpl;
    }

    public function updateTemplate(int $id, array $d): PrintTemplate
    {
        $tpl = PrintTemplate::find($id);
        if (!$tpl) {
            throw new InvalidArgumentException('模板不存在');
        }
        if (array_key_exists('content', $d)) {
            $this->assertContent($d['content']);
        }
        $this->apply($tpl, $d);
        $tpl->save();

        return $tpl;
    }

    /** 软删除（保留 uk_code 唯一性：删除后同编码需换码或硬删） */
    public function deleteTemplate(int $id): void
    {
        $tpl = PrintTemplate::find($id);
        if (!$tpl) {
            throw new InvalidArgumentException('模板不存在');
        }
        $tpl->delete();
    }

    /**
     * 渲染模板为 HTML。
     *
     * @return array{html: string, missing: list<string>}
     */
    public function render(int|string $idOrCode, array $data): array
    {
        $tpl = $this->resolve($idOrCode);
        $missing = [];

        $html = preg_replace_callback(self::PLACEHOLDER_RE, function (array $m) use ($data, &$missing): string {
            $token = $m[1];

            // 内置函数占位符
            if ($token === 'date') {
                return date('Y-m-d');
            }
            if ($token === 'datetime') {
                return date('Y-m-d H:i:s');
            }
            if (str_starts_with($token, 'qr:')) {
                $text = (string) substr($token, 3);
                if ($text === '') {
                    $missing[] = $token;

                    return '';
                }
                try {
                    return $this->qrDataUri($text);
                } catch (\Throwable $e) {
                    $missing[] = "{$token}({$e->getMessage()})";

                    return '';
                }
            }

            // 数据占位符：点路径穿透
            $value = $this->dotGet($data, $token);
            if ($value === null || $value === '') {
                $missing[] = $token;

                return '';
            }

            return (string) $value;
        }, (string) $tpl->content);

        return ['html' => (string) $html, 'missing' => $missing];
    }

    /**
     * 渲染为 PDF 二进制（dompdf，模板纸张与方向生效）。
     */
    public function renderPdf(int|string $idOrCode, array $data): string
    {
        $tpl = $this->resolve($idOrCode);
        $html = $this->render($idOrCode, $data)['html'];

        $dompdf = new Dompdf();
        $dompdf->setPaper((string) $tpl->paper_size, (string) $tpl->orientation);
        $dompdf->loadHtml($html);
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * 二维码 PNG data URI（poster QrcodeGenerator + GD）。
     *
     * @throws \RuntimeException 图像驱动不可用或生成失败
     */
    public function qrDataUri(string $text): string
    {
        try {
            $image = (new QrcodeGenerator())->setText($text)->render();
        } catch (\Throwable $e) {
            throw new \RuntimeException('二维码生成失败: ' . $e->getMessage());
        }

        ob_start();
        $ok = imagepng($image);
        $png = (string) ob_get_clean();
        if (!$ok || $png === '') {
            throw new \RuntimeException('二维码 PNG 编码失败');
        }
        imagedestroy($image);

        return 'data:image/png;base64,' . base64_encode($png);
    }

    /** 提取模板中的全部占位符 token（去重，供设计器提示字段清单） */
    public function parsePlaceholders(string $content): array
    {
        preg_match_all(self::PLACEHOLDER_RE, $content, $m);

        return array_values(array_unique($m[1]));
    }

    // ---------- 私有辅助 ----------

    private function apply(PrintTemplate $tpl, array $d): void
    {
        foreach (['code', 'name', 'target_type', 'content', 'paper_size', 'orientation', 'enabled', 'remark'] as $k) {
            if (array_key_exists($k, $d) && $d[$k] !== null) {
                $tpl->$k = $d[$k];
            }
        }
    }

    private function assertContent(mixed $content): void
    {
        if (!is_string($content) || trim($content) === '') {
            throw new InvalidArgumentException('模板内容不能为空');
        }
    }

    private function resolve(int|string $idOrCode): PrintTemplate
    {
        $tpl = is_numeric($idOrCode)
            ? PrintTemplate::find((int) $idOrCode)
            : PrintTemplate::where('code', (string) $idOrCode)->first();
        if (!$tpl) {
            throw new InvalidArgumentException('打印模板不存在');
        }

        return $tpl;
    }

    /** 点路径取值：'a.b.c' → data[a][b][c]；无命中返回 null */
    private function dotGet(array $data, string $path): mixed
    {
        $current = $data;
        foreach (explode('.', $path) as $seg) {
            if (is_array($current) && array_key_exists($seg, $current)) {
                $current = $current[$seg];
            } elseif ($current instanceof \ArrayAccess && $current->offsetExists($seg)) {
                $current = $current[$seg];
            } else {
                return null;
            }
        }

        return is_scalar($current) || $current === null ? $current : null;
    }
}
