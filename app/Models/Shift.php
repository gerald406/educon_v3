<?php

namespace App\Models;

use App\Casts\TimeCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    /** @use HasFactory<\Database\Factories\ShiftFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'start_time',
        'end_time',
        'status',
    ];

    /**
     * Define los casts para las horas.
     */
    // [CORREGIDO] ShiftManager usa ->format('H:i'): con 'string' fallaba
    protected $casts = [
        'start_time' => TimeCast::class,
        'end_time' => TimeCast::class,
    ];
}
