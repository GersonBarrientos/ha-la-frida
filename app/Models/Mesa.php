<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mesa extends Model
{
    protected $table = 'Mesa';
    protected $primaryKey = 'id_mesa';
    public $timestamps = false;

    protected $fillable = [
        'capacidad',
        'estado'
    ];

    public function pedidos()
    {
        return $this->hasMany(Pedido::class, 'id_mesa', 'id_mesa');
    }
}
