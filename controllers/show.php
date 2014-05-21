<?php

class ShowController extends StudipController {

    public function __construct($dispatcher) {
        parent::__construct($dispatcher);
        $this->plugin = $dispatcher->plugin;
    }

    public function before_filter(&$action, &$args) {

        $this->set_layout($GLOBALS['template_factory']->open('layouts/base_without_infobox'));
//      PageLayout::setTitle('');
    }

    public function index_action() {
        if (Request::submitted('search')) {
            $this->search = new IntelligentSearch(Request::get('search'));
            foreach ($this->search->resultTypes as $type => $results) {
                $this->addToInfobox('Typen', "$type ($results)");
            }
            $this->addToInfobox(_('Info'), sprintf(_('%s Ergebnisse in %s Sekunden'), $this->search->count, round($this->search->time, 3)));
            $this->setInfoBoxImage('sidebar/search-sidebar.png');
        }
    }
    
    public function fast_action() {
        $this->time = IndexManager::sqlIndex();
    }

    // customized #url_for for plugins
    function url_for($to) {
        $args = func_get_args();

        # find params
        $params = array();
        if (is_array(end($args))) {
            $params = array_pop($args);
        }

        # urlencode all but the first argument
        $args = array_map('urlencode', $args);
        $args[0] = $to;

        return PluginEngine::getURL($this->dispatcher->plugin, $params, join('/', $args));
    }

}
