<?php

namespace App\Services;

use App\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaTransformService
{
    private const VARIANT_SIZES = [
        'thumb' => 200,
        'medium' => 800,
        'large' => 1600,
    ];

    public function __construct(
        private readonly MediaUploadService $uploadService,
    ) {}

    /**
     * Resize an asset to the given dimensions and return its URL.
     * The resized image is stored as a variant and the URL is returned.
     */
    public function resize(MediaAsset $asset, int $width, int $height, bool $maintainAspect = true): string
    {
        if (! $this->isImage($asset)) {
            return $this->uploadService->getUrl($asset);
        }

        $variantKey = "resize_{$width}x{$height}".($maintainAspect ? '_fit' : '_stretch');
        $variants = $asset->variants ?? [];

        if (isset($variants[$variantKey]['path'])) {
            return $this->uploadService->getUrl($asset, ['variant' => $variantKey]);
        }

        $imageData = $this->readAssetBytes($asset);
        $sourceImage = $this->createGdImageFromBytes($imageData, $asset->mime_type);

        if ($sourceImage === null) {
            return $this->uploadService->getUrl($asset);
        }

        $srcW = imagesx($sourceImage);
        $srcH = imagesy($sourceImage);

        [$targetW, $targetH] = $this->calculateDimensions($srcW, $srcH, $width, $height, $maintainAspect);

        $canvas = imagecreatetruecolor($targetW, $targetH);
        $this->preserveTransparency($canvas, $asset->mime_type);
        imagecopyresampled($canvas, $sourceImage, 0, 0, 0, 0, $targetW, $targetH, $srcW, $srcH);

        $variantPath = $this->storeGdImage($canvas, $asset, $variantKey, $asset->mime_type);
        imagedestroy($canvas);
        imagedestroy($sourceImage);

        $variants[$variantKey] = ['path' => $variantPath, 'width' => $targetW, 'height' => $targetH];
        $asset->update(['variants' => $variants]);

        return Storage::disk($asset->disk)->url($variantPath);
    }

    /**
     * Crop a region from an asset and return a new MediaAsset variant.
     */
    public function crop(MediaAsset $asset, int $x, int $y, int $width, int $height): MediaAsset
    {
        if (! $this->isImage($asset)) {
            return $asset;
        }

        $imageData = $this->readAssetBytes($asset);
        $sourceImage = $this->createGdImageFromBytes($imageData, $asset->mime_type);

        if ($sourceImage === null) {
            return $asset;
        }

        $canvas = imagecreatetruecolor($width, $height);
        $this->preserveTransparency($canvas, $asset->mime_type);
        imagecopy($canvas, $sourceImage, 0, 0, $x, $y, $width, $height);

        $variantKey = "crop_{$x}_{$y}_{$width}x{$height}";
        $variantPath = $this->storeGdImage($canvas, $asset, $variantKey, $asset->mime_type);
        imagedestroy($canvas);
        imagedestroy($sourceImage);

        $variants = $asset->variants ?? [];
        $variants[$variantKey] = ['path' => $variantPath, 'width' => $width, 'height' => $height];
        $asset->update(['variants' => $variants]);

        return $asset->fresh();
    }

    /**
     * Rotate an asset image by degrees and return the updated MediaAsset.
     */
    public function rotate(MediaAsset $asset, int $degrees): MediaAsset
    {
        if (! $this->isImage($asset)) {
            return $asset;
        }

        $imageData = $this->readAssetBytes($asset);
        $sourceImage = $this->createGdImageFromBytes($imageData, $asset->mime_type);

        if ($sourceImage === null) {
            return $asset;
        }

        // GD rotates counter-clockwise; negate for expected behaviour
        $rotated = imagerotate($sourceImage, -$degrees, 0);
        imagedestroy($sourceImage);

        if ($rotated === false) {
            return $asset;
        }

        $variantKey = "rotate_{$degrees}";
        $variantPath = $this->storeGdImage($rotated, $asset, $variantKey, $asset->mime_type);
        imagedestroy($rotated);

        $variants = $asset->variants ?? [];
        $variants[$variantKey] = [
            'path' => $variantPath,
            'width' => imagesx($rotated),
            'height' => imagesy($rotated),
        ];
        $asset->update(['variants' => $variants]);

        return $asset->fresh();
    }

    /**
     * Generate a square thumbnail and return its URL.
     */
    public function generateThumbnail(MediaAsset $asset, int $size = 200): string
    {
        return $this->resize($asset, $size, $size, true);
    }

    /**
     * Generate thumb, medium, and large variants and return their URLs keyed by size name.
     *
     * @return array<string, string>
     */
    public function generateVariants(MediaAsset $asset): array
    {
        $urls = [];

        foreach (self::VARIANT_SIZES as $name => $size) {
            $urls[$name] = $this->resize($asset, $size, $size, true);
        }

        return $urls;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function isImage(MediaAsset $asset): bool
    {
        return str_starts_with($asset->mime_type, 'image/')
            && $asset->mime_type !== 'image/svg+xml';
    }

    private function readAssetBytes(MediaAsset $asset): string
    {
        return Storage::disk($asset->disk)->get($asset->path) ?? '';
    }

    private function createGdImageFromBytes(string $data, string $mimeType): ?\GdImage
    {
        if (empty($data)) {
            return null;
        }

        $image = @imagecreatefromstring($data);

        return $image === false ? null : $image;
    }

    private function preserveTransparency(\GdImage $canvas, string $mimeType): void
    {
        if (in_array($mimeType, ['image/png', 'image/gif', 'image/webp'], true)) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
            if ($transparent !== false) {
                imagefilledrectangle($canvas, 0, 0, imagesx($canvas), imagesy($canvas), $transparent);
            }
        }
    }

    private function storeGdImage(\GdImage $image, MediaAsset $asset, string $variantKey, string $mimeType): string
    {
        ob_start();

        match (true) {
            $mimeType === 'image/png' => imagepng($image, null, 8),
            $mimeType === 'image/gif' => imagegif($image),
            $mimeType === 'image/webp' => imagewebp($image, null, 85),
            default => imagejpeg($image, null, 85),
        };

        $imageData = ob_get_clean() ?: '';

        $ext = match ($mimeType) {
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $dir = dirname($asset->path);
        $variantPath = $dir.'/'.Str::ulid().'_'.$variantKey.'.'.$ext;

        Storage::disk($asset->disk)->put($variantPath, $imageData);

        return $variantPath;
    }

    /**
     * @return array{int, int}
     */
    private function calculateDimensions(int $srcW, int $srcH, int $targetW, int $targetH, bool $maintainAspect): array
    {
        if (! $maintainAspect || $srcW === 0 || $srcH === 0) {
            return [$targetW, $targetH];
        }

        $ratio = $srcW / $srcH;
        $targetRatio = $targetW / $targetH;

        if ($ratio > $targetRatio) {
            // Width is the constraining dimension
            $newW = $targetW;
            $newH = (int) round($targetW / $ratio);
        } else {
            // Height is the constraining dimension
            $newH = $targetH;
            $newW = (int) round($targetH * $ratio);
        }

        return [max(1, $newW), max(1, $newH)];
    }
}
