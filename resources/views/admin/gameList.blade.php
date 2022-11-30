@extends('admin.layouts')
@section('css')
    <link href="/assets/global/plugins/datatables/datatables.min.css" rel="stylesheet" type="text/css" />
    <link href="/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.css" rel="stylesheet" type="text/css" />
@endsection
@section('content')
    <!-- BEGIN CONTENT BODY -->
    <div class="page-content" style="padding-top:0;">
        <!-- BEGIN PAGE BASE CONTENT -->
        <div class="row">
            <div class="col-md-12">
                <!-- BEGIN EXAMPLE TABLE PORTLET-->
                <div class="portlet light bordered">
                    <div class="portlet-title">
                        <div class="caption font-dark">
                            <span class="caption-subject bold uppercase"> 游戏列表 </span>
                        </div>
                        <div class="actions">
                            <div class="btn-group">
                                <button class="btn sbold blue" onclick="addGame()"> 添加游戏 </button>
                            </div>
                        </div>
                    </div>
                    <div class="portlet-body">
                        <div class="row" style="padding-bottom:5px;">
                            <div class="col-md-3 col-sm-4 col-xs-12">
                                <input type="text" class="col-md-4 form-control" name="title" value="{{Request::get('title')}}" id="title" placeholder="游戏名称" onkeydown="if(event.keyCode==13){do_search();}">
                            </div>
                           <div class="col-md-3 col-sm-4 col-xs-12">
                                <input type="text" class="col-md-4 form-control" name="author" value="{{Request::get('author')}}" id="author" placeholder="游戏区服" onkeydown="if(event.keyCode==13){do_search();}">
                            </div>
                            <div class="col-md-3 col-sm-4 col-xs-12">
                                <select class="form-control" name="platform" id="platform" onChange="do_search()">
                                    <option value="" @if(Request::get('platform') == '') selected @endif>平台</option>
                                    <option value="pc" @if(Request::get('platform') == 'pc') selected @endif>PC</option>
                                    <option value="android" @if(Request::get('platform') == 'android') selected @endif>Android</option>
                                    <option value="ios" @if(Request::get('platform') == 'ios') selected @endif>Ios</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-sm-4 col-xs-12">
                                <button type="button" class="btn blue" onclick="do_search();">查询</button>
                                <button type="button" class="btn grey" onclick="do_reset();">重置</button>
                            </div>
                        </div>
                        <div class="table-scrollable table-scrollable-borderless">
                            <table class="table table-hover table-light">
                                <thead>
                                <tr>
                                    <th> # </th>
                                    <th> 游戏名称 </th>
                                    <th> 游戏区服 </th>
                                    <th> 平台 </th>
                                    <th> 操作 </th>
                                </tr>
                                </thead>
                                <tbody>
                                @if($list->isEmpty())
                                    <tr>
                                        <td colspan="6" style="text-align: center;">暂无数据</td>
                                    </tr>
                                @else
                                    @foreach($list as $vo)
                                        <tr class="odd gradeX">
                                            <td> {{$vo->id}} </td>
                                            <td> <a href="{{url('article?id=' . $vo->id)}}" target="_blank"> {{str_limit($vo->title, 80)}} </a> </td>
                                            <td> {{$vo->author}} </td>
                                            <td> {{$vo->platform}} </td>

                                            <td>
                                                <button type="button" class="btn btn-sm blue btn-outline" onclick="editGame('{{$vo->id}}')"> 编辑 </button>
                                                <button type="button" class="btn btn-sm red btn-outline" onclick="delGame('{{$vo->id}}')"> 删除 </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                                </tbody>
                            </table>
                        </div>
                        <div class="row">
                            <div class="col-md-4 col-sm-4">
                                <div class="dataTables_info" role="status" aria-live="polite">共 {{$list->total()}} 个游戏</div>
                            </div>
                            <div class="col-md-8 col-sm-8">
                                <div class="dataTables_paginate paging_bootstrap_full_number pull-right">
                                    {{ $list->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- END EXAMPLE TABLE PORTLET-->
            </div>
        </div>
        <!-- END PAGE BASE CONTENT -->
    </div>
    <!-- END CONTENT BODY -->
@endsection
@section('script')
    <script type="text/javascript">
        // 添加文章
        function addGame() {
            window.location.href = '{{url('admin/addGame')}}';
        }

        // 编辑文章
        function editGame(id) {
            window.location.href = '{{url('admin/editGame?id=')}}' + id + '&page=' + '{{Request::get('page', 1)}}';
        }

        // 删除文章
        function delGame(id) {
            layer.confirm('确定删除游戏？', {icon: 2, title:'警告'}, function(index) {
                $.post("{{url('admin/delGame')}}", {id:id, _token:'{{csrf_token()}}'}, function(ret) {
                    layer.msg(ret.message, {time:1000}, function() {
                        if (ret.status == 'success') {
                            window.location.reload();
                        }
                    });
                });

                layer.close(index);
            });
        }
        
         // 搜索
        function do_search() {
            var title = $("#title").val();
             var author = $("#author").val();
             var platform = $("#platform option:checked").val();

            window.location.href = '{{url('admin/gameList')}}' + '?title=' + title + '&author=' + author + '&platform=' + platform;
        }

        // 重置
        function do_reset() {
            window.location.href = '{{url('admin/gameList')}}';
        }
    </script>
@endsection