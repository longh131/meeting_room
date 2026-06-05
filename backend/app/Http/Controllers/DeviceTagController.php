<?php

namespace App\Http\Controllers;

use App\Models\DeviceTag;
use Illuminate\Http\Request;

class DeviceTagController extends Controller
{
    public function index(Request $request)
    {
        $tags = DeviceTag::where('status', 1)->orderBy('sort_order')->paginate($request->per_page ?? 10);
        return response()->json([
            'data' => $tags->items(),
            'total' => $tags->total(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('admin');

        $request->validate([
            'name' => 'required|string|max:30',
            'icon' => 'nullable|string|max:50',
        ]);

        $tag = DeviceTag::create([
            'name' => $request->name,
            'icon' => $request->icon,
            'status' => 1,
        ]);

        return response()->json($tag, 201);
    }

    public function update(Request $request, $id)
    {
        $this->authorize('admin');

        $tag = DeviceTag::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:30',
            'icon' => 'nullable|string|max:50',
            'status' => 'boolean',
        ]);

        $tag->update($request->only(['name', 'icon', 'status']));

        return response()->json($tag);
    }

    public function destroy($id)
    {
        $this->authorize('admin');

        $tag = DeviceTag::findOrFail($id);
        $tag->delete();

        return response()->json(['message' => '删除成功']);
    }
}