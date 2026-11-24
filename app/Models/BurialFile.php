<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A photo or death certificate of a burial, kept on the private disk and opened
 * only through the dashboard (never by a public link).
 *
 * @property int $id
 * @property int $burial_id
 * @property string $kind photo | certificate
 * @property string $path
 * @property string $original_name
 * @property string|null $mime
 */
class BurialFile extends Model
{
    public const KINDS = ['photo' => 'صورة', 'certificate' => 'شهادة الوفاة'];

    protected $fillable = ['burial_id', 'kind', 'path', 'original_name', 'mime', 'size'];

    /** @return BelongsTo<Burial, $this> */
    public function burial(): BelongsTo
    {
        return $this->belongsTo(Burial::class);
    }
}
