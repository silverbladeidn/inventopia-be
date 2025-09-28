<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailSettings extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_email',
        'cc_emails',
        'request_notifications',
        'low_stock_notifications',
        'low_stock_threshold'
    ];

    protected $casts = [
        'cc_emails' => 'array',
        'request_notifications' => 'boolean',
        'low_stock_notifications' => 'boolean',
    ];
}
