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

namespace Symmetry\Interfaces;

/**
 * Interface to define Plugin objects that will be called during different points of the
 * Request/Response cycle
 * @author Chuck de Sully <cdesully@webslicer.com>
 *
 */
interface PluginInterface {
    /**
     * Setup area before Controllers are sought out.
     * @param \Symmetry\Core $app
     */
    public function applicationEntry($app);
    
    /**
     * Area to inspect a Request and possibly altering it.
     * @param \Symmetry\Request $request
     * @param \Symmetry\Core $app
     */
    public function classEntry(&$request, $app);
    
    /**
     * Area to inspect a Response and possible altering it.
     * @param \Symmetry\Response $response
     * @param \Symmetry\Core $app
     */
    public function classExit(&$response, $app);
    
    /**
     * Area for possible cleanup after the Request/Response cycle has finished
     * and before the parent architecture is built out.
     * @param \Symmetry\Response $response
     * @param \Symmetry\Core $app
     */
    public function applicationExit(&$response, $app);
}