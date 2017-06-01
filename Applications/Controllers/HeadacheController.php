<?php
class HeadacheController {
    //排重接口 兼容 ios和安卓
    public function tracking() {
        $data = $_REQUEST;
        if(!empty($data['source']) && !empty($data['system']) && !empty($data['imei']) || !empty($data['idfa'])) {
            //查看did_log表中是否存在这条记录 如果存在则表示已经激活过
            $did = !empty($data['imei']) ? $data['imei'] : $data['idfa'];
            //删除这个来源下已经存在库里并且还没有激活过的这条数据 报存最新的这条防止回调地址请求混乱
            if(!empty($data['imei'])) {
                if(userBehaviorVerificationController::queryUserDeviceInformation(['imei' => $data['imei']])) self::info('已经激活过了', 1);
                M()->exec("DELETE FROM ngw_tracking WHERE imei = '{$data['imei']}' AND status=1 AND source = '{$data['source']}'");
            } else {
                if(userBehaviorVerificationController::queryUserDeviceInformation(['idfa' => $data['idfa']])) self::info('已经激活过了', 1);
                M()->exec("DELETE FROM ngw_tracking WHERE idfa = '{$data['idfa']}' AND status=1 AND source = '{$data['source']}'");
            }
            M('tracking')->add($data);
            self::info('ok', 0);
        }
        self::info('缺少参数');
    }
    public static function info($msg,$status = 0) {
        info(['success' => $status, 'msg' => $msg]);
    }
    //ios 点击上报接口
    public function click() {
        $data = $_REQUEST;
        isset($data['ip'], $data['idfa'], $data['callback_url'], $data['source']) OR self::info('缺少参数');
        $where = "idfa = '{$data['idfa']}' and source = '{$data['source']}'";
        M('tracking')->where($where)->select() ? M('tracking')->where($where)->save(['callback_url' => urldecode($data['callback_url'])]) : self::info('还未通过效验');
        self::info('ok', 1);
    }
    //接口关闭
    public function close($data) {
        self::info('产品已经下线');
    }
    // ios 安卓注册 回调
    public function registerActivation($data) {
        $where = !empty($data['imei']) ? "imei = '{$data['imei']}' AND status = 1" : "idfa = '{$data['idfa']}' AND status = 1";
        //检查库里是否有这条记录 并且获取到这条记录的来源 实现扣量~~
        if(!$self = M('tracking')->where($where)->select('single')) return;
        //暂存到库里 状态改为3
        M('tracking')->where($where)->save([
            'status'      => 3,
            'type'        => $data['type'],
            'uid'         => $data['uid'],
            'report_date' => date('Y-m-d')
        ]);
        //查询这个来源下还没回调的数据 并且这些数据其他来源也没有回调过
        $callback = M()->query("SELECT idfa , imei , status , callback_url,source,createdAt FROM ngw_tracking WHERE(idfa NOT IN( SELECT idfa FROM ngw_tracking WHERE status = 2 AND idfa IS NOT NULL) OR imei NOT IN(SELECT imei FROM ngw_tracking WHERE status = 2 AND imei IS NOT NULL)) AND status = 3 AND source = '{$self['source']}' and DATE_ADD(createdAt, INTERVAL 1 day) >= now() order by createdAt desc", 'all');
        //获取回调率以及回调基数
        list($base, $percentage) = $this->setBase($self['system']);
        if(count($callback) >= $base) {
            $key = array_rand($callback, $percentage);
            foreach(is_array($key) ? $key : [$key] as $v) {
                $this->callback($callback[$v]['idfa'], $callback[$v]['imei'], $callback[$v]['callback_url'], $data['type']);
                unset($callback[$v]);
            }
            if(!empty($callback)) {
                $did = array_filter(array_merge(array_column($callback, 'idfa'), array_column($callback, 'imei')));
                if($did) M('tracking')->where("imei IN(".(connectionArray($did)).") OR idfa IN(".(connectionArray($did)).")")->save(['status' => 4]);
            }
        }
    }
    public function callback($idfa, $imei, $callbackUrl, $type) {
        M('tracking')->where("(imei = '{$imei}' OR idfa= '{$idfa}')")->save([
            'status'    => 2,
            'response'  => get_curl(str_replace('amp;','',urldecode($callbackUrl))),
            'type'      => $type
        ]);
    }
    //根据手机系统来设置回调率以及回调基数
    public function setBase($system) {
        static $file = [
            1 => [10, 2],
            2 => [1, 1]
        ];
        return isset($file[$system]) ? $file[$system] : [10000, 1];
    }
    //下单用户进行真实回调
    public function active($data) {
        if(!empty($data)) {
            $uid = connectionArray($data);
            $sql = "SELECT a.uid , a.idfa , a.imei , a.callback_url , a.type FROM(SELECT * FROM ngw_tracking WHERE status != 2) a JOIN( SELECT uid , idfa , imei FROM ngw_did_log WHERE uid IN({$uid}) GROUP BY imei , idfa) b ON a.idfa = b.idfa OR a.imei = b.imei";
            $data = M()->query($sql, 'all');
            //发起回调
            foreach($data as $v) {
                $this->callback($v['idfa'], $v['imei'],$v['callback_url'], 2);
            }
        }
    }

    public function query() {
        $edate=date("Y-m-d");
        $sdate=date("Y-m-d",strtotime($edate." -30 day"));
        if(isset($_REQUEST["sdate"])&&!empty($_REQUEST["sdate"])){
            $sdate = $_REQUEST["sdate"];
        }
        if(isset($_REQUEST["edate"])&&!empty($_REQUEST["edate"])){
            $edate = $_REQUEST["edate"];
        }
        $sql = "SELECT status , source , system , count(0) num FROM ngw_tracking WHERE status != 3 and createdAt between '".$sdate."' and '".$edate."'"." GROUP BY status , source , system ";
        $data = M()->query($sql,'all');
//        D($data);
        if($data){
            //字段映射
            $status = [
                1 => '未下载安装',
                2 => '已成功回调',
                4 => '已被扣量',
                5 => '接口关闭时产生的成功激活',
                6 => '回调失败'
            ];
            //1 是安卓 2是IOS
            $system = [ 1 => '安卓', 2 => 'IOS' ];
            foreach($data as &$v) {
                if (isset($system[$v['system']]))
                    $v['system'] = $system[$v['system']];
                if (isset($status[$v['status']]))
                    $v['status'] = $status[$v['status']];
            }
            //source-system->status->num的二维数组
            $exportdata=[];
            foreach ($data as &$v){
                $exportdata[$v['source']."-".$v['system']][$v['status']]=$v['num'];
            }
            // D($exportdata);
            //对数组按键排序输出，不然呈现时顺序会变
            ksort($exportdata);
            info("列出成功",1,$exportdata);
         // info("列出成功",'1',$data);
        }
      info("暂无数据",'-2');
    }
    public function  task_log(){
        $edate=date("Y-m-d");
        $sdate=date("Y-m-d",strtotime($edate." -7 day"));
        $type='2';
        if(isset($_REQUEST["sdate"])&&!empty($_REQUEST["sdate"])){
            $sdate = $_REQUEST["sdate"];
        }
        if(isset($_REQUEST["edate"])&&!empty($_REQUEST["edate"])){
            $edate = $_REQUEST["edate"];
        }
        if(isset($_REQUEST["type"])&&$_REQUEST["type"]!=null){
            $type = $_REQUEST["type"];
        }
        $sql="select report_date,task_str from ngw_total_daily_report where report_date BETWEEN '".$sdate."' and '".$edate."'  and type='".$type."' ORDER BY report_date ASC";
        $res=M()->query($sql,"all");
        foreach ($res as $k => $v){
            $res[$k]['task']=self::task_str_explain($v['task_str']);
            array_pop($res[$k]['task']);
            unset($res[$k]['task_str']);

            foreach ($res[$k]['task']  as $i =>$j){
                $arr_num=explode('-',$j);
                $res[$k]['task'][$i]=$arr_num[1];
            }
        }
//        D($res);
        $export_res=[];
        $export_res['date_line']=[];
        $export_res['series']=[];
        $export_res['meg']=['完成商品分享','绑定淘宝账号','完成一次好友邀请','完成一次下单','获得一个返利红包','成功邀请一名好友','好友累计2次下单','好友累计2次收货'];
        foreach ($res as $k =>$v){
            array_push($export_res['date_line'],$v['report_date']);
            foreach ($v['task'] as $i =>$j){
                if(!isset($export_res['series'][$export_res['meg'][$i]])){
                    $export_res['series'][$export_res['meg'][$i]]=[];
                }
                array_push($export_res['series'][$export_res['meg'][$i]],$j);
            }

        }

        info("列出成功",1,$export_res);


    }
    //分享次数，点击，授权，活跃用户
    public function user_active(){
        $edate=date("Y-m-d");
        $sdate=date("Y-m-d",strtotime($edate." -7 day"));
        $type='2';
        if(isset($_REQUEST["sdate"])&&!empty($_REQUEST["sdate"])){
            $sdate = $_REQUEST["sdate"];
        }
        if(isset($_REQUEST["edate"])&&!empty($_REQUEST["edate"])){
            $edate = $_REQUEST["edate"];
        }
        if(isset($_REQUEST["type"])&&$_REQUEST["type"]!=null){
            $type = $_REQUEST["type"];
        }
        $type_con='';
        if($type!='2'){
            $type_con="AND type='{$type}'";
        }
        //获取时间数组
        $seconds = strtotime($sdate);
        $seconde = strtotime($edate);
        $date_bet=($seconde - $seconds) / 86400;
//        echo $date_bet;
        $this_date=$sdate;
        $date_line=[$sdate];
         for($i=0;$i<$date_bet;$i++){
             $this_date=date("Y-m-d",strtotime($this_date." +1 day"));
             array_push($date_line,$this_date);
         }

        $res_e=[];
        $res_e['date_line']=$date_line;
        $res_e['merge']=['分享次数','分享次数-去重','点击总数','授权淘宝次数','活跃用户'];
        $res_e['series']['share']=[];
        $res_e['series']['share_q']=[];
        $res_e['series']['click']=[];
        $res_e['series']['taobao']=[];
        $res_e['series']['active_user']=[];

        //获取分享次数
        $sql = "select report_date,sum(share) num from ngw_share_log where report_date BETWEEN '".$sdate."' and '".$edate."'".$type_con." GROUP BY report_date ORDER BY report_date ASC";
        $res=M()->query($sql,'all');
        $res = array_column($res, 'num', 'report_date');
        $res_e['series']['share']=self::fomart_arr($res_e['date_line'],$res_e['series']['share'],$res);

        //获取分享次数--去重
        $sql = "select report_date,count(distinct(uid)) num from ngw_share_log where report_date BETWEEN '".$sdate."' and '".$edate."'".$type_con." GROUP BY report_date ORDER BY report_date ASC";
        $res=M()->query($sql,'all');
        $res = array_column($res, 'num', 'report_date');
        $res_e['series']['share_q']=self::fomart_arr($res_e['date_line'],$res_e['series']['share_q'],$res);

        //获取点击次数-未去重
        $sql = "select report_date,sum(click) num from ngw_click_log where report_date BETWEEN '".$sdate."' and '".$edate."'".$type_con." GROUP BY report_date ORDER BY report_date ASC";
        $res=M()->query($sql,'all');
        $res = array_column($res, 'num', 'report_date');
        $res_e['series']['click']=self::fomart_arr($res_e['date_line'],$res_e['series']['click'],$res);

        //获取授权淘宝次数
        $type_taobao_con='';
        if($type=='1'){
            $type_taobao_con="uid in(select objectId from ngw_uid where type=1) and ";

        }else if($type=='0'){
            $type_taobao_con="uid in(select objectId from ngw_uid where type=0) and ";
        }
        $sql = "SELECT report_date,count(DISTINCT uid) num from ngw_taobao_log where ".$type_taobao_con." report_date BETWEEN '".$sdate."' and '".$edate."' GROUP BY report_date ORDER BY report_date ASC";
        $res=M()->query($sql,'all');
        $res = array_column($res, 'num', 'report_date');
        $res_e['series']['taobao']=self::fomart_arr($res_e['date_line'],$res_e['series']['taobao'],$res);

        //获取活跃用户
        $sql = "select report_date,count(DISTINCT(uid)) num from ngw_click_log where report_date BETWEEN '".$sdate."' and '".$edate."'".$type_con." GROUP BY report_date ORDER BY report_date ASC";
        $res=M()->query($sql,'all');
        $res = array_column($res, 'num', 'report_date');
        $res_e['series']['active_user']=self::fomart_arr($res_e['date_line'],$res_e['series']['active_user'],$res);

//        D($res_e);
        info("列出成功",1,$res_e);


    }

    public static function task_str_explain($str){
        if($str==''||$str==null)  $str="1-0;2-0;3-0;4-0;5-0;6-0;7-0;8-0;";
        $arr = explode(';',$str);
        return  $arr;
    }
    public static function fomart_arr($date_line,$merge_arr,$res){
        foreach($date_line as  $k => $v){
            if(isset($res[$v])){
                array_push($merge_arr,$res[$v]);
            }
            else{
                array_push($merge_arr,0);
            }

        }
        return $merge_arr;
    }
}