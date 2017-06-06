<?php
/**
 * Implements hook_menu().
 *
 * Description
 *
 * @return array An array of menu items
 */
class TestController extends AppController
{
    public function tes() {
        $sql="SELECT a.*,FORMAT((b.rating/100*b.price*".parent::PERCENT."),2) as rating,b.title,b.seller_name nick,b.url,b.store_type,b.pict_url,b.price,b.category_id cid,b.category,b.deal_price zk_final_price,b.item_url,b.reduce,b.volume,concat('".parent::SHARE_URL."',b.num_iid) share_url FROM ngw_goods_info a JOIN ngw_goods_online b ON a.num_iid = b.num_iid AND a.favorite_id = b.favorite_id WHERE a.is_board = 0 AND a.is_show = 1 AND a.is_sold = 1 AND a.status=1 AND b.status= 1 GROUP BY a.num_iid ORDER BY a.is_front DESC,score DESC {$this->limit}";
        $page_no = ($this->dparam['page_no'] - 1) * $this->dparam['page_size'] ;
        $page_size = $page_no + $this->dparam['page_size'] - 1;
        //查询redis 是否有商品 如果有则return 如果没有则查库然后 清空当前redis数据 再存一遍
        $goods = R()->getListPage('soldLists',$page_no,$page_size);
        $total = count($goods);
        if($total >= $page_size) info(['status'=>1,'msg'=>'操作成功!','data'=>$goods,'total'=>$total]);
        $goods = M()->query($sql,'all');
        if(!$this->silent && empty($goods)) info('暂无该分类商品',-1);
        D($goods);exit;
        R()->delFeild('soldLists');
        $this->redisToGoods('soldLists',$goods);
        info(['status'=>1,'msg'=>'操作成功!','data'=>$this->page($goods),'total'=>count($goods)]);
    }
    public function demo() {

        if($a == 2) {
            foreach($b as $v) {

                echo 2
}
}
}
public function __construct()
{
    echo 1
    }
    public function a()
    {
        echo 2;
    }
    public function d() {

    }
    public function a() {
        echo 1;
    }
}
