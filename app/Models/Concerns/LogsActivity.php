<?php

namespace App\Models\Concerns;

use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Automatically logs created, updated, and deleted model events.
 *
 * Use on any Eloquent model to get automatic activity logging.
 * Override getActivityLogProperties() to customize what gets logged.
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(function (Model $model): void {
            ActivityLogger::log(
                action: 'created',
                subject: $model,
                description: class_basename($model).' created',
                properties: $model->getActivityLogProperties('created'),
            );
        });

        static::updated(function (Model $model): void {
            $changes = $model->getChanges();
            unset($changes['updated_at']);

            if (empty($changes)) {
                return;
            }

            ActivityLogger::log(
                action: 'updated',
                subject: $model,
                description: class_basename($model).' updated',
                properties: $model->getActivityLogProperties('updated'),
            );
        });

        static::deleted(function (Model $model): void {
            ActivityLogger::log(
                action: 'deleted',
                subject: $model,
                description: class_basename($model).' deleted',
                properties: $model->getActivityLogProperties('deleted'),
            );
        });
    }

    /**
     * Get properties to store in the activity log for a given event.
     *
     * @return array<string, mixed>
     */
    public function getActivityLogProperties(string $event): array
    {
        return match ($event) {
            'created' => ['attributes' => $this->attributesToArray()],
            'updated' => [
                'old' => array_intersect_key($this->getOriginal(), $this->getChanges()),
                'new' => $this->getChanges(),
            ],
            'deleted' => ['attributes' => $this->attributesToArray()],
            default => [],
        };
    }
}
