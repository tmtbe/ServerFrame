# ServerFrame
基于workerman框架的帧扩展  实现的例子：http://114.55.55.197:9393  
依赖php workerman框架 https://github.com/walkor/Workerman  
配合GatewayWorker框架使用起来更加方便 https://github.com/walkor/gatewayworker  
ChannelEventDispatcher 依赖Channel分布式通讯组件 https://github.com/walkor/channel  
#如何使用
使用GatewayWorker框架时将文件复制到GatewayWorker目录下即可自动加载使用，没有使用GatewayWorker框架请参考GatewayWorker框架 

#功能介绍
  1.EventDispatcher 事件派发器，支持中断。用于管理事件的派发。  
  2.ChannelEventDispatcher 跨进程事件派发器，原理和EventDispatcher一样，结合Channel组件进行跨进程的事件派发。单例模式  
  3.FrameChild 帧处理组件，可嵌套，继承EventDispatcher，支持状态保留reload后恢复数据，自带回收重用，对象池模式。  
  4.Event 事件，EventDispatcher将派发Event事件，自带回收重用，对象池模式。  
  5.MyRedis Redis  
  6.RedisForDb Redis作为Db缓存的基本实现  
  7.Utils 工具类，目前仅提供uuid唯一id。  
  
#EventDispatcher
  addEventListener($type, $listener)添加事件侦听  
  removeEventListener($type, $listener)移除事件侦听  
  removeEventListeners($type = null)移除type类型或所有的事件侦听  
  dispatchEvent($event)派发事件  
  dispatchEventWith($type, $data = null)派发事件 Event使用对象池模式  
  注意addEventListener和removeEventListener请成对使用，避免内存溢出  
  事例：  
  ```php
    mEventDispatcher->addEventListener('event_changer',array($this,'onChangeListener'));
    function onChangeListener(Event $event){
      var_dump($event->type);
      var_dump($event->data);
      ...
    }
  ```
#ChannelEventDispatcher
  用法与EventDispatcher一致，唯一注意的就是ChannelEventDispatcher是单例模式  
  ```php
  ChannelEventDispatcher::$channelAddress='127.0.0.0:8081';//设置channel地址
  ChannelEventDispatcher::getChannelEventDispatcher()->addEventListener('event_changer',array($this,'onChangeListener'));
  ```
  进阶应用，获取Event后可以通过EventDispatcher继续派发下去，这样就完成了跨进程间和进程中的完整消息派发。  
#FrameChild
  以下on方法继承时请override  
  onEnterFrame();每一帧都会触发  
  onAdded();每次被add都会触发，reload时不会触发  
  onResume();每次被add和reload时都会触发，用于恢复状态  
  onRemoved();每次被remove时都会触发，remove不会解除自身包含child的状态  
  onDestory();销毁时会调用，并销毁所有的child  
  addChild($frameChild, $key, $isReload = false) 往自身添加child，reload状态系统会调用，开发者不要赋值  
  removeChild($key)移除子控件，触发子控件的onRemoved方法  
  removeChilden()移除所有的子控件，触发所有child的onRemoved方法  
  removeFromParent()从父级移除自己，触发自身onRemoved方法  
  getChild($key)获取child  
  destory()销毁触发所有child的onRemoved，onDestory方法  
  getChildrenNum()获取child数量  
  pushToMessage()插入消息列表，内部维护一个消息列表，reload不会丢失  
  shiftFromMessage()取出消息  
  shiftAllFromMessage()取出所有消息  
  parent获取父FrameChild对象  
  
  以下是FrameChild使用时的注意事项  
  1.继承FrameChild时构造函数不允许传参数  
  2.对象从对象池中获取，请使用FrameChild::getFromPool($class),$class填写包含命名空间的完整类名  
  3.尽量使用FrameChild::getFromPool方式创建FrameChild对象，他会在destory后自动回收，循环利用
  4.由于workerman有reload机制，正常状态下重启worker加载新的代码，新的进程会使你丢失所有运行时的数据，使用FrameChild的约束，会自动帮你存储数据，恢复数据，重建所有的FrameChild树。  
    使用FrameChild的__set()和__get()魔术方法，当你需要传进一个变量时$frameChild->count = 10;  
    当你需要取得一个变量时var_dump($frameChild->count)  
    如果你这么做count的数据再下次reload时会被保留下来  
    这里有个小小的体验改善，child的对象__get()不到数据时会自动向parent请求，所以：  
  ```php
    $frameParent->addChild($frameChild);
    $frameParent->count=10;
    echo $frameChild->count;
  ```
    会输出10。  
  5.如果要在workerman中使用FrameChild按照以下步骤  
    onWorkerStart()方法中添加以下代码  
  ```php
    /**
    * 参与帧循环的root原件
    *
    * @var FrameChild
    */
    /**
    * 帧频
    *
    * @var int
    */
    public $frameRate = 0;
    public $frameRoot = null;
    protected function onWorkerStart() {
 	$this->frameRoot = new FrameChild ();
  	$this->frameRoot->__onAdded ( $this, false );
  	// 如果使能了EnterFrame就启动Time
  	if ($this->frameRate > 0) {
  		Timer::add(0.5, array($this,'startFrameTimer'),array(),false);			
  		if (isset ( $this->saveData ['@frameAutoSaveData'] )) {
  			$this->frameRoot->loadData ( $this->saveData ['@frameAutoSaveData'] );
  		}
  	}		
	｝
    /**
    * 启动frame定时器
    */
    public function startFrameTimer(){
  	Timer::add ( 1 / $this->frameRate, array (
  			$this->frameRoot,
  			'__onEnterFrame'
  	) );
    }
  ```
  
    
