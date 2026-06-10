<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Letter;
use App\Support\MobileApiFormatter;
use App\Support\TaskInbox;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tasks = TaskInbox::for($user);

        return response()->json([
            'summary' => [
                'letters_total' => Letter::count(),
                'incoming_total' => Letter::where('type', 'Masuk')->count(),
                'outgoing_total' => Letter::where('type', 'Keluar')->count(),
                'new_incoming_total' => Letter::where('type', 'Masuk')->where('status', 'Baru')->count(),
                'task_total' => TaskInbox::countFor($user),
            ],
            'tasks' => [
                'incoming' => $tasks['incoming']->map(fn ($letter) => MobileApiFormatter::letter($letter))->values(),
                'dispositions' => $tasks['dispositions']->map(fn ($disposition) => MobileApiFormatter::disposition($disposition))->values(),
                'approvals' => $tasks['approvals']->map(fn ($approval) => [
                    'id' => $approval->id,
                    'step' => $approval->step,
                    'status' => $approval->status,
                    'target_role' => $approval->target_role,
                    'letter' => $approval->letter ? MobileApiFormatter::letter($approval->letter) : null,
                ])->values(),
                'deadlines' => $tasks['deadlines']->map(fn ($letter) => MobileApiFormatter::letter($letter))->values(),
            ],
        ]);
    }
}
