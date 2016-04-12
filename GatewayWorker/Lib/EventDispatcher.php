<?php

namespace GatewayWorker\Lib;

/**
 * 事件派发器
 *
 * @author 不再迟疑
 *        
 */
class EventDispatcher {
	private $_eventListeners = array ();
	
	/**
	 * Registers an event listener at a certain object.
	 *
	 * @param string $type        	
	 * @param function $listener        	
	 */
	public function addEventListener($type, $listener) {
		if (! array_key_exists ( $type, $this->_eventListeners )) {
			$this->_eventListeners [$type] = array ();
		}
		if (! in_array ( $listener, $this->_eventListeners [$type] )) {
			array_push ( $this->_eventListeners [$type], $listener );
		}
	}
	
	/**
	 * Removes an event listener from the object.
	 *
	 * @param string $type        	
	 * @param function $listener        	
	 */
	public function removeEventListener($type, $listener) {
		if (array_key_exists ( $type, $this->_eventListeners )) {
			$numListeners = count ( $this->_eventListeners [$type] );
		} else {
			$numListeners = 0;
		}
		if ($numListeners > 0) {
			$index = array_search ( $listener, $this->_eventListeners [$type] );
			if ($index!==null) {
				unset ( $this->_eventListeners [$type] [$index] );
			}
		}
		if ($numListeners == 0) {
			unset ( $this->_eventListeners [$type] );
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
		} else {
			$this->_eventListeners = array ();
		}
	}
	
	/**
	 * Dispatches an event to all objects that have registered listeners for its type.
	 * If an event with enabled 'bubble' property is dispatched to a display object, it will
	 * travel up along the line of parents, until it either hits the root object or someone
	 * stops its propagation manually.
	 *
	 * @param Event $event        	
	 */
	public function dispatchEvent($event) {
		if (! array_key_exists ( $event->type, $this->_eventListeners )) {
			return; // no need to do anything
		}
		// we save the current target and restore it later;
		// this allows users to re-dispatch events without creating a clone.
		
		$previousTarget = $event->target;
		$event->target = $this;
		$this->invokeEvent ( $event );
		if ($previousTarget)
			$event->target = $previousTarget;
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
