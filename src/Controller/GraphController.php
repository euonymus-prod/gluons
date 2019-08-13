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

    public function privateName($name = null, $privacy = 1)
    {
        if (($this->Auth->user('role') !== 'admin') && ($privacy == 4)) {
            throw new NotFoundException(__('記事が見つかりません'));
        }

        // $Subjects = TableRegistry::get('Subjects');
      
        // $query = $Subjects->find()->where($Subjects->wherePrivacyNameExplicitly($name, $privacy));
        // if ($query->count() == 0) {
        //     $res = ['status' => 0, 'message' => 'Not found'];
        // } else {
        //     $res = $query->first();
        // }
        $res = ['hoge' => 'hage'];
        $this->set('articles', $res);
        $this->set('_serialize', 'articles');
    }

    public function _getOnesGraph($name)
    {
        $client = ClientBuilder::create()
                ->addConnection('http', 'http://neo4j:neo4jn30Aj@localhost:7474')
                ->build();

        $query = 'MATCH (subject {name: {name}})-[relation]-(object) RETURN DISTINCT subject, object, relation';
        $parameter = ['name' => $name];
        $result = $client->run($query, $parameter);
        if (!$result->records()) return false;

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
