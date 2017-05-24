<?php
date_default_timezone_set('Asia/Shanghai');
class  HtToCashController extends HtController {
    //查询提现申请
     function querycashapply(){
         $data = A('HtToCash:getApplyCash',[$_POST]);
         $data ? info('ok',1,$data) : info('暂无记录',-4);
     }
     //拒绝提现
     function refusetocash() {
         if(!empty($_POST)) {
             $res = A('HtToCash:refuseToCash',[$_POST]);
             $info=M()->query("select price,uid from ngw_pnow where id='".$_POST['id']."'");
             $res2=M()->exec("update ngw_uid set price=price+".$info['price'].",pnow=pnow-".$info['price']."where objectId='".$info['uid']."'");
             M('message')->add(['content' => "您申请提现失败! 请您重新申请提现或联系客服进行提现", 'uid' => $info['uid'], 'report_date' => date('Y-m-d')]);
             $res&$res2? info('拒绝提现成功',1) : info('拒绝提现失败',-1);
         }
         info('暂时无法处理，请稍后重试','-1');
     }
}