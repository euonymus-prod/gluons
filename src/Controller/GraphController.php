<?php
namespace App\Controller;

use App\Controller\AppController;

use Cake\ORM\TableRegistry;
use Cake\Network\Exception\NotFoundException;

use Cake\Cache\Cache;
use Cake\Routing\Router;
use App\Utils\U;

use GraphAware\Neo4j\Client\ClientBuilder;
use Cake\Log\Log;

/**
 * Graph Controller
 *
 * @property Neo4j Nodes
 */
class GraphController extends AppController
{
    public function isAuthorized($user)
    {
        if (in_array($this->request->action, ['name', 'privateName'])) {
            return true;
        }
        return parent::isAuthorized($user);
    }

    public function initialize()
    {
        parent::initialize();
        $this->loadComponent('RequestHandler');
        $this->RequestHandler->renderAs($this, 'json');
        $this->response->type('application/json');
        $this->response->header("Access-Control-Allow-Origin: *");
    }

    // API endpoint:  /graph/:name
    public function name($name = null)
    {
        $graph = $this->_getOnesGraph($name);
        if (!$graph || count($graph) == 0) {
            $res = ['status' => 0, 'message' => 'Not found'];
        } else {
            $res = $graph;
        }
        // Log::write('debug', $res);

        $this->set('articles', $res);
        $this->set('_serialize', 'articles');
    }

    public function privateName($name = null, $privacy = \App\Controller\AppController::PRIVACY_PUBLIC)
    {
        if (($this->Auth->user('role') !== 'admin') && ($privacy == \App\Controller\AppController::PRIVACY_ADMIN)) {
            throw new NotFoundException(__('記事が見つかりません'));
        }

        $graph = $this->_getOnesGraph($name, $privacy, $this->Auth->user('id'));
        if (!$graph || count($graph) == 0) {
            $res = ['status' => 0, 'message' => 'Not found'];
        } else {
            $res = $graph;
        }
        // Log::write('debug', $res);

        $this->set('articles', $res);
        $this->set('_serialize', 'articles');
    }

    public function _getOnesGraph($name, $privacy_mode = \App\Controller\AppController::PRIVACY_PUBLIC, $user_id = null)
    {
        if (($privacy_mode != \App\Controller\AppController::PRIVACY_PUBLIC) && is_null($user_id)) return false;

        // build cypher query
        $where = self::_wherePrivacy($privacy_mode, $user_id);
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
        $ret = ['subject' => $this->_buildNodeArr($subject), 'relations' => []];

        foreach ($result->getRecords() as $key => $record) {
            $active = $this->_getActiveNode($record);
            $passive = $this->_getPassiveNode($record);
            $relation = $record->value('relation');

            $ret['relations'][] = [
                'relation' => $this->_buildRelationshipArr($relation),
                'active' => $this->_buildNodeArr($active),
                'passive' => $this->_buildNodeArr($passive),
            ];
        }
        // Log::write('debug', $ret);
        return $ret;
    }

    public static function _wherePrivacy($privacy_mode, $user_id = 1)
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
    public function _getActiveNode($relation_record)
    {
        $obj = $this->_getGraphReturns($relation_record);
        if (!$obj) return false;
        return $this->_isActiveNode($obj['subject'], $obj['relation']) ? $obj['subject'] : $obj['object'];
    }
    public function _getPassiveNode($relation_record)
    {
        $obj = $this->_getGraphReturns($relation_record);
        if (!$obj) return false;
        return $this->_isActiveNode($obj['subject'], $obj['relation']) ? $obj['object'] : $obj['subject'];
    }
    public function _buildNodeArr($node)
    {
        $ret = [];
        $ret['identity'] = $node->identity();
        $ret['labels'] = $node->labels();
        $ret['values'] = $node->values();
        return $ret;
    }
    public function _buildRelationshipArr($relationship)
    {
        $ret = [];
        $ret['identity'] = $relationship->identity();
        $ret['type'] = $relationship->type();
        $ret['values'] = $relationship->values();
        return $ret;
    }
    public function _getGraphReturns($relation_record)
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
    public function _isActiveNode($node, $relationship)
    {
        return $relationship->startNodeIdentity() == $node->identity();
    }

}
