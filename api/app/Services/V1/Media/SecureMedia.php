<?php

namespace App\Services\V1\Media;

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class SecureMedia
{
    public function addSecureMedia(HasMedia $model, UploadedFile $file, string $collection = 'default'): Media
    {
        $secureFilename = $this->generateSecureFilename($file);
        $processedFile = $this->processFile($file);

        return $model
            ->addMediaFromRequest($processedFile)
            ->sanitizingFileName(function ($fileName) {
                return $this->sanitizeFileName($fileName);
            })
            ->usingName($secureFilename)
            ->usingFileName($secureFilename)
            ->toMediaCollection($collection);
    }

    protected function processFile(UploadedFile $file): UploadedFile
    {
        if (str_starts_with($file->getMimeType(), 'image/')) {
            return $this->sanitizeImage($file);
        }

        return $file;
    }

    protected function sanitizeImage(UploadedFile $file): UploadedFile
    {
        try {
            $image = Image::make($file->getPathname());

            $tempPath = tempnam(sys_get_temp_dir(), 'clean_image_');

            $image->save($tempPath, 90);

            return new UploadedFile(
                $tempPath,
                $this->sanitizeFileName($file->getClientOriginalName()),
                $file->getMimeType(),
                null,
                true
            );

        } catch (\Exception $e) {
            return $file;
        }
    }

    protected function generateSecureFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $hash = hash('sha256', $file->getPathname() . time());

        return substr($hash, 0, 32) . '.' . $extension;
    }

    protected function sanitizeFileName(string $fileName): string
    {
        $fileName = preg_replace('/[^a-zA-Z0-9._-]/', '', $fileName);
        $fileName = Str::limit($fileName, 100, '');
        $fileName = ltrim($fileName, '.');

        return $fileName ?: 'file';
    }
}
