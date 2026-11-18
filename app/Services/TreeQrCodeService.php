<?php

namespace App\Services;

use App\Models\Tree;
use App\Models\TreeQrCode;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\Result\ResultInterface;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Str;

class TreeQrCodeService
{
    /**
     * Length of the random, non-guessable public token (SRS 10.1 point 3).
     */
    public const TOKEN_LENGTH = 40;

    /**
     * Check whether a token has a valid public token format before touching the database.
     */
    public function isValidTokenFormat(string $token): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9]{'.self::TOKEN_LENGTH.'}$/', $token);
    }

    /**
     * Build the public URL that is encoded inside the QR Code.
     */
    public function publicUrl(string $token): string
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $prefix = trim((string) config('durian.public_tree.path_prefix', 't'), '/');

        return "{$baseUrl}/{$prefix}/{$token}";
    }

    /**
     * Get the existing QR Code of a tree or create the first one.
     */
    public function ensureForTree(Tree $tree): TreeQrCode
    {
        $existing = TreeQrCode::query()->where('tree_id', $tree->id)->first();

        if ($existing) {
            return $existing;
        }

        $token = $this->generateUniqueToken();

        return TreeQrCode::create([
            'tree_id' => $tree->id,
            'public_token' => $token,
            'qr_code' => $this->publicUrl($token),
            'generated_at' => now(),
            'is_active' => true,
        ]);
    }

    /**
     * Replace the token of a QR Code. The previous token immediately stops working (SRS 10.1 point 6).
     */
    public function regenerate(TreeQrCode $qrCode): TreeQrCode
    {
        $token = $this->generateUniqueToken();

        $qrCode->update([
            'public_token' => $token,
            'qr_code' => $this->publicUrl($token),
            'generated_at' => now(),
            'is_active' => true,
        ]);

        return $qrCode->refresh();
    }

    /**
     * Render the QR Code image as SVG or PNG.
     */
    public function render(TreeQrCode $qrCode, string $format = 'svg', int $size = 300): ResultInterface
    {
        $writer = $format === 'png' ? new PngWriter : new SvgWriter;

        $builder = new Builder(
            writer: $writer,
            data: $qrCode->qr_code,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: 12,
        );

        return $builder->build();
    }

    /**
     * Generate a random token that is not yet used by another QR Code.
     */
    protected function generateUniqueToken(): string
    {
        do {
            $token = Str::random(self::TOKEN_LENGTH);
        } while (TreeQrCode::query()->where('public_token', $token)->exists());

        return $token;
    }
}
