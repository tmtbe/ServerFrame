<?php
/**
 * Redis的基本实现
 * @author 不再迟疑
 *
 */
namespace GatewayWorker\Lib;

/**
 * 数据库类
 */
class MyRedis
{
    /**
     * 实例数组
     * @var array
     */
    protected static $instance = array();
    
    /**
     * 获取实例
     * @param string $config_name
     * @throws \Exception
     */
    public static function instance($config_name)
    {
        if(!isset(\Config\Redis::$$config_name))
        {
            echo "\\Config\\Redis::$config_name not set\n";
            throw new \Exception("\\Config\\Redis::$config_name not set\n");
        }
        
        if(empty(self::$instance[$config_name]))
        {
            $config = \Config\Redis::$$config_name;
            $myRedis = new \Redis();
            self::$instance[$config_name] = $myRedis;
            if($myRedis->pconnect($config['host'], $config['port'])==false){
                die($myRedis->getLastError());
                throw new \Exception("redis not connect");
            }
            if($myRedis->auth($config['user'].":".$config['password'])==false){
                die($myRedis->getLastError());
                throw new \Exception("redis not auth");
            }
            $myRedis->select($config['select']);
        }
        return self::$instance[$config_name];
    }
    
    /**
     * 关闭redis实例
     * @param string $config_name
     */
    public static function close($config_name)
    {
        if(isset(self::$instance[$config_name]))
        {
            self::$instance[$config_name]->close();
            self::$instance[$config_name] = null;
        }
    }
    
    /**
     * 关闭所有redis实例
     */
    public static function closeAll()
    {
        foreach(self::$instance as $connection)
        {
            $connection->close();
        }
        self::$instance = array();
    }   
}
