@extends('mail.layouts.app', ['title' => 'WhatsApp delivery needs review'])

@section('content')
    <p style="margin:0 0 10px;font-size:12px;font-weight:700;letter-spacing:1.3px;text-transform:uppercase;color:#C65046;">Delivery status</p>
    <h1 style="margin:0;font-size:28px;line-height:1.2;letter-spacing:-.7px;color:#182235;">A WhatsApp message needs a quick check.</h1>
    <p style="margin:16px 0 0;font-size:15px;line-height:1.7;color:#687486;">The provider response did not confirm whether this message arrived. Check its delivery history before retrying, to avoid sending a duplicate.</p>
    <table role="presentation" cellspacing="0" cellpadding="0" style="margin-top:24px;"><tr><td style="border-radius:10px;background:#C65046;"><a href="{{ $url }}" style="display:inline-block;padding:14px 20px;border-radius:10px;color:#FFFFFF;font-size:14px;font-weight:700;text-decoration:none;">Review delivery&nbsp; →</a></td></tr></table>
@endsection
