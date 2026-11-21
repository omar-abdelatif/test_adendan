<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\BoardMembers;

class BoardMembersController extends Controller
{
    public function index()
    {
        $boards = BoardMembers::all();

        return view('frontend.pages.board_members', compact('boards'));
    }
}
