<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light only">
    <title>{{ $subject ?? 'CreativeSuite ERP' }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color:#f1f5f9;padding:32px 16px;">
    <tr>
        <td align="center">
            <table width="600" cellpadding="0" cellspacing="0" role="presentation" style="max-width:600px;width:100%;">
                <tr>
                    <td style="background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 100%);border-radius:16px 16px 0 0;padding:32px 40px;text-align:center;">
                        <div style="display:inline-block;width:48px;height:48px;background:rgba(255,255,255,0.2);border-radius:12px;line-height:48px;font-size:24px;margin-bottom:12px;">⚡</div>
                        <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:700;letter-spacing:-0.3px;">CreativeSuite ERP</h1>
                        @isset($badge)
                        <p style="margin:10px 0 0;color:rgba(255,255,255,0.9);font-size:13px;font-weight:500;">{{ $badge }}</p>
                        @endisset
                    </td>
                </tr>
                <tr>
                    <td style="background-color:#ffffff;padding:40px;border-left:1px solid #e2e8f0;border-right:1px solid #e2e8f0;">
                        @yield('content')
                    </td>
                </tr>
                <tr>
                    <td style="background-color:#f8fafc;border-radius:0 0 16px 16px;padding:24px 40px;border:1px solid #e2e8f0;border-top:none;text-align:center;">
                        <p style="margin:0 0 6px;color:#64748b;font-size:12px;">Email ini dikirim otomatis oleh CreativeSuite ERP.</p>
                        <p style="margin:0;color:#94a3b8;font-size:11px;">© {{ date('Y') }} Creative Network. All rights reserved.</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>