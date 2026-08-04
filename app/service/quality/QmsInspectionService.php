<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\service\quality;

use app\common\SnowflakeService;
use app\model\QualityIpcqRecord;
use app\model\QualityIqcRecord;
use app\model\QualityNonconformity;
use app\model\QualityOqcRecord;

class QmsInspectionService
{
    /**
     * Record inspection result and auto-create nonconformity if rejected
     */
    public function recordInspection(string $recordType, array $data): int
    {
        $id = SnowflakeService::generate();
        $data['id'] = $id;
        $data['code'] = $data['code'] ?? ('QC' . date('YmdHis'));

        $model = match ($recordType) {
            'iqc' => new QualityIqcRecord(),
            'ipqc' => new QualityIpcqRecord(),
            'oqc' => new QualityOqcRecord(),
            default => throw new \InvalidArgumentException('Unknown inspection type: ' . $recordType),
        };

        $model->fill($data);
        $model->save();

        // Auto-create nonconformity when rejected
        if (($data['result'] ?? '') === 'reject' && ($data['rejected_qty'] ?? 0) > 0) {
            $nc = new QualityNonconformity();
            $nc->id = SnowflakeService::generate();
            $nc->code = 'NC' . date('YmdHis');
            $nc->source_type = $recordType;
            $nc->source_id = $id;
            $nc->product_id = $data['product_id'] ?? 0;
            $nc->defect_type = $data['defect_type'] ?? '未指定';
            $nc->defect_qty = $data['rejected_qty'];
            $nc->severity = $data['severity'] ?? 'minor';
            $nc->status = 0; // 待处理
            $nc->reported_by = $data['inspector'] ?? '';
            $nc->save();
        }

        return $id;
    }

    /**
     * Calculate pass rate for a batch of inspections
     */
    public function calculatePassRate(array $records): float
    {
        $totalInspected = 0;
        $totalPassed = 0;
        foreach ($records as $r) {
            $totalInspected += (int)($r['inspected_qty'] ?? 0);
            $totalPassed += (int)($r['passed_qty'] ?? 0);
        }
        return $totalInspected > 0 ? round(($totalPassed / $totalInspected) * 100, 2) : 0;
    }
}
