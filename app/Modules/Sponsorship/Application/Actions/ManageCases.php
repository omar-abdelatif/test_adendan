<?php

namespace App\Modules\Sponsorship\Application\Actions;

use App\Models\SponsorshipCase;
use App\Models\SponsorshipCaseFile;
use App\Modules\Sponsorship\Domain\Enums\CaseDocument;
use App\Modules\Sponsorship\Infrastructure\CaseFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Adding, changing and removing sponsorship cases and their papers.
 *
 * $uploads is a list of [CaseDocument, UploadedFile] pairs. Files are written
 * after the row exists and removed again if the save fails, so no file is left
 * behind without a case.
 */
class ManageCases
{
    public function __construct(private readonly CaseFiles $files) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{0: CaseDocument, 1: UploadedFile}>  $uploads
     */
    public function create(array $data, array $uploads = []): SponsorshipCase
    {
        return $this->withFiles(function () use ($data) {
            return SponsorshipCase::create($data);
        }, $uploads);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{0: CaseDocument, 1: UploadedFile}>  $uploads
     */
    public function update(SponsorshipCase $case, array $data, array $uploads = []): SponsorshipCase
    {
        return $this->withFiles(function () use ($case, $data) {
            $case->update($data);

            return $case;
        }, $uploads);
    }

    /** @param  list<array{0: CaseDocument, 1: UploadedFile}>  $uploads */
    public function addFiles(SponsorshipCase $case, array $uploads): void
    {
        $this->withFiles(fn () => $case, $uploads);
    }

    public function deleteFile(SponsorshipCaseFile $file): void
    {
        $this->files->delete($file);
    }

    /** The case and all its papers. The old system left the files behind. */
    public function delete(SponsorshipCase $case): void
    {
        DB::transaction(fn () => $case->delete());
        $this->files->deleteAll($case);
    }

    /**
     * @param  callable(): SponsorshipCase  $save
     * @param  list<array{0: CaseDocument, 1: UploadedFile}>  $uploads
     */
    private function withFiles(callable $save, array $uploads): SponsorshipCase
    {
        $stored = [];
        try {
            return DB::transaction(function () use ($save, $uploads, &$stored) {
                $case = $save();
                foreach ($uploads as [$kind, $file]) {
                    $stored[] = $this->files->store($case, $file, $kind);
                }

                return $case;
            });
        } catch (Throwable $e) {
            foreach ($stored as $file) {
                $this->files->delete($file);
            }
            throw $e;
        }
    }
}
