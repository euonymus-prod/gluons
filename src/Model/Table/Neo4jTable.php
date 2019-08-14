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
    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config)
    {
        parent::initialize($config);
    }

    /****************************************************************************/
    /* Edit Data                                                                */
    /****************************************************************************/

    /****************************************************************************/
    /* Get Data                                                                 */
    /****************************************************************************/
    public static function getQuarks($privacy_mode = \App\Controller\AppController::PRIVACY_PUBLIC, $user_id = null)
    {
        if (($privacy_mode != \App\Controller\AppController::PRIVACY_PUBLIC) && is_null($user_id)) return false;

        // build cypher query
        // $where = self::wherePrivacy($privacy_mode, $user_id);
        $where = '';
        $query = 'MATCH (subject)'
               .$where
               .'RETURN subject ORDER BY (CASE subject.created WHEN null THEN {} ELSE subject.created END) DESC limit 100';


        // connect to neo4j
        $client = ClientBuilder::create()
                ->addConnection('http', 'http://neo4j:neo4jn30Aj@localhost:7474')
                ->build();

        // run cypher
        $result = $client->run($query);

        $ret = [];
        foreach ($result->getRecords() as $key => $record) {
            $ret[] = self::buildNodeArr($record->value('subject'));
        }
        // Log::write('debug', $ret);
        return $ret;
    }

    public static function getOnesGraph($name, $privacy_mode = \App\Controller\AppController::PRIVACY_PUBLIC, $user_id = null)
    {
        if (($privacy_mode != \App\Controller\AppController::PRIVACY_PUBLIC) && is_null($user_id)) return false;

        // build cypher query
        $where = self::wherePrivacy($privacy_mode, $user_id);
        $query = 'MATCH (subject {name: {name}})-[relation]-(object) '
               .$where
               .'RETURN DISTINCT subject, object, relation';
        $parameter = ['name' => $name];

        // connect to neo4j
        $client = ClientBuilder::create()
                ->addConnection('http', 'http://neo4j:neo4jn30Aj@localhost:7474')
                ->build();

        // run cypher
        $result = $client->run($query, $parameter);
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

    /*******************************************************/
    /* where                                               */
    /*******************************************************/
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

    /****************************************************************************/
    /* Tools                                                                    */
    /****************************************************************************/
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
