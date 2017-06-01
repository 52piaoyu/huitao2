<?php
class HtreportController {
	//入渠道报表 中间表
	private function addData($data) {
		$fields  = M('report')->getTableFields();
		foreach($fields as $k => $v){
			if($v == 'id') unset($fields[$k]);
			if($v == 'system') unset($fields[$k]);
		}
		$sql = 'REPLACE INTO ngw_report('.('`'.implode('`,`', $fields).'`').')VALUES';
		foreach($data as $v) {
			//设置字段默认值为0
			foreach($fields as $value) if(empty($v[$value])) $v[$value] = 0;
			$sql .= '('.$v['fee'].','.$v['benifit'].','.$v['td_fee'].','.$v['td_benifit'].','.$v['share'].','.$v['s_percent'].','.$v['keep'].','.$v['track_num'].','.$v['click_num'].','.$v['invatation_num'].',';
			$sql .= $v['source'] ? "'{$v['source']}'," : "'{$_REQUEST['media_id']}',";
			$sql .= "'".(date('Y-m-d'))."'";
			$sql .= '),';
		}
		return M()->query(rtrim($sql, ','));
	}
	public function calculatio($startTime, $endTime, $data) {
		//生成日期数组
		$dates = $this->prDates($startTime, $endTime);
		$HtreportModel = new HtreportModel($data);
		$mg_list = array_merge_recursive(
		    $this->hdArr($HtreportModel->shareRecord($startTime, $endTime)),                    //用户分享记录
		    $this->hdArr($HtreportModel->shareRate($startTime, $endTime)),                      //用户分享率
		    $this->hdArr($HtreportModel->channelsNewlyAdded($startTime, $endTime)),             //新增用户
		    $this->hdArr($HtreportModel->inviteNewlyAdded($startTime, $endTime)),               //邀请新增
		    $this->hdArr($HtreportModel->validUser($startTime, $endTime)),                      //有效用户
		    $this->hdArr($HtreportModel->leaveBehindUser($startTime, $endTime)),				//用户留存数
		    $this->hdArr($HtreportModel->userPurchaseNumber($startTime, $endTime)),             //用户下单额
		    $this->hdArr($HtreportModel->userFriendProfits($startTime, $endTime)),              //用户带来的毛利润
		    $this->hdArr($HtreportModel->userMaoProfits($startTime, $endTime)),					//用户的好友带来的毛利润
		    $this->hdArr($HtreportModel->userFriendPurchaseNumber($startTime, $endTime))        //用户邀请的好友的下单额
		);
		//字段定义
		$basic_list = ['fee'=>'','benifit'=>'','td_fee'=>'','td_benifit'=>'','share'=>'','s_percent'=>'','keep'=>'','track_num'=>'','click_num'=>'','invatation_num'=>''];
		foreach ($dates as $k => $v) {
			$temp[$v] = isset($mg_list[$v]) ? array_merge($basic_list,$mg_list[$v]) : $basic_list;
		    $temp[$v]['date'] = $v;
		}
		return array_values($temp);
	}
	/**
	 * [channelOneReport 各渠道折线图]
	 */
	public function channelOneReport() {
		if(!empty($_REQUEST['start_time']) && !empty($_REQUEST['end_time']) && !empty($_REQUEST['media_id'])) {
        	$startTime = $_REQUEST['start_time'];
        	//如果截止日期超过当前日期或者大于当前日期则重新计算
        	if($_REQUEST['end_time'] >= date('Y-m-d')) {
        		M('report')->where(['report_date' => ['=', date('Y-m-d')], 'source' => ['=', $_REQUEST['media_id']] ])->save();
        		$endTime = date('Y-m-d');
        	} else $endTime = $_REQUEST['end_time'];
        	//查询中间表是否有数据ilo0IL
        	$intermediateTableData = M('report')->where("report_date BETWEEN '{$startTime}' AND '{$endTime}' AND source = '{$_REQUEST['media_id']}' order by report_date asc")->select('all');
        	//从中间表里查询出来的数据最后记录的一个日期==当前截止日期  且 开始日期==查出来的第一条记录的日期 则直接return掉
        	if(!empty($intermediateTableData) && (end($intermediateTableData))['report_date'] == $endTime && $intermediateTableData[0]['report_date'] == $startTime){
        		foreach($intermediateTableData as &$v) $v['date'] = $v['report_date'];
        		info('ok', 1, $intermediateTableData);
        	}
        	//渠道用户进行计算汇总数据
        	$data = $this->calculatio($startTime, $endTime, connectionArray(M('tracking')->where("uid is not null AND source = '{$_REQUEST['media_id']}'")->select('all'), 'uid'));
        	//全部用户进行计算汇总数据
        	// $data = $this->calculatio($startTime, $endTime, connectionArray(M('uid')->field('objectId')->select('all'), 'objectId'));
        	//存入中间表
        	$this->addData($data);
        	return info('ok', 1, $data);
		}
		info('缺少参数', -1);
	}
	private function prDates($start,$end) {
		$dt_start = strtotime($start);
		$dt_end = strtotime($end);
		while ($dt_start <= $dt_end){
			$dt_list[] = date('Y-m-d',$dt_start);
			$dt_start = strtotime('+1 day',$dt_start);
		}
		return $dt_list;
	}
	private function hdArr($arr) {
		$_arr = [];
		foreach($arr as $k => $v) {
			$_arr[$v['report_date']] = $v;
			unset($_arr[$v['report_date']]['report_date']);
		}
		return $_arr;
	}
}
