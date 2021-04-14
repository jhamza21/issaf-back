<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Request extends Model
{
    protected $fillable = ['sender_id', 'receiver_id','service_id','date_time','status'];
    public function sender(){
        return $this->belongsTo(User::class);
    } 
    public function receiver(){
        return $this->belongsTo(User::class);
    } 
    public function service(){
        return $this->belongsTo(Service::class);
    } 
}
