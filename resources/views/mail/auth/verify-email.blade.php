@extends('mail.layouts.app', ['title' => 'Confirm your email address'])

@section('content')
    <p style="margin:0 0 10px;font-size:12px;font-weight:700;letter-spacing:1.3px;text-transform:uppercase;color:#C65046;">Account security</p>
    <h1 style="margin:0;font-size:28px;line-height:1.2;letter-spacing:-.7px;color:#182235;">Confirm your email address.</h1>
    <p style="margin:16px 0 0;font-size:15px;line-height:1.7;color:#60665f;">Confirm your address to finish securing your staff account and receive important shop updates.</p>
    <table role="presentation" cellspacing="0" cellpadding="0" style="margin-top:24px;"><tr><td style="border-radius:10px;background:#C65046;"><a href="{{ $url }}" style="display:inline-block;padding:14px 20px;border-radius:10px;color:#FFFFFF;font-size:14px;font-weight:700;text-decoration:none;">Confirm email&nbsp; →</a></td></tr></table>
    <p style="margin:20px 0 0;font-size:12px;line-height:1.6;color:#687486;">If you did not create or access this staff account, contact the shop owner.</p>
@endsection
