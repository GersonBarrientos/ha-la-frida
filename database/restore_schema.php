<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

echo "--- RESTAURANDO ESQUEMA DE BASE DE DATOS 'HA LA FRIDA' (BLOQUE A BLOQUE) ---\n";

try {
    $sqlFile = __DIR__.'/Ha_La_Frida_DB.sql';
    if (!File::exists($sqlFile)) {
        throw new Exception("Archivo SQL no encontrado");
    }

    $sql = File::get($sqlFile);
    
    // Separamos por la palabra clave GO que indica fin de lote en SQL Server
    $batches = preg_split("/^\s*GO\s*$/m", $sql);

    foreach ($batches as $batch) {
        $batch = trim($batch);
        if ($batch === '') continue;

        try {
            // Evitamos ejecutar USE master o CREATE DATABASE si ya estamos conectados a la DB correcta
            if (stripos($batch, 'CREATE DATABASE') !== false || stripos($batch, 'USE master') !== false) {
                echo "  > Omitiendo comando de base de datos de nivel superior...\n";
                continue;
            }
            
            DB::unprepared($batch);
            // Mostrar los primeros 30 caracteres del lote para saber por donde va
            $preview = substr(preg_replace('/\s+/', ' ', $batch), 0, 40);
            echo "  ✓ Ejecutado: $preview...\n";
        } catch (\Exception $e) {
            echo "  ⚠ Error en lote: " . substr($e->getMessage(), 0, 100) . "\n";
            // Continuamos con el siguiente lote por si el error es 'el objeto ya existe'
        }
    }

    echo "\n--- ESQUEMA RESTAURADO ---\n";

} catch (Exception $e) {
    echo "❌ ERROR GENERAL: " . $e->getMessage() . "\n";
}
