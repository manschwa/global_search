<?php

class IndexObject_Seminar extends IndexObject
{
    const RATING_SEMINAR = 0.8;
    const RATING_SEMINAR_SUBTITLE = 0.7;
    const RATING_SEMINAR_OTHER = 0.6;

    /**
     * IndexObject_Seminar constructor.
     */
    public function __construct()
    {
        $this->setName(_('Veranstaltungen'));
        $this->setSelects($this->getSelectFilters());
    }

    /**
     * Fills the 'search_object' and 'search_index' tables with seminar
     * specific information.
     */
    public function sqlIndex() {
        IndexManager::createObjects("(SELECT seminar_id, 'seminar', CONCAT(s.name, ' ', '(', sd.name, IF(s.duration_time = -1, '"
            . " - " . _("unbegrenzt") . "', IF(sd2.name = sd.name, '', CONCAT(' - ', sd2.name))), ')'), null, null "
            . " FROM seminare s JOIN semester_data sd ON s.start_time BETWEEN sd.beginn AND sd.ende "
            . " LEFT JOIN semester_data sd2 ON (s.start_time + s.duration_time BETWEEN sd2.beginn AND sd2.ende))");
        IndexManager::log("Seminar objects created");
        IndexManager::createIndex("(SELECT object_id, CONCAT_WS(' ', Veranstaltungsnummer, Name), "
            . IndexManager::relevance(self::RATING_SEMINAR, 'start_time') . " FROM seminare "
            . IndexManager::createJoin('seminar_id') . ")");
        IndexManager::log("Indexed name");
        IndexManager::createIndex("(SELECT object_id, Untertitel, "
            . IndexManager::relevance(self::RATING_SEMINAR_SUBTITLE, 'start_time') . " FROM seminare "
            . IndexManager::createJoin('seminar_id') . " WHERE Untertitel != '')");
        IndexManager::log("Indexed subtitle");
        IndexManager::createIndex("(SELECT object_id, Beschreibung, "
            . IndexManager::relevance(self::RATING_SEMINAR_OTHER, 'start_time') . " FROM seminare "
            . IndexManager::createJoin('seminar_id') . " WHERE Beschreibung != '')");
        IndexManager::log("Indexed description");
        IndexManager::createIndex("(SELECT object_id, Sonstiges, "
            . IndexManager::relevance(self::RATING_SEMINAR_OTHER, 'start_time') . " FROM seminare "
            . IndexManager::createJoin('seminar_id') . " WHERE Sonstiges != '')");
        IndexManager::log("Indexed other");
    }

    /**
     * Determines which filters should be shown if the type 'seminar'
     * is selected.
     *
     * @return array
     */
    public function getSelectFilters()
    {
        $selects = array();
        $selects[$this->getSelectName('semester')] = $this->getSemesters();
        $selects[$this->getSelectName('institute')] = $this->getInstitutes();
        $selects[$this->getSelectName('sem_class')] = $this->getSemClasses();
        return $selects;
    }

    /**
     * Builds and returns an associative array containing SQL-snippets
     * ('joins' and 'conditions') for the different seminar filter options.
     *
     * @return array
     */
    public function getSearchParams()
    {
        $semester = $_SESSION['global_search']['selects'][$this->getSelectName('semester')];
        $institute = $_SESSION['global_search']['selects'][$this->getSelectName('institute')];
        $sem_class = $_SESSION['global_search']['selects'][$this->getSelectName('sem_class')];

        $search_params = array();
        $search_params['joins']     = ' LEFT JOIN seminare ON seminare.Seminar_id = search_object.range_id '
                                    . ' LEFT JOIN seminar_inst ON  seminar_inst.seminar_id = search_object.range_id ';
        $search_params['conditions'] = ($semester ? (" AND (seminare.start_time <= '" . $semester . "' AND ('" . $semester . "' <= (seminare.start_time + seminare.duration_time) OR seminare.duration_time = '-1')) ") : ' ')
                                     . ($institute ? (" AND seminar_inst.institut_id IN ('" . $this->getInstituteString() . "') ") : ' ')
                                     . ($sem_class ? (" AND seminare.status  IN ('" . $this->getSemClassString() . "') ") : ' ');
        return $search_params;
    }

    /**
     * Gets an additional condition if the user is not root.
     * You only can see seminars you are a part of or seminars
     * that are globally visible.
     *
     * @return string
     */
    public function getCondition() {
        return " (EXISTS (SELECT 1 FROM seminare WHERE Seminar_id = search_object.range_id AND visible = 1) OR EXISTS (SELECT 1 FROM seminar_user WHERE Seminar_id = search_object.range_id AND user_id = '{$GLOBALS['user']->id}'))";
    }

    /**
     * Retruns a link to the found seminar for the result presentation
     * depending on your role/rights.
     *
     * @param $object PDO
     * @return string link
     */
    public static function getLink($object) {
        if ($GLOBALS['perm']->have_perm('admin')) {
            return "dispatch.php/course/overview?cid={$object['range_id']}";
        } else {
            return "dispatch.php/course/details/?sem_id={$object['range_id']}";
        }
    }

    /**
     * Name of this IndexObject which is presented to the user.
     *
     * @return string
     */
    public static function getType()
    {
        return _('Veranstaltungen');
    }

    /**
     * Returns an avatar representing the seminar or, if it's a studygroup,
     * the studygroup.
     *
     * @param $object
     * @return Icon
     */
    public static function getAvatar($object)
    {
        $course = Course::find($object['range_id']);
        if ($course->getSemClass()->offsetGet('studygroup_mode')) {
            return StudygroupAvatar::getAvatar($object['range_id'])->getImageTag(Avatar::MEDIUM);
        } else {
            return CourseAvatar::getAvatar($object['range_id'])->getImageTag(Avatar::MEDIUM);
        }
    }

    /**
     * If a new seminar is created, it will be inserted into
     * the 'search_object' and 'search_index' tables.
     *
     * @param $event
     * @param $seminar
     */
    public function insert($event, $seminar)
    {
        // insert new Course into search_object
        $type = 'seminar';
        if ($name = $seminar['name']) {
            $seminar_runtime = $this->getSeminarRuntime($seminar);
            $title = $seminar['name'] . ' ' . $seminar_runtime;
            IndexManager::createObjects(" VALUES ('" . $seminar['seminar_id'] . "', '"
                . $type . "', '"
                . $title . "', '"
                . null . "', '"
                . null . "') ");
        }

        // insert new Course into search_index
        $object_id_query = IndexManager::getSearchObjectId($seminar['seminar_id']);
        if ($name = $seminar['name']) {
            $index_title = $seminar['veranstaltungsnummer'] . ' ' . $name;
            IndexManager::createIndex(" VALUES (" . $object_id_query . ", '" . $index_title . "', "
                . IndexManager::relevance(self::RATING_SEMINAR, $seminar['start_time']) . ") ");
        }
        if ($subtitle = $seminar['untertitel']) {
            IndexManager::createIndex(" VALUES (" . $object_id_query . ", '" . $subtitle . "', "
                . IndexManager::relevance(self::RATING_SEMINAR_SUBTITLE, $seminar['start_time']) . ") ");
        }
        if ($description = $seminar['beschreibung']) {
            IndexManager::createIndex(" VALUES (" . $object_id_query . ", '" . $description . "', "
                . IndexManager::relevance(self::RATING_SEMINAR_OTHER, $seminar['start_time']) . ") ");
        }
    }

    /**
     * If an existing seminar is being edited, it will be deleted and
     * re-inserted into the 'search_object' and 'search_index' tables.
     *
     * @param $event
     * @param $seminar
     */
    public function update($event, $seminar)
    {
        $this->delete($event, $seminar);
        $this->insert($event, $seminar);
    }

    /**
     * If an existing seminar is deleted, it will be deleted from
     * the 'search_object' and 'search_index' tables.
     * NOTE: the order is important!
     *
     * @param $event
     * @param $seminar
     */
    public function delete($event, $seminar)
    {
        // delete from search_index
        IndexManager::deleteIndex($seminar['seminar_id']);

        // delete from search_object
        IndexManager::deleteObjects($seminar['seminar_id']);
    }

    /**
     * Builds the string that shows a seminar's duration, e.g. (SS 2014 - WS 2014/15) or
     * (SS 2016 - unbegrenzt) or (WS 2016/17).
     *
     * @param $seminar
     * @return string
     */
    private function getSeminarRuntime($seminar)
    {
        $start_semester = Semester::findByTimestamp($seminar['start_time']);
        $str = '(' . $start_semester['name'];
        if ($seminar['duration_time'] == -1) {
            $str .= (' - ' . _('unbegrenzt'));
        } else {
            $end_semester = Semester::findByTimestamp($seminar['start_time'] + $seminar['duration_time']);
            $str .= ($start_semester == $end_semester ? '' : ' - ' . $end_semester['name']);
        }
        return $str . ')';
    }
}
