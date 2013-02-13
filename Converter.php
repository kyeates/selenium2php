<?php
/*
 * Copyright 2013 Rnix Valentine
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace Selenium2php;

/**
 * Converts HTML text of Selenium test case recorded from Selenium IDE into
 * PHP code for PHPUnit_Extensions_SeleniumTestCase as TestCase file.
 */
class Converter {
    
    protected $_testName = '';
    protected $_testUrl = '';
    
    protected $_defaultTestName = 'some';
    protected $_defaultTestUrl = 'http://example.com';
    
    protected $_commands = array();
    
    protected $_tplEOL = PHP_EOL;
    protected $_tplFirstLine = '<?php';
    
    /**
     * Array of strings with text before class defenition
     * @var array 
     */
    protected $_tplPreClass = array();
    protected $_tplClassPrefix = '';
    protected $_tplParentClass = 'PHPUnit_Extensions_SeleniumTestCase';
    
    /**
     * Array of strings with some methods in class
     * @var array
     */
    protected $_tplAdditionalClassContent = array();
    
    protected $_browser = '*firefox';
    
    /**
     * Address of Selenium Server
     * @var string
     */
    protected $_remoteHost = '';
    
    /**
     * Port of Selenium Server
     * @var type 
     */
    protected $_remotePort = '';

    /**
     * Parses HTML string into array of commands, 
     * determines testHost and testName. 
     * 
     * @param string $htmlStr
     * @throws \Exception
     */
    protected function _parseHtml($htmlStr){
        require_once 'libs/simple_html_dom.php';
        $html = str_get_html($htmlStr);
        if ($html){
            
            if (!$this->_testUrl){
                $this->_testUrl = $html->find('link', 0)->href;
            }
            if (!$this->_testName) {
                $this->_testName = $html->find('title', 0)->innertext;
            }
            
            foreach ($html->find('table tr') as $row) {
                if ($row->find('td', 2)) {
                    $command = $row->find('td', 0)->innertext;
                    $target = $row->find('td', 1)->innertext;
                    $value = $row->find('td', 2)->innertext;

                    $this->_commands[] = array(
                        'command' => $command,
                        'target' => $target,
                        'value' => $value
                    );
                }
            }
            
        } else {
            throw new \Exception("HTML parse error");
        }
    }    
    
    /**
     * Converts HTML text of Selenium test case into PHP code
     * 
     * @param string $htmlStr content of html file with Selenium test case
     * @return string PHP test case file content
     */
    public function convert($htmlStr){
        $this->_parseHtml($htmlStr);
        $lines = $this->_composeLines();
        return $this->_composeStr($lines);
    }
    
    /**
     * Implodes lines of file into one string
     * 
     * @param array $lines
     * @return string
     */
    protected function _composeStr($lines){
        return implode($this->_tplEOL, $lines);
    }
    
    protected function _composeLines() {
        $lines = array();

        $lines[] = $this->_tplFirstLine;
        $lines[] = $this->_composeComment();
        
        if (count($this->_tplPreClass)) {
            $lines[] = "";
            foreach ($this->_tplPreClass as $mLine) {
                $lines[] =  $mLine;
            }
            $lines[] = "";
        }
        
        $lines[] = "class " . $this->_composeClassName() . " extends " . $this->_tplParentClass . "{";
        $lines[] = "";
        
        if (count($this->_tplAdditionalClassContent)) {
            foreach ($this->_tplAdditionalClassContent as $mLine) {
                $lines[] = $this->_indent(4) . $mLine;
            }
            $lines[] = "";
        }
        
        
        $lines[] = $this->_indent(4) . "function setUp(){";
        foreach ($this->_composeSetupMethodContent() as $mLine){
            $lines[] = $this->_indent(8) . $mLine;
        }
        $lines[] = $this->_indent(4) . "}";
        $lines[] = "";
        
        
        $lines[] = $this->_indent(4) . "function " . $this->_composeTestMethodName() . "(){";
        foreach ($this->_composeTestMethodContent() as $mLine){
            $lines[] = $this->_indent(8) . $mLine;
        }
        $lines[] = $this->_indent(4) . "}";
        $lines[] = "";
        
        
        $lines[] = "}";
        
        return $lines;
    }
    
    protected function _indent($size){
        return str_repeat(" ", $size);
    }
    
    protected function _prepareName(){
        return preg_replace('/[^A-Za-z0-9]/', '_', ucwords($this->_testName));
    }
    
    protected function _composeClassName(){
        return $this->_tplClassPrefix . $this->_prepareName() . "Test";
    }
    
    protected function _composeTestMethodName(){
        return "test" . $this->_prepareName();
    }
    
    protected function _composeSetupMethodContent(){
        $mLines = array();
        $mLines[] = '$this->setBrowser("' . $this->_browser . '");';
        if ($this->_testUrl){
            $mLines[] = '$this->setBrowserUrl("' . $this->_testUrl . '");';
        } else{
            $mLines[] = '$this->setBrowserUrl("' . $this->_defaultTestUrl . '");';
        }
        if ($this->_remoteHost) {
            $mLines[] = '$this->setHost("' . $this->_remoteHost . '");';
        }
        if ($this->_remotePort) {
            $mLines[] = '$this->setHost("' . $this->_remotePort . '");';
        }
        return $mLines;
    }
    
    protected function _composeTestMethodContent(){
        require_once 'Commands.php';
        $commands = new Commands;
        $mLines = array();
        
        
        foreach ($this->_commands as $row){
            $command = $row['command'];
            $target  = html_entity_decode(str_replace('&nbsp;', ' ', $row['target']));
            $value   = $row['value'];
            $res = $commands->$command($target, $value);
            if (is_string($res)){
                $mLines[] = $res;
            } else if (is_array($res)){
                foreach ($res as $subLine){
                    $mLines[] = $subLine;
                }
            }
            
        }
        
        return $mLines;
    }
    
    protected function _composeComment(){
        $lines = array();
        $lines[] = "/*";
        $lines[] = "* Autogenerated from Selenium html test case by Selenium2php.";
        $lines[] = "* " . date("Y-m-d H:i:s");
        $lines[] = "*/";
        $line = implode($this->_tplEOL, $lines);
        return $line;
    }
    
    public function setTestName($testName){
        $this->_testName = $testName;
    }
    
    public function setTestUrl($testUrl){
        $this->_testUrl = $testUrl;
    }
    
    public function setRemoteHost($host){
        $this->_remoteHost = $host;
    }
    
    public function setRemotePort($port){
        $this->_remotePort = $port;
    }
    
    /**
     * Sets browser where test runs
     * 
     * @param string $browser example: *firefox 
     */
    public function setBrowser($browser){
        $this->_browser = $browser;
    }
    
    /**
     * Sets lines of text before test class defenition
     * @param string $text
     */
    public function setTplPreClass($linesOfText){
        $this->_tplPreClass = $linesOfText;
    }
    
    /**
     * Sets lines of text into test class
     * 
     * @param array $content - array of strings with methods or properties
     */
    public function setTplAdditionalClassContent($linesOfText){
        $this->_tplAdditionalClassContent = $linesOfText;
    }
    
    /**
     * Sets name of class as parent for test class
     * Default: PHPUnit_Extensions_SeleniumTestCase
     * 
     * @param string $className
     */
    public function setTplParentClass($className){
        $this->_tplParentClass = $className;
    }
    
    public function setTplClassPrefix($prefix){
        $this->_tplClassPrefix = $prefix;
    }
}