<?php
/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2016 Chuck de Sully
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace Symmetry;

/**
 * An event object that allows messaging to be passed up to parental structures.
 * Events are stored in response objects but accessed in controllers as the Response Tree
 * is being built.
 * @author Chuck de Sully <cdesully@webslicer.com>
 *
 */
class Event {
	/** @var string Event name. */
	public $type;
    /** @var array Param values to utilize in an event function. */
	public $params;
    /** @var mixed value coming from invoked event function being passed in. */
    public $result;
    /** @var \Symmetry\Response Current Response object the event is initiated with. */
    public $response;
    /** @var boolean Flag to indicate event is no longer viable. */
    public $cancelled = false;
	
    /**
     * Initiating an event object with the event name and parameters to pass along for future event listeners
     * @param string $eventStr
     * @param array $params
     * @param \Symmetry\Response $response
     */
	public function __construct($eventStr, $params=[], $response=null){
        $this->type = $eventStr;
        $this->params = $params;
        $this->response = $response;
	}
    
    /**
     * This will flag this event object as no longer to be used.
     */
    public function stopPropagation(){
        $this->cancelled = true;
    }
	
}