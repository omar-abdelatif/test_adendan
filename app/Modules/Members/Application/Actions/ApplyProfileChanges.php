<?php

namespace App\Modules\Members\Application\Actions;

use App\Models\OcrData;
use App\Models\SearchedData;
use App\Models\Subscribers;
use App\Models\UserUpdateStaging;
use App\Modules\Members\Domain\MemberException;
use Illuminate\Support\Facades\DB;

/**
 * Members' data reaches staff from three places, each waiting for approval:
 * the public website form (searched_data), the OCR engine reading national ID
 * cards (ocr_data), and the mobile app (user_update_stagings). Approving one
 * copies the data onto the member and removes the request.
 */
class ApplyProfileChanges
{
    /**
     * @param  array{ssn?: ?string, birthdate?: ?string, address?: ?string, mobile_no?: ?string}  $fields
     * @return int|null id of the website request that was approved
     */
    public function fromWebsite(int $memberId, array $fields): ?int
    {
        $member = Subscribers::where('member_id', $memberId)->first() ?? throw MemberException::notFound();
        $changes = array_filter($fields, fn ($value) => $value !== null && $value !== '');
        if ($changes === []) {
            throw MemberException::nothingToUpdate();
        }

        return DB::transaction(function () use ($member, $changes) {
            $member->update($changes);
            $request = SearchedData::where('member_id', $member->member_id)->first();
            SearchedData::where('member_id', $member->member_id)->delete();

            return $request?->id;
        });
    }

    /** Finds the member by national ID, else by mobile number. */
    public function fromOcr(OcrData $scan): Subscribers
    {
        $member = Subscribers::where('ssn', $scan->nid)->first()
            ?? Subscribers::where('mobile_no', $scan->mobile)->first()
            ?? throw MemberException::notFound();

        return DB::transaction(function () use ($member, $scan) {
            $member->update(array_filter([
                'name' => $member->ssn == $scan->nid ? null : $scan->name,
                'ssn' => $scan->nid,
                'birthdate' => $scan->birth_date,
                'address' => $scan->address,
                'mobile_no' => $scan->mobile,
            ], fn ($value) => $value !== null && $value !== ''));
            $scan->delete();

            return $member;
        });
    }

    /**
     * Staff review the app request in a form and may correct it before approving.
     *
     * @param  array{name: string, address?: ?string, mobile_no?: ?string, ssn?: ?string, job_title?: ?string, birthdate?: ?string}  $reviewed
     */
    public function fromApp(int $memberId, array $reviewed): Subscribers
    {
        if (! UserUpdateStaging::where('member_id', $memberId)->exists()) {
            throw MemberException::notFound();
        }
        $member = Subscribers::where('member_id', $memberId)->first() ?? throw MemberException::notFound();

        return DB::transaction(function () use ($member, $reviewed) {
            $member->update(array_filter([
                'name' => $reviewed['name'],
                'address' => $reviewed['address'] ?? null,
                'mobile_no' => $reviewed['mobile_no'] ?? null,
                'ssn' => $reviewed['ssn'] ?? null,
                'job' => $reviewed['job_title'] ?? null,
                'birthdate' => $reviewed['birthdate'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''));
            UserUpdateStaging::where('member_id', $member->member_id)->delete();

            return $member;
        });
    }
}
