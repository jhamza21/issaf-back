<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $fillable = ['number', 'status', 'date', 'time', 'service_id', 'user_id', 'duration', 'name'];
    protected $casts = [
        'notification' => 'array',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }
}
