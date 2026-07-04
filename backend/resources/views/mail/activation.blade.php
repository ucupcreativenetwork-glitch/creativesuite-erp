@extends('mail.layout')

@section('content')
    <p style="margin:0 0 8px;color:#64748b;font-size:14px;">Halo,</p>
    <h2 style="margin:0 0 20px;color:#0f172a;font-size:24px;font-weight:700;">{{ $fullName }} 👋</h2>

    <p style="margin:0 0 16px;color:#334155;font-size:16px;line-height:1.6;">
        Akun <strong>CreativeSuite ERP</strong> Anda telah <span style="color:#16a34a;font-weight:600;">disetujui</span>.
        Satu langkah lagi untuk mulai menggunakan sistem.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:28px 0;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;">
        <tr>
            <td style="padding:20px 24px;">
                <p style="margin:0 0 6px;color:#64748b;font-size:12px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;">Langkah aktivasi</p>
                <p style="margin:0;color:#334155;font-size:15px;line-height:1.5;">1. Klik tombol di bawah &nbsp;→&nbsp; 2. Buat password &nbsp;→&nbsp; 3. Masukkan kode OTP</p>
            </td>
        </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 0 28px;">
        <tr>
            <td align="center">
                <a href="{{ $activationUrl }}" style="display:inline-block;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#ffffff;text-decoration:none;font-size:16px;font-weight:700;padding:16px 40px;border-radius:10px;box-shadow:0 4px 14px rgba(79,70,229,0.4);">
                    Aktivasi Akun Sekarang →
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 8px;color:#64748b;font-size:13px;line-height:1.5;">
        Tombol tidak berfungsi? Salin link berikut ke browser:
    </p>
    <p style="margin:0 0 24px;word-break:break-all;">
        <a href="{{ $activationUrl }}" style="color:#4f46e5;font-size:13px;">{{ $activationUrl }}</a>
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;">
        <tr>
            <td style="padding:14px 18px;">
                <p style="margin:0;color:#92400e;font-size:13px;line-height:1.5;">
                    ⏱ Link berlaku <strong>{{ $hours }} jam</strong>. Jangan bagikan link ini kepada siapapun.
                </p>
            </td>
        </tr>
    </table>

    <p style="margin:24px 0 0;color:#94a3b8;font-size:12px;line-height:1.5;">
        Jika Anda tidak merasa mengajukan akun ini, abaikan email ini.
    </p>
@endsection