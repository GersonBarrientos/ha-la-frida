<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\Factura;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    public function getSalesHistory(Request $request)
    {
        $query = Factura::with('pedido.usuario')
            ->orderBy('fecha_pago', 'desc');

        if ($request->fecha_inicio) {
            $query->whereDate('fecha_pago', '>=', $request->fecha_inicio);
        }
        if ($request->fecha_fin) {
            $query->whereDate('fecha_pago', '<=', $request->fecha_fin);
        }

        return response()->json($query->get());
    }

    public function exportDailyCSV()
    {
        $data = DB::table('Factura as f')
            ->join('Pedido as p', 'f.id_pedido', '=', 'p.id_pedido')
            ->join('Usuario as u', 'p.id_usuario', '=', 'u.id_usuario')
            ->whereDate('f.fecha_pago', now()->toDateString())
            ->select('f.numero_factura', 'f.total', 'f.metodo_pago', 'f.fecha_pago', 'u.nombre_completo as mesero')
            ->get();

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=ventas_hoy_" . date('Y-m-d') . ".csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use($data) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Factura', 'Total ($)', 'Metodo', 'Fecha', 'Mesero']);

            foreach ($data as $row) {
                fputcsv($file, [$row->numero_factura, $row->total, $row->metodo_pago, $row->fecha_pago, $row->mesero]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
