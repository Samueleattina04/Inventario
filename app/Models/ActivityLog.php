<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = ['user_id', 'action', 'subject_type', 'subject_id', 'old_values', 'new_values', 'description'];

    protected $casts = ['old_values' => 'array', 'new_values' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'scan_save'    => 'Registrazione inventario',
            'admin_edit'   => 'Modifica registrazione',
            'admin_delete' => 'Eliminazione registrazione',
            default        => $this->action,
        };
    }
}
