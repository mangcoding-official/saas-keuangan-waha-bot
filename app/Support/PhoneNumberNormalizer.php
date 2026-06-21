<?php

namespace App\Support;

use InvalidArgumentException;

class PhoneNumberNormalizer
{
    public function normalize(string $rawNumber): string
    {
        $digits = preg_replace('/\D+/', '', trim($rawNumber)) ?? '';

        if ($digits === '') {
            throw new InvalidArgumentException('Nomor WhatsApp wajib diisi.');
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        } elseif (str_starts_with($digits, '620')) {
            $digits = '62'.substr($digits, 3);
        }

        if (! str_starts_with($digits, '62')) {
            throw new InvalidArgumentException('Nomor WhatsApp harus memakai format Indonesia yang valid.');
        }

        if (strlen($digits) < 10 || strlen($digits) > 16) {
            throw new InvalidArgumentException('Nomor WhatsApp harus memiliki panjang 10 sampai 16 digit setelah normalisasi.');
        }

        return $digits;
    }
}
