<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Olshop extends Model
{
    use HasFactory;
    protected $table = "olshop";
    protected $primaryKey = 'id';
    protected $fillable = [
        'no_trn',
        'user_id',
        'warehouse_id',
    ];
}
