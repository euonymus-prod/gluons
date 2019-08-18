<?php
namespace App\Controller;

use App\Controller\AppController;

use Cake\ORM\TableRegistry;
use Cake\Network\Exception\NotFoundException;

use Cake\Cache\Cache;
use Cake\Routing\Router;
use App\Utils\U;

use App\Model\Table\QuarkTypesTable;

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
        $Neo4j = TableRegistry::get('Neo4j');
        $graph = $Neo4j->getOnesGraph($name);
        if (!$graph || count($graph) == 0) {
            $res = ['status' => 0, 'message' => 'Not found'];
        } else {
            $graph['relations'] = $this->_formatByQuarkProperties($graph);
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

        $Neo4j = TableRegistry::get('Neo4j');
        $graph = $Neo4j->getOnesGraph($name, $privacy, $this->Auth->user('id'));
        if (!$graph || count($graph) == 0) {
            $res = ['status' => 0, 'message' => 'Not found'];
        } else {
            $graph['relations'] = $this->_formatByQuarkProperties($graph);
            $res = $graph;
        }
        // Log::write('debug', $res);

        $this->set('articles', $res);
        $this->set('_serialize', 'articles');
    }

    /*************************************************************/
    /* 以下 Quark Property別の Gluon再構成関数                      */
    /*************************************************************/
    public function _formatByQuarkProperties($graph)
    {
        $data = $graph['subject']['values'];
        if (!array_key_exists('quark_type_id', $data) || empty($data['quark_type_id'])) {
            $data['quark_type_id'] = QuarkTypesTable::TYPE_THING;
        }
        $quark_type_id = $data['quark_type_id'];

        $QtypeProperties = TableRegistry::get('QtypeProperties');
        $qtype_properties = $QtypeProperties->findByQuarkTypeId($quark_type_id);
        $ret = [];
        foreach($qtype_properties as $qtype_property) {
            $quark_property_id = $qtype_property['quark_property_id'];
            $gluons_related = $this->_getGluonTypesRelated($quark_property_id, $graph);
            $quark_property = $this->_getQuarkProperty($quark_property_id);

            $ret[$quark_property_id] = [
                'quark_property' => $quark_property,
                'gluons_related' => $gluons_related
            ];
        }

        $ret['others'] = [
            'quark_property' => [
                'name' => 'others',
                'caption' => 'others',
                'caption_ja' => 'その他',
            ],
            'gluons_related' => []
        ];
        foreach($graph['relations'] as $gluon) {
            $notInArray = true;
            foreach($ret as $quark_property) {
                if (count($quark_property['gluons_related']) === 0) continue;
                foreach($quark_property['gluons_related'] as $property_gluons) {
                    // Log::write('debug', 'A: '.$property_gluons['relation']['identity']);
                    // Log::write('debug', $gluon['relation']['identity']);
                    if ($property_gluons['relation']['identity'] == $gluon['relation']['identity']) {
                        $notInArray = false;
                        break;
                    }
                }
                if (!$notInArray) break;
            }
            if ($notInArray) {
                $ret['others']['gluons_related'][] = $gluon;
            }
        }
        return $ret;
    }
    public function _getQuarkProperty($quark_property_id)
    {
        $QuarkProperties = TableRegistry::get('QuarkProperties');
        return $QuarkProperties->get($quark_property_id)->toArray();
    }
    public function _getGluonTypesRelated($quark_property_id, $graph)
    {
        $QpropertyGtypes = TableRegistry::get('QpropertyGtypes');
        $qproperty_gtypes = $QpropertyGtypes->findByQuarkPropertyId($quark_property_id);
        $ret = [];
        foreach ($qproperty_gtypes as $qproperty_gtype) {
            $tmp = $this->_addGluonsByType($qproperty_gtype['gluon_type_id'], $qproperty_gtype['sides'], $graph);
            $ret = array_merge($ret, $tmp);
        }
        return $ret;
    }
    public function _addGluonsByType($gluon_type_id, $sides, $graph)
    {
        $subject_id = $graph['subject']['identity'];
        $candidates = $graph['relations'];
        $ret = [];
        foreach($candidates as $candidate) {
            if (!array_key_exists('gluon_type_id', $candidate['relation']['values'])) {
                continue;
            }
            $candidate_gluon_type_id = $candidate['relation']['values']['gluon_type_id'];
            if ($candidate_gluon_type_id == $gluon_type_id) {
                if ($sides == 0) {
                    $ret[] = $candidate;
                } elseif (($sides == 1) && ($subject_id == $candidate['active']['identity'])) {
                    $ret[] = $candidate;
                } elseif (($sides == 2) && ($subject_id == $candidate['passive']['identity'])) {
                    $ret[] = $candidate;
                }
            }
        }
        return $ret;
    }
}
