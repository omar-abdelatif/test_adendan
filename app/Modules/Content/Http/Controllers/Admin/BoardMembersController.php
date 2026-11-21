<?php

namespace App\Modules\Content\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BoardMembers;
use App\Modules\Shared\Http\Concerns\RespondsWithAlerts;
use App\Modules\Shared\Infrastructure\ImageStorage;
use Illuminate\Http\Request;

class BoardMembersController extends Controller
{
    use RespondsWithAlerts;

    private const FOLDER = 'border-photos';

    public function __construct(private readonly ImageStorage $images)
    {
        $this->middleware('permission:مجلس الادارة');
    }

    public function index()
    {
        return view('pages.board_members', ['members' => BoardMembers::all()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, creating: true);
        BoardMembers::create([...$data, 'img' => $this->images->store($request->file('img'), self::FOLDER)]);

        return $this->success('تم الإضافة بنجاح');
    }

    public function update(Request $request)
    {
        $member = BoardMembers::findOrFail($request->integer('id'));
        $data = $this->validated($request, creating: false);
        if ($request->hasFile('img')) {
            $data['img'] = $this->images->replace($member->img, $request->file('img'), self::FOLDER);
        }
        $member->update($data);

        return $this->success('تم التعديل بنجاح');
    }

    public function destroy(BoardMembers $member)
    {
        $member->delete();
        $this->images->delete($member->img, self::FOLDER);

        return $this->success('تم الحذف بنجاح');
    }

    /** @return array{name: string, position: string, phone_number: string} */
    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'regex:/^0?1[0-9]{9}$/'],
            // The photo column is required, so a new member needs one.
            'img' => [$creating ? 'required' : 'nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ], [], ['name' => 'الاسم', 'position' => 'المنصب', 'phone_number' => 'رقم الهاتف', 'img' => 'الصورة']);
    }
}
