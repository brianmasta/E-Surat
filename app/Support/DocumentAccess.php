<?php

namespace App\Support;

use App\Models\Disposition;
use App\Models\Letter;
use App\Models\LetterAttachment;
use App\Models\User;

class DocumentAccess
{
    public static function canViewLetter(?User $user, Letter $letter): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isAdmin() || $user->isStaff() || $user->isLeader() || $user->isPersonalSecretary()) {
            return true;
        }

        if ($user->isDepartmentHead()) {
            return $letter->dispositions()
                ->where(function ($query) use ($user) {
                    $query
                        ->where('recipient_name', $user->name)
                        ->orWhere('sender_name', $user->name);
                })
                ->exists();
        }

        return false;
    }

    public static function canViewAttachment(?User $user, LetterAttachment $attachment): bool
    {
        $letter = $attachment->letter;

        return $letter instanceof Letter && self::canViewLetter($user, $letter);
    }

    public static function canViewDispositionScan(?User $user, Disposition $disposition): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isAdmin() || $user->isStaff() || $user->isLeader() || $user->isPersonalSecretary()) {
            return true;
        }

        if (! $user->isDepartmentHead()) {
            return false;
        }

        if ($disposition->recipient_name === $user->name || $disposition->sender_name === $user->name) {
            return true;
        }

        return Disposition::query()
            ->where(function ($query) use ($disposition) {
                $query
                    ->where('parent_id', $disposition->id)
                    ->orWhere('id', $disposition->parent_id);
            })
            ->where(function ($query) use ($user) {
                $query
                    ->where('recipient_name', $user->name)
                    ->orWhere('sender_name', $user->name);
            })
            ->exists();
    }
}
