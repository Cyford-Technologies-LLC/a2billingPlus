<?php

namespace Factory;


use Contracts\Factory\Factory;
use Smarty_Security;

class SmartyFactory implements Factory{

    /**
     * @return \Smarty
     * @throws \SmartyException
     */
    public static function getInstance()
    {
        $smarty = new \Smarty();

        $securityPolicy = new Smarty_Security($smarty);
        $smarty->enableSecurity($securityPolicy);

        return $smarty;
    }
}
