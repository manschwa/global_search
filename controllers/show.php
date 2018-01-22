<?php
//require "../../../plugins_packages/intelec/Podium/Podium.php";

class ShowController extends StudipController
{
    /**
     * ShowController constructor.
     * @param $dispatcher
     */
    public function __construct($dispatcher)
    {
        parent::__construct($dispatcher);
        $this->plugin = $dispatcher->plugin;
        //$this->search = new GlobalSearch();
    }

    /**
     * Checks if a new search was initiated.
     *
     * @param $action
     * @param $args
     * @return bool|void
     */
    public function before_filter(&$action, &$args)
    {
        $this->set_layout($GLOBALS['template_factory']->open('layouts/base'));
        // Find query
        $this->query = Request::get('utf8') ? studip_utf8decode(Request::get('search')) : Request::get('search');
        if ($this->query || Request::submitted('search') || Request::submitted('searching')) {
            if ($_SESSION['global_search']['query'] !== $this->query) {
                $this->resetFacetFilters();
            }
            $_SESSION['global_search']['query'] = $this->query;
        }
    }

    /**
     * Checks if the 'reset'-button was pressed and adds the Sidebar.
     */
    public function index_action()
    {
        Helpbar::get()->addPlainText(_('Allgemein'), _('Foobar.'));
        Helpbar::get()->addPlainText(_('Dateisuche'), _('Die Dateisuche kann über einen Schrägstrich (/) verfeinert werden. Beispiel: "Meine Veranstaltung/Datei" zeigt alle Dateien die das Wort "Datei" enthalten und in "Meine Veranstaltung" sind an. Die Veranstaltung kann auch auf einen Teil (z.B. Veran/Datei) oder auf die Großbuchstaben bzw. auch deren Abkürzung (z.B. MV/Datei oder V/Datei) beschränkt werden.'));
        Helpbar::get()->addPlainText(_('Platzhalter'), _('_ ist Platzhalter für ein beliebiges Zeichen. % ist Platzhalter für beliebig viele Zeichen. Me_er findet Treffer für Meyer und Meier. M__er findet zusätzlich auch Mayer und Maier. M%er findet alle vorherigen Treffer aber auch Münchner.'));

        //var_dump(Podium::getTypes());die;
        if (Request::submitted('reset')) {
            $_SESSION['global_search'] = null;
        }
        $this->addSearchSidebar();
    }

    /**
     * This action is called upon initial plugin install. It starts the indexing of the database
     * and creates/fills the search_index and search_object tables.
     *
     * @param null $restriction
     */
    public function indexing_action($restriction = null)
    {
        $GLOBALS['perm']->check('root');
        $this->time = IndexManager::sqlIndex($restriction);
        $this->redirect($this->url_for('show/index?search=' . $_SESSION['global_search']['query']));
    }

    /**
     * Adds the Sidebar containing the categories and their optional filters.
     */
    private function addSearchSidebar()
    {
        $sidebar = Sidebar::get();
        $sidebar->setImage('sidebar/search-sidebar.png');

        // add the semester filter on the top level
        // or the category-filter for a chosen category
//        if ($type = $_SESSION['global_search']['category']) {
//            $class = $this->search->getClass($type);
//            $object = new $class;
//            $filter_widget = $this->getFacetsWidget($object);
//        } else {
            $filter_widget = $this->getSemesterFilterWidget();
//        }

        // initiate the search
//        if ($_SESSION['global_search']['query'] || $_SESSION['global_search']['category']) {
//            $this->search->query($_SESSION['global_search']['query'], $this->getCategoryFilter());
//        }

        // display categories and (their optional) filters
        $category_widget = $this->getCategoryWidget();
        $sidebar->addWidget($category_widget);
        if ($filter_widget) {
            $sidebar->addWidget($filter_widget);
        }

        // display runtime
//        if (Studip\ENV == 'development' && $this->search->time && $GLOBALS['perm']->have_perm('admin')) {
//            $sidebar->addWidget($this->getRuntimeWidget());
//        }
    }

    /**
     * Build a LinksWidget for the sidebar to filter out a specific category from your search results.
     * There can only be one category selected at a time.
     *
     * @return LinksWidget containing all categories included in the search result.
     */
    private function getCategoryWidget()
    {
        $category_widget = new LinksWidget();
        $category_widget->addCSSClass('sidebar-views');
//        $result_count = $this->search->count ? " ({$this->search->count})" : '';
        $category_widget->setTitle(_('Ergebnisse'));

        $link_name = _('Alle Ergebnisse') . ($this->getCategoryFilter() ? '' : $result_count);
        $reset_element = new LinkElement($link_name,
            $this->url_for('show/reset_category_filter'));
        if ($this->getCategoryFilter() === $type) {
            $reset_element->setActive();
        }
        $category_widget->addElement($reset_element);
//
//        // list all possible categories as Links
//        $index_object_types = $this->search->getIndexObjectTypes();
//        foreach ($index_object_types as $type) {
//            $class = $this->search->getClass($type);
//            $object = new $class;
//            $category_widget->addElement($this->categoryLink($type, $object));
//        }
        return $category_widget;
    }

    /**
     * Composes a link to filter for a given category ($type).
     *
     * @param $type string: the category
     * @param $object IndexObject to get the correct type
     * @return LinkElement
     */
    private function categoryLink($type, $object)
    {
//        $facet_count = ($this->search->resultTypes[$type] ? " ({$this->search->resultTypes[$type]})" : '');
//        $link_element = new LinkElement($object::getType() . $facet_count,
//            $this->url_for('show/set_category_filter/' . $type));
//
//        if ($this->getCategoryFilter() === $type) {
//            $link_element->setActive(true);
//        }
//        return $link_element;
    }

    /**
     * Build an OptionsWidget for the sidebar to choose category specific filters for your search results.
     * The filter options shown depend on the chosen category.
     * There can be more than one filter selected per category.
     *
     * @param $object
     * @return OptionsWidget containing category specific filter options.
     */
    private function getFacetsWidget($object)
    {
//        $options_widget = new OptionsWidget;
//        $options_widget->setTitle(_('Filtern nach'));
//
//        // Select-Filters
//        if ($select_filters = $object->getSelects()) {
//            foreach ($select_filters as $name => $selects) {
//                $selected = $_SESSION['global_search']['selects'][$name];
//                $options_widget->addElement(new WidgetElement($name));
//                $options_widget->addSelect($name,                       // Label
//                    $this->url_for('show/set_select/' . $name),         // URL
//                    $name,                                              // Name
//                    $selects,                                           // all options
//                    // need to do this because of implicit type conversion (string to int in associative array)
//                    preg_match('/^[1-9][0-9]*$/', $selected) ? (int)$selected : $selected);      // selected option
//            }
//        }
//        // Facet-Filters (checkboxes)
//        if ($filter_options = $object->getFacets()) {
//            if ($this->search->getActiveFilters()) {
//                $reset_element = new LinkElement(_('Auswahl aufheben'), $this->url_for('show/reset_filter'));
//                $options_widget->addElement($reset_element);
//            }
//
//            foreach ($filter_options as $facet) {
//                $options_widget->addCheckbox($facet,                            // Name
//                    $_SESSION['global_search']['facets'][$facet],               // state
//                    $this->url_for('show/set_facet/' . $facet . '/' . true),    // check action
//                    $this->url_for('show/set_facet/' . $facet . '/' . false));  // uncheck action
//            }
//        }
//        return $options_widget;
    }

    /**
     * This is kind of an extra filter which is not bound to any category/type.
     * This semester filter is visible on the top level and will be propagated
     * to semester-sensitive categories.
     *
     * @return OptionsWidget
     */
    private function getSemesterFilterWidget()
    {
//        $options_widget = new OptionsWidget;
//        $options_widget->setTitle(_('Semesterfilter'));
//        $index_object = new IndexObject();
//        $semesters = $index_object->getSemesters();
//        $name = _('Semester');
//        $selected = $_SESSION['global_search']['selects'][$name];
//        $options_widget->addSelect($name,                       // Label
//            $this->url_for('show/set_select/' . $name),         // URL
//            $name,                                              // Name
//            $semesters,
//            // need to do this because of implicit type conversion (string to int in associative array)
//            preg_match('/^[1-9][0-9]*$/', $selected) ? (int)$selected : $selected);      // selected option
//        return $options_widget;
    }

    /**
     * Generates a SidebarWidget to display the time needed to find the search results.
     * It's only visible for root or in develop mode.
     *
     * @return SidebarWidget
     */
    private function getRuntimeWidget()
    {
//        $runtime_widget = new SidebarWidget();
//        $runtime_widget->setTitle(_('Laufzeit'));
//        $runtime_widget->addElement(new WidgetElement($this->search->time));
//        return $runtime_widget;
    }

    /**
     * Getting the category type that should be shown in the search.
     *
     * @return String: category type
     */
    public function getCategoryFilter()
    {
        return $_SESSION['global_search']['category'];
    }

    /**
     * Set the selected category specific search filter and store the selection in the $_SESSION variable.
     *
     * @param $type string
     * @param null $filter string
     * @param bool $state
     * @throws Trails_DoubleRenderError
     */
    public function set_facet_action($facet = null, $state = true)
    {
        // store facet filter in $_SESSION
        if (!is_null($facet)) {
            $_SESSION['global_search']['facets'][$facet] = (bool)$state;
        }
        $this->redirect($this->url_for('show/index?search=' . $_SESSION['global_search']['query']));
    }

    /**
     * @param null $name
     * @throws Trails_DoubleRenderError
     */
    public function set_select_action($name = null)
    {
        // store facet filter in $_SESSION
        if (!is_null($name)) {
            $_SESSION['global_search']['selects'][$name] = Request::option($name);
        }
        $this->redirect($this->url_for('show/index?search=' . $_SESSION['global_search']['query']));
    }

    /**
     * Set the category (highest level of the search) that should be searched for.
     *
     * @param null $category string: category type
     * @throws Trails_DoubleRenderError
     */
    public function set_category_filter_action($category = null)
    {
        // store category filter in $_SESSION
        if (!is_null($category)) {
            $this->resetFacetFilters();
            $this->resetSelectFilters();
            $_SESSION['global_search']['category'] = $category;
        }
        $this->redirect($this->url_for('show/index?search=' . $_SESSION['global_search']['query']));
    }

    public function reset_category_filter_action() {
        $this->resetCategoryFilter();
        $this->redirect($this->url_for('show/index?search=' . $_SESSION['global_search']['query']));
    }

    public function reset_filter_action() {
        $this->resetFacetFilters();
        $this->redirect($this->url_for('show/index?search=' . $_SESSION['global_search']['query']));
    }

    private function resetSelectFilters()
    {
        $name = _('Semester');
        $semester = $_SESSION['global_search']['selects'][$name];
        $_SESSION['global_search']['selects'] = array();
        $_SESSION['global_search']['selects'][$name] = $semester;
    }

    private function resetFacetFilters()
    {
        $_SESSION['global_search']['facets'] = array();
    }

    private function resetCategoryFilter()
    {
        $this->resetFacetFilters();
        $this->resetSelectFilters();
        $_SESSION['global_search']['category'] = null;
    }

    /**
     * Customized #url_for-method for plugins.
     *
     * @param string $to
     * @return a valid URL
     */
    public function url_for($to = '')
    {
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
