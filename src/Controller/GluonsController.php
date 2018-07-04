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
        //if (in_array($this->request->action, ['add', 'confirm'])) {
        if (in_array($this->request->action, ['add', 'confirm', 'edit'])) {
            return true;
        }

        if (in_array($this->request->action, ['delete'])) {
            $relationId = $this->request->params['pass'][0];
	    $Relations = TableRegistry::get('Relations');
            if ($Relations->isOwnedBy($relationId, $user['id'])) {
                return true;
            }
        }
        return parent::isAuthorized($user);
    }

    public function beforeFilter(Event $event)
    {
        return parent::beforeFilter($event);

	$this->paginate = [
	   'contain' => ['Actives', 'Passives'],
           'limit' => 100
        ];
    }

    public function listview($quark_id = null, $quark_type_id = null)
    {
      // http://ja.localhost:8765/gluons/fa38c825-363d-4157-b972-fc8815f1f23c
      // http://ja.localhost:8765/gluons/fa38c825-363d-4157-b972-fc8815f1f23c/2
      $limit = $this->request->getQuery('limit');
      if (!$limit) $limit = 100;
      $Relations = TableRegistry::get('Relations');

      $gluonTypesByQuarkProperties = $Relations->constGluonTypesByQuarkProperties($quark_id, $quark_type_id);
      $where = $Relations->whereByQpropertyGtypes($quark_id, $gluonTypesByQuarkProperties);
      $query = $Relations->find()->where($where)->order(['Relations.start' => 'Desc'])
	->contain(['Actives', 'Passives'])->limit($limit);

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
		->contain(['Actives', 'Passives'])->limit($limit);
      $gluons_by_property['active'] = $queryActives->all()->toArray();

      $where = $Relations->whereByNoQuarkProperty($quark_id, 'passive');
      $queryPassives = $Relations->find()->where($where)->order(['Relations.start' => 'Desc'])
		->contain(['Actives', 'Passives'])->limit($limit);
      $gluons_by_property['passive'] = $queryPassives->all()->toArray();

      $count = 0;
      $final_result = [];
      foreach($gluons_by_property as $key => $val) {
	$inner = [];
	foreach($val as $k => $v) {
	  $inner[$k] = $v;
	  ++$count;
	  if ($count >= $limit) {
	    break;
	  }
	}
	$final_result[$key] = $inner;
	if ($count >= $limit) {
	  break;
	}
      }

      $this->set('articles', $final_result);
      $this->set('_serialize', 'articles');
    }

    public function add($active_id = null, $baryon_id = null)
    {
/*
        $ready_for_save = false;

        $Subjects = TableRegistry::get('Subjects');

        // Session check
        $this->Session->delete('ExistingSubjectsForRelation');
        $session_relation = unserialize($this->Session->read('SavingRelation'));
	if ($session_relation) {
	  $this->Session->delete('SavingRelation');
	  $active_id = $session_relation['active_id'];
	  $this->request->data = $session_relation;
	}
        $session_passive_id = unserialize($this->Session->read('SavingPassiveId'));
	if ($session_passive_id !== false) {
	  $this->Session->delete('SavingPassiveId');

	  if ($session_passive_id != '0') $ready_for_save = true;
	}

        // Existence check
        if ($this->request->is('post')) {
	  $this->request->data['active_id'] = $active_id;
	  if (!is_null($baryon_id)) {
	    $this->request->data['baryon_id'] = $baryon_id;
	  }

	  $query = $Subjects->search($this->request->data['passive']);
	  if (iterator_count($query)) {
	    $this->Session->write('ExistingSubjectsForRelation', serialize($query->toArray()));
	    $this->Session->write('SavingRelation', serialize($this->request->data));
	    return $this->redirect(['action' => 'confirm']);
	  }
	}

	// Save New Subject for the passive_id
        if ($this->request->is('post') || 
	    (($session_passive_id !== false) && ($session_passive_id == '0'))
	    ) {

	    $saving_subject = [
			       'image_path' => '',
			       'description' => '',
			       'start' => [
					   'year' => '',
					   'month' => '',
					   'day' => '',
					   'hour' => '',
					   'minute' => ''
					   ],
			       'end' => [
					 'year' => '',
					 'month' => '',
					 'day' => '',
					 'hour' => '',
					 'minute' => ''
					 ],
			       'start_accuracy' => '',
			       'end_accuracy' => '',
			       'is_momentary' => '0'
			       ];
	    $saving_subject['name'] = $this->request->data['passive'];

            $subject = $Subjects->formToSaving($saving_subject);

            $subject->is_private = $this->Auth->user('default_saving_privacy');
            $subject->user_id = $this->Auth->user('id');
            $subject->last_modified_user = $this->Auth->user('id');
	    if ($this->Auth->user('role') == 'admin') {
	      $subject->is_exclusive = true;
	    }

            if ($savedSubject = $Subjects->save($subject)) {
                $this->_setFlash(__('The quark has been saved.')); 
		$session_passive_id = $savedSubject->id;
            } else {
                $this->_setFlash(__('The quark could not be saved. Please, try again.'), true); 
            }
	    $ready_for_save = true;
	}


        $relation = $this->Relations->newEntity();
        if ($ready_for_save) {

            $this->request->data['passive_id'] = $session_passive_id;
            //$relation = $this->Relations->patchEntity($relation, $this->request->data);
            $relation = $this->Relations->formToSaving($this->request->data);

            $relation->user_id = $this->Auth->user('id');
            $relation->last_modified_user = $this->Auth->user('id');

            if ($this->Relations->save($relation)) {
                $this->_setFlash(__('The gluon has been saved.')); 

		Cache::clear(false); 
		if (is_null($relation->baryon_id)) {
		  return $this->redirect(['controller' => 'subjects', 'action' => 'relations', $active_id]);
		} else {
		  return $this->redirect(['controller' => 'baryons', 'action' => 'relations', $relation->baryon_id, $active_id]);
		}
            } else {
                $this->_setFlash(__('The gluon could not be saved. Please, try again.'), true); 
            }
        }
	$active = $Subjects->get($active_id, ['contain' => 'Actives']);
        $passives = $this->Relations->Passives->find('list', ['limit' => 200]);


	$title = 'Add new gluon';
	$this->set('gluon_types', $this->Relations->GluonTypes->find('list'));
        $this->set(compact('relation', 'active', 'passives', 'title'));
        $this->set('_serialize', ['relation']);
*/
    }

    public function delete($id = null)
    {
	$res = ['status' => 0, 'message' => 'Not accepted'];

        $this->request->allowMethod(['delete']);
	$Relations = TableRegistry::get('Relations');
        //$relation = $Relations->get($id);
        $relation = $Relations->findById($id)->first();

        /* if ($Relations->delete($relation)) { */
	/*     $res['status'] = 1; */
	/*     $res['message'] = 'The gluon has been deleted.'; */
        /* } else { */
	/*     $res['message'] = 'The gluon could not be deleted. Please, try again.'; */
        /* } */
	$this->set('deleted', $res);
	$this->set('_serialize', 'deleted');
    }

/*
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
*/
}
