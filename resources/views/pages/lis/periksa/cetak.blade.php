<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Hasil Lab_{{ $extPasien->nm_pasien ?? ($model->pasien->nama ?? 'Pasien') }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #000;
            margin: 0;
            padding: 1cm;
            background: #fff;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
        }
        .logo-cell {
            width: 70px;
            vertical-align: top;
        }
        .logo-cell img {
            width: 60px;
            height: auto;
        }
        .title-cell {
            text-align: center;
            vertical-align: top;
        }
        .title-cell h1 {
            font-size: 16px;
            margin: 0 0 3px 0;
            text-transform: uppercase;
        }
        .title-cell p {
            margin: 0 0 2px 0;
            font-size: 10px;
        }
        .divider {
            border-top: 1.5px solid #000;
            margin: 5px 0 10px 0;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .meta-table td {
            padding: 2.5px 0;
            font-size: 10.5px;
            vertical-align: top;
        }
        .meta-table td.label {
            width: 15%;
        }
        .meta-table td.val {
            width: 35%;
        }
        .result-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .result-table th {
            border: 1px solid #ddd;
            padding: 6px;
            font-weight: bold;
            text-align: left;
            background: #f9f9f9;
        }
        .result-table th.center, .result-table td.center {
            text-align: center;
        }
        .result-table td {
            border: 1px solid #ddd;
            padding: 5px 6px;
            vertical-align: middle;
        }
        .grup1-row {
            font-weight: bold;
            color: #660000;
            background: #fff;
        }
        .grup2-row {
            font-weight: bold;
            color: #660000;
            padding-left: 15px !important;
        }
        .grup3-row {
            font-weight: bold;
            color: #660000;
            padding-left: 25px !important;
        }
        .parameter-row {
            padding-left: 10px;
        }
        .flag-span {
            font-weight: bold;
            color: red;
            margin-left: 4px;
        }
        .kritis-cell {
            background-color: #ffcccc !important;
            font-weight: bold;
        }
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
        }
        .footer-table td {
            vertical-align: top;
            font-size: 10px;
        }
        .sig-container {
            text-align: center;
            width: 200px;
        }
        .sig-space {
            height: 50px;
        }
        @media print {
            body {
                padding: 0;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="background: #f0f0f0; padding: 10px; margin-bottom: 20px; border-radius: 5px; text-align: right;">
        <button onclick="window.print();" style="padding: 6px 12px; font-weight: bold; background: #008000; color: #fff; border: none; cursor: pointer; border-radius: 3px;">Cetak Dokumen</button>
    </div>

    <!-- Kop Surat -->
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                <img src="/images/logo.png" onerror="this.src='http://localhost/lis/images/logo.png'" alt="Logo">
            </td>
            <td class="title-cell">
                <h1>Rumah Sakit Pelita Kasih</h1>
                <p>Jl. Jenderal Sudirman No. 123, Jakarta</p>
                <p>Telp: (021) 123456 | Email: lab@rspelitakasih.com</p>
            </td>
        </tr>
    </table>

    <div class="divider"></div>

    <!-- Metadata Pasien & Pemeriksaan -->
    <table class="meta-table">
        <tr>
            <td class="label">No. RM</td>
            <td class="val">: {{ $model->pasien->no_rm ?? '-' }}</td>
            <td class="label">No. Lab</td>
            <td class="val">: {{ $model->nomor }}</td>
        </tr>
        <tr>
            <td class="label">Nama Pasien</td>
            <td class="val">: {{ $extPasien->nm_pasien ?? ($model->pasien->nama ?? '-') }}</td>
            <td class="label">Tgl. Permintaan</td>
            <td class="val">: {{ \Illuminate\Support\Carbon::parse($model->tanggal)->format('d/m/Y H:i:s') }}</td>
        </tr>
        <tr>
            <td class="label">Jenis Kelamin</td>
            <td class="val">: {{ ($extPasien->jk ?? '') === 'L' ? 'Laki-laki' : 'Perempuan' }}</td>
            <td class="label">Tgl. Validasi</td>
            <td class="val">: {{ $model->tgl_validasi ? \Illuminate\Support\Carbon::parse($model->tgl_validasi)->format('d/m/Y H:i:s') : '-' }}</td>
        </tr>
        <tr>
            <td class="label">Tanggal Lahir</td>
            <td class="val">: {{ $extPasien && !empty($extPasien->tgl_lahir) ? \Carbon\Carbon::parse($extPasien->tgl_lahir)->format('d/m/Y') : (!empty($model->pasien->tgl_lahir) ? \Carbon\Carbon::parse($model->pasien->tgl_lahir)->format('d/m/Y') : '-') }}</td>
            <td class="label">Respons Time</td>
            <td class="val">: 
                @if ($model->selesai && $model->tanggal)
                    {{ \Carbon\Carbon::parse($model->tanggal)->diffInMinutes(\Carbon\Carbon::parse($model->selesai)) }} Menit
                @else
                    -
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">Alamat</td>
            <td class="val">: {{ substr($extPasien->alamat ?? ($model->pasien->alamat ?? '-'), 0, 45) }}</td>
            <td class="label">Ruangan</td>
            <td class="val">: {{ $extRuang->nm_poli ?? ($model->ruang->nama ?? '-') }}</td>
        </tr>
        <tr>
            <td class="label">Klinis / Diagnosa</td>
            <td class="val">: {{ $model->ket_klinik ?: '-' }}</td>
            <td class="label">Dokter Pengirim</td>
            <td class="val">: {{ $extDokter->nm_dokter ?? ($model->dokter->nama ?? '-') }}</td>
        </tr>
        <tr>
            <td class="label">Perusahaan/asuransi</td>
            <td class="val">: {{ $extReg->png_jawab ?? ($model->id_penjamin == 1 ? 'UMUM' : ($model->id_penjamin == 2 ? 'BPJS' : ($model->id_penjamin == 3 ? 'ASURANSI' : 'LAIN-LAIN'))) }}</td>
            <td class="label"></td>
            <td class="val"></td>
        </tr>
    </table>

    <!-- Hasil Pemeriksaan -->
    <table class="result-table">
        <thead>
            <tr>
                <th>Pemeriksaan</th>
                <th class="center" style="width: 120px;">Hasil</th>
                <th class="center" style="width: 140px;">Nilai Rujukan</th>
                <th class="center" style="width: 100px;">Satuan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($groupedDetails as $grup1 => $g2s)
                <tr class="grup1-row">
                    <td colspan="4">{{ $grup1 }}</td>
                </tr>
                @foreach ($g2s as $grup2 => $g3s)
                    @if ($grup2 !== $grup1)
                        <tr>
                            <td colspan="4" class="grup2-row">{{ $grup2 }}</td>
                        </tr>
                    @endif
                    @foreach ($g3s as $grup3 => $rows)
                        @if ($grup3 !== $grup2)
                            <tr>
                                <td colspan="4" class="grup3-row">{{ $grup3 }}</td>
                            </tr>
                        @endif
                        @foreach ($rows as $row)
                            <tr>
                                <td class="parameter-row">{{ $row->nama }}</td>
                                <td class="center font-bold {{ $row->is_kritis ? 'kritis-cell' : '' }}">
                                    {{ $row->formatted_nilai }}
                                    @if ($row->flag)
                                        <span class="flag-span">{{ $row->flag }}</span>
                                    @endif
                                </td>
                                <td class="center font-mono">{{ $row->normal_range ?: '-' }}</td>
                                <td class="center font-mono">{{ $row->satuan ?: '-' }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                @endforeach
            @endforeach
        </tbody>
    </table>

    <div class="divider"></div>

    <!-- Notes & Signature -->
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="vertical-align: top; font-size: 10px; width: 60%;">
                <p><strong>L/H</strong> = Diluar batas nilai rujukan | <strong>#</strong> = Nilai kritis</p>
                @if ($model->ket_verifikasi)
                    <p style="color: red;"><strong>Note :</strong> {{ $model->ket_verifikasi }}</p>
                @endif
            </td>
            <td style="vertical-align: top; width: 40%; text-align: right;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="text-align: center; font-size: 10px;">
                            <p>Tgl. Cetak: {{ now()->format('d/m/Y H:i:s') }}</p>
                            <p class="sig-container" style="margin: 15px auto 0 auto;">
                                Petugas Laboratorium
                                <span class="sig-space" style="display: block;"></span>
                                <strong>{{ $model->petugas->nama ?? 'Lab Staff' }}</strong>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <script>
        window.onload = function() {
            // Automatically prompt print dialog when document is ready
            setTimeout(function() {
                window.print();
            }, 500);
        }
    </script>
</body>
</html>
