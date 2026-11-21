<?php

namespace App\Modules\Members\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OcrData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The OCR engine sends national ID card data here (protected by X-Api-Key). */
class OcrController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'nid' => ['required', 'digits:14', 'unique:ocr_data,nid'],
            'birth_date' => ['required', 'string', 'max:50'],
            'mobile' => ['required', 'string', 'max:20'],
        ]);

        OcrData::create($data);

        return response()->json(['message' => 'OCR data stored successfully'], 201);
    }
}
