<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Worker;

class WorkersController extends Controller
{
    public function index()
    {
        return response()->json(Worker::all());
    }
}
