<?php

namespace App\Support;

use App\Models\Disposition;
use App\Models\Letter;
use App\Models\LetterApproval;
use App\Models\User;
use Illuminate\Support\Collection;

class TaskInbox
{
    public static function countFor(?User $user): int
    {
        if (! $user) {
            return 0;
        }

        return collect(static::for($user))
            ->sum(fn (Collection $items) => $items->count());
    }

    public static function for(User $user): array
    {
        return [
            'incoming' => static::incomingForDisposition($user),
            'dispositions' => static::assignedDispositions($user),
            'approvals' => static::pendingApprovals($user),
            'deadlines' => static::deadlineAlerts($user),
        ];
    }

    public static function incomingForDisposition(User $user): Collection
    {
        if (! ($user->isAdmin() || $user->isLeader() || $user->isPersonalSecretary())) {
            return collect();
        }

        return Letter::query()
            ->with('classification')
            ->where('type', 'Masuk')
            ->where('status', 'Baru')
            ->latest('received_date')
            ->latest()
            ->limit(20)
            ->get();
    }

    public static function assignedDispositions(User $user): Collection
    {
        return Disposition::query()
            ->with('letter.classification')
            ->where('recipient_name', $user->name)
            ->whereIn('status', ['Belum Dibaca', 'Diproses'])
            ->latest()
            ->limit(20)
            ->get();
    }

    public static function pendingApprovals(User $user): Collection
    {
        return LetterApproval::query()
            ->with('letter.classification')
            ->where('target_role', $user->role)
            ->where('status', 'Menunggu')
            ->orderBy('sort_order')
            ->latest()
            ->limit(20)
            ->get();
    }

    public static function deadlineAlerts(User $user): Collection
    {
        if (! ($user->isAdmin() || $user->isStaff())) {
            return collect();
        }

        return Letter::query()
            ->with('classification')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', today()->addDays(3))
            ->where('status', '!=', 'Selesai')
            ->orderBy('due_date')
            ->limit(20)
            ->get();
    }
}
