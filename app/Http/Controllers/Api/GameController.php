<?php

namespace App\Http\Controllers\Api;

use App\Components\Helpers;
use App\Http\Controllers\Controller;
use App\Http\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Response;

class GameController extends Controller
{
    protected static $systemConfig;

    function __construct()
    {
        self::$systemConfig = Helpers::systemConfig();
    }

    /**
     *  游戏列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        
        $type = $request->get('type','pc');
        $all_list = Game::query()->where('platform',$type)->get();
        $list = [];
        foreach ($all_list as $v )
        {
            $list[] = [
                'id'=>$v['id'],
                'title'=>$v['title'],
                'author'=>$v['author'],
                'logo'=>URL::asset($v['logo'])
            ];
        }
        return Response::json(['status' => 'success','data' => $list,'message' => '']);
    }

    /**
     *  游戏详情
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function info(Request $request)
    {
        $id = trim($request->get('gameid'));
        $row = Game::find($id);
        if( !$row){
            return Response::json(['status' => 'fail','data' => [],'message' => '游戏不存在']);
        }
        $info = [
            'id'=>$row['id'],
            'title'=>$row['title'],
            'author'=>$row['author'],
            'summary'=>$row['summary'],
            'logo'=>URL::asset($row['logo']),
            'content'=>$row['content']
        ];
        return Response::json(['status' => 'success','data' => $info,'message' => '']);
    }
}