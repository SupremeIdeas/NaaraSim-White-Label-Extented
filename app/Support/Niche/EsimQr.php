<?php

namespace App\Support\Niche;

use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * Renders an eSIM activation QR from its LPA string (`LPA:1$smdp$matchingId`).
 * Generated on our side so a scannable code always exists — even when a provider
 * returns only the text code. SVG for the web (crisp, tiny); PNG for email
 * (SVG isn't reliably rendered by mail clients). Medium error-correction keeps
 * it scannable if the logo/margin crops slightly.
 */
class EsimQr
{
    public static function svg(string $lpa, int $size = 320): string
    {
        return (new SvgWriter)->write(self::code($lpa, $size))->getString();
    }

    /** Raw PNG bytes for inline email embedding. */
    public static function png(string $lpa, int $size = 320): string
    {
        return (new PngWriter)->write(self::code($lpa, $size))->getString();
    }

    private static function code(string $lpa, int $size): QrCode
    {
        return new QrCode(
            data: $lpa,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: 12,
        );
    }
}
