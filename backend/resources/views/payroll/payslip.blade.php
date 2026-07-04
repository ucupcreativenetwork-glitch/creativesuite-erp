<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Slip Gaji — {{ $slip['employee']['full_name'] }}</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 40px; color: #1e293b; }
        .header { border-bottom: 3px solid #4f46e5; padding-bottom: 16px; margin-bottom: 24px; }
        h1 { margin: 0; font-size: 22px; color: #4f46e5; }
        .meta { color: #64748b; font-size: 13px; margin-top: 6px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; font-size: 12px; text-transform: uppercase; color: #64748b; }
        .amount { text-align: right; font-variant-numeric: tabular-nums; }
        .net { background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #fff; font-size: 18px; font-weight: 700; }
        .net td { border: none; padding: 16px 12px; }
        @media print { body { margin: 20px; } }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $slip['company_name'] }}</h1>
        <div class="meta">Slip Gaji — {{ $slip['period_label'] }} · {{ $slip['run_number'] }}</div>
    </div>

    <table>
        <tr><th colspan="2">Data Karyawan</th></tr>
        <tr><td>Nama</td><td>{{ $slip['employee']['full_name'] }}</td></tr>
        <tr><td>No. Karyawan</td><td>{{ $slip['employee']['employee_number'] }}</td></tr>
        <tr><td>Departemen</td><td>{{ $slip['employee']['department'] ?? '—' }}</td></tr>
        <tr><td>Jabatan</td><td>{{ $slip['employee']['job_title'] ?? '—' }}</td></tr>
    </table>

    <table>
        <tr><th>Komponen</th><th class="amount">Jumlah (Rp)</th></tr>
        <tr><td>Gaji Pokok</td><td class="amount">{{ number_format($slip['earnings']['gross_salary'], 0, ',', '.') }}</td></tr>
        <tr><td>Tunjangan</td><td class="amount">{{ number_format($slip['earnings']['allowance'], 0, ',', '.') }}</td></tr>
        <tr><td>Lembur</td><td class="amount">{{ number_format($slip['earnings']['overtime'], 0, ',', '.') }}</td></tr>
        <tr><td>BPJS (Karyawan)</td><td class="amount">- {{ number_format($slip['deductions']['bpjs'], 0, ',', '.') }}</td></tr>
        <tr><td>PPh 21</td><td class="amount">- {{ number_format($slip['deductions']['pph21'], 0, ',', '.') }}</td></tr>
        <tr><td>Potongan Absensi</td><td class="amount">- {{ number_format($slip['deductions']['attendance'], 0, ',', '.') }}</td></tr>
        <tr><td>Potongan Lainnya</td><td class="amount">- {{ number_format($slip['deductions']['other'], 0, ',', '.') }}</td></tr>
        <tr class="net"><td>GAJI BERSIH DITERIMA</td><td class="amount">Rp {{ number_format($slip['net_salary'], 0, ',', '.') }}</td></tr>
    </table>

    <p style="margin-top:32px;font-size:11px;color:#94a3b8;">Dokumen ini digenerate otomatis oleh CreativeSuite ERP · {{ $slip['generated_at'] }}</p>
</body>
</html>