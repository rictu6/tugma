<?php

namespace App\Observers;

use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

class ActivityObserver
{
    public function created(Model $model): void
    {
        app(ActivityLogger::class)->modelEvent($model, 'created');
    }

    public function updated(Model $model): void
    {
        app(ActivityLogger::class)->modelEvent($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        app(ActivityLogger::class)->modelEvent($model, 'deleted');
    }
}
