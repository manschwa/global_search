<?php

require_once 'lib/user_visible.inc.php';

class IndexObject_User extends IndexObject
{

    // arbitrary rating value for this object class for the search presentation
    const RATING_USER = 0.9;

    /**
     * IndexObject_User constructor.
     */
    public function __construct()
    {
        $this->setName(_('Personen'));
        $this->setSelects($this->getSelectFilters());
    }

    /**
     * Fills the 'search_object' and 'search_index' tables with user
     * specific information.
     */
    public function sqlIndex()
    {
        IndexManager::createObjects("SELECT user_id, 'user', CONCAT_WS(' ',title_front, Vorname, Nachname, title_rear), username, null FROM auth_user_md5 JOIN user_info USING (user_id)");
        IndexManager::createIndex("SELECT object_id, CONCAT_WS(' ', Vorname, Nachname, "
                . "CONCAT('(', username, ')')), "
                . self::RATING_USER." + LOG((SELECT avg(score) FROM user_info WHERE score != 0), score + 3) "
                . " FROM auth_user_md5 JOIN user_info USING (user_id) JOIN search_object_temp ON (user_id = range_id)");
    }

    /**
     * Determines which filters should be shown if the type 'user'
     * is selected.
     *
     * @return array
     */
    public function getSelectFilters()
    {
        $selects = array();
        $selects[$this->getSelectName('institute')] = $this->getInstitutes();
        return $selects;
    }

    /**
     * Builds and returns an associative array containing SQL-snippets
     * ('joins' and 'conditions') for the different 'user' filter options.
     *
     * @return array
     */
    public function getSearchParams()
    {
        $institute = $_SESSION['global_search']['selects'][$this->getSelectName('institute')];

        $search_params = array();
        $search_params['joins']     = ' LEFT JOIN user_inst ON  user_inst.user_id = search_object.range_id ';
        $search_params['conditions'] = ($institute ? (" AND Institut_id IN ('" . $this->getInstituteString() . "') AND inst_perms != 'user' ") : ' ');
        return $search_params;
    }

    /**
     * Gets an additional condition if the user is not root.
     * You only can see users that are visible.
     * (using the 'get_vis_query()' method)
     *
     * @return string
     */
    public function getCondition()
    {
        return " (EXISTS (SELECT 1 FROM auth_user_md5 LEFT JOIN user_visibility USING (user_id) WHERE user_id = search_object.range_id AND " . get_vis_query('auth_user_md5', 'search') .")) ";
    }

    /**
     * Retruns a link to the found user for the result presentation.
     *
     * @param $object PDO
     * @return string link
     */
    public static function getLink($object)
    {
        return 'dispatch.php/profile?username=' . $object['range2'];
    }

    /**
     * Name of this IndexObject which is presented to the user.
     *
     * @return string
     */
    public static function getType()
    {
        return _('Personen');
    }

    /**
     * Returns an avatar representing the user.
     *
     * @param $object
     * @return Icon
     */
    public static function getAvatar($object)
    {
        return Avatar::getAvatar($object['range_id'])->getImageTag(Avatar::MEDIUM);
    }

    /**
     * If a new user is created, it will be inserted into
     * the 'search_object' and 'search_index' tables.
     *
     * @param $event
     * @param $user
     */
    public function insert($event, $user)
    {
        $statement = $this->getInsertStatement();

        // insert new User into search_object
        $type = 'user';
        $title = $user['title_front'] . ' ' . $user['vorname'] . ' ' . $user['nachname'] . ' ' . $user['title_rear'];
        $statement['object']->execute(array($user['user_id'], $type, $title, $user['username'], null));

        // insert new User into search_index
        $text = $title . ' (' . $user['username'] . ')';
        $statement['index']->execute(array($user['user_id'], $text));
    }

    /**
     * If an existing user is being edited, it will be deleted and
     * re-inserted into the 'search_object' and 'search_index' tables.
     *
     * @param $event
     * @param $user
     */
    public function update($event, $user)
    {
        $this->delete($event, $user);
        $this->insert($event, $user);
    }

    /**
     * If an existing user is deleted, it will be deleted from
     * the 'search_object' and 'search_index' tables.
     *
     * @param $event
     * @param $user
     */
    public function delete($event, $user)
    {
        $statement = $this->getDeleteStatement();
        // delete from search_index
        $statement['index']->execute(array($user['user_id']));

        // delete from search_object
        $statement['object']->execute(array($user['user_id']));
    }

}
