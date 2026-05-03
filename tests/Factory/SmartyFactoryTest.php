<?php

use Factory\SmartyFactory;
use PHPUnit\Framework\TestCase;

class SmartyFactoryTest extends TestCase
{
    public function testCreateInstance(): void
    {
        $smarty = SmartyFactory::getInstance();

        $this->assertInstanceOf('\Smarty', $smarty);
    }
}

