<?php

namespace App\Services;

use App\Models\ActivityPhoto;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PhotoStorageService
{
    /**
     * Maximum width and height for stored activity photos (SRS / QC Stage 4).
     */
    public const MAX_DIMENSION = 1920;

    /**
     * Maximum width and height for thumbnail images.
     */
    public const THUMBNAIL_DIMENSION = 300;

    /**
     * WebP compression quality (0-100).
     */
    public const WEBP_QUALITY = 82;

    /**
     * Get the configured upload disk.
     */
    public function disk(): Filesystem
    {
        return Storage::disk((string) config('durian.uploads.disk', 'public'));
    }

    /**
     * Store an uploaded image file and return its relative path.
     */
    public function storeFile(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, ['disk' => (string) config('durian.uploads.disk', 'public')]);
    }

    /**
     * Process, compress, convert to WebP, generate thumbnail, and register in activity_photos.
     */
    public function storeActivityPhoto(
        UploadedFile $file,
        string $activityType,
        string $activityId,
        ?string $uploadedBy,
        ?string $caption = null
    ): ActivityPhoto {
        $directory = "activity-photos/{$activityType}/{$activityId}";
        $uuid = (string) Str::uuid();

        $processed = $this->processAndOptimizeImage($file);

        $mainPath = "{$directory}/{$uuid}.webp";
        $this->disk()->put($mainPath, $processed['main_data']);

        $thumbnailPath = null;
        if (! empty($processed['thumbnail_data'])) {
            $thumbnailPath = "{$directory}/thumb_{$uuid}.webp";
            $this->disk()->put($thumbnailPath, $processed['thumbnail_data']);
        }

        return ActivityPhoto::create([
            'activity_type' => $activityType,
            'activity_id' => $activityId,
            'file_path' => $mainPath,
            'thumbnail_path' => $thumbnailPath,
            'file_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime_type' => $processed['mime_type'],
            'file_size_bytes' => strlen($processed['main_data']),
            'width' => $processed['width'],
            'height' => $processed['height'],
            'caption' => $caption,
            'uploaded_by' => $uploadedBy,
        ]);
    }

    /**
     * Process image: resize if too large, convert to WebP, and create thumbnail using GD.
     *
     * @return array{main_data: string, thumbnail_data: ?string, mime_type: string, width: int, height: int}
     */
    public function processAndOptimizeImage(UploadedFile $file): array
    {
        $realPath = $file->getRealPath();
        $sourceImage = null;

        if ($realPath && function_exists('imagecreatefromstring')) {
            $contents = @file_get_contents($realPath);
            if ($contents !== false) {
                $sourceImage = @imagecreatefromstring($contents);
            }
        }

        // Fallback if GD cannot decode image
        if (! $sourceImage) {
            $fallbackData = file_get_contents($file->getRealPath() ?: $file->path());

            return [
                'main_data' => $fallbackData ?: '',
                'thumbnail_data' => null,
                'mime_type' => $file->getClientMimeType() ?: 'image/jpeg',
                'width' => 0,
                'height' => 0,
            ];
        }

        $origWidth = imagesx($sourceImage);
        $origHeight = imagesy($sourceImage);

        // 1. Resize main image if dimensions exceed MAX_DIMENSION
        $targetWidth = $origWidth;
        $targetHeight = $origHeight;

        if ($origWidth > self::MAX_DIMENSION || $origHeight > self::MAX_DIMENSION) {
            $ratio = min(self::MAX_DIMENSION / $origWidth, self::MAX_DIMENSION / $origHeight);
            $targetWidth = (int) round($origWidth * $ratio);
            $targetHeight = (int) round($origHeight * $ratio);
        }

        $mainCanvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($mainCanvas, false);
        imagesavealpha($mainCanvas, true);
        imagecopyresampled($mainCanvas, $sourceImage, 0, 0, 0, 0, $targetWidth, $targetHeight, $origWidth, $origHeight);

        ob_start();
        if (function_exists('imagewebp')) {
            imagewebp($mainCanvas, null, self::WEBP_QUALITY);
            $mainData = (string) ob_get_clean();
            $mimeType = 'image/webp';
        } else {
            imagejpeg($mainCanvas, null, 85);
            $mainData = (string) ob_get_clean();
            $mimeType = 'image/jpeg';
        }
        imagedestroy($mainCanvas);

        // 2. Generate thumbnail (300x300 bounding box)
        $thumbRatio = min(self::THUMBNAIL_DIMENSION / $origWidth, self::THUMBNAIL_DIMENSION / $origHeight);
        $thumbWidth = max(1, (int) round($origWidth * $thumbRatio));
        $thumbHeight = max(1, (int) round($origHeight * $thumbRatio));

        $thumbCanvas = imagecreatetruecolor($thumbWidth, $thumbHeight);
        imagealphablending($thumbCanvas, false);
        imagesavealpha($thumbCanvas, true);
        imagecopyresampled($thumbCanvas, $sourceImage, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $origWidth, $origHeight);

        ob_start();
        if (function_exists('imagewebp')) {
            imagewebp($thumbCanvas, null, 75);
            $thumbnailData = (string) ob_get_clean();
        } else {
            imagejpeg($thumbCanvas, null, 75);
            $thumbnailData = (string) ob_get_clean();
        }
        imagedestroy($thumbCanvas);

        imagedestroy($sourceImage);

        return [
            'main_data' => $mainData,
            'thumbnail_data' => $thumbnailData,
            'mime_type' => $mimeType,
            'width' => $targetWidth,
            'height' => $targetHeight,
        ];
    }

    /**
     * Delete a stored file path if it exists.
     */
    public function deleteFile(?string $path): void
    {
        if ($path !== null && $path !== '' && $this->disk()->exists($path)) {
            $this->disk()->delete($path);
        }
    }

    /**
     * Delete an activity photo record together with its main file and thumbnail.
     */
    public function deleteActivityPhoto(ActivityPhoto $photo): void
    {
        $this->deleteFile($photo->file_path);
        if ($photo->thumbnail_path) {
            $this->deleteFile($photo->thumbnail_path);
        }
        $photo->delete();
    }

    /**
     * Get the public URL of a stored file.
     */
    public function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return $this->disk()->url($path);
    }
}
