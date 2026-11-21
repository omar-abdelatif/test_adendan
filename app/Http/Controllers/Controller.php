<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /** Validation for every uploaded image: real image content, common formats, 2 MB max. */
    public const IMAGE_RULE = 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048';
}
