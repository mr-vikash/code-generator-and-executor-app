<?php
// app/Models/CodeHistory.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CodeHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'prompt',
        'code',
        'type',
        'description',
        'libraries',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}