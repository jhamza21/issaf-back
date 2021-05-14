<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = ['number', 'ticket_id'];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
}
