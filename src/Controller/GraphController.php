<?php
namespace App\Controller;

use App\Controller\AppController;

use Cake\ORM\TableRegistry;
use Cake\Network\Exception\NotFoundException;

use Cake\Cache\Cache;
use Cake\Routing\Router;
use App\Utils\U;

use Neoxygen\NeoClient\ClientBuilder;
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
        // $Subjects = TableRegistry::get('Subjects');
      
        // $query = $Subjects->find()->where($Subjects->wherePrivacyName($name));
        // if ($query->count() == 0) {
        //     $res = ['status' => 0, 'message' => 'Not found'];
        // } else {
        //     $res = $query->first();
        // }

        $connUrl = parse_url('http://localhost:7474/db/data/');
        $user = 'neo4j';
        $password = 'neo4jn30Aj';

        $client = ClientBuilder::create()
                ->addConnection('default', $connUrl['scheme'], $connUrl['host'], $connUrl['port'], true, $user, $password)
                ->setAutoFormatResponse(true)
                ->build();
        $query = 'MATCH (a {name:"眞弓聡"})-[*1..2]-(p) RETURN DISTINCT a,p';
        $result = $client->sendCypherQuery($query)->getResult();
        $user = $result->getSingleNode();
        $name = $user->getProperty('name');

        Log::write('debug', 'aaa');
        Log::write('debug', $user);
        Log::write('debug', $name);
        Log::write('debug', 'bbb');

        $res = ['hoge' => 'hage'];
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

}
