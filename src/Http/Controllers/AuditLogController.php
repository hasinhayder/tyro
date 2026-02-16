<?php

namespace HasinHayder\Tyro\Http\Controllers;

use HasinHayder\Tyro\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends TyroController
{
    public function index(Request $request)
    {
        $query = AuditLog::query()->latest();

        if ($request->has('event')) {
            $query->where('event', 'like', "%{$request->event}%");
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $logs = $query->paginate($request->get('per_page', 20));

        return response()->json($logs);
    }
}
