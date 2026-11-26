<x-mail::message>
# Join {{ $businessName }}

You have been invited to access the barbershop customer CRM. This link expires in 72 hours and can only be used once.

<x-mail::button :url="$invitationUrl">
Accept invitation
</x-mail::button>

If you were not expecting this invitation, you can ignore this message.
</x-mail::message>
