<?php

class IndexObject_Forumentry extends IndexObject
{

    const RATING_FORUMENTRY = 0.6;
    const RATING_FORUMAUTHOR = 0.7;
    const RATING_FORUMENTRY_TITLE = 0.75;

    /**
     * IndexObject_Forumentry constructor.
     */
    public function __construct()
    {
        $this->setName(_('Forumeinträge'));
        $this->setSelects($this->getSelectFilters());
    }

    /**
     * Fills the 'search_object' and 'search_index' tables with forumentry
     * specific information.
     */
    public function sqlIndex()
    {
        IndexManager::createObjects("(SELECT topic_id, 'forumentry', CONCAT(seminare.name, ': ', COALESCE(NULLIF(TRIM(forum_entries.name), ''), '" . _('Forumeintrag') . "')), seminar_id, null FROM forum_entries JOIN seminare USING (seminar_id) WHERE seminar_id != topic_id)");
        IndexManager::createIndex("(SELECT object_id, name, " . IndexManager::relevance(self::RATING_FORUMENTRY_TITLE, 'forum_entries.chdate') . " FROM forum_entries" . IndexManager::createJoin('topic_id') . " WHERE name != '')");
        IndexManager::createIndex("(SELECT object_id, SUBSTRING_INDEX(content, '<admin_msg', 1), " . IndexManager::relevance(self::RATING_FORUMENTRY, 'forum_entries.chdate') . " FROM forum_entries" . IndexManager::createJoin('topic_id') . " WHERE content != '')");
    }

    /**
     * Determines which filters should be shown if the type 'forumentry'
     * is selected.
     *
     * @return array
     */
    public function getSelectFilters()
    {
        $selects = array();
        $selects[$this->getSelectName('semester')] = $this->getSemesters();
        if (!$GLOBALS['perm']->have_perm('admin')) {
            $selects[$this->getSelectName('seminar')] = $this->getSeminars();
        }
        return $selects;
    }

    /**
     * Builds and returns an associative array containing SQL-snippets
     * ('joins' and 'conditions') for the different forumentry filter options.
     *
     * @return array
     */
    public function getSearchParams()
    {

        $seminar = $_SESSION['global_search']['selects'][$this->getSelectName('seminar')];
        $semester = $_SESSION['global_search']['selects'][$this->getSelectName('semester')];

        $search_params = array();
        $search_params['joins']     = ' LEFT JOIN forum_entries ON forum_entries.topic_id = search_object.range_id '
                                    . ' LEFT JOIN seminare ON seminare.Seminar_id = forum_entries.seminar_id ';
        $search_params['conditions'] = ($seminar ? (" AND seminare.Seminar_id ='" . $seminar . "' ") : ' ')
                                     . ($semester ? (" AND seminare.start_time ='" . $semester . "' ") : ' ');
        return $search_params;
    }

    /**
     * Gets an additional condition if the user is not root.
     * You only can see forumentries from seminars you are a part of.
     *
     * @return string
     */
    public function getCondition()
    {
        return " (EXISTS (SELECT 1 FROM seminar_user WHERE Seminar_id = range2 AND user_id = '{$GLOBALS['user']->id}')) ";
    }

    /**
     * Retruns a link to the found forumentry for the result presentation.
     *
     * @param $object PDO
     * @return string link
     */
    public static function getLink($object)
    {
        return "plugins.php/coreforum/index/index/{$object['range_id']}?cid={$object['range2']}";
    }

    /**
     * Name of this IndexObject which is presented to the user.
     *
     * @return string
     */
    public static function getType()
    {
        return _('Forumeinträge');
    }

    /**
     * Returns an avatar representing a forumentry.
     *
     * @param $object
     * @return Icon
     */
    public static function getAvatar($object)
    {
        return Icon::create('forum', array('class' => "original"));
    }

    /**
     * If a new forumentry is uploaded/created, it will be inserted into
     * the 'search_object' and 'search_index' tables.
     *
     * @param $event
     * @param $topic_id
     */
    public function insert($event, $topic_id)
    {
        $forumentry = ForumEntry::getEntry($topic_id);

        // insert new ForumEntry into search_object
        $type = 'forumentry';
        $seminar = Course::find($forumentry['seminar_id']);
        $title = $seminar['Name'] . ': ' . $forumentry['name'];
        IndexManager::createObjects(" VALUES ('" . $topic_id . "', '"
            . $type . "', '"
            . $title . "', '"
            . $forumentry['seminar_id'] . "', '"
            . null . "') ");

        // insert new ForumEntry into search_index
        $object_id_query = IndexManager::getSearchObjectId($topic_id);
        IndexManager::createIndex(" VALUES (" . $object_id_query . ", '" . $forumentry['name'] . "', " . IndexManager::relevance(self::RATING_FORUMENTRY_TITLE, $forumentry['chdate']) . " ) ");
        IndexManager::createIndex(" VALUES (" . $object_id_query . ", '" . ForumEntry::killEdit($forumentry['content']) . "', 0) ");
    }

    /**
     * If an existing forumentry is being edited, it will be deleted and
     * re-inserted into the 'search_object' and 'search_index' tables.
     *
     * @param $event
     * @param $topic_id
     */
    public function update($event, $topic_id)
    {
        $this->delete($event, $topic_id);
        $this->insert($event, $topic_id);
    }

    /**
     * If an existing forumentry is deleted, it will be deleted from
     * the 'search_object' and 'search_index' tables.
     * NOTE: the order is important!
     *
     * @param $event
     * @param $topic_id
     */
    public function delete($event, $topic_id)
    {
        // delete from search_index
        IndexManager::deleteIndex($topic_id);

        // delete from search_object
        IndexManager::deleteObjects($topic_id);
    }
}
