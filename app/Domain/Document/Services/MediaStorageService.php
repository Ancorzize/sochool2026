<?php

namespace App\Domain\Document\Services;

use App\Domain\Document\Enums\FileCategoryEnum;
use App\Domain\Document\Models\MediaFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaStorageService
{
    public function storeMedia(
        int $schoolId,
        Model $entity,
        UploadedFile $file,
        FileCategoryEnum $category,
        ?int $userId = null,
        string $disk = 'local',
        bool $isPublic = false
    ): MediaFile {
        $uuid = (string) Str::uuid();
        $ext = $file->getClientOriginalExtension();
        $fileName = "{$uuid}.{$ext}";
        $path = "tenants/{$schoolId}/{$category->value}/{$fileName}";

        Storage::disk($disk)->putFileAs("tenants/{$schoolId}/{$category->value}", $file, $fileName);

        return MediaFile::create([
            'uuid' => $uuid,
            'school_id' => $schoolId,
            'entity_type' => get_class($entity),
            'entity_id' => $entity->getKey(),
            'file_category' => $category,
            'file_name' => $fileName,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType() ?: $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'disk' => $disk,
            'path' => $path,
            'url' => Storage::disk($disk)->url($path),
            'is_public' => $isPublic,
            'uploaded_by_user_id' => $userId,
        ]);
    }

    public function storeRawMedia(
        int $schoolId,
        Model $entity,
        string $content,
        string $originalName,
        string $mimeType,
        FileCategoryEnum $category,
        ?int $userId = null,
        string $disk = 'local',
        bool $isPublic = false
    ): MediaFile {
        $uuid = (string) Str::uuid();
        $ext = pathinfo($originalName, PATHINFO_EXTENSION) ?: 'pdf';
        $fileName = "{$uuid}.{$ext}";
        $path = "tenants/{$schoolId}/{$category->value}/{$fileName}";

        Storage::disk($disk)->put($path, $content);

        return MediaFile::create([
            'uuid' => $uuid,
            'school_id' => $schoolId,
            'entity_type' => get_class($entity),
            'entity_id' => $entity->getKey(),
            'file_category' => $category,
            'file_name' => $fileName,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'size_bytes' => strlen($content),
            'disk' => $disk,
            'path' => $path,
            'url' => Storage::disk($disk)->url($path),
            'is_public' => $isPublic,
            'uploaded_by_user_id' => $userId,
        ]);
    }
}
