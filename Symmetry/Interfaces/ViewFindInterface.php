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

namespace Symmetry\Interfaces;

/**
 * This interface defines how a view file will be found if not wanting to rely on defaults
 * such as finding views belonging to a specific theme.
 * @author Chuck de Sully <cdesully@webslicer.com>
 *
 */
interface ViewFindInterface {
    /**
     * $viewPath array gets passed in to inspect and provide alternative views 
     * returning false if alternatives are not found.
     * @param array $viewPath
     * @return string|false
     */
    public function getFile($viewPath);
}