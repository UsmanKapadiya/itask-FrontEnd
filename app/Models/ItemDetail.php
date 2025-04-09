<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class ItemDetail extends Model
{
    use SoftDeletes;



    function categoryData()
    {
        return $this->hasOne('App\Models\CategoryDetail', 'id', 'cat_id');
    }


}
