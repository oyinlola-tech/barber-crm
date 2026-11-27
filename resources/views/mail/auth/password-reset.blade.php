@extends('mail.layouts.app', ['title' => 'Reset your password'])

@section('content')
    <p style="margin:0 0 10px;font-size:12px;font-weight:700;letter-spacing:1.3px;text-transform:uppercase;color:#58715a;">Account security</p>
    <h1 style="margin:0;font-size:28px;line-height:1.2;letter-spacing:-.7px;color:#222820;">Choose a new password.</h1>
    <p style="margin:16px 0 0;font-size:15px;line-height:1.7;color:#60665f;">We received a request to reset the password for your staff account. Use the secure link below to continue.</p>
    <table role="presentation" cellspacing="0" cellpadding="0" style="margin-top:24px;"><tr><td style="border-radius:10px;background:#344c3b;"><a href="{{ $url }}" style="display:inline-block;padding:14px 20px;border-radius:10px;color:#fffefa;font-size:14px;font-weight:700;text-decoration:none;">Reset password&nbsp; →</a></td></tr></table>
    <p style="margin:20px 0 0;font-size:12px;line-height:1.6;color:#73786f;">This link expires according to the account security settings and can only be used to reset your password. If you did not request it, you can ignore this email.</p>
@endsection
