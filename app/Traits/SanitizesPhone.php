<?php

namespace App\Traits;

trait SanitizesPhone
{
    private function sanitizePhone(string $phone): string
    {
        $phone = explode(',', $phone)[0];

        return str_replace(['+', '(', ')', '-', ' '], '', $phone);
    }

    /**
     * 11 digits without "+", leading 8 replaced with 7. Null when the phone is not usable.
     */
    private function normalizePhone11(string $phone): ?string
    {
        $number = preg_replace('/\D+/', '', $this->sanitizePhone($phone));

        if (strlen($number) === 10) {
            $number = '7'.$number;
        }

        if (strlen($number) === 11 && $number[0] === '8') {
            $number = '7'.substr($number, 1);
        }

        return strlen($number) === 11 ? $number : null;
    }
}
