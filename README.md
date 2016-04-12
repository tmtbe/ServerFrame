# ServerFrame
基于workerman框架的帧扩展
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
    addEventListener('event_changer',array($this,'onChangeListener'));
    function onChangeListener(Event $event){
      var_dump($event->type);
      var_dump($event->data);
      ....
    }
