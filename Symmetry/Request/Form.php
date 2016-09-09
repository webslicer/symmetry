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

namespace Symmetry\Request;
use Symmetry\Request,
    Symmetry\Core;

/**
 * This class will represent the base class for form inputs.
 * If a user passes numerous values to a function, instead of accepting numerous parameters,
 * one Input object can be accepted.  That Input object extends this class where upon
 * public properties have the option to be filtered via docblock.
 * @author Chuck de Sully <cdesully@webslicer.com>
 * @todo flesh out actions to be performed on bad filter results.
 */
abstract class Form {
    
    /** @var \Symmetry\Request Current Request that child form will get values from. */
    protected $request;
    /** @var boolean Error flag triggered by encountered errors during filter_var. */
    protected $error;
    
    /**
     * Initiate the parent logic to populate request values to child object properties
     * @param \Symmetry\Request $request
     */
    public function __construct(Request $request){
        $cacheStr = get_class($this).'#form';
        $this->request = $request;
        $this->error = false;
        $propertyList = array();
        $cache = Core::fetchCache($cacheStr);
        
        if(is_array($cache)){
            $propertyList = $cache;
        } else if(empty($cache)){
            $reflection = new \ReflectionClass($this);
            $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
            foreach($properties as $prop){
                $pName = $prop->getName();
                if(isset($request->params[$pName]) || $cache === false){
                    $filters = array();
                    $docComment = $prop->getDocComment();
                    if($docComment){
                        $filters = $this->getFilters($docComment);
                    }
                    $propertyList[$pName] = $filters;
                }
            }
            if($cache === false){
                Core::saveCache($cacheStr, $propertyList);
            }
        }
        
        foreach($propertyList as $property=>$filters){
            if(isset($request->params[$property])){
                $value = $request->params[$property];
                foreach($filters as $filter){
                    $ff = '$filterFunction=function($value){
                        return filter_var($value,'.$filter.');
                    };';
                    eval($ff);
                    $value = $filterFunction($value);
                    if($value === false){
                        $this->error = true;
                    }
                }
                $this->$property = $value;
            }
        }
        
        $this->init();
    }
    
    /**
     * A function to override, comparable to a contstruct function for child objects
     */
    public function init(){}
    
    /**
     * Determine if a form encountered an error during filtering
     * @return boolean
     */
    public function hasError(){
        return $this->error;
    }
    
    /**
     * Run a regex on a docComment to pull any filter expressions out
     * @param DocComment $docComment
     * @return array
     */
    private function getFilters($docComment){
        $expr = "/@filter=\"((?:(?!@filter).(?:\r|\n|\r\n)?)*)\"/";
        preg_match_all($expr, $docComment, $matches);
        $filters = $matches[1];
        foreach($filters as $key=>$filter){
            $filters[$key] = preg_replace(array("/(?:\r\n)|\r|\n/","/\s+(?:\*\s+)?/"),array('',' '),$filter);
        }
        return $filters;
    }
}