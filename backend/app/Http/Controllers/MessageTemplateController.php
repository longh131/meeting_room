<?php

namespace App\Http\Controllers;

use App\Models\MessageTemplate;
use App\Services\TemplateService;
use Illuminate\Http\Request;

class MessageTemplateController extends Controller
{
    public function index(Request $request, TemplateService $templateService)
    {
        $this->authorize('admin');
        $tenantId = $request->user()->tenant_id;
        $templateService->ensureDefaults($tenantId);

        $templates = MessageTemplate::orderBy('type')->get()->map(function ($tpl) use ($templateService) {
            return array_merge($tpl->toArray(), [
                'type_label' => $templateService->getTypeLabels()[$tpl->type] ?? $tpl->type,
            ]);
        });

        return response()->json([
            'data' => $templates,
            'variables' => TemplateService::VARIABLES,
            'type_labels' => $templateService->getTypeLabels(),
        ]);
    }

    public function update(Request $request, $id, TemplateService $templateService)
    {
        $this->authorize('admin');

        $template = MessageTemplate::findOrFail($id);
        if ($template->tenant_id !== $request->user()->tenant_id) {
            return response()->json(['message' => '无权限'], 403);
        }

        $request->validate([
            'name' => 'nullable|string|max:100',
            'body' => 'required|string|max:2000',
            'is_active' => 'nullable|boolean',
        ]);

        $template->update($request->only(['name', 'body', 'is_active']));

        return response()->json($template);
    }

    public function preview(Request $request, TemplateService $templateService)
    {
        $this->authorize('admin');

        $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $sample = [
            'title' => '季度复盘会议',
            'room_name' => '301 会议室',
            'start_time' => '2026-07-08 14:00',
            'end_time' => '2026-07-08 15:00',
            'user_name' => '张三',
            'reason' => '未签到自动释放',
            'minutes' => '15',
        ];

        return response()->json([
            'preview' => $templateService->replaceVars($request->body, $sample),
        ]);
    }

    public function reset(Request $request, $id, TemplateService $templateService)
    {
        $this->authorize('admin');

        $template = MessageTemplate::findOrFail($id);
        if ($template->tenant_id !== $request->user()->tenant_id) {
            return response()->json(['message' => '无权限'], 403);
        }

        $defaults = $templateService->getDefaultBodies();
        if (!isset($defaults[$template->type])) {
            return response()->json(['message' => '无法重置此类型'], 400);
        }

        $template->update(['body' => $defaults[$template->type]]);

        return response()->json($template);
    }
}
