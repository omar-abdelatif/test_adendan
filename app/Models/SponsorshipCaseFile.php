<?php

namespace App\Models;

use App\Modules\Sponsorship\Domain\Enums\CaseDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A paper in a case file, kept on the private disk (see Sponsorship\Infrastructure\CaseFiles).
 *
 * @property int $id
 * @property int $case_id
 * @property CaseDocument $kind
 * @property string $path
 * @property string $original_name
 * @property string|null $mime
 * @property int|null $size
 * @property Carbon|null $created_at
 */
class SponsorshipCaseFile extends Model
{
    protected $table = 'sponsorship_case_files';

    protected $fillable = ['kind', 'path', 'original_name', 'mime', 'size'];

    protected $casts = ['kind' => CaseDocument::class];

    /** @return BelongsTo<SponsorshipCase, $this> */
    public function case(): BelongsTo
    {
        return $this->belongsTo(SponsorshipCase::class, 'case_id');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }
}
