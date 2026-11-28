@extends('mail.layouts.app', ['title' => 'Customer visit recorded'])

@section('content')
    <p style="margin:0 0 10px;font-size:12px;font-weight:700;letter-spacing:1.3px;text-transform:uppercase;color:#C65046;">Shop activity</p>
    <h1 style="margin:0;font-size:28px;line-height:1.2;letter-spacing:-.7px;color:#182235;">A completed customer visit was recorded.</h1>
    <p style="margin:16px 0 0;font-size:15px;line-height:1.7;color:#60665f;">A team member updated the visit history. The customer’s private notes and contact details are not included in this email.</p>
    <table role="presentation" cellspacing="0" cellpadding="0" style="margin-top:24px;"><tr><td style="border-radius:10px;background:#C65046;"><a href="{{ $url }}" style="display:inline-block;padding:14px 20px;border-radius:10px;color:#FFFFFF;font-size:14px;font-weight:700;text-decoration:none;">Open the dashboard&nbsp; →</a></td></tr></table>
@endsection
