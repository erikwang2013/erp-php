<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use support\Model;

class CrmContract extends Model
{
    use SoftDeletes;

    protected $table = 'erp_crm_contract';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['code', 'name', 'customer_id', 'opportunity_id', 'quotation_id', 'total_amount', 'status', 'signed_at', 'start_date', 'end_date', 'owner_user_id', 'content', 'remark'];
    protected $casts = ['customer_id' => 'integer', 'opportunity_id' => 'integer', 'quotation_id' => 'integer', 'total_amount' => 'float', 'status' => 'integer', 'signed_at' => 'date', 'start_date' => 'date', 'end_date' => 'date', 'owner_user_id' => 'integer'];

    public function items()
    {
        return $this->hasMany(CrmContractItem::class, 'contract_id');
    }
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
