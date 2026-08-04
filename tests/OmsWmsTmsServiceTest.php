<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\common\AddressValidator;
use app\service\oms\RmaService;
use app\service\tms\FreightCalculatorService;
use app\service\tms\TmsShipmentService;
use app\service\tms\TrackingService;
use app\service\wms\WaveService;
use app\service\wms\WmsInboundService;
use app\service\wms\WmsOutboundService;
use PHPUnit\Framework\TestCase;

class OmsWmsTmsServiceTest extends TestCase
{
    // ============================================================
    // AddressValidator
    // ============================================================

    public function test_address_validator_valid_cn(): void
    {
        $result = AddressValidator::validate([
            'contact_name' => 'Test',
            'country' => 'CN',
            'state' => 'GD',
            'city' => 'SZ',
            'district' => 'NS',
            'address_line1' => 'No.1 Road',
            'postal_code' => '518000',
            'phone' => '13800138000',
        ]);
        $this->assertTrue($result['valid']);
    }

    public function test_address_validator_invalid_postal(): void
    {
        $result = AddressValidator::validate([
            'contact_name' => 'John',
            'country' => 'US',
            'address_line1' => '123 Main St',
            'postal_code' => 'abc',
        ]);
        $this->assertFalse($result['valid']);
    }

    public function test_address_validator_missing_required(): void
    {
        $result = AddressValidator::validate(['country' => 'CN']);
        $this->assertFalse($result['valid']);
    }

    public function test_postal_code_us(): void
    {
        $this->assertTrue(AddressValidator::validatePostalCode('US', '10001'));
        $this->assertTrue(AddressValidator::validatePostalCode('US', '10001-1234'));
        $this->assertFalse(AddressValidator::validatePostalCode('US', '1000'));
    }

    public function test_postal_code_cn(): void
    {
        $this->assertTrue(AddressValidator::validatePostalCode('CN', '518000'));
        $this->assertFalse(AddressValidator::validatePostalCode('CN', '51800'));
    }

    public function test_postal_code_unknown_country_allows_any(): void
    {
        $this->assertTrue(AddressValidator::validatePostalCode('XX', 'anything'));
    }

    public function test_get_fields_for_country_us_no_district(): void
    {
        $fields = AddressValidator::getFieldsForCountry('US');
        $this->assertContains('state', $fields);
        $this->assertNotContains('district', $fields);
    }

    public function test_get_fields_for_country_cn_has_district(): void
    {
        $fields = AddressValidator::getFieldsForCountry('CN');
        $this->assertContains('district', $fields);
    }

    // ============================================================
    // FreightCalculatorService
    // ============================================================

    public function test_freight_calculator_class_exists(): void
    {
        $this->assertTrue(class_exists(FreightCalculatorService::class));
    }

    public function test_freight_calculator_has_required_methods(): void
    {
        $this->assertTrue(method_exists(FreightCalculatorService::class, 'calculate'));
        $this->assertTrue(method_exists(FreightCalculatorService::class, 'rateShop'));
    }

    // ============================================================
    // TrackingService
    // ============================================================

    public function test_tracking_service_class_exists(): void
    {
        $this->assertTrue(class_exists(TrackingService::class));
    }

    public function test_tracking_service_has_required_methods(): void
    {
        $this->assertTrue(method_exists(TrackingService::class, 'recordEvent'));
        $this->assertTrue(method_exists(TrackingService::class, 'processWebhook'));
    }

    // ============================================================
    // TmsShipmentService
    // ============================================================

    public function test_shipment_service_class_exists(): void
    {
        $this->assertTrue(class_exists(TmsShipmentService::class));
    }

    public function test_shipment_service_has_required_methods(): void
    {
        $this->assertTrue(method_exists(TmsShipmentService::class, 'createShipment'));
        $this->assertTrue(method_exists(TmsShipmentService::class, 'confirmShip'));
        $this->assertTrue(method_exists(TmsShipmentService::class, 'updateStatus'));
        $this->assertTrue(method_exists(TmsShipmentService::class, 'updateTrackingNo'));
    }

    // ============================================================
    // WmsInboundService
    // ============================================================

    public function test_wms_inbound_service_class_exists(): void
    {
        $this->assertTrue(class_exists(WmsInboundService::class));
    }

    public function test_wms_inbound_service_has_required_methods(): void
    {
        $this->assertTrue(method_exists(WmsInboundService::class, 'createAsn'));
        $this->assertTrue(method_exists(WmsInboundService::class, 'startReceiving'));
        $this->assertTrue(method_exists(WmsInboundService::class, 'completeReceiving'));
        $this->assertTrue(method_exists(WmsInboundService::class, 'confirmPutaway'));
        $this->assertTrue(method_exists(WmsInboundService::class, 'startPutaway'));
    }

    // ============================================================
    // WmsOutboundService
    // ============================================================

    public function test_wms_outbound_service_class_exists(): void
    {
        $this->assertTrue(class_exists(WmsOutboundService::class));
    }

    public function test_wms_outbound_service_has_required_methods(): void
    {
        $this->assertTrue(method_exists(WmsOutboundService::class, 'startPick'));
        $this->assertTrue(method_exists(WmsOutboundService::class, 'confirmPick'));
        $this->assertTrue(method_exists(WmsOutboundService::class, 'startPack'));
        $this->assertTrue(method_exists(WmsOutboundService::class, 'completePack'));
        $this->assertTrue(method_exists(WmsOutboundService::class, 'confirmShip'));
    }

    // ============================================================
    // WaveService
    // ============================================================

    public function test_wave_service_class_exists(): void
    {
        $this->assertTrue(class_exists(WaveService::class));
    }

    public function test_wave_service_has_required_methods(): void
    {
        $this->assertTrue(method_exists(WaveService::class, 'createWave'));
        $this->assertTrue(method_exists(WaveService::class, 'releaseWave'));
    }

    // ============================================================
    // RmaService
    // ============================================================

    public function test_rma_service_class_exists(): void
    {
        $this->assertTrue(class_exists(RmaService::class));
    }

    public function test_rma_service_has_required_methods(): void
    {
        $this->assertTrue(method_exists(RmaService::class, 'create'));
        $this->assertTrue(method_exists(RmaService::class, 'approve'));
        $this->assertTrue(method_exists(RmaService::class, 'markReturned'));
        $this->assertTrue(method_exists(RmaService::class, 'receive'));
        $this->assertTrue(method_exists(RmaService::class, 'refund'));
        $this->assertTrue(method_exists(RmaService::class, 'reject'));
    }

    // ============================================================
    // Tracking webhook middleware
    // ============================================================

    public function test_tracking_signature_middleware_class_exists(): void
    {
        $this->assertTrue(class_exists(\app\middleware\TrackingSignature::class));
    }

    public function test_tracking_signature_implements_interface(): void
    {
        $this->assertContains(
            \Webman\MiddlewareInterface::class,
            class_implements(\app\middleware\TrackingSignature::class)
        );
    }

    // ============================================================
    // ApiHandler (JSON exception)
    // ============================================================

    public function test_api_handler_class_exists(): void
    {
        $this->assertTrue(class_exists(\app\exception\ApiHandler::class));
    }

    public function test_api_handler_extends_base_handler(): void
    {
        $this->assertTrue(is_subclass_of(\app\exception\ApiHandler::class, \support\exception\Handler::class));
    }
}
