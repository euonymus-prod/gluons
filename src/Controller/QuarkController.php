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

    public function initialize()
    {
      parent::initialize();
      $this->loadComponent('RequestHandler');
      $this->RequestHandler->renderAs($this, 'json');
      $this->response->type('application/json');
    }

    public function view($name = null)
    {
      $Subjects = TableRegistry::get('Subjects');
      
      $query = $Subjects->find()->where(['name' => $name]);
      $this->set('articles', $this->paginate($query));
   
      /* $this->paginate = [ */
      /* 			 'table' => '', */
      /* 			 'conditions' => ['user_id' => $this->Auth->user('id')], */
      /* 			 ]; */
      /* $this->set('articles', $this->paginate()); */
      // JsonView がシリアライズするべきビュー変数を指定する
      $this->set('_serialize', 'articles');
	
      /*
	try {
	  \App\Model\Table\SubjectsTable::$cachedRead = true;
	  $subject = $this->Subjects->getRelationsByName($name, $contain, 2, $second_type);
	} catch(\Exception $e) {
	  try {
	    $forRedirect = $this->Subjects->get($name);
	  } catch(\Exception $e) {
	    throw new NotFoundException('Record not found in table "subjects"');
	  }
	  $suffix = ($second_type == 'active') ? '' : '/' . $second_type;
	  return $this->redirect('/subjects/relations/' . urlencode($forRedirect->name) . $suffix, 301);
	}

	// just in case;
	if (!$subject) return $this->redirect('/');

	$title_second_level = '';
	if ($second_type == 'passive') {
	  //$title_second_level = '[' . $second_type . ' relation]';
	  $title_second_level = '[' . $second_type . ']';
	} elseif ($second_type == 'none') {
	  //$title_second_level = '[no second relation]';
	  $title_second_level = '[simple]';
	}
	$title = $subject->name . $title_second_level;

	// build canonical
	$second_type_path = ($second_type == 'active') ? '' : '/' . $second_type;
	$domain = Router::url('/', true);
	$canonical = $domain . 'subjects/relations/' . $name . $second_type_path;

        $QpropertyGtypes = TableRegistry::get('QpropertyGtypes');
	$this->set('qproperty_gtypes', $QpropertyGtypes->find());
        $this->set(compact('subject', 'second_type', 'title', 'canonical'));
        $this->set('_serialize', ['subject']);
      */
    }

}
