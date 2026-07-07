<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('department');

        if ($request->has('keyword')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->keyword . '%')
                  ->orWhere('email', 'like', '%' . $request->keyword . '%');
            });
        }

        if ($request->has('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        $users = $query->paginate(20);

        return UserResource::collection($users);
    }

    public function show($id)
    {
        $user = User::with('department')->findOrFail($id);
        return new UserResource($user);
    }

    public function store(StoreUserRequest $request)
    {
        $userData = $request->only(['name', 'email', 'department_id', 'position']);
        $userData['password'] = bcrypt($request->password);
        $userData['status'] = 1;

        $user = User::create($userData);

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function update(Request $request, $id)
    {
        $this->authorize('admin');

        $user = User::findOrFail($id);
        $tenantId = $request->user()->tenant_id;

        $request->validate([
            'name' => 'required|string|max:50',
            'email' => [
                'required', 'email',
                Rule::unique('users')->where(fn ($q) => $q->where('tenant_id', $tenantId))->ignore($id),
            ],
            'department_id' => 'nullable|integer|exists:departments,id',
            'position' => 'nullable|string|max:50',
            'is_manager' => 'boolean',
            'is_admin' => 'boolean',
            'status' => 'boolean',
        ]);

        $user->update($request->only(['name', 'email', 'department_id', 'position', 'is_manager', 'is_admin', 'status']));

        return new UserResource($user);
    }

    public function destroy($id)
    {
        $this->authorize('admin');

        $user = User::findOrFail($id);
        $user->delete();

        return response()->json(['message' => '删除成功']);
    }

    public function getDepartments()
    {
        $departments = Department::where('status', 1)->get();
        return response()->json($departments);
    }

    public function getAvailableAttendees(Request $request)
    {
        $excludeIds = $request->exclude_ids ?? [];
        $excludeIds[] = $request->user()->id;

        $users = User::with('department')
            ->where('status', 1)
            ->whereNotIn('id', $excludeIds)
            ->get();

        return UserResource::collection($users);
    }

    public function import(Request $request)
    {
        $this->authorize('admin');

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();

        if ($extension === 'csv') {
            $content = file_get_contents($file->getPathname());
            $lines = explode("\n", $content);
            array_shift($lines);

            $successCount = 0;
            $failCount = 0;
            $defaultPassword = Str::random(12);

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;

                $data = str_getcsv($line);
                if (count($data) < 2) continue;

                try {
                    $tenantId = $request->user()->tenant_id;

                    User::create([
                        'tenant_id' => $tenantId,
                        'name' => $data[0] ?? '',
                        'email' => $data[1] ?? '',
                        'password' => bcrypt($defaultPassword),
                        'department_id' => isset($data[2]) && $data[2] !== '' ? (int)$data[2] : null,
                        'position' => $data[3] ?? '',
                        'is_manager' => isset($data[4]) && $data[4] === '1',
                        'is_admin' => isset($data[5]) && $data[5] === '1',
                        'status' => 1,
                    ]);
                    $successCount++;
                } catch (\Exception $e) {
                    $failCount++;
                }
            }

            return response()->json([
                'success_count' => $successCount,
                'fail_count' => $failCount,
                'message' => $successCount > 0 ? '导入成功，初始密码为随机生成，请通过管理员重置' : '导入完成',
            ]);
        }

        return response()->json(['message' => '不支持的文件格式'], 400);
    }
}
