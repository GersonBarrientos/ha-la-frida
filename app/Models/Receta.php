<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Receta extends Model
{
    protected $table = 'Receta';
    protected $primaryKey = 'id_receta';
    public $timestamps = false;

    protected $fillable = [
        'id_producto',
        'id_insumo',
        'cantidad_necesaria'
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }

    public function insumo()
    {
        return $this->belongsTo(Insumo::class, 'id_insumo', 'id_insumo');
    }
}
