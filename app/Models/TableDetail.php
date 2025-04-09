<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class TableDetail extends Model
{
    use SoftDeletes;

    protected $connection = 'mysql2';
    
    protected $table = 'tables';
     
     /**
     * Get member data
     */
    function residentData()
    {
        return $this->hasOne('App\Models\ResidentDetail', 'table_id', 'table_id');
    }

}
