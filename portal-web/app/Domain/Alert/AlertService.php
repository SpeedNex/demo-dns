<?php

declare(strict_types=1);

namespace App\Domain\Alert;

use App\Models\Alert;
use Illuminate\Support\Str;

final class AlertService
{
    public static function create(
        string $level,
        string $title,
        string $message,
        string $code = 'generic',
        string $source = 'system',
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $payload = [],
    ): Alert {
        return Alert::create([
            'code' => $code,
            'level' => $level,
            'source' => $source,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'title' => $title,
            'message' => $message,
            'payload' => $payload ?: null,
            'status' => 'open',
        ]);
    }
}