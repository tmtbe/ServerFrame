<?php
namespace GatewayWorker\Lib;

/**
 * Redis作为Db缓存的基本实现
 * @author 不再迟疑
 *
 */
class RedisForDb
{
    /**
     * 将一个二维数组以field字段为索引序列化到redis的hash表中
     * @param string $redis_name 
     * @param string $key hash表的key
     * @param array $array 二维数组
     * @param string $field 索引字段不传默认0-N,一般为数据库字段名
     */
    public static function putToRedisHash($redis_name,$key,$array,$field_name_db=''){
        $redis = MyRedis::instance($redis_name);
        $set_array = array();
        if(empty($field_name_db)){
            foreach ($array as $value){            
                array_push($set_array, json_encode($value));
            }
        }else{
            foreach ($array as $value){                
                $set_array[$value[$field_name_db]] = json_encode($value);
            }
        }
        $redis->hMset($key,$set_array);
    }
    /**
     * 从redis中批量获取,方法不保证全部都能有值
     * @param string $redis_name
     * @param string $key
     * @param array $field 为空代表取全部 例子array(1,2,3)
     * @return array 额定为二维数组,不存在返回null
     */
    public static function getHashFromRedis($redis_name,$key,$fields=null){
        $redis = MyRedis::instance($redis_name);
        if(empty($fields)){
            $result = $redis->hGetAll($key);
        }else{
            $result = $redis->hmGet($key, $fields);
        }
        if($result){//存在
            foreach ($result as $key=>$value){
                $result[$key]=json_decode($value,true);
            }
            return $result;
        }else {
            return null;
        }        
    }
    /**
     * 先从redis中找，找不到再从db中找，存入redis
     * @param DbConnection $db_r 数据库查询方法，不要存在where in语句如果在redis中没有命中where方法将被修改
     * @param string $redis_name 
     * @param string $key hash键名
     * @param string $field_name_db field的唯一名称，数据存入hash的唯一索引，field必须是数据库存在的字段名
     * @param array $fields $field_name_db在数据库对应的值的数组
     * @return array 找不到为null，否则额定为二维数组@data_from后面表示数据来源
     */
    public static function getHashFromRedisAndDb($redis_name,$key,$field_name_db,$fields,DbConnection $db_r){
        $result = RedisForDb::getHashFromRedis($redis_name,$key,$fields);
        $needSeachFromDb = array();
        foreach ($fields as $value){            
            if(empty($result[$value])){
                array_push($needSeachFromDb, $value);
            }else{
                $result[$value]['@data_from']='redis';
            }
        }
        if(!empty($needSeachFromDb)){//此处处理未命中数据，注意where方法将被修改
        	if(count($needSeachFromDb)>1){
            	$resultFromDb = $db_r->where($field_name_db.' in ('.implode(',',$needSeachFromDb).')')->query();//向db请求
        	}else{
        		$resultFromDb = $db_r->where($field_name_db."='".$needSeachFromDb[0]."'")->query();//向db请求
        	}
        	if(empty($resultFromDb)){//代表从数据库中找不到
        		return null;
        	}
            RedisForDb::putToRedisHash($redis_name,$key,$resultFromDb,$field_name_db); //写入redis
            foreach ($resultFromDb as $value){
                $result[$value[$field_name_db]]=$value; //拼合数据
                $result[$value[$field_name_db]]['@data_from']='db';
            }
        }
        return $result;
    }
    /**
     * 批量更新Redis中的数据（不支持插入数据），先从redis批量获取数据->合并数据->传回redis，该方法适合不完整的数据合并，
     * 插入数据请直接使用putToRedisHash，切记
     * 如果redis中不含有对应的数据，强制合并后的数据也将是不完整的，
     * 所以redis中不存在的数据，使用该方法将会忽略掉。
     * @param string $redis_name
     * @param string $key
     * @param array $updateValueArrary 二维数组符合redisfordb返回的结构{$field=>{},$field=>{}};
     */
    public static function updateHashToRedis($redis_name,$key,$updateValueArrary){
        $fields = array_keys($updateValueArrary);
        $resultFromRedis = RedisForDb::getHashFromRedis($redis_name,$key,$fields);
        foreach ($resultFromRedis as $r_key=>$r_value){//剔除不完整的数据
            if(empty($r_value)){
                unset($fields[$r_key]);
            }
        }        
        $result = array();
        foreach ($fields as $value){
            $result[$value] = json_encode(array_merge($resultFromRedis[$value],$updateValueArrary[$value]));
        }        
        $redis = MyRedis::instance($redis_name);
        $redis->hMset($key,$result);        
    }
}

?>