<?php

namespace App\Modules\Sponsorship\Infrastructure;

use App\Models\SponsorshipCase;
use App\Models\SponsorshipCaseFile;
use App\Modules\Sponsorship\Domain\Enums\CaseDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Case papers (ID cards, social research, photos) live on the private "local"
 * disk under sponsorship/cases/{id}/, with random names, and open only through
 * the dashboard. In the old system they sat in a public folder.
 */
class CaseFiles
{
    private const DISK = 'local';

    public function store(SponsorshipCase $case, UploadedFile $file, CaseDocument $kind): SponsorshipCaseFile
    {
        $name = Str::uuid()->toString().'.'.strtolower($file->getClientOriginalExtension() ?: (string) $file->extension());
        $path = Storage::disk(self::DISK)->putFileAs("sponsorship/cases/{$case->id}", $file, $name);

        return $case->files()->create([
            'kind' => $kind,
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    public function delete(SponsorshipCaseFile $file): void
    {
        Storage::disk(self::DISK)->delete($file->path);
        $file->delete();
    }

    public function deleteAll(SponsorshipCase $case): void
    {
        Storage::disk(self::DISK)->deleteDirectory("sponsorship/cases/{$case->id}");
    }

    public function open(SponsorshipCaseFile $file): StreamedResponse
    {
        return Storage::disk(self::DISK)->response($file->path, $file->original_name, [], 'inline');
    }
}
