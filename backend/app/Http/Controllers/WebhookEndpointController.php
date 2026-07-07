<?php

namespace App\Http\Controllers;

use App\Models\WebhookEndpoint;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebhookEndpointController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('admin');
        return response()->json(WebhookEndpoint::orderByDesc('id')->get());
    }

    public function store(Request $request)
    {
        $this->authorize('admin');

        $request->validate([
            'name' => 'required|string|max:100',
            'url' => 'required|url|max:500',
            'events' => 'required|array|min:1',
            'events.*' => 'in:' . implode(',', WebhookEndpoint::EVENTS),
        ]);

        $endpoint = WebhookEndpoint::create([
            'name' => $request->name,
            'url' => $request->url,
            'secret' => Str::random(32),
            'events' => $request->events,
            'is_active' => true,
        ]);

        return response()->json($endpoint, 201);
    }

    public function update(Request $request, $id)
    {
        $this->authorize('admin');

        $endpoint = WebhookEndpoint::findOrFail($id);
        $request->validate([
            'name' => 'nullable|string|max:100',
            'url' => 'nullable|url|max:500',
            'events' => 'nullable|array|min:1',
            'events.*' => 'in:' . implode(',', WebhookEndpoint::EVENTS),
            'is_active' => 'nullable|boolean',
        ]);

        $endpoint->update($request->only(['name', 'url', 'events', 'is_active']));

        return response()->json($endpoint);
    }

    public function destroy(Request $request, $id)
    {
        $this->authorize('admin');
        WebhookEndpoint::findOrFail($id)->delete();
        return response()->json(['message' => '已删除']);
    }

    public function regenerateSecret(Request $request, $id)
    {
        $this->authorize('admin');
        $endpoint = WebhookEndpoint::findOrFail($id);
        $endpoint->update(['secret' => Str::random(32)]);
        return response()->json($endpoint);
    }
}
