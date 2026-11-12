<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class ActivityLogger
{
    public function modelEvent(Model $model, string $action): void
    {
        $attributes = $action === 'updated' ? $model->getChanges() : $model->getAttributes();
        $attributes = Arr::except($attributes, ['created_at', 'updated_at', 'password', 'remember_token']);
        $attributes = array_filter($attributes, fn (string $key): bool => ! preg_match('/password|token|secret/i', $key), ARRAY_FILTER_USE_KEY);
        $old = $action === 'created' ? [] : Arr::only($model->getRawOriginal(), array_keys($attributes));
        $new = $action === 'deleted' ? [] : $attributes;

        if ($action === 'updated' && $model->wasChanged('password')) {
            $old['password'] = '[redacted]';
            $new['password'] = '[changed]';
        }

        if ($action === 'updated' && $old === [] && $new === []) {
            return;
        }

        $this->record($model, $action, $old, $new);
    }

    public function record(Model $model, string $action, array $old, array $new): void
    {
        if ($old === $new) {
            return;
        }

        $actor = auth()->user();

        ActivityLog::query()->create([
            'user_id' => $actor?->getAuthIdentifier(),
            'actor_name' => $actor?->name,
            'action' => $action,
            'module' => class_basename($model),
            'subject_type' => $model::class,
            'subject_id' => (string) $model->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'created_at' => now(),
        ]);
    }
}
