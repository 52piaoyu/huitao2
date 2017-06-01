<?php
class TestController extends AppController {
    public function test() {
        $numid = '999';
        $uid = 'xiaomi';
        //如果filed存在key中 则取出累加 否则就是直接创建新增
        $usersBrowseMerchandiseRecords = R()->getHashSingle('usersBrowseMerchandiseRecords', $uid);
        if(!is_array($usersBrowseMerchandiseRecords)) $usersBrowseMerchandiseRecords = [];
        //去重重复的数据
        if(isset($usersBrowseMerchandiseRecords[$numid])) unset($usersBrowseMerchandiseRecords[$numid]);
        array_unshift($usersBrowseMerchandiseRecords, $this->n());
        //只保留最新的10个 如果超过10个则移除掉最后一个商品数据
        if(count($usersBrowseMerchandiseRecords) >= 11) {
           array_pop($usersBrowseMerchandiseRecords);
        }
        foreach($usersBrowseMerchandiseRecords as $k => $v) {
            $usersBrowseMerchandiseRecords[$v['num_iid']] = $v;
            if(!isset($usersBrowseMerchandiseRecords[$v['num_iid']]['expirationDate'])) {
                //设置过期时间
                $usersBrowseMerchandiseRecords[$v['num_iid']]['expirationDate'] = 3600 * 24 * 7;
                //设置入库时间
                $usersBrowseMerchandiseRecords[$v['num_iid']]['date'] = time();
            }
            if(time() > $usersBrowseMerchandiseRecords[$v['num_iid']]['date'] + $usersBrowseMerchandiseRecords[$v['num_iid']]['expirationDate']) {

            }
            unset($usersBrowseMerchandiseRecords[$k]);
        }
        R()->hSet('usersBrowseMerchandiseRecords', $uid, json_encode($usersBrowseMerchandiseRecords, JSON_UNESCAPED_UNICODE));
        D(R()->getHashSingle('usersBrowseMerchandiseRecords', $uid));
    }
    public function n() {
        return [
            "id" => "128730",
            "score" => "50.00",
            "top" => "0",
            "created_date" => "2017-04-25",
            "createdAt" => "2017-04-25 13:49:28",
            "updatedAt" => "2017-05-16 18:14:30",
            "category_id" => "114",
            "favorite_id" => "4344772",
            "num_iid" => "53087751458",
            "rating" => "0.97",
            "title" => "夏装新款简约字母人物印花短袖T恤女学生韩版百搭圆领打底衫上衣",
            "nick" => null,
            "url" => null,
            "store_type" => "0",
            "pict_url" => "http://img3.tbcdn.cn/tfscom/i4/TB1Q5BhJFXXXXXJXXXXXXXXXXXX_!!0-item_pic.jpg"
        ];
    }
}
