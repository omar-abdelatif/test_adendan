<?php

namespace App\Modules\Shared\Infrastructure;

use Illuminate\Http\UploadedFile;

/**
 * Stores uploaded images under public/assets/images/<folder>, where the views
 * already read them. One place instead of a copy of the move/unlink code in
 * every controller.
 *
 * Names come from hashName(): random, with the extension of the real content.
 */
class ImageStorage
{
    public function store(UploadedFile $file, string $folder): string
    {
        $name = $file->hashName();
        $file->move($this->directory($folder), $name);

        return $name;
    }

    /** Stores the new file and removes the old one. */
    public function replace(?string $oldName, UploadedFile $file, string $folder): string
    {
        $name = $this->store($file, $folder);
        $this->delete($oldName, $folder);

        return $name;
    }

    public function delete(?string $name, string $folder): void
    {
        if ($name === null || $name === '') {
            return;
        }
        // basename(): a stored name can never point outside its folder.
        $path = $this->directory($folder).DIRECTORY_SEPARATOR.basename($name);
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function directory(string $folder): string
    {
        return public_path('assets/images/'.trim($folder, '/\\'));
    }
}
