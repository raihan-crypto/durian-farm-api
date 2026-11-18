<?php

namespace App\Services;

use App\Models\ActivityPhoto;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PhotoStorageService
{
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
     * Store an uploaded photo and register it in activity_photos (SRS 5.5).
     */
    public function storeActivityPhoto(
        UploadedFile $file,
        string $activityType,
        string $activityId,
        ?string $uploadedBy,
        ?string $caption = null
    ): ActivityPhoto {
        $path = $this->storeFile($file, "activity-photos/{$activityType}/{$activityId}");

        return ActivityPhoto::create([
            'activity_type' => $activityType,
            'activity_id' => $activityId,
            'file_path' => $path,
            'file_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'caption' => $caption,
            'uploaded_by' => $uploadedBy,
        ]);
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
     * Delete an activity photo record together with its file.
     */
    public function deleteActivityPhoto(ActivityPhoto $photo): void
    {
        $this->deleteFile($photo->file_path);
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
