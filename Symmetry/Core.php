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
 * The Core class processes a Request object.
 * Plugins are detected and activated at appropriate points, 
 * Functions requests are directed to appropriate class files with the requested function,
 * Function parameters are detected and docblock filtered if present.
 * @author Chuck de Sully <cdesully@webslicer.com>
 *
 */
class Core {
    /** @var array List of plugins to activate as controller calls are performed. */
    protected $pluginStack = array();
    /** @var array List to hold queue of request objects such as in Controller::forward instances. */
    protected $requestQueue = array();
    /** @var array|null Response to assign as a child to another Response. */
    private $useResponse;
    /** @var array List of finder strategies to resolve parent connections. */
    private static $parentFinder = [];
    /** @var Interfaces\CacheInterface Cache to improve efficiency. */
    private static $cache;
    /** @var integer Time To Live for provided Cache. */
    private static $cacheTtl;
    /** @var array Array Cache to use before provided cache is checked. */
    private static $internalCache = [];
    /** @var string Location of a view script so that framework exceptions can be wrapped. */
    public static $defaultExceptionView;
    
    /**
     * Takes a Request and adds it to a Request queue.
     * The Request queue is then processed with any plugin functions present.
     * @param \Symmetry\Request $request
     * @return \Symmetry\Response
     */
    public function getResponse($request=null){
        $response = null;
        if(!is_null($request)){
            $this->addRequest($request);
        }
        foreach($this->pluginStack as $plugin){
            $plugin->applicationEntry($this);
        }
        while (!empty($this->requestQueue)){
            $request = array_shift($this->requestQueue);   
            try{
                $response = $this->preProcessRequest($request);
            } catch(\Exception $e){
                $data = array('success'=>false,'errMsg'=>$e->getMessage());
                $response = Response::getInstance(self::$defaultExceptionView, $data, $request);
                break;//do not process any further pages or error will get buried
            }
        }
        foreach($this->pluginStack as $plugin){
            $plugin->applicationExit($response, $this);
        }
        //bubble up     
        $checkUpline = true;
        $levelUp = $response->levelUp;
        $requestHistory = [$request];
        while($checkUpline && ($levelUp > 0 || $levelUp == -1)) {
            $checkUpline = false;
            if(!empty($response->parent)){
                $checkUpline = true;
                $parent = $response->parent;
                $contentKey = 'content';                
                if(is_array($parent)){
                    //make string
                    //capture a defined $contentKey
                    if(isset($parent[1]) && is_string($parent[1])){
                        $contentKey = $parent[1];
                    }
                    $parent = $parent[0];                    
                }
                
                if(is_string($parent)){
                    //determine if "json" or file path thus response
                    //or no file suffix thus web request
                    $file = pathinfo($parent);
                    if($parent == 'json' || (!empty($file['extension']) 
                        && $file['extension'] == Response::$defaultViewArr['suffix'])){
                        //get response instance
                        //embed child response into parent with "content" key
                        $parent = Response::getInstance($parent, [$contentKey=>$response]);
                        $checkUpline = false;
                    } else {
                        $parent = new Request\UrlRequest($parent,[]);
                    }
                }
                
                if(is_object($parent)){
                    $context = $response->getContext();
                    if($parent instanceof Request){
                        $this->useResponse = [$contentKey=>$response];
                        if(!isset($parent->params['context']) && !empty($context)){
                            $parent->params['context'] = $context;
                        }
                        if(!isset($parent->params['levelUp'])){
                            if($levelUp == -1){
                                $parent->params['levelUp'] = -1;
                            } else if($levelUp > 0){
                                $parent->params['levelUp'] = $levelUp - 1;
                            } else {
                                $parent->params['levelUp'] = 0;
                            }
                        }
                        $parent->history = $requestHistory;
                        $response = $this->preProcessRequest($parent);
                        $parent->history = null;//memory cleanup
                        $requestHistory[] = $parent;
                    } else if($parent instanceof Response){
                        if(empty($parent->data)){
                            $parent->data[$contentKey] = $response;
                        }// else {
                            //assume that if data is populated so would be the embedded response
                        //}
                        $parentContext = $parent->getContext();
                        if(empty($parentContext) && !empty($context)){
                            $parent->setContext($context);
                        }
                        $response = $parent;
                    }
                }
                $levelUp = $response->levelUp;
            }
        }
        return $response;
    }
    
    /**
     * Applying class entry/exit plugins wrapped around processRequest
     * Present to allow internal requests to be able to apply plugin interaction
     * @param \Symmetry\Request $request
     * @return \Symmetry\Response
     */
    public function preProcessRequest(&$request){
        foreach($this->pluginStack as $plugin){
            $plugin->classEntry($request, $this);
        }

        $response = $this->processRequest($request);

        foreach($this->pluginStack as $plugin){
            $plugin->classExit($response, $this);
        }

        return $response;
    }
    
    /**
     * Add a Request object to the queue list
     * @param \Symmetry\Request $request
     */
    public function addRequest(Request $request){
        $this->requestQueue[] = $request;
    }
    
    /**
     * Add a plugin interface to the plugin list
     * @param \Symmetry\Interfaces\PluginInterface $plugin
     */
    public function addPlugin(Interfaces\PluginInterface $plugin){
        $this->pluginStack[] = $plugin;
    }
    
    /**
     * Process a Request object by getting the appropriate class and function to be called.
     * Parameters are discovered and matched up with incoming parameters.
     * Optional docblock filtering happens with parameters.
     * @param \Symmetry\Request $request
     * @throws \Exception
     * @return Response
     */
    public function processRequest($request){        
        $controller = null;
        $method = $request->method;
        $reflect = null;
        $paramList = array();
        $docComment = null;
        $cacheStr = $request->nsClassName.'#'.$method;
        $methodAttr = self::fetchCache($cacheStr);//null|false|array        
        
        if(class_exists($request->nsClassName)){
            $controller = new $request->nsClassName($this,$request,$this->useResponse);
            $this->useResponse = null;//reset
            $response = $controller->getResponse();
            $parent = false;
            $paramArr = null;
            
            //determine default parent if declared via parent strategy method
            if(false !== $parent = $this->findParent($request->nsClassName, $method)){
                $response->parent = $parent;
            }
            //find default parent and params if there is a cache
            if(is_array($methodAttr)){
                if(empty($response->parent)){
                    $response->parent = $methodAttr['parent'];
                }
                $paramArr = $methodAttr['params'];
            }
            
            if(empty($methodAttr)){
                $reflClass = new \ReflectionClass($request->nsClassName);
                $defaultParent = '';
                //attempt to read method
                try{
                    $reflect = $reflClass->getMethod($request->method);
                    $paramList = $reflect->getParameters();

                    if(!empty($paramList) || empty($response->parent) || $methodAttr === false){
                        $docComment = $reflect->getDocComment();
                        if(empty($response->parent) || $methodAttr === false){
                            //check for parent declaration in method
                            if(preg_match('/\s@parent\s+(\S+)(?:\s+(\w+))?/i', $docComment, $matches)){
                                if(count($matches) > 2){
                                    $defaultParent = [$matches[1],$matches[2]];
                                } else {
                                    $defaultParent = $matches[1];
                                }                              
                            }
                        }                        
                    }
                } catch(\Exception $e){
                    if(!method_exists($controller, '__call')){
                        throw new \Exception('"'.$method.'" method does not exist.');
                    }
                }
                
                /*
                 * Find parent of class, even if magic __call method
                 */
                if(empty($defaultParent) && (empty($response->parent) || $methodAttr === false)){
                    //check for parent declaration in class
                    $classComment = $reflClass->getDocComment();
                    if(preg_match('/\s@parent\s+(\S+)(?:\s+(\w+))?/i', $classComment, $matches)){
                        if(count($matches) > 2){
                            $defaultParent = [$matches[1],$matches[2]];
                        } else {
                            $defaultParent = $matches[1];
                        }
                    } else {
                        //default to parent declaration in config                    
                        $defaultParent = Response::$defaultParent;
                        //else hard code it to a json
                        if(trim($defaultParent) == ''){
                            $defaultParent = 'json';
                        }
                    }
                }
                                
                if(empty($response->parent)){
                    $response->parent = $defaultParent;
                }
                
                //param and filter detection
                $filters = array();
                if(!empty($paramList) && $docComment){
                    //parse comment for filter string for each parameter
                    $expr = "/@param\s+[^\\$]*\\$(\S+)\s*{(?:\s*\*?\s*filter=\"(?:(?!filter=).(?:\r|\n|\r\n)?)*\",?)+\s*\*?\s*}/";
                    preg_match_all($expr, $docComment, $matches);
                    foreach($matches[1] as $key=>$param){
                        $expr = "/filter=\"((?:(?!filter=).(?:\r|\n|\r\n)?)*)\"/";
                        preg_match_all($expr, $matches[0][$key], $filterMatches);
                        foreach($filterMatches[1] as $filter){
                            $filters[$param][] = preg_replace(array("/(?:\r\n)|\r|\n/","/\s+(?:\*\s+)?/"),array('',' '),$filter);
                        }
                    }
                }
                
                $paramArr = array();
                foreach($paramList as $param){
                    $pName = $param->getName();
                    $paramArr[$pName]['default'] = null;
                    if($param->isDefaultValueAvailable()){
                        $paramArr[$pName]['default'] = $param->getDefaultValue();
                    }
                    $paramArr[$pName]['class'] = null;
                    $paramClass = $param->getClass();
                    if($paramClass){
                        $paramArr[$pName]['class'] = $paramClass->getName();
                    }
                    $paramArr[$pName]['filters'] = array();
                    if(isset($filters[$pName])){
                        $paramArr[$pName]['filters'] = $filters[$pName];
                    }
                }
                
                if($methodAttr === false){
                    $cacheVal = array('parent'=>$defaultParent, 'params'=>$paramArr);
                    self::saveCache($cacheStr, $cacheVal);
                }
                
            } else {
                //detect if method exists directly                
                if(!method_exists($controller, $method) && !method_exists($controller, '__call')){
                    throw new \Exception('"'.$method.'" method does not exist.');
                }
            }            
        } else {
            throw new \Exception('"'.$request->nsClassName.'" controller does not exist.');
        }
        
        $params = array();
        foreach($paramArr as $pName=>$val){
            $params[$pName] = $val['default'];
            if(isset($request->params[$pName]) && empty($val['class'])){
                $value = $request->params[$pName];
                foreach($val['filters'] as $filter){
                    $ff = '$filterFunction=function($value){
                               return filter_var($value,'.$filter.');
                           };';
                    eval($ff);
                    $value = $filterFunction($value);
                    if($value === false){
                        $this->error = true;
                    }
                }
                $params[$pName] = $value;
            } else if(!empty($val['class'])
                && is_subclass_of($val['class'], '\Symmetry\Request\Form')){
                $class = $val['class'];
                $params[$pName] = new $class($request);
            }
        }
        
        $response = call_user_func_array(array($controller,$method), $params);
        if(!($response instanceof Response)){
            $data = $response;
            $response = $controller->getResponse();
            if(!is_null($data)){
                if(empty($response->data) && $response->data !== false){
                    $response->data = $data;
                } else {
                    $response->data = array_merge((array)$response->data, (array)$data);
                }
            }
        }
        return $response;
    }
    
    /**
     * Instantiates a new Core thread to process requests with associated plugins
     * @param \Symmetry\Request $request
     * @param array $plugins
     * @return Response
     */
    public static function call($request,array $plugins=array()){
        $app = new Core();
        foreach($plugins as $p){
            $app->addPlugin($p);
        }
        $response = $app->getResponse($request);
        return $response;
    }
    
    /**
     * Cache to use to help speed up lookups for Symmetry to be more efficient.
     * @param \Symmetry\Interfaces\CacheInterface $cache
     * @param int $ttl
     */
    public static function registerCache(Interfaces\CacheInterface $cache, $ttl = 0){
        self::$cache = $cache;
        self::$cacheTtl = $ttl;
    }
    
    /**
     * Return cached data, first check internal array, then fallback to user provided.
     * @param string $id
     * @return type
     */
    public static function fetchCache($id){
        if(self::$cache){
            if(isset(self::$internalCache[$id])){
                return self::$internalCache[$id];
            } else {
                $value = self::$cache->fetch($id);
                if($value !== false){
                    self::$internalCache[$id] = $value;
                }
                return $value;
            }
        }
    }
    
    /**
     * Data caching, both internal array and user provided.
     * @param type $id
     * @param type $data
     * @return type
     */
    public static function saveCache($id,$data){
        if(self::$cache){
            self::$internalCache[$id] = $data;
            return self::$cache->save($id, $data, self::$cacheTtl);
        }
    }
    
    /**
     * A list of Finder interfaces to assist in finding parent connections based upon a ruleset
     * @param type $vf
     */
    public static function registerParentFinder($pf){
        self::$parentFinder[] = $pf;
    }

    /**
     * Progamattically defined strategy to resolve a parent connection
     * @param string $class
     * @param string $method
     * @return boolean|array|object
     */
    private function findParent($class, $method){
        foreach(self::$parentFinder as $pf){
            $foundParent = $pf->getParent($class, $method);
            if($foundParent !== false){
                return $foundParent;
            }
        }
        return false;
    }
}