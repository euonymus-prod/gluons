<?php
namespace App\Controller;
use Cake\Event\Event;

use App\Controller\AppController;

use Cake\ORM\TableRegistry;
use Cake\Network\Exception\NotFoundException;

use Cake\Cache\Cache;
use Cake\Routing\Router;
use App\Utils\U;

/**
 * Gluons Controller
 *
 * @property \App\Model\Table\SubjectsTable $Relations
 */
class GluonsController extends AppController
{
    public function isAuthorized($user)
    {
        if (in_array($this->request->action, ['add', 'edit', 'confirm'])) {
            return true;
        }

        // The owner of a subject can delete it
        if (in_array($this->request->action, ['delete'])) {
            $subjectId = $this->request->params['pass'][0];
            if ($this->Subjects->isOwnedBy($subjectId, $user['id'])) {
                return true;
            }
        }
        return parent::isAuthorized($user);
    }

    public function beforeFilter(Event $event)
    {
        $this->Auth->allow(['view', 'byQuarkProperty']);

	$this->paginate = [
	   'contain' => ['Actives', 'Passives'],
           'limit' => 100
        ];
    }

    public function initialize()
    {
      parent::initialize();
      $this->loadComponent('RequestHandler');
      $this->RequestHandler->renderAs($this, 'json');
      $this->response->type('application/json');
      $this->response->header("Access-Control-Allow-Origin: *");
    }

    // $quark_property_id is quark_property_id, but 'active', 'passive' are exceptionally accepted
    public function byQuarkProperty($quark_id = null, $quark_property_id = 'active')
    {
      $Relations = TableRegistry::get('Relations');

      if (in_array($quark_property_id, ['active', 'passive'])) {
	$where = $Relations->whereByNoQuarkProperty($quark_id, $quark_property_id);
      } else {
	$where = $Relations->whereByQuarkProperty($quark_id, $quark_property_id);
      }
      $query = $Relations->find()->where($where)->order(['Relations.start' => 'Desc']);
      $this->set('articles', $this->paginate($query));
      $this->set('_serialize', 'articles');
    }

    public function view($quark_id = null, $gluon_sides = 'active')
    {
      $Relations = TableRegistry::get('Relations');
      $where = $Relations->whereByGluonSides($quark_id, $gluon_sides);
      $query = $Relations->find()->where($where)->order(['Relations.start' => 'Desc']);
      $this->set('articles', $this->paginate($query));
      $this->set('_serialize', 'articles');
    }
}
