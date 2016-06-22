<?php

class IndexObject_Forumentry extends IndexObject
{

    const RATING_FORUMENTRY = 0.6;
    const RATING_FORUMAUTHOR = 0.7;
    const RATING_FORUMENTRY_TITLE = 0.75;

    const BELONGS_TO = array('seminar', 'institute');

    public function __construct()
    {
        $this->setName(_('Forumeintr�ge'));
        $this->setFacets(array('Foo', 'Bar', 'Foobar'));
    }

    public function sqlIndex()
    {
        IndexManager::createObjects("SELECT topic_id, 'forumentry', CONCAT(seminare.name, ': ', COALESCE(NULLIF(TRIM(forum_entries.name), ''), '" . _('Forumeintrag') . "')), seminar_id, null FROM forum_entries JOIN seminare USING (seminar_id) WHERE seminar_id != topic_id");
        IndexManager::createIndex("SELECT object_id, name, " . IndexManager::relevance(self::RATING_FORUMENTRY_TITLE, 'forum_entries.chdate') . " FROM forum_entries" . IndexManager::createJoin('topic_id') . " WHERE name != ''");
        IndexManager::createIndex("SELECT object_id, content, " . IndexManager::relevance(self::RATING_FORUMENTRY, 'forum_entries.chdate') . " FROM forum_entries" . IndexManager::createJoin('topic_id') . " WHERE content != ''");
        IndexManager::createIndex("SELECT object_id, CONCAT_WS(' ', vorname, nachname, username), " . IndexManager::relevance(self::RATING_FORUMAUTHOR, 'forum_entries.chdate') . " FROM forum_entries JOIN auth_user_md5 USING (user_id) " . IndexManager::createJoin('topic_id'));
    }

    public function getLink($object)
    {
        return "plugins.php/coreforum/index/index/{$object['range_id']}?cid={$object['range2']}";
    }

    public function getCondition()
    {
        return "EXISTS (SELECT 1 FROM seminar_user WHERE Seminar_id = range2 AND user_id = '{$GLOBALS['user']->id}')";
    }

    public function getAvatar()
    {
        return Assets::img('icons/16/black/forum.png');
    }

    /**
     * @param $type string
     * @return bool
     */
    public static function belongsTo($type)
    {
        return in_array($type, self::BELONGS_TO);
    }

}
