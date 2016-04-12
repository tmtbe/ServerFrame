<?php

namespace GatewayWorker\Lib;

use Channel\Client;

/**
 * Channel事件派发器,单例
 *
 * @author 不再迟疑
 *        
 */
class ChannelEventDispatcher {
	private static $channelEventDispatcher;
	public static $channelAddress;
	private $_eventListeners = array ();
	public static function getChannelEventDispatcher(): ChannelEventDispatcher {
		if (ChannelEventDispatcher::$channelEventDispatcher == null) {
			ChannelEventDispatcher::$channelEventDispatcher = new ChannelEventDispatcher ();
			// 连接Channel
			if (! empty ( ChannelEventDispatcher::$channelAddress )) {
				$channelAddressArray = explode ( ':', ChannelEventDispatcher::$channelAddress );
				Client::connect ( $channelAddressArray [0], $channelAddressArray [1] );
			} else {
				$this->log ( "ERROR:not set ChannelAddress" );
			}
		}
		return ChannelEventDispatcher::$channelEventDispatcher;
	}
	public function addEventListener($type, $listener) {
		if (! array_key_exists ( $type, $this->_eventListeners )) {
			$this->_eventListeners [$type] = array ();
			Client::on ( $type, array (
					$this,
					'onChannelCallBack' 
			) );
		}
		if (! in_array ( $listener, $this->_eventListeners [$type] )) {
			array_push ( $this->_eventListeners [$type], $listener );
		}
	}
	/**
	 * channel的回复
	 *
	 * @param unknown $data        	
	 */
	public function onChannelCallBack($data) {
		if (! array_key_exists ( $data ['type'], $this->_eventListeners )) {
			return; // no need to do anything
		}
		if (isset ( $data ['data'] )) {
			$tempData = unserialize ( $data ['data'] );
		} else {
			$tempData = null;
		}
		$event = Event::fromPool ( $data ['type'], $tempData );
		$previousTarget = $event->target;
		$event->target = $this;
		$this->invokeEvent ( $event );
		if ($previousTarget)
			$event->target = $previousTarget;
		Event::toPool ( $event );
	}
	/**
	 * Removes an event listener from the object.
	 *
	 * @param string $type        	
	 * @param function $listener        	
	 * @param bool $ifAllWorker        	
	 */
	public function removeEventListener($type, $listener) {
		if (array_key_exists ( $type, $this->_eventListeners )) {
			$numListeners = count ( $this->_eventListeners [$type] );
		} else {
			$numListeners = 0;
		}
		if ($numListeners > 0) {
			$index = array_search ( $listener, $this->_eventListeners [$type] );
			if ($index !== null) {
				unset ( $this->_eventListeners [$type] [$index] );
			}
		}
		if ($numListeners == 0) {
			unset ( $this->_eventListeners [$type] );
			Client::unsubscribe ( $type );
		}
	}
	
	/**
	 * Removes all event listeners with a certain type, or all of them if type is null.
	 * Be careful when removing all event listeners: you never know who else was listening.
	 *
	 * @param string $type        	
	 */
	public function removeEventListeners($type = null) {
		if ($type) {
			unset ( $this->_eventListeners [$type] );
			Client::unsubscribe ( $type );
		} else {
			foreach ( $this->_eventListeners as $key => $value ) {
				Client::unsubscribe ( $key );
			}
			$this->_eventListeners = array ();
		}
	}
	
	/**
	 * Dispatches an event to all objects that have registered listeners for its type.
	 *
	 * @param Event $event        	
	 */
	public function dispatchEvent($event) {
		$data ['type'] = $event->type;
		if ($event->data != null) {
			$data ['data'] = serialize ( $event->data );
		}
		Client::publish ( $data ['type'], $data );
	}
	
	/**
	 * @private
	 * Invokes an event on the current object.
	 * This method does not do any bubbling, nor
	 * does it back-up and restore the previous target on the event. The 'dispatchEvent'
	 * method uses this method internally.
	 *
	 * @param Event $event        	
	 */
	private function invokeEvent($event) {
		if (array_key_exists ( $event->type, $this->_eventListeners )) {
			$listeners = $this->_eventListeners [$event->type];
			$numListeners = count ( $listeners );
		}
		if ($numListeners) {
			$event->currentTarget = $this;
			foreach ( $listeners as $listener ) {
				call_user_func ( $listener, $event );
				if ($event->stopsImmediatePropagation) {
					return true;
				}
			}
			return $event->stopsImmediatePropagation;
		} else {
			return false;
		}
	}
	
	/**
	 * Dispatches an event with the given parameters to all objects that have registered
	 * listeners for the given type.
	 * The method uses an internal pool of event objects to
	 * avoid allocations.
	 *
	 * @param string $type        	
	 */
	public function dispatchEventWith($type, $data = null) {
		$event = Event::fromPool ( $type, $data );
		$this->dispatchEvent ( $event );
		Event::toPool ( $event );
	}
}
