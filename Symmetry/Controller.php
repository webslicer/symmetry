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
 * The Controller represents a page-logic/front-model api.
 * This class sets up object instances to be reached, 
 * functions to access other Controllers within the framework scenario,
 * and a connecting Response object to interact with.
 * @author Chuck de Sully <cdesully@webslicer.com>
 *
 */
class Controller {
    /** @var \Symmetry\Core reference to the calling Core class */
    protected $application;
    
    /** @var \Symmetry\Request reference to the calling Request object */
    protected $request;
    
    /** @var \Symmetry\Response reference to the Response object to be populated */
    protected $response;
    
    /**
     * Initialize Controller with references and possible pre-fill of data
     * @param \Symmetry\Core $application
     * @param \Symmetry\Request $request
     * @param \Symmetry\Response $response
     */
    public function __construct(Core $application, Request $request, $response=null){
        $this->application = $application;
        $this->request = $request;
        if($response instanceof Response){
            $this->response = $response;
        } else {
            $this->response = new Response($this->request);
            if(is_array($response) && !empty($response)){
                $this->response->data = $response;
                foreach ($response as $resp){
                    if($resp instanceof Response){
                        $this->response->eventArr = $resp->eventArr;
                    }
                }
            }
        }
        $this->init();
    }
    
    /**
     * To be overridden. In sub-classes, instead of using a constructor 
     * setup logic should be entered here.
     */
    public function init(){}
    
    /**
     * Initiate an event with optional parameter values attached
     * Will bubble up to parent controllers.
     * @param type $event
     * @param type $params
     * @return null
     */
    public function trigger($event,$params=[]){
        if(is_string($event)){
            $event = new Event($event, $params, $this->response);
        }
        if($event instanceof Event){
            $this->response->eventArr[$event->type][] = $event;
        }
    }
    
    /**
     * Capture an event and run an anonymous function
     * Event object is always passed in as the function's first parameter
     * @param type $eventStr
     * @param type $fx
     * @return null
     */
    public function listen($eventStr, $fx){
        if(isset($this->response->eventArr[$eventStr])){
            foreach($this->response->eventArr[$eventStr] as $event){
                if(!$event->cancelled){
                    $event->result = $fx($event);
                    if($event->result === false){
                        $event->cancelled = true;
                    }
                }
            }
        }
    }
    
    /**
     * This will call another Controller function via the framework without 
     * plugin interruption unless it's an UrlRequest object.
     * @param string $method
     * @param string $class
     * @param string $paramArr
     * @return unknown
     */
    protected function call($method,$class=null,$paramArr=null){
        $context = $this->response->getContext();
        if($method instanceof Request){
            $response = Response::getInstance($method, $this->application, $context);
        } else {
            if(is_array($class) && is_null($paramArr)){
                $paramArr = $class;
                $class = null;
            }
            if(empty($class)){
                $class = $this->request->class;
            }
            $response = Response::getInstance([$class,$method], $paramArr, $this->application, $context);
        }
        
        $this->response->eventArr = array_merge_recursive($this->response->eventArr, $response->eventArr);
        return $response;
    }
    
    /**
     * This will set up a new Request to be processed after the current requested function is finished.
     * @param string $method
     * @param string $class
     * @param string $params
     */
    protected function forward($method,$class=null,$params=null){
        $request = null;
        $context = $this->response->getContext();
        if ($method instanceof Request) {
            $request = $method;
            if (!isset($request->params['context']) && !empty($context)) {
                $request->params['context'] = $context;
            }
        } else {
            if (is_array($class) && is_null($params)) {
                $params = $class;
                $class = null;
            }
            if (empty($class)) {
                $class = $this->request->class;
            }
            //carry forward context expectation unless preset
            if (!isset($params['context']) && !empty($context)) {
                $params['context'] = $context;
            }
            $request = new Request($method, $class, $params);
        }
        $this->application->addRequest($request);
    }
    
    /**
     * Returns if the currect Request is a POST or not.
     */
    protected function isPost(){
        return $this->request->isPost();
    }
    
    /**
     * Calling another Controller function explicitly through a browser redirect.
     * Application immediately quits after this function.
     * @param string $method
     * @param string $class
     * @param string $params
     */
    protected function redirect($method,$class=null,$params=null){        
        if(preg_match('/^https?:\/\//', $method)){
            header('Location: '.$method);
            exit;
        }
        $url = $this->getUrl($method, $class, $params);
        header('Location: '.$url);
        exit;
    }
    
    /**
     * Get a formed URL string
     * @param type $method
     * @param type $class
     * @param type $params
     * @return string
     */
    public function getUrl($method,$class=null,$params=null){
        if(is_array($class) && is_null($params)){
            $params = $class;
            $class = null;
        }
        $protocol = 'http';
        if(Request::$useHttps === true 
            || (Request::$useHttps !== false && !empty($_SERVER['HTTPS']))){
            $protocol = 'https';
        }
        $host = '';
        if(isset($_SERVER['HTTP_HOST'])){
            $host = $protocol.'://'.$_SERVER['HTTP_HOST'];
        }
        $url = $host.Request::$basePath;
        if(empty($class)){
            $class = $this->request->class;
        }
        $class = Request::toUrlForm($class);
        $url .= '/'.$class;
        if(!empty($method)){
            $method = Request::toUrlForm($method);
            $url .= '/'.$method;
        }
        if(!empty($params)){
            $url .= '?'.http_build_query($params);
        }
        return $url;
    }
    
    /**
     * Access to the Response object.
     * @return \Symmetry\Response
     */
    public function getResponse(){
        return $this->response;
    }
    
    /**
     * Set the Response object
     * @param \Symmetry\Response $resp
     * @return \Symmetry\Controller
     */
    public function setResponse(Response $resp){
        $this->response = $resp;
        return $this;
    }
    
    /**
     * Wrap an object as a Response so as to benefit from having a view
     * provide full filepath starting after view folder
     * @param string $filepath location of view file
     * @param Array|Object $data data that will be assigned to view
     * @param string|null $contextIn context the response should display in
     * @return Response
     */
    public function asResponse($filepath, $data, $contextIn=null){
        $contextArr = ['context'=>$contextIn];
        $context = $this->response->getContext();
        if(empty($contextIn) && $contextIn !== false && !empty($context)){
            $contextArr['context'] = $context;
        }
        return Response::getInstance($filepath, $data, $contextArr);
    }
    
}