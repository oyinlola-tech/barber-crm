@extends('mail.layouts.app', ['title' => 'WhatsApp event processing needs attention'])

@section('content')
    <p style="margin:0 0 10px;font-size:12px;font-weight:700;letter-spacing:1.3px;text-transform:uppercase;color:#C65046;">WhatsApp operations</p>
    <h1 style="margin:0;font-size:28px;line-height:1.2;letter-spacing:-.7px;color:#182235;">An incoming event could not be processed.</h1>
    <p style="margin:16px 0 0;font-size:15px;line-height:1.7;color:#687486;">An incoming WhatsApp event reached a final failed state after the configured retries. Its customer content has been removed from the failed record.</p>
    <p style="margin:12px 0 0;font-size:15px;line-height:1.7;color:#687486;">Review system health and the shared inbox for any missed updates.</p>
    <table role="presentation" cellspacing="0" cellpadding="0" style="margin-top:24px;"><tr><td style="border-radius:10px;background:#C65046;"><a href="{{ $url }}" style="display:inline-block;padding:14px 20px;border-radius:10px;color:#FFFFFF;font-size:14px;font-weight:700;text-decoration:none;">Review system health&nbsp; →</a></td></tr></table>
    <p style="margin:20px 0 0;font-size:12px;line-height:1.6;color:#687486;">The event payload and provider response are not included in this email.</p>
@endsection
