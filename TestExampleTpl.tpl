<?php
{$comment}
class S2p_{$className} extends PHPUnit_Extensions_SeleniumTestCase{

    function setUp(){
        $this->setBrowser("{$browser}");
        $this->setBrowserUrl("{$testUrl}");
        $this->setHost("{$remoteHost}");
        $this->setPort({$remotePort});
    }

    function {$testMethodName}(){
        {$testMethodContent}
    }

}