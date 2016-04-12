<?php

namespace GatewayWorker\Lib;

/**
 * 工具类
 * @author 不再迟疑
 */
class Utils {
	public static function uuid() {
		if (function_exists ( 'com_create_guid' )) {
			return com_create_guid ();
		} else {
			mt_srand ( ( double ) microtime () * 10000 ); // optional for php 4.2.0 and up.随便数播种，4.2.0以后不需要了。
			$charid = strtoupper ( md5 ( uniqid ( rand (), true ) ) ); // 根据当前时间（微秒计）生成唯一id.
			$hyphen = chr ( 45 ); // "-"
			$uuid = '' . substr ( $charid, 0, 8 ) . $hyphen . substr ( $charid, 8, 4 ) . $hyphen . substr ( $charid, 12, 4 ) . $hyphen . substr ( $charid, 16, 4 ) . $hyphen . substr ( $charid, 20, 12 );
			return $uuid;
		}
	}
}
