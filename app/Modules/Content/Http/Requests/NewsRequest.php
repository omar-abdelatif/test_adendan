<?php

namespace App\Modules\Content\Http\Requests;

use App\Http\Controllers\Controller;
use App\Modules\Content\Domain\NewsCategory;
use App\Modules\Content\Domain\YouTubeLink;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NewsRequest extends FormRequest
{
    public function rules(): array
    {
        $updating = $this->routeIs('news.update');

        return [
            'id' => [$updating ? 'required' : 'prohibited', 'integer', 'exists:news,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:65000'],
            'category' => ['required', Rule::enum(NewsCategory::class)],
            'img' => Controller::IMAGE_RULE,
            'thumbnail' => ['nullable', 'array', 'max:30'],
            'thumbnail.*' => Controller::IMAGE_RULE,
            'url' => [$updating ? 'prohibited' : 'nullable', 'array', 'max:10'],
            'url.*' => self::videoRule(),
        ];
    }

    /** @return array<int, mixed> */
    public static function videoRule(): array
    {
        return ['nullable', 'url:https,http', function (string $attribute, mixed $value, Closure $fail) {
            if ($value && ! YouTubeLink::isValid($value)) {
                $fail('رابط الفيديو يجب أن يكون رابط يوتيوب صحيح.');
            }
        }];
    }

    public function attributes(): array
    {
        return ['title' => 'العنوان', 'description' => 'المحتوى', 'category' => 'التصنيف', 'img' => 'الصورة', 'url.*' => 'رابط الفيديو'];
    }

    /** @return array{title: string, description: string, category: string} */
    public function fields(): array
    {
        return $this->only(['title', 'description', 'category']);
    }
}
