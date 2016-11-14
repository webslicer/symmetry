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
 * Static helper class to assist in Symmetry initialization
 * @author Chuck de Sully <cdesully@webslicer.com>
 */
class Setup {
    
    /**
     * static function to pass a config array into to pre-load custom values and 
     * file paths for Symmetry setup
     * @param array $config
     * @throws \Exception
     */
    public static function init($config){
        if (!defined('SYMMETRY_ROOT')) {
            define('SYMMETRY_ROOT', __DIR__ . DIRECTORY_SEPARATOR);
            require(SYMMETRY_ROOT . 'Psr4Autoloader.php');
        }
        $loader = new Psr4Autoloader();
        $loader->addNamespace('Symmetry', SYMMETRY_ROOT);
        $loader->register();
        if(!empty($config['autoload'])){
            foreach($config['autoload'] as $ns=>$path){
                if(is_array($path)){
                    foreach($path as $path2){
                        $loader->addNamespace($ns, $path2);
                    }
                } else {
                    $loader->addNamespace($ns, $path);
                }
            }
        }
        
        if(isset($config['request']['default_method'])){
            Request::$defaultMethod = $config['request']['default_method'];
        }
        if(isset($config['request']['default_class'])){
            Request::$defaultClass = $config['request']['default_class'];
        }
        if(!isset($config['request']['default_namespace'])){
            $msg = 'This is the root namespace where your controllers are located.';
            throw new \Exception('Missing $config[\'request\'][\'default_namespace\'] '.$msg);
        }
        Request::$defaultNamespace = $config['request']['default_namespace'];
        if(isset($config['request']['base_path'])){
            Request::$basePath = $config['request']['base_path'];
        }        
        if(isset($config['request']['enable_https'])){
            Request::$useHttps = $config['request']['enable_https'];
        }
        
        if(isset($config['response']['default_parent'])){
            Response::$defaultParent = $config['response']['default_parent'];
        } //else json is assigned
        
        if(!isset($config['response']['view_directory'])){
            $msg = 'This is the real filepath to the root folder of your views';
            throw new \Exception('Missing $config[\'response\'][\'view_directory\'] '.$msg);
        }
        Response::$defaultViewArr['directory'] = $config['response']['view_directory'];
        if(!isset($config['response']['exception_view'])){
            $msg = 'Starting from but not counting the root view folder,'
                . ' this is the filepath to a view that can receive'
                . ' an exception error message coming from the framework.';
            throw new \Exception('Missing $config[\'response\'][\'exception_view\'] '.$msg);
        }
        Core::$defaultExceptionView = $config['response']['exception_view'];
    }
    
    /**
     * Registering a caching scenario with Core for cutting down on Reflection
     * @param \Symmetry\Interfaces\CacheInterface $cacheObj
     * @param int $ttl time to live in seconds
     */
    public static function registerCache($cacheObj, $ttl){
        Core::registerCache($cacheObj, $ttl);
    }
    
    /**
     * Registering an alternative strategy to finding parent response objects
     * @param \Symmetry\Interfaces\ParentFindInterface $strategy
     */
    public static function registerParentFinder($strategy){
        Core::registerParentFinder($strategy);
    }
    
    /**
     * Registering an alternative strategy to finding view files for response objects
     * @param \Symmetry\Interfaces\ViewFindInterface $strategy
     */
    public static function registerViewFinder($strategy){
        Response::registerViewFinder($strategy);
    }
    
}