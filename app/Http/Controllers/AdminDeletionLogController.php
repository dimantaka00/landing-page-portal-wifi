<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDeletionLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'reason' => ['nullable', 'in:automatic,manual'],
        ]);

        return view('admin.deletion-logs', [
            'logs' => DB::table('announcement_deletion_logs')
                ->when($filters['q'] ?? null, fn ($query, $term) => $query->where('title', 'like', '%'.$term.'%'))
                ->when($filters['reason'] ?? null, fn ($query, $reason) => $query->where('reason', $reason))
                ->orderByDesc('deleted_at')->orderByDesc('id')->paginate(20)->withQueryString(),
        ]);
    }
}
