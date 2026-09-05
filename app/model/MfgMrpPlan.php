<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class MfgMrpPlan extends Model
{
    use Searchable;
    protected $table = 'mfg_mrp_plan';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['code', 'period_year', 'period_month', 'status', 'generated_at'];
    protected $casts = ['period_year' => 'integer', 'period_month' => 'integer', 'status' => 'integer'];

    public function items()
    {
        return $this->hasMany(MfgMrpItem::class, 'plan_id');
    }
}
