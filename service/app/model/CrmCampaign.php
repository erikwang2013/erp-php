<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);
namespace app\model;
use Illuminate\Database\Eloquent\SoftDeletes;
use support\Model;

class CrmCampaign extends Model
{
    use SoftDeletes;
    protected $table = 'erik_crm_campaign';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
}
