<?php

/**
 * 帧处理
 * 重写所有的on开头的方法
 * @author 不再迟疑
 *
 */
namespace GatewayWorker\Lib;

use GatewayWorker\ThriftBusinessWorker;

/**
 * 帧处理类
 * 构造函数不允许传参数，对象从对象池中获取
 * 由于是循环队列，所以不允许使用$_SESSION
 */
class FrameChild extends EventDispatcher {
	private static $pool = array ();
	/**
	 * 从池子中获取对象
	 *
	 * @param string $class        	
	 */
	public static function getFromPool($class): FrameChild {
		$temp = null;
		if (array_key_exists ( $class, FrameChild::$pool )) {
			if (count ( FrameChild::$pool [$class] ) > 0) {
				$temp = array_shift ( FrameChild::$pool [$class] );
			}
		}
		if ($temp == null) {
			$class = new \ReflectionClass ( $class );
			$temp = $class->newInstanceArgs ( [ ] );
		}
		return $temp;
	}
	/**
	 * 放入池子
	 *
	 * @param FrameChild $intance        	
	 */
	public static function putToPool(FrameChild $intance, $class) {
		if (! array_key_exists ( $class, FrameChild::$pool )) {
			FrameChild::$pool [$class] = array ();
		}
		array_push ( FrameChild::$pool [$class], $intance );
	}
	/**
	 * $thriftBusinessWorker
	 *
	 * @var ThriftBusinessWorker
	 */
	protected $thriftBusinessWorker;
	/**
	 * 防止重复用的计数器
	 *
	 * @var int
	 */
	protected $count = 0;
	/**
	 * 自身的saveData
	 */
	protected $saveData = array ();
	/**
	 * 父容器
	 *
	 * @var FrameChild
	 */
	public $parent;
	/**
	 * 参与帧循环的子原件
	 *
	 * @var array $children_list
	 */
	public $children_list = array ();
	/**
	 * 在父级的位置
	 *
	 * @var string
	 */
	public $key = null;
	/**
	 * 存活时间-1代表不限,最好都有最长存活时间(秒)
	 *
	 * @var int
	 */
	public $maxLifeTime = - 1;
	/**
	 * 准备销毁
	 *
	 * @var string $readyDestory
	 */
	public $readyDestory = false;
	/**
	 * 准备移除
	 *
	 * @var string $readyDestory
	 */
	public $readyRemove = false;
	/**
	 * 已存活时间
	 *
	 * @var integer
	 */
	protected $startTime = 0;
	/**
	 * 当前帧
	 *
	 * @var integer $currentFrame
	 */
	protected $currentFrame = 0;
	protected $messageList = array ();
	/**
	 * 帧循环
	 */
	public function __onEnterFrame() {
		foreach ( $this->children_list as $key => $value ) {
			$need = $value->__onEnterFrame ();
			if (! $need) {
				unset ( $this->children_list [$key] );
			}
		}
		if ($this->readyDestory) { // 准备销毁了
			$this->onRemoved ();
			$this->onDestory ();
			$this->__reset ();
			return false;
		} elseif ($this->readyRemove) { // 准备移除了
			$this->__onRemoved ();
			return false;
		} else {
			$this->onEnterFrame ();
			$this->currentFrame ++;
			if ($this->maxLifeTime != - 1 && time () - $this->startTime >= $this->maxLifeTime) { // 到达存活上限
				$this->readyDestory = true;
			}
		}
		return true;
	}
	public function onEnterFrame() {
	}
	/**
	 * 加入循环
	 */
	public function __onAdded(ThriftBusinessWorker $thriftBusinessWorker, $isReload) {
		$this->thriftBusinessWorker = $thriftBusinessWorker;
		$this->startTime = time ();
		$this->onResume ();
		if (! $isReload) {
			$this->onAdded ();
		}
	}
	/**
	 * reload和create都执行
	 */
	public function onResume() {
	}
	/**
	 * 只执行一次
	 */
	public function onAdded() {
	}
	/**
	 * 移除循环时
	 */
	private function __onRemoved() {
		$this->onRemoved ();
		$this->parent = null;
		$this->key = null;
	}
	public function onRemoved() {
	}
	/**
	 * 加入子控件
	 *
	 * @param FrameChild $frameChild        	
	 * @param string $key        	
	 * @param bool $isReload        	
	 * @return string $key key重复将后面接@尾数
	 */
	public function addChild($frameChild, $key, $isReload = false) {
		$frameChild->removeFromParent ();
		$frameChild->parent = $this;
		$frameChild->__onAdded ( $this->thriftBusinessWorker, $isReload );
		if ($isReload) {
			// 覆盖
			$this->children_list [$key] = $frameChild;
			$frameChild->key = $key;
			return $frameChild->key;
		} else {
			if (array_key_exists ( $key, $this->children_list )) { // key重复
				$key = $key . '@' . $this->count;
				$this->count ++;
			}
			$this->children_list [$key] = $frameChild;
			$frameChild->key = $key;
			return $frameChild->key;
		}
	}
	/**
	 * 移除子控件
	 *
	 * @param unknown $index        	
	 * @return boolean
	 */
	public function removeChild($key) {
		if (array_key_exists ( $key, $this->children_list )) {
			$this->children_list [$key]->readyRemove = true;
			return true;
		}
		return false;
	}
	/**
	 * 移除所有的子控件
	 */
	public function removeChilden() {
		foreach ( $this->children_list as $value ) {
			$value->readyRemove = true;
		}
	}
	/**
	 * 从父级移除自己
	 */
	public function removeFromParent() {
		if (isset ( $this->parent )) {
			$this->parent->removeChild ( $this->key );
		}
	}
	/**
	 * 获取child
	 *
	 * @param string $key        	
	 * @return FrameChild|NULL
	 */
	public function getChild($key) {
		if (array_key_exists ( $key, $this->children_list )) {
			return $this->children_list [$key];
		} else {
			return null;
		}
	}
	/**
	 * 重置，回收
	 */
	private function __reset() {
		$this->thriftBusinessWorker = null;
		$this->count = 0;
		$this->parent = null;
		$this->children_list = array ();
		$this->saveData = array ();
		$this->currentFrame = 0;
		$this->key = null;
		$this->maxLifeTime = - 1;
		$this->readyDestory = false;
		$this->messageList = array ();
		$this->startTime = 0;
		$this->readyRemove = false;
		$key = null;
		FrameChild::putToPool ( $this, get_called_class () );
	}
	/**
	 * 销毁
	 */
	public function destory() {
		foreach ( $this->children_list as $value ) {
			$value->destory ();
		}
		$this->readyDestory = true;
		$this->removeFromParent ();
		$this->removeEventListeners ();
	}
	public function onDestory() {
	}
	/**
	 * 保存数据
	 *
	 * @param unknown $saveData        	
	 */
	public function saveData(&$saveData) {
		$this->saveData ['@class_name'] = get_called_class ();
		$this->saveData ['@count'] = $this->count;
		$this->saveData ['@key'] = $this->key;
		$this->saveData ['@children_list'] = array ();
		$this->saveData ['@startTime'] = $this->startTime;
		$this->saveData ['@maxLifeTime'] = $this->maxLifeTime;
		$this->saveData ['@readyDestory'] = $this->readyDestory;
		$this->saveData ['@readyRemove'] = $this->readyRemove;
		$this->saveData ['@messageList'] = $this->messageList;
		$saveData = $this->saveData;
		foreach ( $this->children_list as $key => $value ) {
			$saveData ['@children_list'] [$key] = array ();
			$value->saveData ( $saveData ['@children_list'] [$key] );
		}
	}
	/**
	 * 读取数据
	 *
	 * @param unknown $saveData        	
	 */
	public function loadData($saveData) {
		$this->saveData = $saveData;
		$this->count = $this->saveData ['@count'];
		$this->key = $this->saveData ['@key'];
		$this->startTime = $this->saveData ['@startTime'];
		$this->maxLifeTime = $this->saveData ['@maxLifeTime'];
		$this->readyDestory = $this->saveData ['@readyDestory'];
		$this->readyRemove = $this->saveData ['@readyRemove'];
		$this->messageList = $this->saveData ['@messageList'];
		foreach ( $this->saveData ['@children_list'] as $key => $value ) {
			$class = new \ReflectionClass ( $value ['@class_name'] );
			$instance = $class->newInstanceArgs ( [ ] );
			$this->addChild ( $instance, $key, true );
			$instance->loadData ( $saveData ['@children_list'] [$key] );
		}
	}
	public function __set($name, $value) {
		$this->saveData [$name] = $value;
	}
	/**
	 * 找不到会从父集中找
	 *
	 * @param unknown $name        	
	 */
	public function &__get($name) {
		if (array_key_exists ( $name, $this->saveData )) {
			return $this->saveData [$name];
		} elseif ($this->parent !== null) {
			return $this->parent->__get ( $name );
		} else {
			throw new \Error ( "$name not find in " . get_called_class () );
		}
	}
	/**
	 * 获取child数量
	 */
	public function getChildrenNum() {
		return count ( $this->children_list );
	}
	/**
	 * 插入消息列表
	 *
	 * @param unknown $value        	
	 */
	public function pushToMessage($value) {
		array_push ( $this->messageList, $value );
	}
	/**
	 * 取出消息
	 *
	 * @return mixed
	 */
	public function shiftFromMessage() {
		return array_shift ( $this->messageList );
	}
	/**
	 * 取出所有消息
	 *
	 * @return mixed
	 */
	public function shiftAllFromMessage() {
		return array_splice ( $this->messageList, 0 );
	}
}
