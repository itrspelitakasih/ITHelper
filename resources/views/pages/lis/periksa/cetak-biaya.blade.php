<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Nota Biaya Lab_{{ $model->nomor }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #000;
            margin: 0;
            padding: 0.5cm;
            background: #fff;
            width: 10cm;
        }
        .center {
            text-align: center;
        }
        .header-title {
            font-size: 13px;
            font-weight: bold;
            margin: 0 0 3px 0;
            text-transform: uppercase;
        }
        .divider {
            border-top: 1px dashed #000;
            margin: 8px 0;
        }
        .meta-table, .items-table {
            width: 100%;
            border-collapse: collapse;
        }
        .meta-table td {
            padding: 2px 0;
            font-size: 10px;
        }
        .items-table th {
            text-align: left;
            border-bottom: 1px dashed #000;
            padding: 4px 0;
            font-size: 10px;
        }
        .items-table td {
            padding: 4px 0;
            font-size: 10px;
        }
        .items-table td.right, .items-table th.right {
            text-align: right;
        }
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="background: #f0f0f0; padding: 10px; margin-bottom: 15px; text-align: right; width: 100%; box-sizing: border-box;">
        <button onclick="window.print();" style="padding: 4px 8px; font-weight: bold; background: #008000; color: #fff; border: none; cursor: pointer; border-radius: 3px;">Cetak Nota</button>
    </div>

    <!-- Kop Nota -->
    <div class="center">
        <h2 class="header-title">Rumah Sakit Pelita Kasih</h2>
        <p style="margin: 0 0 2px 0; font-size: 9px;">Instalasi Laboratorium</p>
        <p style="margin: 0 0 2px 0; font-size: 8px;">Jl. Jenderal Sudirman No. 123, Jakarta</p>
    </div>

    <div class="divider"></div>

    <!-- Meta data -->
    <table class="meta-table">
        <tr>
            <td style="width: 30%;">No. Lab</td>
            <td>: {{ $model->nomor }}</td>
        </tr>
        <tr>
            <td>No. Reg</td>
            <td>: {{ $model->no_reg ?: '-' }}</td>
        </tr>
        <tr>
            <td>Nama Pasien</td>
            <td>: {{ $model->pasien->nama ?? '-' }}</td>
        </tr>
        <tr>
            <td>Tanggal</td>
            <td>: {{ \Illuminate\Support\Carbon::parse($model->tanggal)->format('d/m/Y H:i:s') }}</td>
        </tr>
    </table>

    <div class="divider"></div>

    <!-- Item tarif -->
    <table class="items-table">
        <thead>
            <tr>
                <th>Item Pemeriksaan</th>
                <th class="right" style="width: 80px;">Tarif</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($modelBiaya as $item)
                <tr>
                    <td>{{ $item->tarif_id ? (\App\Models\Lis\Tarif::find($item->tarif_id)->nama ?? 'Tarif #' . $item->tarif_id) : 'Tarif #' . $item->id }}</td>
                    <td class="right">{{ number_format($item->tarif, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="divider"></div>

    <table class="items-table" style="font-weight: bold;">
        <tr>
            <td>TOTAL BIAYA</td>
            <td class="right" style="width: 80px;">Rp {{ number_format($total, 0, ',', '.') }}</td>
        </tr>
    </table>

    <div class="divider" style="margin-top: 15px;"></div>
    
    <div class="center" style="font-size: 8px; margin-top: 10px;">
        <p>Terima Kasih Atas Kepercayaan Anda</p>
        <p>Semoga Lekas Sembuh</p>
    </div>

    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        }
    </script>
</body>
</html>
