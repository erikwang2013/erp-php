<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;

class ControllerPatternTest extends TestCase
{
    /**
     * All business controllers should extend BaseController
     */
    public function testProductControllerExtendsBaseController(): void
    {
        $class = 'app\\controller\\product\\ProductController';
        $this->assertTrue(class_exists($class));
        $this->assertTrue(is_subclass_of($class, 'app\\admin\\controller\\BaseController'));
    }

    public function testReceiveControllerExtendsBaseController(): void
    {
        $class = 'app\\controller\\purchase\\ReceiveController';
        $this->assertTrue(class_exists($class));
        $this->assertTrue(is_subclass_of($class, 'app\\admin\\controller\\BaseController'));
    }

    public function testDeliveryControllerExtendsBaseController(): void
    {
        $class = 'app\\controller\\sales\\DeliveryController';
        $this->assertTrue(class_exists($class));
        $this->assertTrue(is_subclass_of($class, 'app\\admin\\controller\\BaseController'));
    }

    /**
     * All models should have non-incrementing primary keys.
     * Validates via source analysis (support\Model base class is not available in test).
     */
    public function testProductModelUsesSnowflakePrimaryKey(): void
    {
        $source = file_get_contents(__DIR__ . '/../app/model/Product.php');
        $this->assertStringContainsString('public $incrementing = false', $source, 'Product must use non-incrementing PK');
        $this->assertStringContainsString("protected \$keyType = 'int'", $source, 'Product keyType must be int');
        $this->assertStringContainsString('erik_product', $source, 'Product table must use erik_ prefix');
    }

    public function testInventoryModelUsesSnowflakePrimaryKey(): void
    {
        $source = file_get_contents(__DIR__ . '/../app/model/Inventory.php');
        $this->assertStringContainsString('public $incrementing = false', $source, 'Inventory must use non-incrementing PK');
        $this->assertStringContainsString("protected \$keyType = 'int'", $source, 'Inventory keyType must be int');
    }

    /**
     * Verify that key controllers have expected methods
     */
    public function testProductControllerHasCrudMethods(): void
    {
        $methods = get_class_methods('app\\controller\\product\\ProductController');
        $this->assertContains('index', $methods);
        $this->assertContains('store', $methods);
        $this->assertContains('show', $methods);
        $this->assertContains('update', $methods);
        $this->assertContains('destroy', $methods);
    }

    public function testDeliveryControllerHasStoreMethod(): void
    {
        $methods = get_class_methods('app\\controller\\sales\\DeliveryController');
        $this->assertContains('store', $methods, 'DeliveryController must have cross-module store() method');
    }

    /**
     * All key service classes exist
     */
    public function testAllKeyServiceClassesExist(): void
    {
        $services = [
            'app\\service\\inventory\\InventoryService',
            'app\\service\\finance\\FinanceService',
        ];
        foreach ($services as $service) {
            $this->assertTrue(class_exists($service), "Service {$service} should exist");
        }
    }

    /**
     * All finance controllers exist (fix Critical #1-2 validation)
     */
    public function testAllFinanceControllersExist(): void
    {
        $controllers = [
            'app\\controller\\finance\\ArApController',
            'app\\controller\\finance\\ReceiptController',
            'app\\controller\\finance\\PaymentController',
            'app\\controller\\finance\\SettlementController',
            'app\\controller\\finance\\ReportController',
            'app\\controller\\finance\\ExpenseController',
            'app\\controller\\finance\\VoucherController',
            'app\\controller\\finance\\BankAccountController',
            'app\\controller\\finance\\CashJournalController',
        ];
        foreach ($controllers as $controller) {
            $this->assertTrue(class_exists($controller), "Controller {$controller} should exist");
        }
    }
}
