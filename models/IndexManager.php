<?php

/**
 * IndexManager
 * Transforms an array of a definded type into a searchindex
 *
 * @author      Nobody
 */
class IndexManager
{

    // column names in the search_index and search_object table
    const OBJECT_ID = 'object_id';
    const RANGE_ID = 'range_id';
    const TYPE = 'type';
    const TITLE = 'title';
    const RANGE2 = 'range2';
    const RANGE3 = 'range3';
    const TEXT = 'text';
    const RELEVANCE = 'relevance';

    public static $log;
    public static $current_file;
    public static $db;

    public static function sqlIndex($restriction = null)
    {
        set_time_limit(3600);
        self::$db = DBManager::get();
        $time = time();

        self::log("### Indexing started");

        try {
            // Purge DB
            self::$db->query("DROP TABLE IF EXISTS search_object_old, search_index_old");
            self::$db->query('RENAME TABLE search_object TO search_object_old, search_index TO search_index_old');
            self::log("Rename tables.");

            // Create temporary tables
            self::$db->query('CREATE TABLE search_object LIKE search_object_old');
            self::$db->query('CREATE TABLE search_index LIKE search_index_old');
            self::log("New tables created.");

            foreach (glob(__DIR__ . '/IndexObject_*') as $indexFile) {
                $type = explode('_', $indexFile);
                if (!$restriction || stripos(array_pop($type), $restriction) !== false) {
                    $indexClass = basename($indexFile, ".php");
                    $indexObject = new $indexClass;
                    self::log("Indexing $indexClass");
                    $indexObject->sqlIndex();
                    self::log("Finished $indexClass");
                }
            }
            self::log("Finished indexing");

            // Drop old index
            self::$db->query('DROP TABLE search_object_old, search_index_old');
            self::log("Old tables dropped");

            $runtime = time() - $time;
            self::log("FINISHED! Runtime: " . floor($runtime / 60) . ":" . ($runtime % 60));

            // Return runtime
            return $runtime;

        // In case of mysql error imediately abort
        } catch (PDOException $e) {
            // Swap tables
            self::$db->query('DROP TABLE search_object, search_index');
            self::$db->query('RENAME TABLE '
                . 'search_object_old TO search_object,'
                . 'search_index_old TO search_index');
            self::log("MySQL Error occured!");
            self::log($e->getMessage());
            self::log("Aborting");
            self::log("Tables recovered.");
        }
    }

    /**
     * Executes an insert-statement for the table search_object.
     * It is uses as an 'INSERT ... SELECT' statement for the initial indexing
     * and as an 'INSERT ... VALUES' statement for later IndexObject creation.
     *
     * @param $sql string: - '(SELECT ...)' for initial indexing
     *                      - 'VALUES (...)' for later IndexObject creation
     */
    public static function createObjects($sql)
    {
        $stmt = DBManager::get()->prepare("INSERT INTO search_object ("
            . self::RANGE_ID .", "
            . self::TYPE . ", "
            . self::TITLE .", "
            . self::RANGE2 .", "
            . self::RANGE3 .") "
            . $sql );
        $stmt->execute();
    }

    /**
     * Executes an insert-statement for the table search_index.
     * It is uses as an 'INSERT ... SELECT' statement for the initial indexing
     * and as an 'INSERT ... VALUES' statement for later IndexObject creation.
     *
     * @param $sql string: - '(SELECT ...)' for initial indexing
     *                      - 'VALUES (...)' for later IndexObject creation
     */
    public static function createIndex($sql)
    {
        $stmt = DBManager::get()->prepare("INSERT INTO search_index ("
            . self::OBJECT_ID . ", "
            . self::TEXT . ", "
            . self::RELEVANCE .") "
            . $sql);
        $stmt->execute();
    }

    /**
     * Executes a delete-statement to delete the IndexObject from
     * the table search_object.
     *
     * @param $object_id
     */
    public static function deleteObjects($object_id)
    {
        $stmt = DBManager::get()->prepare("DELETE FROM search_object "
            ." WHERE range_id = '" . $object_id . "'");
        $stmt->execute();
    }

    /**
     * Executes a delete-statement to delete the indexed information
     * for respective IndexObject from the table search_object.
     *
     * @param $object_id
     */
    public static function deleteIndex($object_id)
    {
        $stmt = DBManager::get()->prepare("DELETE FROM search_index "
            . " WHERE object_id = " . IndexManager::getSearchObjectId($object_id));
        $stmt->execute();
    }

    public static function relevance($base, $modifier)
    {
        return "pow( $base , ((UNIX_TIMESTAMP() - $modifier ) / 31556926))";
    }

    public static function getSearchObjectId($object_id)
    {
        return " (SELECT object_id FROM search_object WHERE range_id = '" . $object_id . "') ";
    }

    public static function createJoin($on)
    {
        return " JOIN search_object ON (search_object.range_id = $on) ";
    }

    /**
     * Logs an indexing event in the index.log file
     *
     * @param type $info
     */
    public static function log($info)
    {
        if (!self::$log) {
            Log::set('indexlog', dirname(__DIR__) . '/index.log');
            self::$log = Log::get('indexlog');
        }
        self::$log->info((self::$current_file ? : "IndexManager") . ": " . $info);
    }

}
