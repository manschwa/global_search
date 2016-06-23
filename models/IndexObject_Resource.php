<?php

class IndexObject_Resource extends IndexObject
{

    const RATING_RESOURCE_NAME = 1.0;
    const RATING_RESOURCE_DESCRIPTION = 0.8;

    public function __construct()
    {
        $this->setName(_('Ressourcen'));
        $this->setFacets(array('R�ume', 'Andere'));
    }

    public function sqlIndex() {
        IndexManager::createObjects("SELECT resource_id, 'resource', name, null,null FROM resources_objects");
        IndexManager::createIndex("SELECT object_id, name, " . self::RATING_RESOURCE_NAME . " FROM resources_objects" . IndexManager::createJoin('resource_id') . " WHERE name != ''");
        IndexManager::createIndex("SELECT object_id, description, " . self::RATING_RESOURCE_DESCRIPTION . " FROM resources_objects" . IndexManager::createJoin('resource_id') . " WHERE description != ''");
    }

    public function getLink($object) {
        return "resources.php?open_level={$object['range_id']}";
    }

    public function getAvatar() {
        return Assets::img('icons/16/black/resources.png');
    }
}
