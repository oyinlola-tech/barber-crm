@extends('mail.layouts.app', ['title' => 'New WhatsApp message'])

@section('content')
    <p style="margin:0 0 10px;font-size:12px;font-weight:700;letter-spacing:1.3px;text-transform:uppercase;color:#C65046;">Inbox update</p>
    <h1 style="margin:0;font-size:28px;line-height:1.2;letter-spacing:-.7px;color:#182235;">A customer sent a WhatsApp message.</h1>
    <p style="margin:16px 0 0;font-size:15px;line-height:1.7;color:#60665f;">A new message is waiting in the shared inbox. Open the conversation to review and reply.</p>
    <table role="presentation" cellspacing="0" cellpadding="0" style="margin-top:24px;"><tr><td style="border-radius:10px;background:#C65046;"><a href="{{ $url }}" style="display:inline-block;padding:14px 20px;border-radius:10px;color:#FFFFFF;font-size:14px;font-weight:700;text-decoration:none;">Open the inbox&nbsp; →</a></td></tr></table>
    <p style="margin:20px 0 0;font-size:12px;line-height:1.6;color:#687486;">Customer message content is kept out of email. Sign in to the CRM to view it according to your access.</p>
@endsection
