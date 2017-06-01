<?php
class UserRecordController extends AppController
{
	public $status	= true;
	public $count	= 10;
	public $type	= null;
	public $expire 	= 3600 * 24 * 7;
	private static $behaviour;


	public function __construct(){
	}


	public static function getObj(){
		if(!(self::$behaviour instanceof self))
			self::$behaviour = new self;
		return self::$behaviour;
	}


	//{"user_id":"","num_iid":""}
	/**
	 * [click 记录用户点击行为]
	 */
	public function clickRecord($uid,$numid,$system) {
		if(!isset($uid) || !isset($numid) || !isset($system)) info('数据不完整Record',-1);

		$this->type = 'click';
		$this->uid = $uid;
		$this->numid = $numid;
		$this->system = $system;
		$this->commit(['uid'=>$this->uid,'content'=>$this->numid ,'type'=>$this->system]);
		if(!$this->status) return;
		$this->key = "history_{$uid}";
		// 用户浏览的商品记录
		if(R()->hashFeildExisit($this->key,$this->type))
			$data = $this->update();
		else
			R()->hsetnx($this->key,$this->type,[$numid => $this->goodInfo($numid)],$this->expire);
	}
	/**
	 * [usersBrowseMerchandiseRecords 用户历史浏览记录]
	 * @param  [type] $uid   [description]
	 * @param  [type] $numid [description]
	 * @return [type]        [description]
	 */
	public function usersBrowseMerchandiseRecords($uid, $numid) {
		//如果filed存在key中 则取出累加 否则就是直接创建新增
		$usersBrowseMerchandiseRecords = R()->getHashSingle('usersBrowseMerchandiseRecords', $uid) ? : [];
		//去重重复的数据
		if(isset($usersBrowseMerchandiseRecords[$numid])) unset($usersBrowseMerchandiseRecords[$numid]);
		array_unshift($usersBrowseMerchandiseRecords, $this->goodInfo());
		//只保留最新的10个 如果超过10个则移除掉最后一个商品数据
		if(count($usersBrowseMerchandiseRecords) >= 11) {
		   array_pop($usersBrowseMerchandiseRecords);
		}
		foreach($usersBrowseMerchandiseRecords as $k => $v) {
		    unset($usersBrowseMerchandiseRecords[$k]);
		    $usersBrowseMerchandiseRecords[$v['num_iid']] = $v;
		}
		R()->hSet('usersBrowseMerchandiseRecords', $uid, json_encode($usersBrowseMerchandiseRecords, JSON_UNESCAPED_UNICODE));
	}

	/**
	 * [shareRecord 记录用户分享行为]
	 */
	public function shareRecord($uid,$numid,$system,$sharetype)
	{
		if(!isset($uid) || !isset($system) || !isset($sharetype)) info('数据不完整',-1);
		$this->type = 'share';
		$this->uid = $uid;
		$this->numid = $numid;
		$this->system = $system;
		$this->commit(['uid'=>$this->uid,'content'=>$this->numid ,'type'=>$this->system,'share_type'=>$sharetype]);

	}


	/**
	 * [searchRecord 记录用户搜索行为]
	 */
	public function searchRecord($uid,$content,$system)
	{
		if(!isset($uid) || !isset($content) || !isset($system)) info('数据不完整',-1);

		$this->type = 'search';
		$this->uid = $uid;
		$this->numid = $content;
		$this->system = $system;
		$this->commit(['uid'=>$this->uid,'content'=>$this->numid ,'type'=>$this->system]);
	}


	public function commit($data)
	{
		R()->addListSingle($this->type,$data);
	}


	private function goodInfo()
	{
		$sql	= "SELECT a.*,FORMAT((b.rating/100*b.price*".parent::PERCENT."),2) as rating,b.title,b.seller_name nick,b.url,b.store_type,b.pict_url,b.price,b.category_id cid,b.category,b.deal_price zk_final_price,b.item_url,b.reduce,b.volume,concat('".parent::SHARE_URL."',b.num_iid) share_url, 1 type FROM ngw_goods_info a JOIN ngw_goods_online b ON a.num_iid = b.num_iid AND a.favorite_id = b.favorite_id WHERE a.num_iid = {$this->numid}";
		// $sql	= "SELECT title,seller_name nick,pict_url,price,deal_price zk_final_price,item_url,reduce,volume, 1 type FROM ngw_goods_online WHERE num_iid = {$this->numid}";
		$info 	= M()->query($sql,'single');
		// D($info);die;
		if(empty($info)) info('商品不存在!',-1);
		return $info;
	}


	/**
	 * [update 取出原来的redis 行为数据并追加]
	 */
	private function update()
	{
		$info = $this->ckGoodsCount(R()->getHashSingle($this->key,$this->type));
		$info[$this->numid] = $this->goodInfo($this->numid);
		R()->addHashSingle($this->key,$this->type,$info);
	}


	/**
	 * [ckGoodsCount 点击行为规则]
	 */
	private function ckGoodsCount($data)
	{
		//小于规定的条数通过
		if(count($data) < $this->count) return $data;

		//大于了删除最早的一条返回
		//删除最早一条
		$data = array_reverse($data,true);
		//提交
		$this->commit($this->uid,$this->numid,$this->count);
		array_pop($data);
		return array_reverse($data,true);
	}



}