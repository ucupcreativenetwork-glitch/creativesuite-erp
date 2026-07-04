<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CreativeSuite ERP — Server API</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: linear-gradient(160deg, #0f172a 0%, #1e3a8a 100%);
            color: #e2e8f0;
            min-height: 100vh;
            padding: 24px 20px 40px;
        }
        .wrap { max-width: 420px; margin: 0 auto; }
        .badge {
            display: inline-block;
            background: #22c55e33;
            color: #86efac;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 12px;
            border-radius: 20px;
            margin-bottom: 16px;
        }
        h1 { font-size: 26px; font-weight: 800; color: #fff; margin-bottom: 8px; }
        .sub { color: #94a3b8; font-size: 14px; line-height: 1.5; margin-bottom: 24px; }
        .card {
            background: #ffffff0d;
            border: 1px solid #ffffff1a;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 12px;
        }
        .card h2 { font-size: 14px; color: #93c5fd; margin-bottom: 8px; text-transform: uppercase; letter-spacing: .5px; }
        .card p { font-size: 14px; line-height: 1.55; color: #cbd5e1; }
        .url {
            display: block;
            margin-top: 8px;
            padding: 10px 12px;
            background: #00000040;
            border-radius: 8px;
            font-size: 13px;
            color: #fbbf24;
            word-break: break-all;
            text-decoration: none;
        }
        .warn {
            background: #f59e0b22;
            border-color: #f59e0b44;
            color: #fde68a;
            font-size: 13px;
            line-height: 1.5;
        }
        .footer { text-align: center; margin-top: 28px; font-size: 12px; color: #64748b; }
    </style>
</head>
<body>
    <div class="wrap">
        <span class="badge">● Server aktif</span>
        <h1>CreativeSuite ERP</h1>
        <p class="sub">Ini adalah <strong>server API backend</strong>, bukan tampilan aplikasi absensi. Jangan login di sini.</p>

        <div class="card warn">
            ⚠️ Halaman Laravel default sudah diganti. Buka alamat di bawah sesuai kebutuhan Anda.
        </div>

        <div class="card">
            <h2>📱 Aplikasi Absensi (Karyawan)</h2>
            <p>Install <strong>Expo Go</strong> di HP, lalu jalankan project mobile di komputer server:</p>
            <code class="url">cd creativesuite-erp-mobile → npm start</code>
            <p style="margin-top:10px;font-size:13px">Di login app, URL API:</p>
            <a class="url" href="/api/v1/health">{{ $apiBase }}/api/v1</a>
        </div>

        <div class="card">
            <h2>🖥️ Web ERP (Admin / HR)</h2>
            <p>Frontend Next.js — biasanya port <strong>3000</strong>:</p>
            <a class="url" href="{{ $webUrl }}">{{ $webUrl }}</a>
        </div>

        <div class="card">
            <h2>🔌 Cek API</h2>
            <a class="url" href="/api/v1/health">{{ $apiBase }}/api/v1/health</a>
        </div>

        <p class="footer">CreativeSuite ERP · {{ date('Y') }}</p>
    </div>
</body>
</html>