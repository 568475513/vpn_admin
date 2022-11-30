<?php

namespace App\Http\Controllers\Api\WebApi;

use App\Models\Node;
use Illuminate\Http\JsonResponse;

use App\Models\User;
use App\Http\Models\SsNode;
use App\Http\Models\UserLabel;

class SSRController extends BaseController
{
    // 获取节点信息
    public function getNodeInfo(Node $node): JsonResponse
    {
        return $this->returnData('获取节点信息成功', 'success', 200, [
            'id'           => $node->id,
            'method'       => $node->single_method,
            'protocol'     => $node->single_protocol,
            'obfs'         => $node->single_obfs,
            'obfs_param'   => $node->single_obfs_param ?? '',
            'is_udp'       => 1,
            'speed_limit'  => $node->getOriginal('speed_limit') * 120000,
            'client_limit' => $node->client_limit,
            'single'       => $node->single,
            'port'         => (string) $node->port,
            'passwd'       => $node->single_passwd ?? '',
            'push_port'    => 0,
            'secret'       => 'wyjsq',
            'redirect_url' => null,
        ]);
    }

    // 获取节点可用的用户列表
    public function getUserList(Node $node): JsonResponse
    {
        
        $ssnode = SsNode::where('id',$node->id)->first();
        $labels = $ssnode->label->pluck('label_id');
        $user_ids = UserLabel::whereIn('label_id',$labels)->pluck('user_id');

        $users = User::activeUser()
            ->where('level', '>=', $this->attributes['level']??0)
            ->whereIn('id', $user_ids)
            ->get();
       
        foreach ($users as $user) {
            $data[] = [
                'uid'         => $user->id,
                'port'        => $user->port,
                'passwd'      => $user->passwd,
                'method'      => $user->method,
                'protocol'    => $user->protocol,
                'obfs'        => $user->obfs,
                'obfs_param'  => $node->obfs_param,
                'speed_limit' => $user->getOriginal('speed_limit_per_user'),
                'enable'      => $user->enable,
            ];
        }
        return $this->returnData('获取用户列表成功', 'success', 200, $data ?? [], ['updateTime' => time()]);
    }
}
