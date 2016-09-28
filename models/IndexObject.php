<?php
/**
 * Parent class to all the searchable 'objects'.
 * This class mainly contains methods for facet- (checkboxes) and select- (dropdown) filters.
 *
 * User: manschwa
 * Date: 22.06.16
 */
class IndexObject
{
    /** @var  string object name */
    protected $name;
    /** @var  array with select-filters (dropdowns) */
    protected $selects;
    /** @var  array with facet-filters (checkboxes) */
    protected $facets;

    /**
     * Method to get the right select filter name for the $SESSION variable in one place.
     *
     * @param $type string name of the select filter
     * @return string select filter name
     */
    public static function getSelectName($type)
    {
        switch ($type) {
            case 'semester':
                return _('Semester');
            case 'seminar':
                return _('Veranstaltungen');
            case 'user':
                return _('Personen');
            case 'institute':
                return _('Einrichtungen');
            case 'sem_class':
                return _('Veranstaltungsarten');
            case 'file_type':
                return _('Dateitypen');
            default:
                return '';
        }
    }

    /**
     * Get seminars/courses for the seminar-select-filter (dropdown).
     * The seminar filter is dependent on the semester filter.
     *
     * @return array with key => value pairs like: array('seminar_id' => 'seminar_name (semester_name)')
     */
    protected function getSeminars()
    {
        $seminars = array();
        if (isset($GLOBALS['user'])) {
            $statement = DBManager::get()->prepare("SELECT Seminar_id, seminare.Name, semester_data.name "
                        . " FROM seminar_user JOIN seminare USING (Seminar_id) "
                        . " JOIN semester_data ON seminare.start_time = semester_data.beginn "
                        . " WHERE user_id = :user_id AND " . $this->getSeminarsForSemester());
            $statement->bindParam(':user_id', $GLOBALS['user']->id);
        }
        $statement->execute();

        while ($seminar = $statement->fetch(PDO::FETCH_ASSOC)) {
            $seminars[$seminar['Seminar_id']] = $seminar['Name'] . ' (' . $seminar['name'] . ')';
        }
        // clear the seminar filter if the semester filter changes and the seminar does not exist in the chosen semester
        if (!array_key_exists($_SESSION['global_search']['selects'][$this->getSelectName('seminar')], $seminars)) {
            $_SESSION['global_search']['selects'][$this->getSelectName('seminar')] = '';
        }
        asort($seminars);
        $first_entry[''] = _('Alle Veranstaltungen');
        return array_merge($first_entry, $seminars);
    }

    /**
     * Get semesters for the semester-select-filter (dropdown).
     * The semester filter shows all available semesters
     * and sets the current semester as the selected default.
     *
     * @return array with key => value pairs like: array('semester_beginn' => 'semester_name')
     */
    public function getSemesters()
    {
        // set current semester as selected
        // considering the given SEMESTER_TIME_SWITCH in the CONFIG
        // (n weeks before next semester)
        if (!$_SESSION['global_search']['selects']) {
            $sem_time_switch = Config::get()->getValue('SEMESTER_TIME_SWITCH');
            $current_sem = Semester::findByTimestamp(time()
                + $sem_time_switch * 7 * 24 * 3600);
            $_SESSION['global_search']['selects'][$this->getSelectName('semester')] = $current_sem['beginn'];
        }

        $semesters = array();
        $sems = array_reverse(Semester::getAll());
        $semesters[' '] = _('Alle Semester');
        foreach ($sems as $semester) {
            $semesters[$semester['beginn']] = $semester['name'];
        }
        return $semesters;
    }

    /**
     * Get institutes for the institute-select-filter (dropdown).
     * The institute filter shows all available institutes and presents the 2-level hierarchy with indented names.
     *
     * @return array with key => value pairs like: array('institute_id' => 'institute_name')
     */
    protected function getInstitutes()
    {
        $institutes = array();
        $insts = Institute::getInstitutes();
        foreach ($insts as $institute) {
            $institutes[$institute['Institut_id']] = ($institute['is_fak'] ? '' : '  ') . $institute['Name'];
        }
        $first_entry[''] = _('Alle Einrichtungen');
        return array_merge($first_entry, $institutes);
    }

    /**
     * Get seminar types for the seminar-type-select-filter (dropdown).
     * The seminar type filter shows all available seminar types and
     *  seminar type classes which are presented as a 2-level hierarchy with indented names.
     *
     * @return array with key => value pairs like: array('seminar_type_id' => 'seminar_type_name')
     */
    protected function getSemClasses()
    {
        $sem_classes = array();
        $sem_classes[''] = _('Alle Veranstaltungsarten');
        foreach ($GLOBALS['SEM_CLASS'] as $class_id => $class) {
            $sem_classes[$class_id] = $class['name'];
            if (!$class['studygroup_mode']) {
                foreach ($class->getSemTypes() as $type_id => $type) {
                    $sem_classes[$class_id . '_' . $type_id] = '  ' . $type['name'];
                }
            }
        }
        return $sem_classes;
    }

    /**
     * Get all active facets as a string to use in an SQL query.
     *
     * @return string: active facets formatted for an SQL query
     */
    protected function getActiveFacets()
    {
        $facets = array();
        foreach ($_SESSION['global_search']['facets'] as $facet => $value) {
            if ($value) {
                array_push($facets, $facet);
            }
        }
        if ($facets) {
            return implode('\', \'', $facets);
        } else {
            return '';
        }
    }

    /**
     * Get the selected seminar class with sub-types
     * or a single seminar type as a string to use in an SQL query.
     *
     * @return string: seminar class/types formatted for an SQL query
     */
    protected function getSemClassString()
    {
        $classes = SemClass::getClasses();
        $sem_class = $_SESSION['global_search']['selects'][$this->getSelectName('sem_class')];
        if ($pos = strpos($sem_class, '_')) {
            // return just the sem_types.id (which is equal to seminare.status)
            return substr($sem_class, $pos + 1);
        } else {
            $type_ids = array();
            // return a concatenated string containing all sem_types
            // belonging to the chosen sem_class
            $class = $classes[$sem_class];
            foreach ($class->getSemTypes() as $types_id => $types) {
                array_push($type_ids, $types['id']);
            }
            return implode('\', \'', $type_ids);
        }
    }

    /**
     * Get the selected institute with sub-institutes
     * or a single institute as a string to use in an SQL query.
     *
     * @return string: institutes formatted for an SQL query
     */
    protected function getInstituteString()
    {
        $institutes = Institute::findByFaculty($_SESSION['global_search']['selects'][$this->getSelectName('institute')]);
        if ($institutes) {
            $var = implode('\', \'', array_column($institutes, 'Institut_id'));
            // append the parent institute itself
            return $var . '\', \'' . $_SESSION['global_search']['selects'][$this->getSelectName('institute')];
        } else {
            return $_SESSION['global_search']['selects'][$this->getSelectName('institute')];
        }
    }


    /**
     * Get file types for the file-type-select-filter (dropdown).
     * The file type filter shows a static predefined list of file type names.
     *
     * @return array with key => value pairs like: array('file_type_id' => 'file_type_name')
     */
    protected function getStaticFileTypes()
    {
        $file_types = array();
        $file_types[''] = _('Alle Dateitypen');
        $file_types[1]  = _('PDF');
        $file_types[2]  = _('Text');
        $file_types[3]  = _('Bilder');
        $file_types[4]  = _('Audio');
        $file_types[5]  = _('Video');
        $file_types[6]  = _('Tabellen');
        $file_types[7]  = _('Präsentationen');
        $file_types[8]  = _('Komprimierte Dateien');
        return $file_types;
    }

    /**
     * List of file extensions for each file_type_name (see getStaticFileTypes()).
     *
     * @param $category int: file_type_id
     * @return string
     */
    protected function getFileTypesString($category)
    {
        switch ($category) {
            case 1: // PDF
                return "('pdf')";
            case 2: // Text
                return "('txt', 'doc', 'docx', 'odt', 'log', 'rtf', 'tex', 'pages', 'fodt', 'sxw')";
            case 3: // Pictures
                return "('jpg', 'jpeg', 'png', 'gif', 'bmp', 'psd', 'tif', 'tiff', 'eps', 'svg', 'odg', 'fodg')";
            case 4: // Audio
                return "('mp3', 'wav', 'wma', 'midi', 'mp4a', 'm4p', 'aiff', 'aa', 'aac', 'aax')";
            case 5: // Video
                return "('mov', 'mp4', 'wmv', 'avi', 'flv', 'mkv', 'webm', 'gifv', 'qt', 'mpg', 'mpeg', 'mpv', 'm4v', '3gp', '3g2')";
            case 6: // Spreadsheets
                return "('xls', 'xlsx', 'ods', 'fods', 'numbers')";
            case 7: // Presentations
                return "('ppt', 'pptx', 'pps', 'key', 'odp', 'fodp')";
            case 8: // Compressed Files
                return "('zip', 'rar', 'tz', 'rz', 'bz2', '7zip', '7z', 'tar', 'tgz')";
            default:
                throw new InvalidArgumentException(_('Der ausgewählte Dateityp existiert leider nicht.'));
        }
    }

    public static function getFileTypes($category)
    {
        switch ($category) {
            case 1: // PDF
                return array('pdf');
            case 2: // Text
                return array('txt', 'doc', 'docx', 'odt', 'log', 'rtf', 'tex', 'pages', 'fodt', 'sxw');
            case 3: // Pictures
                return array('jpg', 'jpeg', 'png', 'gif', 'bmp', 'psd', 'tif', 'tiff', 'eps', 'svg', 'odg', 'fodg');
            case 4: // Audio
                return array('mp3', 'wav', 'wma', 'midi', 'mp4a', 'm4p', 'aiff', 'aa', 'aac', 'aax');
            case 5: // Video
                return array('mov', 'mp4', 'wmv', 'avi', 'flv', 'mkv', 'webm', 'gifv', 'qt', 'mpg', 'mpeg', 'mpv', 'm4v', '3gp', '3g2');
            case 6: // Spreadsheets
                return array('xls', 'xlsx', 'ods', 'fods', 'numbers');
            case 7: // Presentations
                return array('ppt', 'pptx', 'pps', 'key', 'odp', 'fodp');
            case 8: // Compressed Files
                return array('zip', 'rar', 'tz', 'rz', 'bz2', '7zip', '7z', 'tar', 'tgz');
            default:
                throw new InvalidArgumentException(_('Der ausgewählte Dateityp existiert leider nicht.'));
        }
    }

    /**
     * If a semester is selected in the filter, an SQL condition will be returned.
     *
     * @return int|string: condition if a semester is selected, 1 otherwise
     */
    private function getSeminarsForSemester()
    {
        if ($semester = $_SESSION['global_search']['selects'][$this->getSelectName('semester')]) {
            return " seminare.start_time <= '" . $semester . "' AND ('" . $semester
                 . "' <= (seminare.start_time + seminare.duration_time) OR seminare.duration_time = '-1')";
        } else {
            return 1;
        }
    }

    /**
     * @return mixed
     */
    public function getCondition()
    {
        return 1;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        if (is_string($name)) {
            $this->name = (string)$name;
        }
    }

    /**
     * @param mixed $selects
     */
    public function setSelects($selects)
    {
        if (is_array($selects)) {
            $this->selects = $selects;
        }
    }

    /**
     * @param array $facets
     */
    public function setFacets($facets)
    {
        if (is_array($facets)) {
            $this->facets = (array)$facets;
        }
    }

    /**
     * @return array
     */
    public function getFacets()
    {
        return $this->facets;
    }

    /**
     * @return mixed
     */
    public function getSelects()
    {
        return $this->selects;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

}
