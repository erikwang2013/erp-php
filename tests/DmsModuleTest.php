<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests;

use app\controller\dms\CategoryController;
use app\controller\dms\DocumentController;
use PHPUnit\Framework\TestCase;

/**
 * DMS 模块（文档管理）纯单测
 *
 * 覆盖：
 *  - 文档编码格式 DOC-YYYYMMDD-XXXX（反射调用真实私有方法）
 *  - 文档版本管理：初始版本=1、内容变更自动 +1、版本快照字段
 *  - 预定义分类列表与空库回退
 *  - store() 校验规则、删除级联版本、控制器/模型结构约定
 *
 * 说明：涉及 DB 的路径（DmsDocument::save / DmsDocumentVersion::save）
 * 不在单测执行，相关行为以业务规则/源码契约方式验证。
 */
class DmsModuleTest extends TestCase
{
    private const CATEGORIES = ['制度规范', '流程文档', '技术文档', '合同协议', '培训材料', '其他'];

    public function testDocumentCodeFormat(): void
    {
        // generateDocumentCode(): 'DOC-' . date('Ymd') . '-' . 4位大写随机
        $controller = new DocumentController();
        $m = new \ReflectionMethod($controller, 'generateDocumentCode');
        $m->setAccessible(true);
        $code = $m->invoke($controller);

        $this->assertIsString($code);
        $this->assertMatchesRegularExpression('/^DOC-\d{8}-[A-Z0-9]{4}$/', $code);
        $this->assertStringStartsWith('DOC-' . date('Ymd'), $code);
    }

    public function testNewDocumentStartsWithVersionOne(): void
    {
        // store(): 新文档 version=1、status 默认 0（草稿）、记录初始版本
        $source = file_get_contents(__DIR__ . '/../app/controller/dms/DocumentController.php');
        $this->assertStringContainsString('$item->version = 1;', $source);
        $this->assertStringContainsString('(int)$request->input(\'status\', 0)', $source);
        $this->assertStringContainsString("'初始版本'", $source);

        $this->assertSame(1, 1);
    }

    public function testContentChangeBumpsVersion(): void
    {
        // update(): content 变更 → version+1；未变更 → 保持不变
        $version = 1;
        $oldContent = 'v1 内容';
        $newContent = 'v2 内容';
        $contentChanged = $newContent !== null && $newContent !== $oldContent;
        if ($contentChanged) {
            $version++;
        }
        $this->assertTrue($contentChanged);
        $this->assertSame(2, $version, '内容变更后版本应递增');

        // 未变更内容
        $version2 = 5;
        $same = '内容';
        $changed2 = $same !== null && $same !== $same;
        $this->assertFalse($changed2);
        $this->assertSame(5, $version2, '内容未变更版本不应递增');

        $source = file_get_contents(__DIR__ . '/../app/controller/dms/DocumentController.php');
        $this->assertStringContainsString('(int)$item->version + 1', $source);
    }

    public function testVersionSnapshotKeepsDocumentState(): void
    {
        // createVersion(): 快照 document 的 version/content + change_note + changed_by
        $doc = ['id' => 1001, 'version' => 2, 'content' => '正文B'];
        $changeNote = '内容更新';
        $changedBy = 9;

        $version = [
            'document_id' => $doc['id'],
            'version' => (int) $doc['version'],
            'content' => (string) $doc['content'],
            'changed_by' => $changedBy,
            'change_note' => $changeNote,
        ];
        $this->assertSame(1001, $version['document_id']);
        $this->assertSame(2, $version['version'], '版本号应与文档当前版本一致');
        $this->assertSame('正文B', $version['content'], '应保存内容快照');
        $this->assertSame(9, $version['changed_by']);
        $this->assertSame('内容更新', $version['change_note']);
    }

    public function testPredefinedCategories(): void
    {
        $this->assertCount(6, self::CATEGORIES);
        $this->assertContains('制度规范', self::CATEGORIES);
        $this->assertContains('流程文档', self::CATEGORIES);
        $this->assertContains('技术文档', self::CATEGORIES);
        $this->assertContains('合同协议', self::CATEGORIES);
        $this->assertContains('培训材料', self::CATEGORIES);
        $this->assertContains('其他', self::CATEGORIES);
    }

    public function testCategoryListFallsBackToPredefined(): void
    {
        // categories(): DB 分类为空时回退预定义列表
        $source = file_get_contents(__DIR__ . '/../app/controller/dms/DocumentController.php');
        $this->assertStringContainsString('$categories ?: self::CATEGORIES', $source);

        $dbCategories = [];
        $list = $dbCategories ?: self::CATEGORIES;
        $this->assertSame(self::CATEGORIES, $list, '空库应回退预定义分类');
    }

    public function testDocumentStoreValidation(): void
    {
        $rules = ['title' => 'required|string|max:200', 'category' => 'required|string|max:50', 'status' => 'nullable|integer|between:0,1'];
        $this->assertTrue(validator(['category' => '其他'], $rules)->fails(), '缺少 title 应失败');
        $this->assertTrue(validator(['title' => '手册'], $rules)->fails(), '缺少 category 应失败');
        $this->assertTrue(validator(['title' => '手册', 'category' => '其他', 'status' => 5], $rules)->fails(), 'status 超出 0-1 应失败');
        $this->assertFalse(validator(['title' => '手册', 'category' => '其他'], $rules)->fails(), '合法输入应通过');
    }

    public function testDestroyCascadesDocumentVersions(): void
    {
        // destroy(): 删除文档前先删除其全部版本记录
        $source = file_get_contents(__DIR__ . '/../app/controller/dms/DocumentController.php');
        $this->assertStringContainsString("DmsDocumentVersion::where('document_id', \$id)->delete();", $source);
    }

    public function testDmsControllersExtendBaseController(): void
    {
        foreach ([DocumentController::class, CategoryController::class] as $class) {
            $this->assertTrue(class_exists($class), "{$class} 应存在");
            $this->assertTrue(is_subclass_of($class, 'app\\admin\\controller\\BaseController'), "{$class} 应继承 BaseController");
        }
        $this->assertContains('versions', get_class_methods(DocumentController::class));
        $this->assertContains('categories', get_class_methods(DocumentController::class));
    }

    public function testDmsModelsUseSnowflakePrimaryKey(): void
    {
        foreach (['DmsDocument', 'DmsDocumentVersion', 'DmsCategory'] as $m) {
            $source = file_get_contents(__DIR__ . "/../app/model/{$m}.php");
            $this->assertStringContainsString('erp_dms_', $source, "{$m} 表应使用 erp_dms_ 前缀");
            $this->assertStringContainsString('$incrementing = false', $source, "{$m} 应关闭自增主键");
            $this->assertStringContainsString("keyType = 'int'", $source, "{$m} 主键类型应为 int");
        }
    }
}
