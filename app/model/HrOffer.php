<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class HrOffer extends Model
{
    use Searchable;
    protected $table = 'hr_offer';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['candidate_id', 'offered_salary', 'onboard_date', 'status'];
    protected $casts = [
        'candidate_id' => 'integer',
        'status' => 'integer',
        'offered_salary' => 'decimal:2',
    ];
}
