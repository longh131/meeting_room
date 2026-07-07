<?php

namespace App\Http\Controllers;

use App\Models\CreditLog;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    public function myLogs(Request $request)
    {
        $logs = CreditLog::where('user_id', $request->user()->id)
            ->with('reservation:id,title')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'credit_score' => $request->user()->credit_score,
            'logs' => $logs,
        ]);
    }
}
