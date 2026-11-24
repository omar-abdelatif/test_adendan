<?php

namespace App\Modules\Cemeteries\Infrastructure;

use App\Models\Burial;
use App\Models\BurialFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Photos and death certificates live on the private "local" disk
 * (storage/app/cemeteries/…), with random names, and open only through the dashboard.
 */
class BurialFiles
{
    private const DISK = 'local';

    public function store(Burial $burial, UploadedFile $file, string $kind): BurialFile
    {
        $name = Str::uuid()->toString().'.'.strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $path = Storage::disk(self::DISK)->putFileAs("cemeteries/burials/{$burial->id}", $file, $name);

        return $burial->files()->create([
            'kind' => $kind,
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    public function delete(BurialFile $file): void
    {
        Storage::disk(self::DISK)->delete($file->path);
        $file->delete();
    }

    public function open(BurialFile $file): StreamedResponse
    {
        return Storage::disk(self::DISK)->response($file->path, $file->original_name, [], 'inline');
    }
}
