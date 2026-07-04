@extends('mail.layout')

@section('content')
    <p style="margin:0 0 8px;color:#64748b;font-size:14px;">Verifikasi OTP</p>
    <h2 style="margin:0 0 20px;color:#0f172a;font-size:24px;font-weight:700;">Hampir selesai! 🎉</h2>

    <p style="margin:0 0 24px;color:#334155;font-size:16px;line-height:1.6;">
        Masukkan kode OTP berikut untuk menyelesaikan aktivasi akun Anda:
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 0 28px;">
        <tr>
            <td align="center" style="background:linear-gradient(135deg,#4f46e5,#7c3aed);border-radius:14px;padding:28px;">
                <p style="margin:0 0 8px;color:rgba(255,255,255,0.8);font-size:12px;text-transform:uppercase;letter-spacing:2px;font-weight:600;">Kode OTP</p>
                <p style="margin:0;color:#ffffff;font-size:42px;font-weight:800;letter-spacing:12px;font-family:'Courier New',monospace;">{{ $otpCode }}</p>
            </td>
        </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;margin-bottom:20px;">
        <tr>
            <td style="padding:14px 18px;">
                <p style="margin:0;color:#92400e;font-size:13px;line-height:1.5;">
                    ⏱ Berlaku <strong>{{ $minutes }} menit</strong> &nbsp;·&nbsp; Maksimal <strong>5 percobaan</strong>
                </p>
            </td>
        </tr>
    </table>

    <p style="margin:0;color:#94a3b8;font-size:12px;line-height:1.5;">
        🔒 Jangan bagikan kode ini kepada siapapun, termasuk pihak yang mengaku dari CreativeSuite ERP.
    </p>
@endsection