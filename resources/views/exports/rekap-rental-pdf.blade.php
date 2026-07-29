<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #222; }
        h2 { margin-bottom: 2px; }
        .meta { margin-bottom: 14px; color: #666; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 5px 6px; text-align: left; }
        th { background: #f0f0f0; }
        tfoot td { font-weight: bold; background: #f7f7f7; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <h2>Rekap Rental</h2>
    <div class="meta">Dicetak: {{ now()->format('d M Y H:i') }}</div>

    <table>
        <thead>
            <tr>
                <th>Pelanggan</th>
                <th>Unit / Mobil</th>
                <th>Paket</th>
                <th>Mulai</th>
                <th>Selesai</th>
                <th>Status</th>
                <th class="text-right">Total Tagihan</th>
                <th class="text-right">Terbayar</th>
                <th class="text-right">Sisa</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['customer_name'] }}</td>
                    <td>{{ $row['unit_label'] }}</td>
                    <td>{{ $row['package_label'] }}</td>
                    <td>{{ $row['start_date'] }}</td>
                    <td>{{ $row['end_date'] }}</td>
                    <td>{{ $row['status'] }}</td>
                    <td class="text-right">Rp {{ number_format($row['total_price'], 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($row['amount_paid'], 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($row['outstanding'], 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6">Total</td>
                <td class="text-right">Rp {{ number_format($totalPrice, 0, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($totalPaid, 0, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($totalOutstanding, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>