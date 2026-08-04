<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\service\tms;

use app\model\TmsFreightRate;

class FreightCalculatorService
{
    /** 计算运费 — 匹配费率卡 */
    public function calculate(int $carrierServiceId, string $destCountry, float $weightKg): array
    {
        $rate = TmsFreightRate::where('carrier_service_id', $carrierServiceId)
            ->where('status', 1)
            ->where('weight_from_kg', '<=', $weightKg)
            ->where('weight_to_kg', '>=', $weightKg)
            ->where(function ($q) use ($destCountry) {
                $q->where('dest_country', $destCountry)->orWhere('dest_country', '');
            })
            ->orderByDesc('dest_country')
            ->first();

        if (!$rate) {
            return ['charge' => 0, 'currency' => 'CNY', 'rate_id' => null];
        }

        $charge = (float) $rate->base_rate + ($weightKg * (float) $rate->per_kg_rate);
        if ((float) $rate->fuel_surcharge_pct > 0) {
            $charge += $charge * ((float) $rate->fuel_surcharge_pct / 100);
        }

        return ['charge' => round($charge, 2), 'currency' => $rate->currency, 'rate_id' => $rate->id];
    }

    /** 比价 — 按目的国/重量查找所有可用费率 */
    public function rateShop(string $destCountry, float $weightKg): array
    {
        $rates = TmsFreightRate::where('status', 1)
            ->where('weight_from_kg', '<=', $weightKg)
            ->where('weight_to_kg', '>=', $weightKg)
            ->where(function ($q) use ($destCountry) {
                $q->where('dest_country', $destCountry)->orWhere('dest_country', '');
            })->get();

        $results = [];
        foreach ($rates as $rate) {
            $charge = (float) $rate->base_rate + ($weightKg * (float) $rate->per_kg_rate);
            if ((float) $rate->fuel_surcharge_pct > 0) {
                $charge += $charge * ((float) $rate->fuel_surcharge_pct / 100);
            }
            $results[] = [
                'rate_id' => $rate->id,
                'carrier_service_id' => $rate->carrier_service_id,
                'charge' => round($charge, 2),
                'currency' => $rate->currency,
            ];
        }
        usort($results, fn ($a, $b) => $a['charge'] <=> $b['charge']);

        return $results;
    }
}
