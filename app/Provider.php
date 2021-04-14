<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Provider extends Model
{
    protected $fillable = ['title', 'description', 'address','mobile','email','url', 'image','user_id'];
    public function user(){
        return $this->belongsTo(User::class);
    }
    public function services(){
        return $this->hasMany(Service::class);
    } 
}