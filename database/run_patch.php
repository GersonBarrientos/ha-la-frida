<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

echo "--- APLICANDO PATCH V2 ---\n";
try {
    $sql = File::get(__DIR__.'/patch_v2.sql');
    $batches = preg_split("/^\s*GO\s*$/m", $sql);
    foreach ($batches as $batch) {
        $batch = trim($batch);
        if ($batch === '') continue;
        DB::unprepared($batch);
        echo "  ✓ Lote ejecutado.\n";
    }
    echo "--- PATCH APLICADO ---";
} catch (Exception $e) { echo "❌ ERROR: " . $e->getMessage(); }
