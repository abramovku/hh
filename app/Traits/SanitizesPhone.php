<?php

namespace App\Traits;

trait SanitizesPhone
{
    private function sanitizePhone(string $phone): string
    {
        return str_replace(['+', '(', ')', '-', ' '], '', $phone);
    }
}
