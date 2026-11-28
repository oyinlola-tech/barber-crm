@extends('mail.layouts.app', ['title' => 'Your staff invitation'])

@section('content')
    <p style="margin:0 0 10px;font-size:12px;font-weight:700;letter-spacing:1.3px;text-transform:uppercase;color:#C65046;">Staff access</p>
    <h1 style="margin:0;font-size:30px;line-height:1.15;letter-spacing:-1px;color:#182235;">You’re invited to join {{ $businessName }}.</h1>
    <p style="margin:18px 0 0;font-size:15px;line-height:1.7;color:#60665f;">An authorized team member invited you to the private customer workspace. Use the secure link below to set your own password and activate your account.</p>
    <table role="presentation" cellspacing="0" cellpadding="0" style="margin-top:26px;"><tr><td style="border-radius:10px;background:#C65046;">
        <a href="{{ $invitationUrl }}" style="display:inline-block;padding:14px 20px;border-radius:10px;color:#FFFFFF;font-size:14px;font-weight:700;text-decoration:none;">Set up staff access&nbsp; →</a>
    </td></tr></table>
    <p style="margin:22px 0 0;font-size:13px;line-height:1.65;color:#687486;">This link expires in 72 hours and can only be used once. If you weren’t expecting this invitation, you can ignore this email.</p>
@endsection
