<?php

declare(strict_types=1);

namespace App\Support;

final class SpreadsheetSanitizer
{
    /**
     * Prevent Excel/CSV formula injection on user-controlled text values.
     */
    public static function sanitizeCell(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $trimmedLeft = ltrim($value);
        if ($trimmedLeft !== '' && in_array($trimmedLeft[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * Build a sanitized export filename with period and optional shop code.
     */
    public static function buildFilename(
        string $prefix,
        string $fromDate,
        string $toDate,
        string $extension,
        ?string $shopCode = null,
    ): string {
        $safePrefix = preg_replace('/[^A-Za-z0-9\-_]/', '-', $prefix) ?: 'Report';
        $safeFrom = preg_replace('/[^0-9\-]/', '', $fromDate) ?: date('Y-m-d');
        $safeTo = preg_replace('/[^0-9\-]/', '', $toDate) ?: date('Y-m-d');
        $safeExt = preg_replace('/[^a-z0-9]/', '', strtolower($extension)) ?: 'csv';

        $shopSegment = '';
        if ($shopCode !== null && $shopCode !== '') {
            $cleanShop = preg_replace('/[^A-Za-z0-9\-_]/', '', $shopCode);
            if ($cleanShop !== '') {
                $shopSegment = '-'.$cleanShop;
            }
        }

        return "{$safePrefix}{$shopSegment}-{$safeFrom}-to-{$safeTo}.{$safeExt}";
    }
}
