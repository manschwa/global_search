<?php

class IndexObject_Institute extends IndexObject
{

    const RATING_INSTITUTE = 1.1;

    /**
     * IndexObject_Institute constructor.
     */
    public function __construct()
    {
        $this->setName(_('Einrichtungen'));
    }

    /**
     * Fills the 'search_object' and 'search_index' tables with institute
     * specific information.
     */
    public function sqlIndex()
    {
        IndexManager::createObjects("(SELECT Institut_id, 'institute', Name, null,null FROM Institute)");
        IndexManager::createIndex("(SELECT object_id, Name, " . self::RATING_INSTITUTE . " FROM Institute"
            . IndexManager::createJoin('Institut_id') . " WHERE Name != '')");
    }

    /**
     * Retruns a link to the found institute for the result presentation.
     *
     * @param $object PDO
     * @return string link
     */
    public static function getLink($object)
    {
        return "dispatch.php/institute/overview?cid={$object['range_id']}";
    }

    /**
     * Name of this IndexObject which is presented to the user.
     *
     * @return string
     */
    public static function getType()
    {
        return _('Einrichtungen');
    }

    /**
     * Returns an avatar representing the institute
     * (generic or institute specific).
     *
     * @param $object
     * @return Icon
     */
    public static function getAvatar($object)
    {
        return InstituteAvatar::getAvatar($object['range_id'])->getImageTag(Avatar::MEDIUM);
    }

    /**
     * If a new institute is uploaded/created, it will be inserted into
     * the 'search_object' and 'search_index' tables.
     *
     * @param $event
     * @param $institute
     */
    public function insert($event, $institute)
    {
        // insert new Institute into search_object
        $type = 'institute';
        $title = $institute['name'];
        IndexManager::createObjects(" VALUES ('" . $institute['institut_id'] . "', '"
            . $type . "', '"
            . $title . "', '"
            . null . "', '"
            . null . "') ");

        // insert new Institute into search_index
        $object_id_query = IndexManager::getSearchObjectId($institute['institut_id']);
        IndexManager::createIndex(" VALUES (" . $object_id_query . ", '" . $title . "', "
            . self::RATING_INSTITUTE . ") ");
    }

    /**
     * If an existing institute is being edited, it will be updated
     * in the 'search_object' and 'search_index' tables.
     *
     * @param $event
     * @param $institute
     */
    public function update($event, $institute)
    {
        $this->delete($event, $institute);
        $this->insert($event, $institute);
    }

    /**
     * If an existing institute is deleted, it will be deleted from
     * the 'search_object' and 'search_index' tables.
     * NOTE: the order is important!
     *
     * @param $event
     * @param $institute
     */
    public function delete($event, $institute)
    {
        // delete from search_index
        IndexManager::deleteIndex($institute['institut_id']);

        // delete from search_object
        IndexManager::deleteObjects($institute['institut_id']);
    }
}
