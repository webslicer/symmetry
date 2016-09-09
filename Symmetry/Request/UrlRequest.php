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

namespace Symmetry\Request;
use Symmetry\Request,
    Symmetry\Response;

/**
 * The class takes an incoming GET/POST and pattern matches to find the intended namespace/class/function to call.
 * @author Chuck de Sully <cdesully@webslicer.com>
 *
 */
class UrlRequest extends Request{
    
    /**
     * Initializing a Request by either passing in an intended URL or if empty 
     * getting values from the $_SERVER values.
     * @param string|null $url
     * @param array|null $params
     * @param string|null $requestMethod
     */
    public function __construct($url=null,$params=null,$requestMethod=null){        
        $fromServer = false;
        if(empty($url) && !empty($_SERVER['REQUEST_URI'])){
            $fromServer = true;
            $url = $_SERVER['REQUEST_URI'];
            $params = $_REQUEST;
            $requestMethod = strtolower($_SERVER['REQUEST_METHOD']);
        }
        $url = parse_url($url,PHP_URL_PATH);
        
        //utilize shortened versions
        if(isset($params['ctx']) && !isset($params['context'])){
            $params['context'] = $params['ctx'];
        }
        
        if(isset($params['lvl']) && !isset($params['levelUp'])){
            $params['levelUp'] = $params['lvl'];
        }
        
        //normalize url by stripping basePath and other cleanup
        if(!empty(self::$basePath) && strpos($url, self::$basePath) === 0){
            $subUrl = substr($url, strlen(self::$basePath));
            if(empty($subUrl)){
                $url = '/';
            } else if(strpos($subUrl,'/') === 0){
                $url = $subUrl;
            } //else basepath happens to be a subpart of a dir name
        }        
        $url = str_replace('-', '_', preg_replace('/\/{2,}/', '/', $url));

        $pathInfo = pathinfo($url);
        
        if(empty($params['context'])){
            $isAjax = ($fromServer && isset($_SERVER['HTTP_X_REQUESTED_WITH']) 
                && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
                ? true : false;
            if(!empty($pathInfo['extension'])){
                if(false !== $pos = strpos($pathInfo['filename'], '.')){
                    $media = substr($pathInfo['filename'], $pos+1);
                    $pathInfo['filename'] = substr($pathInfo['filename'], 0, $pos);
                    $pathInfo['extension'] = $media . '.' . $pathInfo['extension'];
                }
                $params['context'] = strtolower($pathInfo['extension']);
                if(!$isAjax && $params['context'] == Response::$defaultViewArr['media']
                    && (!isset($params['levelUp']) || !is_numeric($params['levelUp']))){
                    $params['levelUp'] = -1;//show layout
                }
            } else if($isAjax){
                $params['context'] = 'json';
            }
        } else {
            //clean up filename
            if(false !== $pos = strpos($pathInfo['filename'], '.')){
                $pathInfo['filename'] = substr($pathInfo['filename'], 0, $pos);
            }
        }
        
        $class = self::$defaultClass;
        $method = self::$defaultMethod;
        if(!empty($pathInfo['dirname']) && $pathInfo['dirname'] !== DIRECTORY_SEPARATOR){
            $class = $pathInfo['dirname'];
            $method = $pathInfo['filename'];
        } else {
            if(!empty($pathInfo['filename'])){
                $class = $pathInfo['filename'];                
            }
        }
        
        $class = trim(str_replace(' ','',ucwords(str_replace(array('_','/'), array(' ',' \ '), $class))),'\\');
        $method = lcfirst(str_replace(' ','',ucwords(str_replace('_', ' ', $method))));
        
        $this->requestCheck($method, $class);
        
        parent::__construct($method, $class, $params, $requestMethod);           
    }
}
