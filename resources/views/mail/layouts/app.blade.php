<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $title ?? config('app.name') }}</title>
</head>
<body style="margin:0;background:#f1f1ec;color:#222820;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f1f1ec;padding:32px 12px;">
        <tr><td align="center">
            <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="width:100%;max-width:600px;border-collapse:separate;border-spacing:0;overflow:hidden;border-radius:20px;background:#fffefa;">
                <tr><td style="padding:22px 28px;border-bottom:1px solid #e8e8e1;">
                    <table role="presentation" cellspacing="0" cellpadding="0"><tr>
                        <td style="padding-right:12px;"><img src="{{ asset('brand/barber-crm-mark.svg') }}" width="42" height="42" alt="" style="display:block;border-radius:12px;"></td>
                        <td><p style="margin:0;font-size:15px;font-weight:700;letter-spacing:-.2px;color:#222820;">{{ config('app.name') }}</p><p style="margin:4px 0 0;font-size:12px;color:#73786f;">A thoughtful way to keep in touch.</p></td>
                    </tr></table>
                </td></tr>
                <tr><td style="padding:36px 28px 40px;">
                    @yield('content')
                </td></tr>
                <tr><td style="background:#202522;padding:28px;color:#fff;">
                    <p style="margin:0;font-size:12px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#b9d3ad;">Good service stays with you.</p>
                    <p style="margin:12px 0 0;font-size:24px;font-weight:700;line-height:1.2;letter-spacing:-.7px;">Thanks for being part of<br>the shop.</p>
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:24px;border-top:1px solid #ffffff25;"><tr>
                        <td style="padding-top:16px;font-size:11px;line-height:1.6;color:#b9beb9;">Private communication for authorized team members.<br>{{ config('app.name') }}</td>
                        <td align="right" style="padding-top:16px;font-size:11px;color:#b9beb9;">{{ now()->year }}</td>
                    </tr></table>
                </td></tr>
            </table>
            <p style="max-width:560px;margin:16px auto 0;font-size:11px;line-height:1.6;color:#73786f;text-align:center;">This message was sent by the barbershop CRM. Replies may not be monitored unless stated in the message.</p>
        </td></tr>
    </table>
</body>
</html>
