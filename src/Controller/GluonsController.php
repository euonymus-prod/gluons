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
        if (in_array($this->request->action, ['add', 'one', 'edit', 'privateListview'])) {
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
      $this->_list($quark_id, $quark_type_id);
    }
    public function privateListview($quark_id = null, $quark_type_id = null, $privacy = 1)
    {
      if (($this->Auth->user('role') !== 'admin') && ($privacy == 4)) {
	throw new NotFoundException(__('記事が見つかりません'));
      }
      $this->_list($quark_id, $quark_type_id, $privacy);
    }
    public function _list($quark_id = null, $quark_type_id = null, $privacy = 1)
    {
      // http://ja.localhost:8765/gluons/fa38c825-363d-4157-b972-fc8815f1f23c
      // http://ja.localhost:8765/gluons/fa38c825-363d-4157-b972-fc8815f1f23c/2
      $limit = $this->request->getQuery('limit');
      if (!$limit) $limit = 100;
      $Relations = TableRegistry::get('Relations');

      $Relations->belongsToActives($privacy);
      $Relations->belongsToPassives($privacy);

      $gluonTypesByQuarkProperties = $Relations->constGluonTypesByQuarkProperties($quark_id, $quark_type_id);
      $where = $Relations->whereByQpropertyGtypes($quark_id, $gluonTypesByQuarkProperties);
      $query = $Relations->find()->where($where)->order(['Relations.start' => 'Desc'])
	->contain(['Actives', 'Passives'])->limit($limit);

      // build cache slag
      if ($privacy === self::PRIVACY_PUBLIC) {
	$privacy_slag = '';
      } else {
	$privacy_slag = $this->Auth->user('id') . $privacy;
      }
      if (array_key_exists('page', $this->request->query)) {
	$page_slag = $this->request->query['page'];
      } else {
	$page_slag = '';
      }

      // cache the query
      $query = $query->cache('gluons_' . $this->lang . $privacy_slag . $quark_id . $limit . $page_slag);

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
      // cache the query
      $queryActives = $queryActives->cache('gluonsactive_' . $this->lang . $privacy_slag . $quark_id . $limit . $page_slag);
      $gluons_by_property['active'] = $queryActives->all()->toArray();

      $where = $Relations->whereByNoQuarkProperty($quark_id, 'passive');
      $queryPassives = $Relations->find()->where($where)->order(['Relations.start' => 'Desc'])
		->contain(['Actives', 'Passives'])->limit($limit);
      $queryPassives = $queryPassives->cache('gluonspassive_' . $this->lang . $privacy_slag . $quark_id . $limit . $page_slag);
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

    public function add($active_id = null)
    {
	$res = ['status' => 0, 'message' => 'Not accepted'];

	$Subjects = TableRegistry::get('Subjects');
	$activeQuark = $Subjects->findById($active_id)->first();
	if (!$activeQuark) {
	} elseif ($this->request->is('post') || array_key_exists('passive', $this->request->data)) {
	  $Subjects = TableRegistry::get('Subjects');
	  $quarkToGlue = $Subjects->findByName($this->request->data['passive'])->first();
	  $passive_id = false;
	  if ($quarkToGlue) {
	    $passive_id = $quarkToGlue->id;
	  } else {
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
	      $passive_id = $savedSubject->id;
	      Cache::clear(false);
            } else {
	      $res['message'] = 'The quark to glue could not be saved. Please, try again.';
	      $res['result'] = $savedSubject;
            }
	  }

	  if ($passive_id) {
	    $Relations = TableRegistry::get('Relations');
	    $relation = $Relations->newEntity();
            $this->request->data['active_id'] = $active_id;
            $this->request->data['passive_id'] = $passive_id;
            $relation = $Relations->formToSaving($this->request->data);

            $relation->user_id = $this->Auth->user('id');
            $relation->last_modified_user = $this->Auth->user('id');

            if ($savedRelation = $Relations->save($relation)) {
	      $res['status'] = 1;
	      $res['message'] = 'The gluon has been saved.';
	      $res['result'] = $savedRelation;
	      Cache::clear(false);
            } else {
	      $res['message'] = 'The gluon could not be saved. Please, try again.';
	      $res['result'] = $savedRelation;
            }
	  }
	}

	$this->set('newQuark', $res);
	$this->set('_serialize', 'newQuark');
    }

    public function one($id = null)
    {
	$res = ['status' => 0, 'message' => 'Not accepted'];
        $Relations = TableRegistry::get('Relations');
        $query = $Relations->findById($id)->contain(['Actives', 'Passives']);
	if ($query->count() == 0) {
	  $res = ['status' => 0, 'message' => 'Not found'];
	} else {
	  $res = $query->first();
	}

	$this->set('editedGluon', $res);
	$this->set('_serialize', 'editedGluon');
    }

    public function edit($id = null)
    {
	$res = ['status' => 0, 'message' => 'Not accepted'];
        $Relations = TableRegistry::get('Relations');
        $relation = $Relations->findById($id);
        if ($this->request->is(['patch']) && ($relation->count() == 1)) {
            $relation = $Relations->patchEntity($relation->first(), $this->request->data);
            if ($savedRelation = $Relations->save($relation)) {

	      $Subjects = TableRegistry::get('Subjects');
	      $subject = $Subjects->findById($savedRelation->active_id);
	      $savedRelation->active = $subject->first();

	      $res['status'] = 1;
	      $res['message'] = 'The gluon has been saved.';
	      $res['result'] = $savedRelation;
	      Cache::clear(false);
            } else {
	      $res['message'] = 'The gluon could not be saved. Please, try again.';
	      $res['result'] = $savedRelation;
            }
        }
	$this->set('editedGluon', $res);
	$this->set('_serialize', 'editedGluon');
    }

    public function delete($id = null)
    {
	$res = ['status' => 0, 'message' => 'Not accepted'];

        $this->request->allowMethod(['delete']);
	$Relations = TableRegistry::get('Relations');
        //$relation = $Relations->get($id);
        $relation = $Relations->findById($id)->first();

        if ($Relations->delete($relation)) {
	    $res['status'] = 1;
	    $res['message'] = 'The gluon has been deleted.';
	    Cache::clear(false);
        } else {
	    $res['message'] = 'The gluon could not be deleted. Please, try again.';
        }
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
