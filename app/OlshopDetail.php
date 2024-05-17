<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OlshopDetail extends Model
{
    use HasFactory;
    protected $table = "olshop_detail";
    protected $primaryKey = 'id_detail';
    protected $fillable = [
        'olshop_id',
        'product_id',
        'no_resi',
        'no_pesanan',
        'variasi',
        'harga',
        'qty'
    ];
}
