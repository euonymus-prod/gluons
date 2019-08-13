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
        // $Subjects = TableRegistry::get('Subjects');
      
        // $query = $Subjects->find()->where($Subjects->wherePrivacyName($name));
        // if ($query->count() == 0) {
        //     $res = ['status' => 0, 'message' => 'Not found'];
        // } else {
        //     $res = $query->first();
        // }


        $client = ClientBuilder::create()
                ->addConnection('http', 'http://neo4j:neo4jn30Aj@localhost:7474')
                ->build();

        $query = 'MATCH (a {name:"ドナルド・トランプ"})-[*1..2]-(p) RETURN DISTINCT a,p';
        $result = $client->run($query)->getRecords();
        Log::write('debug', $result);




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
