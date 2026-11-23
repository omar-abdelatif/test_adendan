<?php

namespace Tests\Unit\Members;

use App\Models\Subscribers;
use App\Modules\Members\Application\Queries\ProfileCompleteness;
use Tests\TestCase;

class ProfileCompletenessTest extends TestCase
{
    private function member(array $attributes): Subscribers
    {
        return (new Subscribers)->forceFill($attributes);
    }

    public function test_a_full_file_is_complete(): void
    {
        $member = $this->member([
            'img' => 'a.jpg', 'id_img' => 'b.jpg', 'ssn' => '29001011234567', 'mobile_no' => '1012345678', 'birthdate' => '1990-01-01',
            'address' => 'أسوان', 'martial_status' => 'متزوج', 'job' => 'مهندس', 'job_destination' => 'بنك مصر', 'educational_qualification' => 'بكالوريوس',
        ]);

        $this->assertSame([], (new ProfileCompleteness)->missing($member));
        $this->assertSame(100, (new ProfileCompleteness)->percent($member));
    }

    public function test_blank_and_zero_values_count_as_missing(): void
    {
        // The old import wrote 0 for an unknown national ID or mobile.
        $member = $this->member(['ssn' => 0, 'mobile_no' => '0', 'address' => '  ', 'job' => 'مدرس']);

        $missing = (new ProfileCompleteness)->missing($member);

        $this->assertContains('الرقم القومي', $missing);
        $this->assertContains('رقم المحمول', $missing);
        $this->assertContains('العنوان', $missing);
        $this->assertNotContains('الوظيفة', $missing);
        $this->assertSame(10, (new ProfileCompleteness)->percent($member));
    }
}
