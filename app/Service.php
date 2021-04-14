<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = ['title', 'description', 'image', 'avg_time_per_client', 'counter', 'work_start_time', 'work_end_time', 'open_days', 'status','provider_id','admin_id'];
    protected $casts = [
        'open_days' => 'array'
    ];
    public function provider(){
        return $this->belongsTo(Provider::class);
    } 
    public function tickets(){
        return $this->hasMany(Ticket::class);
    } 
}
