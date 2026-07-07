<?php

namespace App\Http\Controllers;

use App\Models\DeviceTag;
use Illuminate\Http\Request;

class DeviceTagController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $tags = DeviceTag::where('status', 1)
            ->where(function ($q) use ($tenantId) {
                $q->whereNull('tenant_id');
                if ($tenantId) {
                    $q->orWhere('tenant_id', $tenantId);
                }
            })
            ->orderBy('sort_order')
            ->paginate($request->per_page ?? 10);

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
            'tenant_id' => $request->user()->tenant_id,
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

        if ($tag->isSystemTag()) {
            return response()->json(['message' => '系统预设标签不可修改'], 403);
        }

        if ($tag->tenant_id !== $request->user()->tenant_id) {
            return response()->json(['message' => '无权限修改'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:30',
            'icon' => 'nullable|string|max:50',
            'status' => 'boolean',
        ]);

        $tag->update($request->only(['name', 'icon', 'status']));

        return response()->json($tag);
    }

    public function destroy(Request $request, $id)
    {
        $this->authorize('admin');

        $tag = DeviceTag::findOrFail($id);

        if ($tag->isSystemTag()) {
            return response()->json(['message' => '系统预设标签不可删除'], 403);
        }

        if ($tag->tenant_id !== $request->user()->tenant_id) {
            return response()->json(['message' => '无权限删除'], 403);
        }

        $tag->delete();

        return response()->json(['message' => '删除成功']);
    }
}
