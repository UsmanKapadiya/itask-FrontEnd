<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class ResidentDetail extends Model
{
    use SoftDeletes;

    protected $table = 'residents';


    /**
     * Get table data
     */
    function tableData()
    {
        return $this->hasOne('App\Models\TableDetail', 'table_id', 'table_id');
    }

}
