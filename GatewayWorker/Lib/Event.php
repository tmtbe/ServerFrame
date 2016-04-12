<?php

namespace GatewayWorker\Lib;

/**
 * Event objects are passed as parameters to event listeners when an event occurs.
 *
 * @see EventDispatcher
 * @author 不再迟疑
 */
class Event {
	private static $sEventPool = array ();
	/**
	 *
	 * @var EventDispatcher $_target
	 */
	public $target;
	/**
	 *
	 * @var EventDispatcher $_currentTarget
	 */
	public $currentTarget;
	/**
	 *
	 * @var string $_type
	 */
	public $type;
	/**
	 *
	 * @var boolean $_stopsImmediatePropagation
	 */
	public $stopsImmediatePropagation;
	public $data;
	
	/**
	 * Creates an event object that can be passed to listeners.
	 */
	public function __construct($type, $data = null) {
		$this->type = $type;
		$this->data = $data;
	}
	
	/**
	 * Prevents any other listeners from receiving the event.
	 */
	public function stopImmediatePropagation() {
		$this->stopsImmediatePropagation = true;
	}
	
	// event pooling
	
	/**
	 *
	 * @param string $type        	
	 * @param unknown $data        	
	 */
	public static function fromPool($type, $data = null) {
		if (count ( Event::$sEventPool ))
			return array_pop ( Event::$sEventPool )->reset ( $type, $data );
		else
			return new Event ( $type, $data );
	}
	
	/**
	 *
	 * @param Event $event        	
	 */
	public static function toPool($event) {
		$event->data = $event->target = $event->currentTarget = null;
		array_push ( Event::$sEventPool, $event );
	}
	
	/**
	 *
	 * @param string $type        	
	 * @param * $data        	
	 */
	public function reset($type, $data = null) {
		$this->type = $type;
		$this->data = $data;
		$this->target = $this->currentTarget = null;
		$this->stopsImmediatePropagation = false;
		return $this;
	}
}