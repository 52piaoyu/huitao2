<?php
class TaoBaoKeModel {
    public $filed = '';
    //批量添加所需要添加的字段
    public function addOnlineGoods($data) {
        $sql = 'INSERT IGNORE INTO ngw_goods_online('.('`'.implode('`,`', array_keys($this->setFileds([], 'goods_online'))).'`').') VALUES ';
        foreach($data as $v) {
            $v['small_images']  = json_encode($v['small_images'], JSON_UNESCAPED_UNICODE);  //小图列表
            $v['store_type']    = $v['store_type'] ? 0 : 1;
            $v['source']        = 10;
            $v['rating']        = $v['rating'] / 100;
            $v['status']        = 3;
            $v['created_date']  = date('Y-m-d');
            $sql .= '('.implode($this->setFileds($v, 'goods_online'), ',').'),';
        }
        return $this->exec($sql) ? 1 : 0;
    }
    public function exec($sql) {
        return substr($sql, -1) == ',' ? M()->exec(rtrim($sql, ',')) : '';
    }
    public function setFileds($value = [], $table = 'order_status', $unsetFiled = ['id', 'updatedAt', 'createdAt']) {
        //缓存表字段
        static $tables = null;
        static $filed = null;
        if($table != $tables) {
            $tables = $table;
            $filed = M($tables)->getTableFields();
        }
        //如果未定义值 则默认值为null
        foreach($filed as $v)
            $fileds[$v] = isset($value[$v]) ? is_string($value[$v]) && !empty($value[$v]) ? "'{$value[$v]}'" : $value[$v] : 'NULL';
        foreach($unsetFiled as $v)
            unset($fileds[$v]);
        return $fileds;
    }
}