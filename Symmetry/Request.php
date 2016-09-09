<?php
/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2011 Chuck de Sully
 * Modified 2016
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
 * The Request class contains the namespace, class, function, and parameters being requested.
 * @author Chuck de Sully <cdesully@webslicer.com>
 *
 */
class Request {
    /** @var string method/function to be called in a class */
	public $method;
    
    /** @var string namespaced class name starting after controller namespace */
	public $class;
    
    /** @var string namespace prefix that defines the Controller directory */
	public $nsClassName;
    
    /** @var array|null associative array of parameters to be passed to function */
	public $params;
    
    /** @var array history of requests being made that grows as parents are produced */
    public $history;
    
    /** @var boolean convenience variable to hold if http request is post or not */
	protected $isPost=false;
    
    /** @var string default method/function name if requested name can't be mapped */
	public static $defaultMethod = 'index';
    
    /** @var string default class name if requested name can't be mapped */
    public static $defaultClass = 'Index';
    
    /** @var string what all controller classes get prefixed with for a namespace */
    public static $defaultNamespace = '';
    
    /** @var string the start of the url path that doesn't have class mapping purpose */
    public static $basePath = '';
    
    /** @var boolean|null a marker to indicate if a redirect needs to enforce ssl or not */
    public static $useHttps;
	
    /**
     * Initiate the Class/method([params]) to be accessed
     * @param string $method
     * @param string $class
     * @param array|null $params
     * @param string|null $requestMethod
     */
	public function __construct($method,$class,$params=null,$requestMethod=null){
		$this->method = $method;
		$this->class = $class;
		$this->nsClassName = self::$defaultNamespace.ucfirst($class);
		$this->params = $params;
		if(!empty($requestMethod)){
		    if(strtolower($requestMethod) == 'post'){
		        $this->isPost = true;
		    }
		}
	}
    
    /**
     * To be used by child classes to ensure class/method exists and explore default possibilities
     * Not in constructor because it is assumed direct instantiation of this class
     * would require correct knowledge of the class/method to invoke
     * @param string $method
     * @param string $class
     */
    protected function requestCheck(&$method, &$class){
        $classPath = self::$defaultNamespace.$class;
        if(!method_exists($classPath, $method)){
            if(method_exists($classPath.'\\'.ucfirst($method), self::$defaultMethod)){ 
                $class = $class .'\\'.ucfirst($method);
                $method = self::$defaultMethod;
            } else if(!method_exists($classPath, '__call')){
                if(method_exists($classPath.'\\'.ucfirst($method), '__call')){
                    $class = $class .'\\'.ucfirst($method);
                    $method = self::$defaultMethod;
                } else {
                    //Page does not exist.
                    //will be caught later
                }
            }
        }
    }
    
	/**
	 * To differentiate if an incoming Request is POST or GET
     * @return boolean
	 */
	public function isPost(){
	    return $this->isPost;
	}
    
    /**
     * get the first request in the chain
     * @return \Symmetry\Request
     */
    public function getInitRequest(){
        if(empty($this->history)){
            return $this;
        } else {
            return $this->history[0];
        }
    }
	
	/**
	 * This will convert a camelcased namespaced class/function path to an
	 * all lower case hyphenated form to use in urls
	 * @param string $string
	 * @return string
	 */
	public static function toUrlForm($string){
	    return str_replace(array('/ ',' '),array('/','-'),trim(strtolower(preg_replace(array('/([A-Z])/','/\\\/'),array(' $1','/'),$string))));
	}
}