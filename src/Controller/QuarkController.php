<?php
namespace App\Controller;

use App\Controller\AppController;

use Cake\ORM\TableRegistry;
use Cake\Network\Exception\NotFoundException;

use Cake\Cache\Cache;
use Cake\Routing\Router;
use App\Utils\U;

/**
 * Quark Controller
 *
 * @property \App\Model\Table\SubjectsTable $Subjects
 */
class QuarkController extends AppController
{
    public function isAuthorized($user)
    {
        if (in_array($this->request->action, ['view', 'listview', 'add', 'edit'])) {
            return true;
        }

        // The owner of a subject can delete it
        if (in_array($this->request->action, ['delete'])) {
            $subjectId = $this->request->params['pass'][0];
	    $Subjects = TableRegistry::get('Subjects');
            if ($Subjects->isOwnedBy($subjectId, $user['id'])) {
                return true;
            }
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

    // API endpoint:  /quark/:name
    public function view($name = null)
    {
      $Subjects = TableRegistry::get('Subjects');
      
      $query = $Subjects->find()->where($Subjects->wherePrivacyName($name));
      if ($query->count() == 0) {
	$res = ['status' => 0, 'message' => 'Not found'];
      } else {
	$res = $query->first();
      }
      $this->set('articles', $res);
      $this->set('_serialize', 'articles');
    }

    // API endpoint:  /quark_by_id/:id
    public function quarkById($id = null)
    {
      $Subjects = TableRegistry::get('Subjects');
      
      $query = $Subjects->findById($id);
      $this->set('articles', $query->first());
      $this->set('_serialize', 'articles');
    }

    // API endpoint:  /quarks
    public function listview($name = null)
    {
        $Subjects = TableRegistry::get('Subjects');
      
        $options = [
            'conditions' => [$Subjects->wherePrivacy()]
        ];
	$order = false;
        if (!isset($this->request->query['type']) || $this->request->query['type'] != 0) {
	  $options['order'] = ['Subjects.created' => 'desc'];
	  $order = true;
        }
        $this->paginate = $options;

        $query = $this->paginate($Subjects);

	$this->set('subjects', $query);
	$this->set('_serialize', 'subjects');
    }

    public function add()
    {
	$res = ['status' => 0, 'message' => 'Not accepted'];

        // Existence check
        if ($this->request->is('post') && array_key_exists('name', $this->request->data)) {
	  $Subjects = TableRegistry::get('Subjects');
	  $query = $Subjects->findByName($this->request->data['name']);
	  if (iterator_count($query)) {
	    $res['message'] = 'The user already exists';
	  } else {
	    // Saving
	    $subject = $Subjects->newEntity();
	    $subject = $Subjects->formToSaving($this->request->data);

	    $subject->user_id = $this->Auth->user('id');
	    $subject->last_modified_user = $this->Auth->user('id');

            if ($savedSubject = $Subjects->save($subject)) {
	      $res['status'] = 1;
	      $res['message'] = 'The quark has been saved.';
	      $res['result'] = $savedSubject;
	    } else {
	      $res['message'] = 'The quark could not be saved. Please, try again.';
	      $res['result'] = $savedSubject;
	    }
	  }
	}
	$this->set('newQuark', $res);
	$this->set('_serialize', 'newQuark');
    }

    public function edit($id = null)
    {
	$res = ['status' => 0, 'message' => 'Not accepted'];
        $Subjects = TableRegistry::get('Subjects');
        $subject = $Subjects->findById($id);
        if ($this->request->is(['patch', 'post', 'put']) && ($subject->count() == 1)) {
            $subject = $Subjects->formToEditing($subject->first(), $this->request->data);

            $subject->last_modified_user = $this->Auth->user('id');
            if ($savedSubject = $Subjects->save($subject)) {
	      $res['status'] = 1;
	      $res['message'] = 'The quark has been saved.';
	      $res['result'] = $savedSubject;
	    } else {
	      $res['message'] = 'The quark could not be saved. Please, try again.';
	      $res['result'] = $savedSubject;
            }
        } else {
	    $res['message'] = 'Invalid';
	}
	$this->set('newQuark', $res);
	$this->set('_serialize', 'newQuark');
    }

    public function delete($id = null)
    {
	$res = ['status' => 0, 'message' => 'Not accepted'];

        $this->request->allowMethod(['delete']);
	$Subjects = TableRegistry::get('Subjects');
        $subject = $Subjects->get($id);

        if ($Subjects->delete($subject)) {
	    $res['status'] = 1;
	    $res['message'] = 'The quark has been deleted.';
        } else {
	    $res['message'] = 'The quark could not be deleted. Please, try again.';
        }
	$this->set('deleted', $res);
	$this->set('_serialize', 'deleted');
    }
}
