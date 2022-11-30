<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 用户分组控制.
 */
class NodeLabel extends Model
{
    public $timestamps = false;
    protected $table = 'ss_node_label';
    protected $guarded = [];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function nodes()
    {
        return $this->belongsToMany(Node::class);
    }
}
