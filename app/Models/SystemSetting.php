<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SystemSetting extends Model
{
    protected $fillable = ['application_name', 'support_email'];

    public static function applicationName(): string
    {
        if (! Schema::hasTable('system_settings')) {
            return 'TUGMA';
        }

        return static::query()->whereKey(1)->value('application_name') ?? 'TUGMA';
    }
}
