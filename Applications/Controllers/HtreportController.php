<?php
class HtreportController
{
    /**
     * [timingTask 定时任务 计算渠道报表数据入库]
     */
    public function timingTask()
    {
        $source = M()->query('SELECT source, system FROM ngw_tracking GROUP BY source,system', 'all');
        $time = date('Y-m-d', strtotime('-1 day'));
        foreach ($source as $v) {
            $this->addData($this->calculatio($time, $time, connectionArray(M('tracking')->where("uid is not null AND source = '{$v['source']}' and system = {$v['system']}")->select('all'), 'uid')), $v['source'], $v['system']);
        }
    }
    private function addData($data, $source, $system)
    {
        $sql = 'INSERT IGNORE INTO ngw_report(fee, benifit, td_fee, td_benifit, share, s_percent, keep, track_num, click_num, invatation_num, source, system, report_date)VALUES';
        foreach ($data as $k => $v) {
            foreach ($v as $key => $value) {
                if (!$v[$key]) {
                    $v[$key] = 0;
                }
            }    //设置字段默认值
            $sql .= "({$v['fee']}, {$v['benifit']}, {$v['td_fee']}, {$v['td_benifit']}, {$v['share']}, {$v['s_percent']}, {$v['keep']}, {$v['track_num']}, {$v['click_num']}, {$v['invatation_num']}, '{$source}', '{$system}', '{$v['date']}'),";
        }
        return M()->exec(rtrim($sql, ','));
    }
    public function calculatio($startTime, $endTime, $data)
    {
        $HtreportModel = new HtreportModel($data, $startTime, $endTime);
        $mg_list = array_merge_recursive(
            $this->hdArr($HtreportModel->shareRecord()),                    //用户分享记录
            $this->hdArr($HtreportModel->shareRate()),                      //用户分享率
            $this->hdArr($HtreportModel->channelsNewlyAdded()),             //新增用户
            $this->hdArr($HtreportModel->inviteNewlyAdded()),               //邀请新增
            $this->hdArr($HtreportModel->validUser()),                      //有效用户
            $this->hdArr($HtreportModel->leaveBehindUser()),                //用户留存数
            $this->hdArr($HtreportModel->userPurchaseNumber()),             //用户下单额
            $this->hdArr($HtreportModel->userFriendProfits()),              //用户带来的毛利润
            $this->hdArr($HtreportModel->userMaoProfits()),                    //用户的好友带来的毛利润
            $this->hdArr($HtreportModel->userFriendPurchaseNumber())        //用户邀请的好友的下单额
        );
        //字段定义
        $basic_list = ['fee'=>'', 'benifit'=>'', 'td_fee'=>'', 'td_benifit'=>'', 'share'=>'', 's_percent'=>'',' keep'=>'', 'track_num'=>'', 'click_num'=>'', 'invatation_num'=>''];
        foreach ($this->prDates($startTime, $endTime) as $v) {
            $temp[$v] = isset($mg_list[$v]) ? array_merge($basic_list, $mg_list[$v]) : $basic_list;
            $temp[$v]['date'] = $v;
        }
        return array_values($temp);
    }
    /**
     * [channelOneReport 各渠道折线图]
     */
    public function channelOneReport()
    {
        if (empty($_REQUEST['start_time']) || empty($_REQUEST['end_time']) || empty($_REQUEST['media_id'])) {
            info('缺少参数', -1);
        }
        $startTime = $_REQUEST['start_time'];
        //如果截止日期超过当前日期或者大于当前日期则重新计算
        if ($_REQUEST['end_time'] >= date('Y-m-d')) {
            M('report')->where(['report_date' => ['=', date('Y-m-d')], 'source' => ['=', $_REQUEST['media_id']] ])->save();
            $endTime = date('Y-m-d');
        } else {
            $endTime = $_REQUEST['end_time'];
        }
        /**
    	 * 先查询中间表是否有数据 如果有则info掉 如果没有则重新计算返回
    	 * 从中间表里查询出来的数据最后记录的一个日期==当前截止日期  且 开始日期==查出来的第一条记录的日期
    	 */
        $intermediateTableData = M('report')->where("report_date BETWEEN '{$startTime}' AND '{$endTime}' AND source = '{$_REQUEST['media_id']}' order by report_date asc")->select('all');
        if (!empty($intermediateTableData) && (end($intermediateTableData))['report_date'] == $endTime && $intermediateTableData[0]['report_date'] == $startTime) {
            foreach ($intermediateTableData as &$v) {
                $v['date'] = $v['report_date'];
            }
            info('ok', 1, $intermediateTableData);
        }
        $data = $this->calculatio($startTime, $endTime, connectionArray(M('tracking')->where("uid is not null AND source = '{$_REQUEST['media_id']}'")->select('all'), 'uid'));
        // $data = $this->calculatio($startTime, $endTime, connectionArray(M('uid')->field('objectId')->select('all'), 'objectId'));
        info('ok', 1, $data);
    }
    private function prDates($start, $end)
    {
        $dt_start = strtotime($start);
        $dt_end = strtotime($end);
        while ($dt_start <= $dt_end) {
            $dt_list[] = date('Y-m-d', $dt_start);
            $dt_start = strtotime('+1 day', $dt_start);
        }
        return $dt_list;
    }
    private function hdArr($arr)
    {
        $_arr = [];
        if (!empty($arr)) {
            foreach ($arr as $k => $v) {
                $_arr[$v['report_date']] = $v;
                unset($_arr[$v['report_date']]['report_date']);
            }
        }
        return $_arr;
    }
}
