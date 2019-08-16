<?php
namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

use Cake\ORM\TableRegistry;
use Cake\Network\Http\Client;
use Cake\Cache\Cache;

use App\Model\Table\QuarkTypesTable;

use Cake\Network\Exception\NotFoundException;

use App\Utils\U;
use App\Utils\NgramConverter;

use Cake\Log\Log;
use GraphAware\Neo4j\Client\ClientBuilder;

/**
 * Neo4j Model
 */
class Neo4jTable extends AppTable
{
    const CYPHER_CREATE_QUARK =<<<__EOD__
CREATE (n:[NODE_LABEL] {
    id: {id},
    name: {name},
    en_name: {en_name},
    image_path: {image_path},
    description: {description},
    en_description: {en_description},
    start: {start},
    end: {end},
    start_accuracy: {start_accuracy},
    end_accuracy: {end_accuracy},
    is_momentary: {is_momentary},
    url: {url},
    affiliate: {affiliate},
    gender: {gender},
    is_private: {is_private},
    is_exclusive: {is_exclusive},
    user_id: {user_id},
    last_modified_user: {last_modified_user},
    quark_type_id: {quark_type_id},
    created: {created},
    modified: {modified}
})
SET
  n.start = CASE n.start
    WHEN 'NULL' THEN null
    WHEN '0000-00-00 00:00:00' THEN null
    ELSE datetime(n.start)
    END,
  n.end = CASE n.end
    WHEN 'NULL' THEN null
    WHEN '0000-00-00 00:00:00' THEN null
    ELSE datetime(n.end)
    END,
  n.created = CASE n.created
    WHEN 'NULL' THEN null
    WHEN '0000-00-00 00:00:00' THEN null
    ELSE datetime(n.created)
    END,
  n.modified = CASE n.modified
    WHEN 'NULL' THEN null
    WHEN '0000-00-00 00:00:00' THEN null
    ELSE datetime(n.modified)
    END
RETURN n
__EOD__;

    const NEO4J_DATETIME_FORMAT = 'Y-m-d\TH:i:s+0900';
    const RECORD_PER_PAGE = 100;
    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config)
    {
        parent::initialize($config);

        // connect to neo4j
        $this->client = ClientBuilder::create()
                      ->addConnection('http', 'http://neo4j:neo4jn30Aj@localhost:7474')
                      ->build();
    }

    /****************************************************************************/
    /* Get Data                                                                 */
    /****************************************************************************/
    public function getNode($id)
    {
        if (!is_numeric($id)) return false;
        $query = 'MATCH (n) WHERE ID(n) = '.$id.' RETURN n';

        // run cypher
        $result = $this->client->run($query);
        if (count($result->records()) === 0) return false;
        return self::buildNodeArr($result->getRecord()->value('n'));
    }
    public function getByName($name)
    {
        // build cypher query
        $query = 'MATCH (subject {name: {name} }) RETURN subject';
        $parameters = ['name' => $name];

        // run cypher
        $result = $this->client->run($query, $parameters);
        if (count($result->records()) === 0) return false;

        return self::buildNodeArr($result->getRecord()->value('subject'));
    }

    public function getQuarks($page, $privacy_mode = \App\Controller\AppController::PRIVACY_PUBLIC, $user_id = null)
    {
        if (($privacy_mode != \App\Controller\AppController::PRIVACY_PUBLIC) && is_null($user_id)) return false;

        // build cypher query
        $skip = self::RECORD_PER_PAGE * ($page - 1);
        $where = self::whereNodePrivacy($privacy_mode, $user_id);
        $query = 'MATCH (subject) '
               .$where
               .'RETURN subject ORDER BY (CASE subject.created WHEN null THEN {} ELSE subject.created END) DESC SKIP '. $skip.' LIMIT '.self::RECORD_PER_PAGE;
        // NOTE: Null always comes the first, when Desc Order. So above the little bit of trick.
        // https://github.com/opencypher/openCypher/issues/238

        // run cypher
        $result = $this->client->run($query);

        $ret = [];
        foreach ($result->getRecords() as $key => $record) {
            $ret[] = self::buildNodeArr($record->value('subject'));
        }
        // Log::write('debug', $ret);
        return $ret;
    }

    public function searchQuarks($search_words, $page, $privacy_mode = \App\Controller\AppController::PRIVACY_PUBLIC, $user_id = null)
    {
        if (($privacy_mode != \App\Controller\AppController::PRIVACY_PUBLIC) && is_null($user_id)) return false;

        // build cypher query
        $skip = self::RECORD_PER_PAGE * ($page - 1);
        $where = self::whereNodePrivacy($privacy_mode, $user_id, 'node');
        $query = 'CALL db.index.fulltext.queryNodes("nameAndDescription", {search_words}) YIELD node '
               .$where
               .'RETURN node as subject SKIP '. $skip.' LIMIT '.self::RECORD_PER_PAGE;
        $parameters = ['search_words' => $search_words];
        // Log::write('debug',$query);

        // run cypher
        $result = $this->client->run($query, $parameters);
        // Log::write('debug',$result);
        

        $ret = [];
        foreach ($result->getRecords() as $key => $record) {
            $ret[] = self::buildNodeArr($record->value('subject'));
        }
        // Log::write('debug', $ret);
        return $ret;
    }

    public function getOnesGraph($name, $privacy_mode = \App\Controller\AppController::PRIVACY_PUBLIC, $user_id = null)
    {
        if (($privacy_mode != \App\Controller\AppController::PRIVACY_PUBLIC) && is_null($user_id)) return false;

        // build cypher query
        $where = self::wherePrivacy($privacy_mode, $user_id);
        $query = 'MATCH (subject {name: {name}})-[relation]-(object) '
               .$where
               .'RETURN DISTINCT subject, object, relation';
        $parameters = ['name' => $name];

        // run cypher
        $result = $this->client->run($query, $parameters);
        if (!$result->records()) return false;

        // format result array
        $subject = $result->getRecord()->value('subject');
        $ret = ['subject' => self::buildNodeArr($subject), 'relations' => []];

        foreach ($result->getRecords() as $key => $record) {
            $active = self::getActiveNode($record);
            $passive = self::getPassiveNode($record);
            $relation = $record->value('relation');

            $ret['relations'][] = [
                'relation' => self::buildRelationshipArr($relation),
                'active' => self::buildNodeArr($active),
                'passive' => self::buildNodeArr($passive),
            ];
        }
        // Log::write('debug', $ret);
        return $ret;
    }

    /*******************************************************/
    /* Save Data                                           */
    /*******************************************************/
    public function saveQuark($data, $user_id)
    {
        if (!array_key_exists('name', $data) || empty($data['name'])) return false;
        if (!array_key_exists('quark_type_id', $data) || empty($data['quark_type_id']))
            $data['quark_type_id'] = QuarkTypesTable::TYPE_THING;

        // build cypher query
        $label = self::getLabel($data['quark_type_id']);
        $query = str_replace('[NODE_LABEL]', $label, self::CYPHER_CREATE_QUARK);

        // Format Properties
        $parameters = self::formatQuarkParameters($data, $user_id);
        // Log::write('debug', var_dump($parameters));

        // run cypher
        $result = $this->client->run($query, $parameters);
        if (count($result->records()) === 0) return false;
        return self::buildNodeArr($result->getRecord()->value('n'));
    }
    public function deleteNode($id)
    {
        // existence check
        $node = $this->getNode($id);
        if (!$node) return false;
        Log::write('debug', 'deleting: ' . $node['values']['name']);

        // build delete cypher query
        $query = 'MATCH (n) WHERE ID(n) = '.$id.' DETACH DELETE n';

        // run cypher
        return $this->client->run($query);
    }

    /*******************************************************/
    /* Edit Data                                           */
    /*******************************************************/
    public function editQuark($id, $data, $user_id)
    {
        // existence check
        $node = $this->getNode($id);
        if (!$node) return false;
        Log::write('debug', 'updating: ' . $node['values']['name']);


        // TODO: 最初に quark_type_id の変更があるかどうかをチェック（Labelの更新が必要になる)
        $label = false;
        if (array_key_exists('quark_type_id', $data) && !empty($data['quark_type_id']))
            $label = self::getLabel($data['quark_type_id']);




        Log::write('debug', $data);



        // if (!array_key_exists('name', $data) || empty($data['name'])) return false;

        // // build cypher query
        // $label = self::getLabel($data['quark_type_id']);
        // $query = str_replace('[NODE_LABEL]', $label, self::CYPHER_CREATE_QUARK);

        // // Format Properties
        // $parameters = self::formatQuarkParameters($data, $user_id);






        // // build delete cypher query
        // $query = 'MATCH (n) WHERE ID(n) = '.$id.' DETACH DELETE n';

        // // run cypher
        // return $this->client->run($query);
    }


    /*******************************************************/
    /* where                                               */
    /*******************************************************/
    public static function whereNodePrivacy($privacy_mode, $user_id = 1, $node_name = 'subject')
    {
        if ($privacy_mode == \App\Controller\AppController::PRIVACY_PUBLIC) {
            // Only Public
            return 'WHERE '.$node_name.'.is_private = false ';
        } elseif ($privacy_mode == \App\Controller\AppController::PRIVACY_PRIVATE) {
            // Only Private
            return 'WHERE '.$node_name.'.is_private = true AND '.$node_name.'.user_id = '.$user_id.' ';
        } elseif ($privacy_mode == \App\Controller\AppController::PRIVACY_ALL) {
            // All The User can see
            return 'WHERE ('.$node_name.'.is_private = false OR '.$node_name.'.user_id = '.$user_id.') ';
        } elseif ($privacy_mode == \App\Controller\AppController::PRIVACY_ADMIN) {
            return '';
        }
    }
        
    public static function wherePrivacy($privacy_mode, $user_id = 1)
    {
        if ($privacy_mode == \App\Controller\AppController::PRIVACY_PUBLIC) {
            // Only Public
            return 'WHERE subject.is_private = false AND object.is_private = false ';
        } elseif ($privacy_mode == \App\Controller\AppController::PRIVACY_PRIVATE) {
            // Only Private
            return 'WHERE subject.is_private = true AND subject.user_id = '.$user_id.
                   ' AND object.is_private = true AND object.user_id = '.$user_id. ' ';
        } elseif ($privacy_mode == \App\Controller\AppController::PRIVACY_ALL) {
            // All The User can see
            return 'WHERE ('
                .'(subject.is_private = false OR subject.user_id = '.$user_id.') AND '
                .'(object.is_private = false OR object.user_id = '.$user_id.') '
                .') ';
        } elseif ($privacy_mode == \App\Controller\AppController::PRIVACY_ADMIN) {
            return '';
        }
    }

    /*******************************************************/
    /* quarks                                              */
    /*******************************************************/
    /*******************************************************/
    /* gluons                                              */
    /*******************************************************/

    /*******************************************************/
    /* Formatter                                           */
    /*******************************************************/
    public static function buildGuid()
    {
        return U::buildGuid(); // varchar 36 フィールドのinsertには必要。
    }
    public static function addImageBySearch($data)
    {
        if (array_key_exists('image_path', $data) && !empty($data['image_path'])) return $data;
        if (!array_key_exists('name', $data) || empty($data['name'])) return $data;

        $QuarkTypes = TableRegistry::get('QuarkTypes');

        if (!array_key_exists('quark_type_id', $data) || empty($data['quark_type_id']))
            $data['quark_type_id'] = QuarkTypesTable::TYPE_THING;

        $quark_type = $QuarkTypes->get($data['quark_type_id']);
        $data['image_path'] = $quark_type->image_path;
        return $data;
    }
    public static function addTextProperty($data, $key)
    {
        if (!array_key_exists($key, $data) || empty($data[$key])) {
            $data[$key] = null;
        }
        return $data;
    }
    public static function addDateTimeProperty($data, $key)
    {
        if (array_key_exists($key, $data) && !empty($data[$key])) {
            $time = strtotime($data[$key]);
            $data[$key] = date(self::NEO4J_DATETIME_FORMAT, $time);
        } else {
            $data[$key] = null;
        }
        return $data;
    }
    public static function addBoolProperty($data, $key)
    {
        if (array_key_exists($key, $data) && !empty($data[$key])) {
            $data[$key] = !!$data[$key];
        } else {
            $data[$key] = false;
        }
        return $data;
    }
    public static function formatQuarkParameters($data, $user_id)
    {
        $data['id'] = self::buildGuid();
        $data['user_id'] = $user_id;
        $data['last_modified_user'] = $user_id;
        $data = self::addImageBySearch($data);

        $data = self::addDateTimeProperty($data, 'start');
        $data = self::addDateTimeProperty($data, 'end');

        foreach (['description', 'start_accuracy', 'end_accuracy', 'url', 'affiliate'] as $property) {
            $data = self::addTextProperty($data, $property);
        }
        foreach (['is_momentary', 'is_private', 'is_exclusive'] as $property) {
            $data = self::addBoolProperty($data, $property);
        }

        $now = date(self::NEO4J_DATETIME_FORMAT, time());
        $data['created'] = $now;
        $data['modified'] = $now;

        // extra properties for future use
        $data['en_name'] = '';
        $data['en_description'] = '';
        $data['gender'] = null;

        return $data;
    }

    /*******************************************************/
    /* Tools                                               */
    /*******************************************************/
    public static function getLabel($quark_type_id)
    {
        $QuarkTypes = TableRegistry::get('QuarkTypes');
        // NOTE: Model->get($id) Issues Exception when there is not. So I don't check existance of name
        $quark_type = $QuarkTypes->get($quark_type_id);
        return $quark_type->name;
    }
    public static function buildNodeArr($node)
    {
        return [
            'identity' => $node->identity(),
            'labels' => $node->labels(),
            'values' => $node->values()
        ];
    }
    public static function buildRelationshipArr($relationship)
    {
        return [
            'identity' => $relationship->identity(),
            'type' => $relationship->type(),
            'values' => $relationship->values()
        ];
    }
    public static function getActiveNode($relation_record)
    {
        $obj = self::getGraphReturns($relation_record);
        if (!$obj) return false;
        return self::isActiveNode($obj['subject'], $obj['relation']) ? $obj['subject'] : $obj['object'];
    }
    public static function getPassiveNode($relation_record)
    {
        $obj = self::getGraphReturns($relation_record);
        if (!$obj) return false;
        return self::isActiveNode($obj['subject'], $obj['relation']) ? $obj['object'] : $obj['subject'];
    }
    public static function getGraphReturns($relation_record)
    {
        if (!in_array('subject', $relation_record->keys()) ||
            !in_array('object', $relation_record->keys()) ||
            !in_array('relation', $relation_record->keys())) return false;
        return [
            'subject' => $relation_record->value('subject'),
            'object' => $relation_record->value('object'),
            'relation' => $relation_record->value('relation')
        ];
    }
    public static function isActiveNode($node, $relationship)
    {
        return $relationship->startNodeIdentity() == $node->identity();
    }
}
