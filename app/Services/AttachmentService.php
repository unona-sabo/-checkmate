<?php

namespace App\Services;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class AttachmentService
{
    /**
     * Store uploaded attachments from a request and associate them with a model.
     */
    public function storeFromRequest(Model $model, Request $request, string $storagePath): void
    {
        if (! $request->hasFile('attachments')) {
            return;
        }

        foreach ($request->file('attachments') as $file) {
            $path = $file->store($storagePath, 'public');
            $model->attachments()->create([
                'original_filename' => $file->getClientOriginalName(),
                'stored_path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }

    /**
     * Store a single uploaded file and associate it with a model.
     */
    public function storeSingleFile(Model $model, \Illuminate\Http\UploadedFile $file, string $storagePath): Attachment
    {
        $path = $file->store($storagePath, 'public');

        return $model->attachments()->create([
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    /**
     * Delete all attachments belonging to a model (removes files from disk and DB records).
     */
    public function deleteAll(Model $model): void
    {
        foreach ($model->attachments as $attachment) {
            Storage::disk('public')->delete($attachment->stored_path);
        }
    }

    /**
     * Delete a single attachment (removes file from disk and DB record).
     */
    public function deleteOne(Attachment $attachment): void
    {
        Storage::disk('public')->delete($attachment->stored_path);
        $attachment->delete();
    }

    /**
     * Copy a collection of attachments to a target model.
     */
    public function copyTo(Model $target, Collection $attachments, string $storagePath): void
    {
        foreach ($attachments as $attachment) {
            if (! Storage::disk('public')->exists($attachment->stored_path)) {
                continue;
            }

            $extension = pathinfo($attachment->stored_path, PATHINFO_EXTENSION);
            $newPath = $storagePath.'/'.uniqid().'.'.$extension;
            Storage::disk('public')->copy($attachment->stored_path, $newPath);

            $target->attachments()->create([
                'original_filename' => $attachment->original_filename,
                'stored_path' => $newPath,
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
            ]);
        }
    }
}
