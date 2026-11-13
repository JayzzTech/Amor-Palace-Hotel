<?php

namespace App\Util;

final class Dates
{
    public static function parse(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return null;
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $d ?: null;
    }
}
