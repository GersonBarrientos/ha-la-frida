<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Insumo extends Model
{
    protected $table = 'Insumo';
    protected $primaryKey = 'id_insumo';
    public $timestamps = false;

    protected $fillable = [
        'nombre_insumo',
        'unidad_medida',
        'stock_actual',
        'nivel_alerta',
        'fecha_vencimiento',
        'estado'
    ];

    public function recetas()
    {
        return $this->hasMany(Receta::class, 'id_insumo', 'id_insumo');
    }
}
