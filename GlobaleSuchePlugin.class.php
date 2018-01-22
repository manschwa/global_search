<?php

require 'bootstrap.php';

/**
 * GlobaleSuchePlugin.class.php
 */
class GlobaleSuchePlugin extends StudIPPlugin implements SystemPlugin {

    public function __construct() {
        parent::__construct();
        $this->setupAutoload();
        $navigation = new AutoNavigation(_('Globale Suche'));
        $navigation->setURL(PluginEngine::GetURL($this, array(), 'show/index'));

        //Insert even before courses search
        Navigation::insertItem('/search/suche', $navigation, 'courses');

        // Take over search button
        Navigation::getItem('/search')->setURL(PluginEngine::GetURL($this, array(), 'show/index'));

        PageLayout::addStylesheet($this->getPluginURL() . '/assets/globalsearch.css');
        PageLayout::addScript('globalsearch.js');
        PageLayout::addScript('search.js');
    }

    public function initialize() {
    }

    public function perform($unconsumed_path) {

        $dispatcher = new Trails_Dispatcher(
                $this->getPluginPath(), rtrim(PluginEngine::getLink($this, array(), null), '/'), 'show'
        );
        $dispatcher->plugin = $this;
        $dispatcher->dispatch($unconsumed_path);
    }

    private function setupAutoload() {
        if (class_exists("StudipAutoloader")) {
            StudipAutoloader::addAutoloadPath(__DIR__ . '/models');
        } else {
            spl_autoload_register(function ($class) {
                include_once __DIR__ . $class . '.php';
            });
        }
    }

    public static function onEnable($pluginId) {
        parent::onEnable($pluginId);
        $_SESSION['global_search'] = array();
    }

    public static function onDisable($pluginId) {
        parent::onDisable($pluginId);
        $_SESSION['global_search'] = array();
    }

}
