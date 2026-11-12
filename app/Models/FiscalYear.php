<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'year',
    'start_date',
    'end_date',
    'is_active',
])]
class FiscalYear extends Model
{
    public function targets(): HasMany
    {
        return $this->hasMany(Target::class);
    }

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }
    
    public function activate(): void
    {
        DB::transaction(function () {
            // Lock existing fiscal year records during activation.
            static::query()
                ->lockForUpdate()
                ->get();

            // Deactivate all other fiscal years.
            static::query()
                ->where($this->getKeyName(), '!=', $this->getKey())
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                ]);

            // Activate the selected fiscal year.
            $this->update([
                'is_active' => true,
            ]);
        });
    }
}
