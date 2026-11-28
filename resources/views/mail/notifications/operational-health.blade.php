@extends('mail.layouts.app', ['title' => $recovered ? 'System health recovered' : 'System health needs attention'])

@section('content')
    <p style="margin:0 0 10px;font-size:12px;font-weight:700;letter-spacing:1.3px;text-transform:uppercase;color:{{ $recovered ? '#39745B' : '#C65046' }};">{{ $recovered ? 'System update' : 'Action may be needed' }}</p>
    <h1 style="margin:0;font-size:28px;line-height:1.2;letter-spacing:-.7px;color:#182235;">{{ $recovered ? 'Service restored.' : 'System health changed.' }}</h1>
    <p style="margin:16px 0 0;font-size:15px;line-height:1.7;color:#60665f;">The following system status changed for {{ $businessName }}:</p>
    <ul style="margin:12px 0 0;padding-left:22px;font-size:14px;line-height:1.8;color:#182235;">
        @foreach($changes as $change)
            <li><strong>{{ $change['component'] }}:</strong> {{ strtolower($change['status']) }}@if($change['previousStatus'] !== null) (previously {{ strtolower($change['previousStatus']) }})@endif</li>
        @endforeach
    </ul>
    <table role="presentation" cellspacing="0" cellpadding="0" style="margin-top:24px;"><tr><td style="border-radius:10px;background:#C65046;"><a href="{{ $url }}" style="display:inline-block;padding:14px 20px;border-radius:10px;color:#FFFFFF;font-size:14px;font-weight:700;text-decoration:none;">View system health&nbsp; →</a></td></tr></table>
    <p style="margin:20px 0 0;font-size:12px;line-height:1.6;color:#687486;">This alert contains system status only. It does not include customer information, message content, or integration credentials.</p>
@endsection
