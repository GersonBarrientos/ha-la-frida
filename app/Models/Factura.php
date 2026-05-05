<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Factura extends Model
{
    protected $table = 'Factura';
    protected $primaryKey = 'id_factura';
    public $timestamps = false;

    protected $fillable = [
        'numero_factura',
        'total',
        'metodo_pago',
        'fecha_pago',
        'id_pedido'
    ];

    public function pedido()
    {
        return $this->belongsTo(Pedido::class, 'id_pedido', 'id_pedido');
    }
}
