@extends('mail.layouts.app', ['title' => 'Staff access changed'])

@section('content')
    <p style="margin:0 0 10px;font-size:12px;font-weight:700;letter-spacing:1.3px;text-transform:uppercase;color:#C65046;">Security update</p>
    <h1 style="margin:0;font-size:28px;line-height:1.2;letter-spacing:-.7px;color:#182235;">Staff access changed.</h1>
    <p style="margin:16px 0 0;font-size:15px;line-height:1.7;color:#60665f;">{{ $summary }}</p>
    <p style="margin:10px 0 0;font-size:14px;line-height:1.6;color:#182235;"><strong>Staff member:</strong> {{ $staffName }}</p>
    <table role="presentation" cellspacing="0" cellpadding="0" style="margin-top:24px;"><tr><td style="border-radius:10px;background:#C65046;"><a href="{{ $url }}" style="display:inline-block;padding:14px 20px;border-radius:10px;color:#FFFFFF;font-size:14px;font-weight:700;text-decoration:none;">Review the audit log&nbsp; →</a></td></tr></table>
    <p style="margin:20px 0 0;font-size:12px;line-height:1.6;color:#687486;">This notice is sent to the owner and staff members authorized to review audit records. No invitation links or credentials are included.</p>
@endsection
