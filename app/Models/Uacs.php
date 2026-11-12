<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'description'])]
class Uacs extends Model
{
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
