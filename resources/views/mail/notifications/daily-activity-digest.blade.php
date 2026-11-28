@extends('mail.layouts.app', ['title' => 'Daily activity digest'])

@section('content')
    <p style="margin:0 0 10px;font-size:12px;font-weight:700;letter-spacing:1.3px;text-transform:uppercase;color:#C65046;">Business update</p>
    <h1 style="margin:0;font-size:28px;line-height:1.2;letter-spacing:-.7px;color:#182235;">Your daily activity digest.</h1>
    <p style="margin:16px 0 0;font-size:15px;line-height:1.7;color:#60665f;">{{ $businessName }} recorded {{ $totalEvents }} auditable {{ Str::plural('action', $totalEvents) }} on {{ \Illuminate\Support\Carbon::parse($date)->format('F j, Y') }}.</p>

    @if (count($actions) > 0)
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:22px;border-collapse:collapse;">
            @foreach ($actions as $action)
                <tr>
                    <td style="padding:12px 0;border-bottom:1px solid #e8e8e1;font-size:14px;line-height:1.5;color:#182235;">{{ $action['label'] }}</td>
                    <td align="right" style="padding:12px 0;border-bottom:1px solid #e8e8e1;font-size:14px;font-weight:700;color:#182235;">{{ $action['count'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <table role="presentation" cellspacing="0" cellpadding="0" style="margin-top:24px;"><tr><td style="border-radius:10px;background:#C65046;"><a href="{{ $url }}" style="display:inline-block;padding:14px 20px;border-radius:10px;color:#FFFFFF;font-size:14px;font-weight:700;text-decoration:none;">Review the activity&nbsp; →</a></td></tr></table>
    <p style="margin:20px 0 0;font-size:12px;line-height:1.6;color:#687486;">This summary includes action names and totals only. It excludes customer message contents, contact details, credentials and private record values.</p>
@endsection
