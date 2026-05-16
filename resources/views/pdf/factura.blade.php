<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Factura {{ $factura->numero_factura }}</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 14px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 24px; color: #333; }
        .details { margin-bottom: 20px; width: 100%; }
        .details th, .details td { padding: 5px; text-align: left; }
        .items { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .items th, .items td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .items th { background-color: #f2f2f2; }
        .total { text-align: right; font-size: 18px; font-weight: bold; margin-top: 20px;}
    </style>
</head>
<body>
    <div class="header">
        <h1>Ha La Frida</h1>
        <p>Restaurante & Bar</p>
    </div>

    <div class="details">
        <table style="width: 100%;">
            <tr>
                <td style="vertical-align: top;">
                    <strong>Factura N°:</strong> {{ $factura->numero_factura }}<br>
                    <strong>Fecha:</strong> {{ $factura->fecha_pago }}<br>
                    <strong>Método de Pago:</strong> {{ $factura->metodo_pago }}
                </td>
                <td style="vertical-align: top; text-align: right;">
                    <strong>Mesero:</strong> {{ $factura->pedido->usuario->nombre_completo ?? 'N/A' }}<br>
                    <strong>Mesa:</strong> {{ $factura->pedido->mesa->numero_mesa ?? 'N/A' }}
                </td>
            </tr>
        </table>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>Producto</th>
                <th style="text-align: center;">Cant.</th>
                <th style="text-align: right;">Precio Unit.</th>
                <th style="text-align: right;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($factura->pedido->detalles as $detalle)
            <tr>
                <td>{{ $detalle->producto->nombre_prod }}</td>
                <td style="text-align: center;">{{ $detalle->cantidad }}</td>
                <td style="text-align: right;">${{ number_format($detalle->precio_unitario, 2) }}</td>
                <td style="text-align: right;">${{ number_format($detalle->cantidad * $detalle->precio_unitario, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total">
        Total a Pagar: ${{ number_format($factura->total, 2) }}
    </div>
</body>
</html>
