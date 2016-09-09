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
 * The Response object holds the necessary file path details to map to a view file or instruction set.
 * Multiple Response objects can be nested within each other.
 * @author Chuck de Sully <cdesully@webslicer.com>
 *
 */
class Response {
    /**
     * Represents the medium that this object will attempt to flatten to.
     * @var string
     */
    private $context;
    
    /**
     * File links to media types are broken apart by this array to be analyzed 
     * and manipulated by further processes.
     * @var array 
     */
    private $viewPathArr = array('directory'=>'','class'=>'','method'=>'','media'=>'','suffix'=>'');
    
    /**
     * The parent Response object that this is a child of.
     * @var Response|string|array
     */
    public $parent;
    
    /**
     * An indicator of where in the chain the Core will place this object.
     * @var integer
     */
    public $levelUp;//if there is no parent, levelUp is ignored
    
    /**
     * A stringified version of the final file path this data will associated to.
     * @var string
     */
    public $viewPath = null;
    
    /**
     * A helper variable that determines a url piece that does not yet include the controller
     * @var string
     */
    public $basePath = '';
    
    /**
     * The associative array that will get represented as keys=>variables in the view script
     * populated by the controller.
     * @var array
     */
    public $data = array();
    
    /**
     * An array holding event objects that get bubbled up to parents.
     * @var array
     */
    public $eventArr = [];//depended on by controller
    
    /**
     * @var array A provided alternative method of interpreting viewPathArr to viewPath.
     * Multiple strategies can be stacked until once succeeds.
     */
    private static $viewFinder = array();
    
    /**
     * Internal flags for $capture
     * @var array
     */
    private static $captureOptions = array('start'=>false,'position'=>'top','append'=>true);
    
    /**
     * Provides the place to capture strings and echo them out at later Response elements
     * @var array
     */
    public static $capture = array('top'=>array(),'bottom'=>array());
    
    /**
     * Default values that will start off viewPathArr
     * @var array
     */
    public static $defaultViewArr = array('directory'=>'','media'=>'html','suffix'=>'php');
    
    /**
     * If parent is not provided the defaultParent will be used.
     * @var Response|string|array
     */
    public static $defaultParent = '';
    
    /**
     * Initializing a default configuration based on incoming $request.
     * @param type $request
     */
    public function __construct($request){
        $this->viewPathArr = array_merge($this->viewPathArr, self::$defaultViewArr);
        if(isset($request->params['levelUp']) && is_numeric($request->params['levelUp'])){
            $this->levelUp = (int) $request->params['levelUp'];
        }
        if(isset($request->params['context']) && !empty($request->params['context'])){
            $this->setContext($request->params['context']);
            if(is_null($this->levelUp)){
                $this->levelUp = 0;
            }
        } else {
            if(is_null($this->levelUp)){
                $this->levelUp = -1;
            }
        }
        
        if(!empty($request->class)){
            $this->viewPathArr['class'] = Request::toUrlForm($request->class);
        }
        if(!empty($request->method)){
            $this->viewPathArr['method'] = Request::toUrlForm($request->method);
        }
        $this->basePath = Request::$basePath;
    }
    
    /**
     * This will recursively process Response objects and any other connected parameter.
     * Output data is organized in arrays, all array keys will become variable names with 
     * the array values becoming the variable values.
     * data['key']='value' translates to $key='value' within the included script file.
     * 
     * @param string $top designates the top level response object so as to process with different headers.
     * @param boolean $headers
     * @return multitype:|string
     */
    public function display($top=false, $headers=false){
        $this->data = (array)$this->data;
        
        //find response objects or array of response objects and dereference
        foreach($this->data as $key=>$resp){
            if ($resp instanceof Interfaces\ResponseInterface){
                $resp = $resp->getResponse();
            }
            if($resp instanceof Response){
                $this->data[$key] = $resp->display();
            } else if (is_array($resp)){
                foreach($resp as $key2=>$resp2){
                    if ($resp2 instanceof Interfaces\ResponseInterface){
                        $resp2 = $resp2->getResponse();
                    }
                    if($resp2 instanceof Response){
                        $this->data[$key][$key2] = $resp2->display();
                    }
                }
            }
        }
        if($this->context == 'json' || $this->context == 'raw'
            || $this->context == 'javascript'){
             if($top && ($headers || $this->context == 'json') || $this->context == 'javascript'){
                $data = json_encode($this->data);
                if($headers){
                    if(isset($_GET['callback'])){
                        if (preg_match('/\W/', $_GET['callback'])) {
                            // if $_GET['callback'] contains a non-word character,
                            // this could be an XSS attack.
                            header('HTTP/1.1 400 Bad Request');
                            exit();
                        }
                        $data = sprintf('%s(%s);', $_GET['callback'], $data);
                        header('Content-type: application/javascript; charset=utf-8');
                    } else {
                        header('Content-type: application/json; charset=utf-8');
                    }
                }
                return $data;
            } else {
                //to allow recursive response objects to trickle object properties up
                return $this->data;
            }            
        } else {
            //(x)html or other format                
            ob_start();
            extract($this->data);
            if($this->checkViewPath()){
                include $this->viewPath;
            } else {
                ob_end_clean();
                error_log('View file not found. '.$this->viewPath);
                if($top && $headers){
                    header('HTTP/1.1 501 Not Implemented');
                }
                return 'View file not found.';
            }
            return ob_get_clean();
        }
    }
    
    /**
     * Get the context that this Response will be presented in.
     * @return String
     */
    public function getContext(){
        return $this->context;
    }
    
    /**
     * Set the context that this Response will be presented in.
     * An empty context will get a default media type.
     * @param type $ctx
     * @return \Symmetry\Response
     */
    public function setContext($ctx){        
        if(empty($ctx)){
            $this->context = null;
            $this->viewPathArr['media'] = self::$defaultViewArr['media'];
        } else {
            $this->context = $ctx;
            $this->viewPathArr['media'] = $ctx;
        }
        return $this;
    }
    
    /**
     * Shortcut to get stringified version of a response object.
     * Can be used to include files within view scripts
     * @param type $filepath
     * @param type $data
     * @param type $plugins
     * @return type
     */
    public function includeView($filepath, $data=null, $plugins=array()){
        return self::getInstance($filepath, $data, $plugins)->display(true);
    }
    
    /**
     * A list of Finder interfaces to assist in finding files based upon a ruleset
     * @param type $vf
     */
    public static function registerViewFinder($vf){
        self::$viewFinder[] = $vf;
    }


    /**
     * check and load a valid view file
     * the file path is loaded in the object to be included by the calling function
     * the validity of the file existence is returned via boolean
     * @return boolean
     */
    private function checkViewPath(){
        foreach(self::$viewFinder as $vf){
            $foundFile = $vf->getFile($this->viewPathArr);
            if($foundFile !== false){
                $this->viewPath = $foundFile;
                return true;
            }
        }
        $this->viewPath = $this->viewPathArr['directory'].'/'.$this->viewPathArr['class']
            . '/'.$this->viewPathArr['method']
            . '.'.$this->viewPathArr['media']
            . '.'.$this->viewPathArr['suffix'];
        if(file_exists($this->viewPath)){
            return true;
        } else {
            return false;
        }
    }
        
    /**
     * sets the view script path
     * parameters represent the literal folder and file names
     * be careful of case sensitivity
     * @param string $method
     * @param string $class
     * @param string $suffix
     * @param string $dir
     * @return Response
     */
    public function setPath($method,$class=null,$suffix='php',$dir=null){
		if(!empty($dir)){
			$this->viewPathArr['directory'] = $dir;
		}
		if(!empty($class)){
			$this->viewPathArr['class'] = $class;
		}
		$this->viewPathArr['method'] = $method;
		$this->viewPathArr['suffix'] = $suffix;
        return $this;
    }
        
    /**
     * Prevent the system from bubbling up to parent responses
     * @param bool $disable
     */
    public function disableLayout($disable=true){
		if($disable){
			$this->levelUp = 0;
		} else {
			$this->levelUp = -1;
		}
    }
    
    /**
     * Enable how many parent levels up Core will attempt to go.
     * @param int $level
     */
    public function enableLayout($level = null){
        if($level !== null && $level >= 0){
            $this->levelUp = $level;
        } else {
            $this->levelUp = -1;
        }
    }
    
    /**
     * Takes a portion of a view script to store for later output.
     * View scripts may be rapidly developed by putting css and js details in with the php,
     * by so doing css and js could be littered throughout the final output.
     * By capturing css and js, those bits can be output in one spot, prefereably in a layout.
     * @param bool $start a start flag to indicate being turned on.
     * @param string $position a label describing the bin the captured data goes into
     * @param bool $append whether to put data at the end of the beginning of the bin.
     */
    public function capture($start=true,$position="top",$append=true){
        if(!self::$captureOptions['start'] && $start){
            //turn on buffer
            self::$captureOptions['start'] = true;
            self::$captureOptions['position'] = $position;
            self::$captureOptions['append'] = $append;
            ob_start();
        } else if(self::$captureOptions['start'] && !$start){
            //turn off buffer
            self::$captureOptions['start'] = false;
            $capture = ob_get_clean();
            if(!isset(self::$capture[self::$captureOptions['position']])){
                self::$capture[self::$captureOptions['position']] = array();
            }
            if(self::$captureOptions['append']){
                array_push(self::$capture[self::$captureOptions['position']],$capture);
            } else {
                array_unshift(self::$capture[self::$captureOptions['position']],$capture);
            }
        }
    }
    
    /**
     * Short cut function for capture()
     * @param string $position
     * @param boolean $append
     */
    public function captureOn($position="top",$append=true){
        $this->capture(true,$position,$append);
    }
    
    /**
     * Short cut function to turn capture() off
     */
    public function captureOff(){
        $this->capture(false);
    }
    
    /**
     * Retrieves captured data from the corresponding bin name
     * @param string $position bin name
     * @return string
     */
    public function getCapture($position="top"){
        if(isset(self::$capture[$position])){
            return implode("\r\n",self::$capture[$position]);
        }        
    }
    
    /**
     * Magic method to set class data variables as if directly part of the class
     * @param unknown $name
     * @param unknown $value
     */
    public function __set($name, $value){
        $this->data[$name] = $value;
    }
    
    /**
     * Magic method to get class data variables as if directly part of the class
     * @param unknown $name
     * @return multitype:|NULL
     */
    public function __get($name){
        if(isset($this->data[$name])){
            return $this->data[$name];
        } else {
            return null;
        }
    }
    
    /**
     * Echoing this object should be the final step to output to client
     * @return type
     */
    public function __toString()
    {
        try {
            return $this->display(true, true);
        } catch (\Exception $e){
            return $e->getMessage();
        }
    }
    
    /**
     * A factory method that provides a Response object accepting a variety of input methods.
     * @param String $filepath | 'json'
     * @param Array|Object|Response $data
     * @param Request $request
     * @param String $dfltCtx default context/media type
     * @return Response
     */
    public static function getInstance($filepath, $data, $request=null, $dfltCtx=null){
        if(is_string($filepath)){
            $filepath = strtolower($filepath);
            if($filepath != 'json' && $filepath != 'raw' && $filepath != 'javascript'){
                $suffix = '.'.self::$defaultViewArr['suffix'];
                $suffixLn = strlen($suffix);
                if(substr_compare($filepath, $suffix, -$suffixLn, $suffixLn, true) !== 0){
                    //no default suffix, make url call
                    $filepath = array($filepath);
                }
            }
        }
        
        if(is_string($filepath)){
            if(is_object($data)){
                $data = get_object_vars($data);
            } else if(!is_array($data)){
                $data = (array) $data;
            }
            if(!($request instanceof Request)){
                $request = new Request(Request::$defaultMethod,Request::$defaultClass,$request);
            }
            $response = new Response($request);
            $response->data = $data;
            
            if($filepath == 'json' || $filepath == 'raw' || $filepath == 'javascript'){
                $response->setContext($filepath);
            } else {
                $pathInfo = pathinfo($filepath);
                if(false !== $pos = strpos($pathInfo['filename'], '.')){
                    $media = substr($pathInfo['filename'], $pos+1);
                    $pathInfo['filename'] = substr($pathInfo['filename'], 0, $pos);
                    $context = $response->getContext();
                    if(empty($context)){
                        $response->setContext($media);
                    }
                }
                if(!isset($pathInfo['extension'])){
                    $pathInfo['extension'] = self::$defaultViewArr['suffix'];
                }
                $response->setPath($pathInfo['filename'], 
                    $pathInfo['dirname'], 
                    $pathInfo['extension']);
            }
            return $response;
        } else {
            $corePlugin = $request;//swap out variable names
            if(is_array($filepath)){
                if(count($filepath) > 1){
                    //absolute request
                    $request = new Request($filepath[1], $filepath[0], $data);
                } else {
                    //url request
                    $request = new Request\UrlRequest($filepath[0], $data);
                }
            } else {
                $request = $filepath;
                if(!is_null($data)){
                    //migrate short signature to longer one
                    $dfltCtx = $corePlugin;
                    $corePlugin = $data;
                    $data = null;
                }
            }
            if(is_string($corePlugin) && empty($dfltCtx)){
                $dfltCtx = $corePlugin;
                $corePlugin = array();
            }
            
            if($request instanceof Request){
                if(!isset($request->params['context']) && !empty($dfltCtx)){
                    $request->params['context'] = $dfltCtx;
                }
                if($corePlugin instanceof Core){
                    if($request instanceof Request\UrlRequest){
                        return $corePlugin->preProcessRequest($request);
                    } else {
                        return $corePlugin->processRequest($request);
                    }
                } else {
                    return Core::call($request, (array) $corePlugin);
                }
            }
            return false;
        }
    }
}