@extends('mail.layouts.app', ['title' => 'Your temporary owner access'])

@section('content')
    <p style="margin:0 0 10px;font-size:12px;font-weight:700;letter-spacing:1.3px;text-transform:uppercase;color:#C65046;">Owner access</p>
    <h1 style="margin:0;font-size:30px;line-height:1.15;letter-spacing:-1px;color:#182235;">Your {{ $businessName }} workspace is ready.</h1>
    <p style="margin:18px 0 0;font-size:15px;line-height:1.7;color:#60665f;">Use the sign-in page and your email address with the temporary password below. You must choose a new password before accessing the CRM.</p>
    <div style="margin-top:24px;padding:18px 20px;border-radius:12px;background:#F3F5F2;border:1px solid #E2E6E0;">
        <p style="margin:0 0 8px;font-size:12px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#60665f;">Temporary password</p>
        <p style="margin:0;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:18px;letter-spacing:1px;color:#182235;">{{ $temporaryPassword }}</p>
        <p style="margin:10px 0 0;font-size:12px;line-height:1.6;color:#687486;">This credential expires in 24 hours. If it expires, request a password reset from the sign-in page.</p>
    </div>
    <table role="presentation" cellspacing="0" cellpadding="0" style="margin-top:26px;"><tr><td style="border-radius:10px;background:#C65046;">
        <a href="{{ route('login') }}" style="display:inline-block;padding:14px 20px;border-radius:10px;color:#FFFFFF;font-size:14px;font-weight:700;text-decoration:none;">Open secure sign in&nbsp; →</a>
    </td></tr></table>
    <p style="margin:22px 0 0;font-size:13px;line-height:1.65;color:#687486;">If you did not expect this message, contact the person who provisioned the barbershop. Never forward this password.</p>
@endsection
