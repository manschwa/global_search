<?php

class IndexObject_Forumentry {

    const RATING_FORUMENTRY = 0.7;

    public static function sqlIndex() {
        IndexManager::createObjects("SELECT topic_id, 'forumentry', name, seminar_id, null FROM forum_entries");
        IndexManager::createIndex("SELECT object_id, content, " . IndexManager::relevance(self::RATING_FORUMENTRY, 'forum_entries.chdate') . " FROM forum_entries".IndexManager::createJoin('topic_id'));
    }

    public static function getName() {
        return _('Foreneintrag');
    }
    
    public static function link($object) {
        return "plugins.php/coreforum/index/index/{$object['range_id']}?cid={$object['range2']}";
    }
    
    public static function getCondition() {
        return "EXISTS (SELECT 1 FROM seminar_user WHERE Seminar_id = range2 AND user_id = '{$GLOBALS['user']->id}')";
    }

}