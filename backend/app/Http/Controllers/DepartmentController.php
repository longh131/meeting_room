<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::orderBy('sort_order')->get();
        return response()->json($departments);
    }

    public function store(Request $request)
    {
        $this->authorize('admin');

        $request->validate([
            'name' => 'required|string|max:50',
            'code' => 'required|string|max:20|unique:departments',
            'sort_order' => 'nullable|integer',
        ]);

        $department = Department::create([
            'name' => $request->name,
            'code' => $request->code,
            'parent_id' => $request->parent_id,
            'status' => $request->status ?? 1,
            'sort_order' => $request->sort_order ?? 0,
        ]);

        return response()->json($department, 201);
    }

    public function update(Request $request, $id)
    {
        $this->authorize('admin');

        $department = Department::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:50',
            'code' => 'required|string|max:20|unique:departments,code,' . $id,
            'sort_order' => 'nullable|integer',
        ]);

        $department->update([
            'name' => $request->name,
            'code' => $request->code,
            'parent_id' => $request->parent_id,
            'status' => $request->status ?? 1,
            'sort_order' => $request->sort_order ?? 0,
        ]);

        return response()->json($department);
    }

    public function destroy($id)
    {
        $this->authorize('admin');

        $department = Department::findOrFail($id);
        $department->delete();

        return response()->json(['message' => '删除成功']);
    }
}