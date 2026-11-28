<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $title ?? config('app.name') }}</title>
</head>
<body style="margin:0;background:#F4F6F8;color:#182235;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#F4F6F8;padding:32px 12px;">
        <tr><td align="center">
            <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="width:100%;max-width:600px;border-collapse:separate;border-spacing:0;overflow:hidden;border-radius:20px;background:#FFFFFF;">
                <tr><td style="padding:22px 28px;border-bottom:1px solid #e8e8e1;">
                    <table role="presentation" cellspacing="0" cellpadding="0"><tr>
                        <td style="padding-right:12px;"><img src="{{ asset('brand/barber-crm-mark.svg') }}" width="42" height="42" alt="" style="display:block;border-radius:12px;"></td>
                        <td><p style="margin:0;font-size:15px;font-weight:700;letter-spacing:-.2px;color:#182235;">{{ config('app.name') }}</p><p style="margin:4px 0 0;font-size:12px;color:#687486;">A thoughtful way to keep in touch.</p></td>
                    </tr></table>
                </td></tr>
                <tr><td style="padding:36px 28px 40px;">
                    @yield('content')
                </td></tr>
                <tr><td style="background:#172235;padding:32px 28px 25px;color:#fff;">
                    <p style="margin:0;font-size:11px;font-weight:700;letter-spacing:1.7px;text-transform:uppercase;color:#F08378;">GOOD SERVICE STAYS WITH PEOPLE</p>
                    <p style="margin:14px 0 0;font-size:34px;font-weight:700;line-height:1.03;letter-spacing:-1.2px;">Good service<br>deserves a return visit<span style="color:#F08378;">.</span></p>
                    <p style="max-width:440px;margin:14px 0 0;font-size:13px;line-height:1.65;color:#c3cbd7;">A thoughtful follow-up, a remembered visit, and a team that stays in sync.</p>
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:25px;border-top:1px solid #ffffff25;"><tr>
                        <td style="padding-top:17px;font-size:10px;line-height:1.65;color:#aab4c2;">PRIVATE TEAM WORKSPACE<br>{{ config('app.name') }}</td>
                        <td align="right" style="padding-top:17px;font-size:10px;line-height:1.65;color:#aab4c2;">Authorized staff only<br>{{ now()->year }}</td>
                    </tr></table>
                </td></tr>
            </table>
            <p style="max-width:560px;margin:16px auto 0;font-size:11px;line-height:1.6;color:#687486;text-align:center;">This message was sent by the barbershop CRM. Replies may not be monitored unless stated in the message.</p>
        </td></tr>
    </table>
</body>
</html>
