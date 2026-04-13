<?php

namespace App\Traits;

trait SanitizesPhone
{
    private function sanitizePhone(string $phone): string
    {
        $phone = explode(',', $phone)[0];

        return str_replace(['+', '(', ')', '-', ' '], '', $phone);
    }
}
