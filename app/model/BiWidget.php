<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class BiWidget extends Model
{
    use Searchable;
    protected $table = 'bi_widget';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['dashboard_id', 'name', 'type', 'dataset_id', 'config', 'position_x', 'position_y', 'width', 'height'];
    public $timestamps = true;
}
