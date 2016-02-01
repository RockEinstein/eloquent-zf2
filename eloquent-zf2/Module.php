<?php
/**
 * EloquentZF2 (https://github.com/RockEinstein/Eloquent-ZF2)
 * Eloquent ORM Module for Zend Framework 2 which integrates Illuminate\Database
 * from Laravel Framework with ZF2.
 *
 * @link      https://github.com/RockEinstein/Eloquent-ZF2
 * @copyright Copyright (c) 2014 Edvinas Klovas
 * @license   http://opensource.org/licenses/MIT MIT License
 * @author    Edvinas Klovas <edvinas@pnd.io> 2014
 * @author    Anderson Luciano <andersonlucianodev@gmail.com> 2016
 */

namespace EloquentZF2;

use Illuminate\Database\Capsule\Manager as Capsule;
use Zend\Mvc\MvcEvent;

/**
 * Module class is used as a requirements for Zend Framework2 ModuleManager.r
 */
class Module
{
    /**
     * getAutoloaderConfig is called automatically and it returns an array for
     * Zend Framework 2 AutloaderFactory. You can configure it to add classmap
     * file o the ClassMapAutoloader and module's namespace to the
     * StandardAutoloader.
     *
     * @return void
     */
    public function getAutoloaderConfig()
    {
        // autoloader config
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    } // getAutoloaderConfig()

    /**
     * onBootstrap method is called on every page request and should only
     * be used to do module-specific configuration or setup event listeners
     * for the module.
     *
     * @param MvcEvent $e e
     *
     * @return void
     */
    public function onBootstrap(MvcEvent $e)
    {
        // inspect configuration and check if database connection variable is
        $sm = $e->getApplication()->getServiceManager();

        // get Config service instance
        $config = $sm->get('Config');

        // try to initialize database if configuration is set
        if ($config['database_eloquent']) {

            // Create Capsule manager instance
            // Capsule aims to make configuring the library for usage outside of
            // the Laravel framework as easy as possible
            $capsule = new Capsule;

            // add connection
            $capsule->addConnection($config['database_eloquent']);

            // make this Capsule instance available globally via static methods
            $capsule->setAsGlobal();

            // boot Eloquent ORM
            $capsule->bootEloquent();

        }

    } // onBootstrap()


    /**
     * getServiceConfig is called automatically and it returns an array for
     * Zend Framework 2 ServiceManager.
     *
     * @return void
     */
    public function getServiceConfig()
    {
        return array(
            'factories' => array(
                'EloquentZF2' =>  function($sm) {
                    // Create Capsule manager instance
                    // Capsule aims to make configuring the library for usage outside of
                    // the Laravel framework as easy as possible
                    $capsule = new Capsule;

                    $config = $sm->get('Config');
                    if ($config['database_eloquent']) {
                        // add connection
                        $capsule->addConnection($config['database_eloquent']);
                        // TODO: multiple connection support
                    }
                    // TODO: make configurable parameters in eloquent config
                    // make this Capsule instance available globally via static methods
                    $capsule->setAsGlobal();

                    // boot Eloquent ORM
                    $capsule->bootEloquent();

                    return $capsule;
                },
            ),
        );
    }

}
