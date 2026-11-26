<?php

namespace App\Utils;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class PhoneNumberNormalizer
{
    public function normalize(string $phoneNumber): ?string
    {
        try {
            $phone = PhoneNumberUtil::getInstance()->parse($phoneNumber, config('crm.phone_default_region'));
        } catch (NumberParseException) {
            return null;
        }

        if (! PhoneNumberUtil::getInstance()->isValidNumber($phone)) {
            return null;
        }

        return PhoneNumberUtil::getInstance()->format($phone, PhoneNumberFormat::E164);
    }
}
