<?php

namespace App\Services;

class HashIdService
{
    /**
     * Encode numeric ID with prefix into secure hashed string ID.
     * Example: encode(4, 'PRT') => 'PRT-9X8A2B'
     */
    public static function encode(int $id, string $prefix = ''): string
    {
        $secretKey = config('app.key', 'fitcore_secret_key');
        $hashNum = ($id * 2654435761) % 4294967296; // Knuth multiplicative hash algorithm
        $encoded = strtoupper(base_convert($hashNum, 10, 36));
        $padded = str_pad($encoded, 6, '0', STR_PAD_LEFT);

        return $prefix ? strtoupper($prefix) . '-' . $padded : $padded;
    }

    /**
     * Decode hashed string ID back to underlying numeric ID.
     * Example: decode('PRT-9X8A2B', 'PRT') => 4
     */
    public static function decode(string $hashId, string $prefix = ''): ?int
    {
        // Remove prefix if present (e.g. 'PRT-9X8A2B' -> '9X8A2B')
        if ($prefix && str_contains($hashId, '-')) {
            $parts = explode('-', $hashId);
            $hashId = end($parts);
        }

        if (is_numeric($hashId)) {
            return (int) $hashId;
        }

        $hashNum = base_convert(strtolower($hashId), 36, 10);
        
        // Inverse Knuth multiplicative hash algorithm
        for ($i = 1; $i <= 100000; $i++) {
            if ((($i * 2654435761) % 4294967296) == $hashNum) {
                return $i;
            }
        }

        return null;
    }
}
