<?php
namespace PromoteObjects\Plugin;

use PromoteObjects\Model\Utility;

class Install
{

    private static $_instance;
    private $_utilityObj;

    public function __construct()
    {
        $this->setUtilityObj(Utility::getResources());
    }

    /**
     *
     * @return the $_utilityObj
     */
    public function getUtilityObj()
    {
        return $this->_utilityObj;
    }

    /**
     *
     * @param field_type $_utilityObj            
     */
    private function setUtilityObj($_utilityObj)
    {
        $this->_utilityObj = $_utilityObj;
    }

    /**
     * Get utility class object
     */
    public static function getResources()
    {
        if(!empty(self::$_instance)){
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Add class
     */
    public function createPluginClass()
    {
        $this->getUtilityObj()->addClass();
    }

    /**
     * Import class definition
     */
    public function setup()
    {
        $this->getUtilityObj()->importClass();
    }
}
