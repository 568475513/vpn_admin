<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 游戏管理
 * Class Article
 *
 * @package App\Http\Models
 * @mixin \Eloquent
 */
class Game extends Model
{
    use SoftDeletes;

    protected $table = 'game';
    protected $primaryKey = 'id';
    protected $dates = ['deleted_at'];

    // 筛选类型
    function scopeType($query, $type)
    {
        return $query->where('type', $type);
    }
}