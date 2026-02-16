<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLogger
{
    public static function log(
        string $action,
        ?Model $subject = null,
        ?string $description = null,
        array $properties = [],
        ?int $userId = null,
    ): ActivityLog {
        $resolvedUserId = $userId ?? Auth::id();

        $data = [
            'user_id' => $resolvedUserId,
            'action' => $action,
            'description' => $description,
            'properties' => $properties,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ];

        if ($subject !== null) {
            $data['subject_type'] = $subject->getMorphClass();
            $data['subject_id'] = $subject->getKey();
        }

        return ActivityLog::create($data);
    }
}
