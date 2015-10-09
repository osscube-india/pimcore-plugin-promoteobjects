<?php
namespace PromoteObjects;

use Pimcore\API\Plugin as PluginLib;

class Plugin extends PluginLib\AbstractPlugin implements PluginLib\PluginInterface
{

    /**
     * Initialize plugin setting
     */
    public function init()
    {
        // Add new PromoteList class if does not exist
        $install = new Plugin\Install();
        $install->createPluginClass();
    }

    /**
     * Install plugin
     */
    public static function install()
    {
        $path = self::getInstallPath();
        
        if (! is_dir($path)) {
            mkdir($path);
        }
        
        if (self::isInstalled()) {
            $install = new Plugin\Install();
            $install->setup();
            return "PromoteObjects Plugin successfully installed.";
        } else {
            return "PromoteObjects Plugin could not be installed";
        }
    }

    /**
     * Uninstall plugin
     */
    public static function uninstall()
    {
        rmdir(self::getInstallPath());
        
        if (! self::isInstalled()) {
            return "PromoteObjects Plugin successfully uninstalled.";
        } else {
            return "PromoteObjects Plugin could not be uninstalled";
        }
    }

    /**
     * Validate either plugin is installed or not.
     */
    public static function isInstalled()
    {
        return is_dir(self::getInstallPath());
    }

    /**
     * Get plugin installed "install" folder
     */
    public static function getInstallPath()
    {
        return PIMCORE_PLUGINS_PATH . "/PromoteObjects/install";
    }
}
