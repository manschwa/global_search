<?php

/**
 * Class GlobalSearch
 * Main class for the search. Contains all relevant query building search functions.
 */
class GlobalSearch extends SearchType {

    public $query;
    private $category_filter;
    public $results = array();
    public $resultTypes = array();
    public $time = 0;
    public $count = 0;
    public $error;
    private $resultsPerPage = 10;
    private $pages_shown = 10;
    private $minLength = 4;
    private $limit = 100;

    /**
     * This function is called first by the show.php controller and
     * starts the search and calls $this->search().
     *
     * @param $query string: search string entered by the user
     * @param null $category_filter: string of a category if selected
     */
    public function query($query, $category_filter = null)
    {
        $this->query = $query;
        $this->category_filter = $category_filter;
        if (($this->query && strlen($query) >= $this->minLength) || $this->category_filter) {
            $this->search($this->category_filter);
        } else {
            $this->error = _('Der eingegebene Suchbegriff ist zu kurz');
        }
    }

    /**
     * Function to call $this->getResultSet() and compose the results for the user.
     *
     * @param null $category: filters the results for the given category
     */
    private function search($category = null)
    {
        // Timecapture
        $time = microtime(1);

        $results = $this->getResultSet($category);

        // determine which SQL records (found objects) should be shown to the user
        // (and adding them to $this->results)
        foreach ($results as $result) {
                $class = self::getClass($result['type']);
                $result['name'] = $class::getType();
                $result['link'] = $class::getLink($result);
                    $this->results[] = $result;
                    $this->resultTypes[$result['type']]++;
                    $this->count++;
        }

        $this->time = microtime(1) - $time;
    }

    /**
     * Method to build the SQL-query string and return the result set from the DB.
     *
     * @param $type string relevant if a category type is given for the search
     * @return object statement result set of the search
     */
    private function getResultSet($type)
    {
        $is_root = $GLOBALS['perm']->have_perm('root');
        $search = $this->getSearchQuery($this->query);
        $statement = DBManager::get()->prepare("SELECT search_object.*, text FROM search_object JOIN "
            . $search . " USING (object_id) WHERE " . ($type ? (' type = :type ') : ' 1 ') . " GROUP BY object_id ");

//        if ($type) {
//            $class = $this->getClass($type);
//            $object = new $class;
//            if (method_exists($object, 'getSearchParams')) {
//                $search_params = $object->getSearchParams();
//            }
//        } else if ($semester = $_SESSION['global_search']['selects']['Semester']) {
//            $search_params['joins'] = " LEFT JOIN dokumente ON  dokumente.dokument_id = search_object.range_id "
//                                    . " LEFT JOIN seminare as ds ON dokumente.seminar_id = ds.Seminar_id "
//                                    . " LEFT JOIN forum_entries ON forum_entries.topic_id = search_object.range_id "
//                                    . " LEFT JOIN seminare as fs ON fs.Seminar_id = forum_entries.seminar_id "
//                                    . " LEFT JOIN seminare ON seminare.Seminar_id = search_object.range_id "
//                                    . " LEFT JOIN seminar_inst ON  seminar_inst.seminar_id = search_object.range_id "
//                                    . " LEFT JOIN user_inst ON  user_inst.user_id = search_object.range_id ";
//            $search_params['conditions'] = " AND (ds.start_time = " . $semester
//                                         . " OR fs.start_time = " . $semester
//                                         . " OR (seminare.start_time <= '" . $semester . "' AND ('" . $semester . "' <= (seminare.start_time + seminare.duration_time) OR seminare.duration_time = '-1'))"
//                                         . " OR type = 'user' OR type = 'institute') ";
//            $semester_condition['conditions'] = " AND (seminare.start_time <= '" . $semester . "' AND ('" . $semester . "' <= (seminare.start_time + seminare.duration_time) OR seminare.duration_time = '-1')) ";
//        }
//        $statement = DBManager::get()->prepare("SELECT search_object.*, text "
//                . " FROM search_object JOIN " . $search . " USING (object_id) " . $search_params['joins']
//                . " WHERE " . ($type ? (' type = :type ') : ' 1 ') . $search_params['conditions']
//                . ($GLOBALS['perm']->have_perm('root') || !$type ? '' : " AND " . $object->getCondition())
//                . (!$type && $this->query ? $this->buildWhere() : ' ') . " GROUP BY object_id "
//                . ($this->query ? '' : " LIMIT $this->limit")
//                . $this->getRelatedObjects($type, ($type ? $search_params : $semester_condition)));

        if ($type) {
            $statement->bindParam(':type', $type);
        }
        $statement->execute();
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($results as $key => $result) {
            switch ($result['type']) {
                case 'document':
                    $document = StudipDocument::find($result['range_id']);
                    // test general access
                    if (!$document->checkAccess($GLOBALS['user']->id) && !$is_root) {
                        unset($results[$key]);
                        break;
                    }
                    // semester filter
                    if (!$this->checkSemester($document['course'])) {
                        unset($results[$key]);
                        break;
                    }
                    // seminar filter
                    if ($seminar = $_SESSION['global_search']['selects'][IndexObject::getSelectName('seminar')]) {
                        if ($document['course']['seminar_id'] !== $seminar) {
                            unset($results[$key]);
                            break;
                        }
                    }
                    // institute filter
                    if (!$this->checkInstitute($document['course'])) {
                        unset($results[$key]);
                        break;
                    }
                    // file_type filter
                    if (!$this->checkFileType($document['filename'])) {
                        unset($results[$key]);
                        break;
                    }
                    break;
                case 'forumentry':
                    // test general access (course membership)
                    if (!$this->checkMembership($result['range2']) && !$is_root) {
                        unset($results[$key]);
                        break;
                    }
                    // semester filter
                    if (!$this->checkSemester(Course::find($result['range2']))) {
                        unset($results[$key]);
                        break;
                    }
                    // seminar filter
                    if ($seminar = $_SESSION['global_search']['selects'][IndexObject::getSelectName('seminar')]) {
                        if ($result['range2'] !== $seminar) {
                            unset($results[$key]);
                            break;
                        }
                    }
                    break;
                case 'institute':
                    // no restrictions here
                    break;
                case 'seminar':
                    // test general access (visibility)
                    $course = Course::find($result['range_id']);
                    if (!$course['visible'] && !$this->checkMembership($course['seminar_id'])) {
                        unset($results[$key]);
                        break;
                    }
                    // semester filter
                    if (!$this->checkSemester($course)) {
                        unset($results[$key]);
                        break;
                    }
                    // institute filter
                    if (!$this->checkInstitute($course)) {
                        unset($results[$key]);
                        break;
                    }
                    // seminar type filter
                    if (!$this->checkSemType($course)) {
                        unset($results[$key]);
                        break;
                    }
                    break;
                case 'user':
                    // test general access (visibility with get_vis_query())
                    if (!$this->checkUser($result['range_id'])) {
                        unset($results[$key]);
                        break;
                    }
                    // institute filter
                    if (!$this->checkInstituteForUser($result['range_id'])) {
                        unset($results[$key]);
                        break;
                    }
                    break;
                default:
                    throw new InvalidArgumentException(_('Der ausgewählte IndexObject_Type existiert leider nicht.'));
            }
        }
        return $results;
    }

    /**
     * Checks if a given course matches the semester selected by the user.
     *
     * @param $course
     * @return bool
     */
    private function checkSemester($course)
    {
        if ($semester = $_SESSION['global_search']['selects'][IndexObject::getSelectName('semester')]) {
            if ($course['start_time'] <= $semester
                && ($semester <= ($course['start_time'] + $course['duration_time'])
                    || $course['duration_time'] == -1)) {
                return true;
            } else {
                return false;
            }
        }
        // the option 'all semesters' is selected
        return true;
    }

    /**
     * Checks if a given course matches the faculty/institute selected by the user.
     *
     * @param $course
     * @return bool
     */
    private function checkInstitute($course)
    {
        if ($institute_ids = $this->getInstituteIds()) {
                return (in_array($course['Institut_id'], $institute_ids));
        }
        // the option 'all institutes' is selected
        return true;
    }

    private function checkInstituteForUser($user_id)
    {
        if ($_SESSION['global_search']['selects'][IndexObject::getSelectName('institute')]) {
            $institutes = InstituteMember::findByUser($user_id);
            $institute_user_ids = array_column($institutes, 'Institut_id');
            $institute_ids = $this->getInstituteIds();
            if (array_intersect($institute_ids, $institute_user_ids)) {
                return true;
            } else {
                return false;
            }
        }
        // the option 'all institutes' is selected
        return true;
    }

    private function getInstituteIds()
    {
        if ($institute = $_SESSION['global_search']['selects'][IndexObject::getSelectName('institute')]) {
            if ($institutes = Institute::findByFaculty($institute)) {
                $institute_ids = array_column($institutes, 'Institut_id');
                array_push($institute_ids, $institute);
                return $institute_ids;
            } else {
                return array($institute);
            }
        }
        return null;
    }

    /**
     * Checks if a given filename matches the filetype selected by the user.
     *
     * @param $filename
     * @return bool
     */
    private function checkFileType($filename)
    {
        if ($filetype = $_SESSION['global_search']['selects'][IndexObject::getSelectName('file_type')]) {
            $file_extension = substr($filename, strrpos($filename, '.') + 1);
            return in_array($file_extension, IndexObject::getFileTypes($filetype));
        }
        // the option 'all filetypes' is selected
        return true;
    }

    /**
     * @param $course_id
     * @return bool
     */
    private function checkMembership($course_id)
    {
        $course_ids = array_column(CourseMember::findByUser($GLOBALS['user']->id), 'seminar_id');
        return in_array($course_id, $course_ids);
    }

    /**
     * @param $course
     * @return bool
     */
    private function checkSemType($course)
    {
        if ($sem_class = $_SESSION['global_search']['selects'][IndexObject::getSelectName('sem_class')]) {
            if ($pos = strpos($sem_class, '_')) {
                // return just the sem_types.id (which is equal to seminare.status)
                return $course['status'] == substr($sem_class, $pos + 1);
            } else {
                $classes = SemClass::getClasses();
                $type_ids = array();
                // fill an array containing all sem_types belonging to the chosen sem_class
                $class = $classes[$sem_class];
                foreach ($class->getSemTypes() as $types_id => $types) {
                    array_push($type_ids, $types['id']);
                }
                return in_array($course['status'], $type_ids);
            }
        }
        // the option 'all seminar types' is selected
        return true;
    }

    /**
     * @param $user_id
     * @return bool
     */
    private function checkUser($user_id)
    {
        $users = User::findBySQL(" LEFT JOIN user_visibility USING (user_id) "
            . " JOIN search_object ON search_object.range_id = user_id WHERE "
            . get_vis_query('auth_user_md5', 'search'));
        $user_ids = array_column($users, 'user_id');
        if (in_array($user_id, $user_ids)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Builds SQL-search string which is included into the statement below if a query is given.
     *
     * @param $search_string string entered by the user
     * @return string: SQL query
     */
    private function getSearchQuery ($search_string)
    {
        if ($search_string) {
            $query = '"' . $search_string . '"';
            return "(SELECT object_id, text FROM search_index"
                . " WHERE MATCH (text) AGAINST ('" . $query . "')"
                . " GROUP BY object_id"
                . ") as sr";
        } else {
            return 'search_index';
        }
    }

    /**
     * Gets related objects for a given search string (case: there is no Username for an
     * 'author' of a seminar/forumentry/document stored in the search_index table, so you need
     * a search query that finds seminars etc. by username. Reason: if the name of a
     * Person changes, you don't want to update all entries in the search_index table).
     *
     * @param $type string: category_type
     * @param $search_params array with search conditions for the further use
     * @return string SQL-Query
     */
    private function getRelatedObjects($type, $search_params)
    {
        if ($this->query) {
            switch ($type) {
                case 'seminar':
                    return $this->getRelatedSeminars($search_params);
                case 'forumentry':
                    return $this->getRelatedForumentries($search_params);
                case 'document':
                    return $this->getRelatedDocuments($search_params);
                case 'institute':
                case 'user':
                    return '';
                default:
                    return $this->getRelatedSeminars($search_params)
                         . $this->getRelatedForumentries($search_params)
                         . $this->getRelatedDocuments($search_params);
            }
        } else {
            return '';
        }
    }

    /**
     * If a given search string provides a person as a result, this query finds the
     * Seminars where this person is the lecturer (status = 'dozent').
     *
     * @param $search_params
     * @return string
     */
    private function getRelatedSeminars($search_params)
    {
        return " UNION SELECT so1.*, so2.title as text "
            . " FROM search_object as so1 "
            . " LEFT JOIN seminar_user as su ON so1.range_id = su.Seminar_id "
            . " LEFT JOIN search_object as so2 ON su.user_id = so2.range_id "
            . " JOIN seminare ON seminare.Seminar_id = so1.range_id "
            . " LEFT JOIN seminar_inst ON  seminar_inst.seminar_id = so1.range_id "
            . " WHERE so1.type = 'seminar' " . $search_params['conditions']
            . " AND su.status = 'dozent' AND su.user_id IN "
            . $this->getUserIdsForQuery()
            . ($GLOBALS['perm']->have_perm('root') ? '' : " AND "
            . " (EXISTS (SELECT 1 FROM seminare WHERE Seminar_id = so1.range_id AND visible = 1) "
            . " OR EXISTS (SELECT 1 FROM seminar_user WHERE Seminar_id = so1.range_id "
            . " AND user_id = '{$GLOBALS['user']->id}')) ");
    }

    /**
     * If a given search string provides a person as a result, this query finds the
     * Forumentries where this person is the author.
     *
     * @param $search_params
     * @return string
     */
    private function getRelatedForumentries($search_params)
    {
        return " UNION SELECT so1.*, so2.title as text "
            . " FROM search_object as so1 "
            . " LEFT JOIN forum_entries ON so1.range_id = forum_entries.topic_id "
            . " LEFT JOIN search_object as so2 ON forum_entries.user_id = so2.range_id "
            . " LEFT JOIN seminare ON seminare.Seminar_id = forum_entries.seminar_id "
            . " WHERE so1.type = 'forumentry'" . $search_params['conditions']
            . " AND forum_entries.user_id IN "
            . $this->getUserIdsForQuery()
            . ($GLOBALS['perm']->have_perm('root') ? '' : " AND "
            . " (EXISTS (SELECT 1 FROM seminar_user "
            . " WHERE Seminar_id = so1.range2 AND user_id = '{$GLOBALS['user']->id}')) ");
    }

    /**
     * If a given search string provides a person as a result, this query finds the
     * Forumentries where this person is the uploader.
     *
     * @param $search_params
     * @return string
     */
    private function getRelatedDocuments($search_params)
    {
        return " UNION SELECT so1.*, so2.title as text "
            . " FROM search_object as so1 "
            . " LEFT JOIN dokumente ON so1.range_id = dokumente.dokument_id "
            . " LEFT JOIN search_object as so2 ON dokumente.user_id = so2.range_id "
            . " LEFT JOIN seminare ON dokumente.seminar_id = seminare.Seminar_id "
            . " WHERE so1.type = 'document' " . $search_params['conditions']
            . " AND dokumente.user_id IN "
            . $this->getUserIdsForQuery()
            . ($GLOBALS['perm']->have_perm('root') ? '' : " AND "
            . " (EXISTS (SELECT 1 FROM seminar_user "
            . " WHERE Seminar_id = so1.range2 AND user_id = '{$GLOBALS['user']->id}')) ");
    }

    /**
     * Provides the user_id (for a specific query) for further use in other queries.
     *
     * @return string
     */
    private function getUserIdsForQuery()
    {
        return " (SELECT range_id "
             . " FROM search_index JOIN search_object USING (object_id) WHERE type = 'user'"
             . " AND MATCH (text) AGAINST ('" . $this->query . "') "
             . " GROUP BY object_id) ";
    }

    /**
     * Returns the condition queries for each IndexObject. Is just called if no type/category is chosen.
     *
     * @return int|string
     */
    public function buildWhere()
    {
        if ($GLOBALS['perm']->have_perm('root')) {
            return '';
        }
        foreach ($this->getIndexObjectTypes() as $type) {
            $indexClass = $this->getClass($type);
            $indexObject = new $indexClass;
            if ($indexObject->getCondition()) {
                $condititions[] = " (search_object.type = '$type' AND " . $indexObject->getCondition() . ") ";
            } else {
                $condititions[] = " (search_object.type = '$type') ";
            }
        }
        return ' AND (' . join(' OR ', $condititions) . ') ';
    }

    /**
     * Returns the active filter options for the given category type chosen by the user.
     *
     * @return array containing only the checked/active filters for the given category.
     */
    public function getActiveFilters()
    {
        $facets = array();
        foreach ($_SESSION['global_search']['facets'] as $facet => $value) {
            if ($_SESSION['global_search']['facets'][$facet]) {
                array_push($facets, $facet);
            }
        }
        return $facets;
    }

    /**
     * Get all IndexObject_'types' which should be searchable/found by a user
     * and return them in an array.
     *
     * @return array
     */
    public function getIndexObjectTypes()
    {
        $types = array();
        foreach (glob(__DIR__ . '/IndexObject_*') as $indexFile) {
            $indexClass = basename($indexFile, ".php");
            $typename = explode('_', $indexClass);
            $typename = strtolower($typename[1]);
            array_push($types, $typename);
        }
        return $types;
    }

    /**
     * Returns the class name of a given type.
     *
     * @param $type
     * @return string
     */
    public function getClass($type)
    {
        return "IndexObject_" . ucfirst($type);
    }

    /**
     * Returns the part of the indexed text (table search_index) which contains the
     * search query and highlights it for the html output.
     *
     * @param $object
     * @param $query
     * @return mixed
     */
    public function getInfo($object, $query)
    {
        // Cut down if info is to long
        if (strlen($object['text']) > 200) {
            $object['text'] = substr($object['text'], max(array(0, $this->findWordPosition($query, $object['text']) - 100)), 200);
        }

        // Split words to get them marked individual
        $words = str_replace(' ', '|', preg_quote($query));

        return preg_replace_callback("/$words/i", function($hit) {
            return "<span class='result'>$hit[0]</span>";
        }, htmlReady($object['text']));
    }

    /**
     * @return string
     */
    public function includePath()
    {
        return __FILE__;
    }

    /**
     * @param string $keyword
     * @param array $contextual_data
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getResults($keyword, $contextual_data = array(), $limit = PHP_INT_MAX, $offset = 0)
    {
        foreach (glob(__DIR__ . '/IndexObject_*') as $indexFile) {
            include $indexFile;
        }

        $this->query = $keyword;
        $stmt = $this->getResultSet(10);
        while ($object = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[] = array($object['object_id'], $object['title']);
        }
        return $result;
    }

    /**
     * @param int $page
     * @return array
     */
    public function resultPage($page = 0)
    {
        return array_slice($this->results, $page * $this->resultsPerPage, $this->resultsPerPage);
    }

    /**
     * @param string $id
     * @return mixed
     */
    public function getAvatarImageTag($id)
    {
        $stmt = DBManager::get()->prepare('SELECT * FROM search_object WHERE object_id = ? LIMIT 1');
        $stmt->execute(array($id));
        $object = $stmt->fetch(PDO::FETCH_ASSOC);
        $class = self::getClass($object['type']);
        return $class::getAvatar($object);
    }

    /**
     * Calculates the 10 pages (for the pagination) that should be shown to the user.
     *
     * @param int $current : The current page in the pagination.
     * @return array of the 10 shown pages in the pagination.
     *          Initially (*1*, 2, 3, ... 9, 10) if you are on page 0 and
     *          i.e. (5, 6, 7, 8, 9, *10*, 11, 12, 13, 14, 15) if you are on page 9
     *          (given $pages_shown = 10).
     */
    public function getPages($current = 0)
    {
        $minimum = max(0, $current - ($this->pages_shown / 2));
        $maximum = $current <= ($this->pages_shown / 2) - 1 ?
            min($this->pages_shown - 1 , $this->countResultPages() - 1) :
            min($current + ($this->pages_shown / 2), $this->countResultPages() - 1);
        return range($minimum, $maximum);
    }

    /**
     * @return float
     */
    public function countResultPages()
    {
        return ceil(count($this->results) / $this->resultsPerPage);
    }

    /**
     * Finds the position of the search query in the respective indexed text
     * (table: search_index) so it can be highlighted correctly.
     *
     * @param $words
     * @param $text
     * @return int
     */
    private function findWordPosition($words, $text)
    {
        foreach (explode(' ', $words) as $word) {
            $pos = stripos($text, $word);
            if ($pos) {
                return $pos;
            }
        }
    }

    /**
     * @return int
     */
    public function getCount()
    {
        return $this->count;
    }

    /**
     * @return mixed
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @return array
     */
    public function getResultTypes()
    {
        return $this->resultTypes;
    }

    /**
     * @return int
     */
    public function getTime()
    {
            return isset($this->time) ? $this->time : null;
    }

    /**
     * @return mixed
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * @return array
     */
    public function getResultsArray()
    {
        return $this->results;
    }

    /**
     * @return int
     */
    public function getPagesShown()
    {
        return $this->pages_shown;
    }
}
