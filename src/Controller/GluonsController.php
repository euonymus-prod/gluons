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

    public function view($quark_id = null, $quark_type_id = null)
    {
      // http://ja.localhost:8765/gluons/fa38c825-363d-4157-b972-fc8815f1f23c
      // http://ja.localhost:8765/gluons/fa38c825-363d-4157-b972-fc8815f1f23c/2
      $Relations = TableRegistry::get('Relations');

      $gluonTypesByQuarkProperties = $Relations->constGluonTypesByQuarkProperties($quark_id, $quark_type_id);
      $where = $Relations->whereByQpropertyGtypes($quark_id, $gluonTypesByQuarkProperties);
      $query = $Relations->find()->where($where)->order(['Relations.start' => 'Desc'])
	->contain(['Actives', 'Passives'])->limit(100);

      $gluons_by_property = [];
      foreach($query as $key => $val) {

	foreach($gluonTypesByQuarkProperties as $key => $gluonTypes) {
	  $flg = false;
	  foreach($gluonTypes as $key => $gluonType) {
	    if ($gluonType->gluon_type_id != $val->gluon_type_id) {
	      continue;
	    }
	    if ((($gluonType->sides == 1) && ($val->active_id == $quark_id)) ||
		(($gluonType->sides == 2) && ($val->passive_id == $quark_id)) ||
		($gluonType->sides == 0) ) {
	      if (!array_key_exists($gluonType->quark_property_id, $gluons_by_property)) {
		$gluons_by_property[$gluonType->quark_property_id] = [];
	      }
	      $gluons_by_property[$gluonType->quark_property_id][] = $val;
	      $flg = true;
	      break;
	    }
	  }
	  if ($flg) {
	    break;
	  }
	}
      }

      $where = $Relations->whereByNoQuarkProperty($quark_id, 'active');
      $queryActives = $Relations->find()->where($where)->order(['Relations.start' => 'Desc'])
		->contain(['Actives', 'Passives'])->limit(100);
      $gluons_by_property['active'] = $queryActives->all()->toArray();

      $where = $Relations->whereByNoQuarkProperty($quark_id, 'passive');
      $queryPassives = $Relations->find()->where($where)->order(['Relations.start' => 'Desc'])
		->contain(['Actives', 'Passives'])->limit(100);
      $gluons_by_property['passive'] = $queryPassives->all()->toArray();

      $this->set('articles', $gluons_by_property);
      $this->set('_serialize', 'articles');
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

    public function view_bk($quark_id = null, $gluon_sides = 'active')
    {
      $Relations = TableRegistry::get('Relations');
      $where = $Relations->whereByGluonSides($quark_id, $gluon_sides);
      $query = $Relations->find()->where($where)->order(['Relations.start' => 'Desc']);
      $this->set('articles', $this->paginate($query));
      $this->set('_serialize', 'articles');
    }
}
