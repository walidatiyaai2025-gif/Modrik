<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class SystemUpdateHistory extends Model
{
    use HasUlids;

    protected $table = 'system_update_history';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['safe_details' => 'array'];
    }
}
