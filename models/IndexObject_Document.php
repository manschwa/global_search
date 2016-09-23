<?php

class IndexObject_Document extends IndexObject
{

    const RATING_DOCUMENT_TITLE = 0.9;
    const RATING_DOCUMENT_DESCRIPTION = 0.8;

    /**
     * IndexObject_Document constructor.
     */
    public function __construct()
    {
        $this->setName(_('Dokumente'));
        $this->setSelects($this->getSelectFilters());
    }

    /**
     * Fills the 'search_object' and 'search_index' tables with document
     * specific information.
     */
    public function sqlIndex()
    {
        IndexManager::createObjects("(SELECT dokument_id, 'document', CONCAT(seminare.name, ': ', COALESCE(NULLIF(TRIM(dokumente.name), ''), '" . _('Datei') . "')), seminar_id, range_id FROM dokumente JOIN seminare USING (seminar_id))");
        IndexManager::createIndex("(SELECT object_id, name, " . IndexManager::relevance(self::RATING_DOCUMENT_TITLE, 'dokumente.chdate') . " FROM dokumente" . IndexManager::createJoin('dokument_id') . " WHERE name != '')");
        IndexManager::createIndex("(SELECT object_id, description, " . IndexManager::relevance(self::RATING_DOCUMENT_DESCRIPTION, 'dokumente.chdate') . " FROM dokumente" . IndexManager::createJoin('dokument_id'). " WHERE description != '')");
    }

    /**
     * Determines which filters should be shown if the type 'documents'
     * is selected.
     *
     * @return array containing the filter names and their selectable contents.
     */
    public function getSelectFilters()
    {
        $selects = array();
        $selects[$this->getSelectName('semester')] = $this->getSemesters();
        if (!$GLOBALS['perm']->have_perm('admin')) {
            $selects[$this->getSelectName('seminar')] = $this->getSeminars();
        }
        $selects[$this->getSelectName('institute')] = $this->getInstitutes();
        $selects[$this->getSelectName('file_type')] = $this->getStaticFileTypes();
        return $selects;
    }

    /**
     * Builds and returns an associative array containing SQL-snippets
     * ('joins' and 'conditions') for the different document filter options.
     *
     * @return array
     */
    public function getSearchParams()
    {
        $institute = $_SESSION['global_search']['selects'][$this->getSelectName('institute')];
        $seminar = $_SESSION['global_search']['selects'][$this->getSelectName('seminar')];
        $semester = $_SESSION['global_search']['selects'][$this->getSelectName('semester')];
        $file_type = $_SESSION['global_search']['selects'][$this->getSelectName('file_type')];

        $search_params = array();
        $search_params['joins']     = ' LEFT JOIN dokumente ON  dokumente.dokument_id = search_object.range_id '
                                    . ' LEFT JOIN seminare ON dokumente.seminar_id = seminare.Seminar_id ';
        $search_params['conditions'] = ($institute ? (" AND seminare.Institut_id IN ('" . $this->getInstituteString() . "') ") : ' ')
                                     . ($seminar ? (" AND dokumente.seminar_id ='" . $seminar . "' ") : ' ')
                                     . ($semester ? (" AND seminare.start_time ='" . $semester . "' ") : ' ')
                                     . ($file_type ? (" AND SUBSTRING_INDEX(dokumente.filename, '.', -1) IN " . $this->getFileTypesString($file_type)) : ' ');
        return $search_params;
    }

    /**
     * Gets an additional condition if the user is not root.
     * You only can see documents from seminars you are a part of.
     * (this is the first step, the StudipDocument->checkAccess() method
     * is called later (before presenting the results to the user) in
     * the GlobalSearch.php class).
     *
     * @return string
     */
    public function getCondition()
    {
        return " (EXISTS (SELECT 1 FROM seminar_user WHERE Seminar_id = range2 AND user_id = '{$GLOBALS['user']->id}')) ";
    }

    /**
     * Retruns a link to the found document for the result presentation.
     *
     * @param $object PDO
     * @return string link
     */
    public static function getLink($object)
    {
        return "folder.php?cid={$object['range2']}&data[cmd]=tree&open={$object['range_id']}#anker";
    }

    /**
     * Name of this IndexObject which is presented to the user.
     *
     * @return string
     */
    public static function getType()
    {
        return _('Dokumente');
    }

    /**
     * Returns an avatar representing a generic document.
     *
     * @param $object
     * @return Icon
     */
    public static function getAvatar($object)
    {
        return Icon::create('file', array('class' => "original"));
    }

    /**
     * If a new document is uploaded/created, it will be inserted into
     * the 'search_object' and 'search_index' tables.
     *
     * @param $event
     * @param $document
     */
    public function insert($event, $document)
    {
        // insert new Document into search_object
        $type = 'document';
        $seminar = Course::find($document['seminar_id']);
        $title = $seminar['Name'] . ': ' . $document['name'];
        IndexManager::createObjects(" VALUES ('" . $document['dokument_id'] . "', '"
            . $type . "', '"
            . $title . "', '"
            . $document['seminar_id'] . "', '"
            . $document['range_id'] . "') ");

        // insert new Document into search_index
        $object_id_query = IndexManager::getSearchObjectId($document['dokument_id']);
        IndexManager::createIndex(" VALUES (" . $object_id_query . ", '" . $document['name'] . "', 0) ");
        IndexManager::createIndex(" VALUES (" . $object_id_query . ", '" . $document['description'] . "', 0) ");
    }

    /**
     * If an existing document is being edited, it will be deleted and
     * re-inserted into the 'search_object' and 'search_index' tables.
     *
     * @param $event
     * @param $document
     */
    public function update($event, $document)
    {
        $this->delete($event, $document);
        $this->insert($event, $document);
    }

    /**
     * If an existing document is deleted, it will be deleted from
     * the 'search_object' and 'search_index' tables.
     * NOTE: the order is important!
     *
     * @param $event
     * @param $document
     */
    public function delete($event, $document)
    {
        // delete from search_index
        IndexManager::deleteIndex($document['dokument_id']);

        // delete from search_object
        IndexManager::deleteObjects($document['dokument_id']);
    }
}
