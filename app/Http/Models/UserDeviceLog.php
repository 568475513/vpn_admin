<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 用户登录日志
 * Class UserLoginLog
 *
 * @package App\Http\Models
 * @mixin \Eloquent
 */
class UserDeviceLog extends Model
{
    protected $table = 'user_login_device_log';
    protected $primaryKey = 'id';

}
