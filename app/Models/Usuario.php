<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Usuario extends Authenticatable
{
    use Notifiable;

    protected $table = 'Usuario';
    protected $primaryKey = 'id_usuario';
    public $timestamps = false;

    protected $fillable = [
        'nombre_completo',
        'correo',
        'telefono',
        'pin_acceso',
        'estado',
        'id_rol'
    ];

    protected $hidden = [
        'pin_acceso',
    ];

    public function getAuthPassword()
    {
        return $this->pin_acceso;
    }

    public function rol()
    {
        return $this->belongsTo(Rol::class, 'id_rol', 'id_rol');
    }

    public function pedidos()
    {
        return $this->hasMany(Pedido::class, 'id_usuario', 'id_usuario');
    }
}
