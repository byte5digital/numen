<?php

namespace App\Services;

use App\Jobs\GenerateVariantsJob;
use App\Models\MediaAsset;
use App\Models\MediaFolder;
use App\Models\Space;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MediaUploadService
{
    /**
     * Supported MIME types for upload.
     */
    private const DEFAULT_MIME_TYPES = [
        // Images
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        'image/avif',
        // Documents
        'application/pdf',
        // Video
        'video/mp4',
        'video/webm',
        'video/ogg',
        // Audio
        'audio/mpeg',
        'audio/ogg',
        'audio/wav',
        'audio/webm',
    ];

    private const DEFAULT_MAX_FILE_SIZE_BYTES = 50 * 1024 * 1024; // 50 MB

    public function __construct(
        private readonly ?string $disk = null,
    ) {}

    /**
     * Upload a file for a space, optionally placing it in a folder.
     */
    public function upload(UploadedFile $file, Space $space, ?MediaFolder $folder = null): MediaAsset
    {
        $this->validateFile($file);

        $disk = $this->resolveDisk();
        $extension = $file->getClientOriginalExtension();
        $filename = $file->getClientOriginalName();
        $uniqueName = Str::ulid().'.'.$extension;
        $directory = 'media/'.$space->id;
        $path = $file->storeAs($directory, $uniqueName, $disk);

        if ($path === false) {
            throw new \RuntimeException('Failed to store uploaded file.');
        }

        $mimeType = $file->getMimeType() ?? $file->getClientMimeType();
        $sizeBytes = $file->getSize();

        // Extract image dimensions
        [$width, $height] = $this->extractImageDimensions($file, $mimeType);

        /** @var MediaAsset $asset */
        $asset = MediaAsset::create([
            'space_id' => $space->id,
            'folder_id' => $folder?->id,
            'filename' => $filename,
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
            'file_size' => $sizeBytes,
            'width' => $width,
            'height' => $height,
            'source' => 'upload',
            'is_public' => true,
        ]);

        if (str_starts_with($mimeType, 'image/') && $mimeType !== 'image/svg+xml') {
            GenerateVariantsJob::dispatch($asset);
        }

        return $asset;
    }

    /**
     * Delete a media asset from storage and the database.
     */
    public function delete(MediaAsset $asset): void
    {
        $disk = $asset->disk;

        if (Storage::disk($disk)->exists($asset->path)) {
            Storage::disk($disk)->delete($asset->path);
        }

        // Also remove stored variants
        if (! empty($asset->variants)) {
            foreach ($asset->variants as $variant) {
                if (isset($variant['path']) && Storage::disk($disk)->exists($variant['path'])) {
                    Storage::disk($disk)->delete($variant['path']);
                }
            }
        }

        $asset->delete();
    }

    /**
     * Get the public or signed URL for a media asset.
     *
     * @param  array<string, mixed>  $transforms  Optional transform params appended as query string for CDN
     */
    public function getUrl(MediaAsset $asset, array $transforms = []): string
    {
        $cdnEnabled = (bool) config('media.cdn_enabled', env('CDN_ENABLED', false));
        $cdnBase = config('media.cdn_url', env('CDN_URL', ''));

        if ($cdnEnabled && $cdnBase) {
            $url = rtrim($cdnBase, '/').'/'.$asset->path;
            if (! empty($transforms)) {
                $url .= '?'.http_build_query($transforms);
            }

            return $url;
        }

        $disk = Storage::disk($asset->disk);

        // S3 / cloud disks: generate temporary signed URL
        if (method_exists($disk, 'temporaryUrl')) {
            try {
                return $disk->temporaryUrl($asset->path, now()->addHours(2));
            } catch (\Exception) {
                // Fall through to public URL if temporary URL not supported
            }
        }

        return $disk->url($asset->path);
    }

    /**
     * @return string[]
     */
    public function getSupportedMimeTypes(): array
    {
        $configured = config('media.allowed_mime_types');

        return is_array($configured) ? $configured : self::DEFAULT_MIME_TYPES;
    }

    public function getMaxFileSizeBytes(): int
    {
        return (int) config('media.max_file_size_bytes', self::DEFAULT_MAX_FILE_SIZE_BYTES);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function validateFile(UploadedFile $file): void
    {
        if ($file->getSize() > $this->getMaxFileSizeBytes()) {
            throw ValidationException::withMessages([
                'file' => ['File size exceeds the maximum allowed size of '.($this->getMaxFileSizeBytes() / 1024 / 1024).' MB.'],
            ]);
        }

        $mime = $file->getMimeType() ?? $file->getClientMimeType();
        if (! in_array($mime, $this->getSupportedMimeTypes(), true)) {
            throw ValidationException::withMessages([
                'file' => ['File type '.$mime.' is not supported.'],
            ]);
        }
    }

    private function resolveDisk(): string
    {
        if ($this->disk !== null) {
            return $this->disk;
        }

        return config('media.disk', config('filesystems.default', 'local'));
    }

    /**
     * @return array{int|null, int|null}
     */
    private function extractImageDimensions(UploadedFile $file, string $mimeType): array
    {
        if (! str_starts_with($mimeType, 'image/') || $mimeType === 'image/svg+xml') {
            return [null, null];
        }

        $size = @getimagesize($file->getRealPath());
        if ($size === false || $size === null) {
            return [null, null];
        }

        return [$size[0] ?: null, $size[1] ?: null];
    }
}
