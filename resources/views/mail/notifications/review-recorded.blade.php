@extends('mail.layouts.app', ['title' => 'New customer feedback'])

@section('content')
    <p style="margin:0 0 10px;font-size:12px;font-weight:700;letter-spacing:1.3px;text-transform:uppercase;color:#C65046;">Customer relationships</p>
    <h1 style="margin:0;font-size:28px;line-height:1.2;letter-spacing:-.7px;color:#182235;">A customer shared feedback.</h1>
    <p style="margin:0;font-size:15px;line-height:1.7;color:#60665f;">New customer feedback has been recorded in the CRM. Sign in to review it with your team.</p>
    <table role="presentation" cellspacing="0" cellpadding="0" style="margin-top:24px;"><tr><td style="border-radius:10px;background:#C65046;"><a href="{{ $url }}" style="display:inline-block;padding:14px 20px;border-radius:10px;color:#FFFFFF;font-size:14px;font-weight:700;text-decoration:none;">Review customer feedback&nbsp; →</a></td></tr></table>
    <p style="margin:20px 0 0;font-size:12px;line-height:1.6;color:#687486;">Customer names, phone numbers, ratings, and feedback text are not included in this email.</p>
@endsection
