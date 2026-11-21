<?php

namespace App\Modules\Members\Application\Queries;

use App\Models\OcrData;
use App\Models\SearchedData;
use App\Models\Subscribers;

/** Read-only lists for the member report pages; each returns its view's variables. */
class MemberReports
{
    public function byJob(?string $keyword): array
    {
        return $this->search('keyword', 'jobs', $keyword, 'برجاء إدخال إسم وظيفة.',
            fn (string $k) => Subscribers::where('job', 'like', '%'.$this->escapeLike($k).'%')->get());
    }

    public function byBirthYear(?string $date): array
    {
        $year = $date ? (int) date('Y', strtotime($date)) : 0;

        return $this->search('ageKey', 'ages', $year > 0 ? $date : null, 'برجاء إدخال سنة ميلاد صحيح',
            fn () => Subscribers::whereYear('birthdate', $year)->get());
    }

    public function byAddress(?string $address): array
    {
        return $this->search('locationKey', 'locations', $address, 'برجاء إدخال إسم المنطقة.',
            fn (string $k) => Subscribers::where('address', 'like', '%'.$this->escapeLike($k).'%')->get());
    }

    public function associates(): array
    {
        return ['associates' => Subscribers::where('membership_type', 'إنتساب')->get()];
    }

    public function incomplete(): array
    {
        $missingSsn = Subscribers::where('ssn', 0)->get();
        $missingMobile = Subscribers::where('mobile_no', 0)->get();
        $missingAddress = Subscribers::where(fn ($q) => $q->whereNull('address')->orWhere('address', ''))->get();

        return [
            'incompleteSSN' => $missingSsn,
            'incompleteSSNCount' => $missingSsn->count(),
            'incompleteMobile' => $missingMobile,
            'incompleteMobileCount' => $missingMobile->count(),
            'incompleteAddress' => $missingAddress,
            'incompleteAddressCount' => $missingAddress->count(),
        ];
    }

    public function pendingProfileChanges(): array
    {
        return ['data' => SearchedData::latest('id')->get(), 'ocr' => OcrData::latest('id')->get()];
    }

    private function search(string $keyName, string $resultName, ?string $keyword, string $emptyMessage, callable $query): array
    {
        $keyword = $keyword !== null ? trim($keyword) : null;
        if ($keyword === null || $keyword === '') {
            return [$keyName => $keyword, $resultName => collect(), 'message' => $emptyMessage];
        }

        $results = $query($keyword);

        return [
            $keyName => $keyword,
            $resultName => $results,
            'message' => $results->isEmpty() ? 'لا توجد نتائج بحث عن "'.$keyword.'".' : null,
        ];
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
