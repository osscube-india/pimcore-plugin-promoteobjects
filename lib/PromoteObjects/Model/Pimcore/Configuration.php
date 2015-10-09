<?php
namespace PromoteObjects\Model\Pimcore;

class Configuration
{

    public static function getConfig()
    {
        $configuration = array();
        
        if (file_exists(PIMCORE_PLUGINS_PATH . "/PromoteObjects/plugin.xml")) {
            $pluginConf = new \Zend_Config_Xml(PIMCORE_PLUGINS_PATH . "/PromoteObjects/plugin.xml");
            $configuration = $pluginConf->plugin->toArray();
        }
        return $configuration;
    }
}

?>