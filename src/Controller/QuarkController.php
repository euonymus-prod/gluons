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
        if (in_array($this->request->action, ['view', 'listview', 'add'])) {
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
      $this->response->header("Access-Control-Allow-Origin: *");
    }

    // API endpoint:  /quark/:id
    public function view($name = null)
    {
      $Subjects = TableRegistry::get('Subjects');
      
      $query = $Subjects->find()->where($Subjects->wherePrivacyName($name));
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
      pr('here');
        $newQuark = ['hoge' => 'hage'];
	$this->set('newQuark', $newQuark);
	$this->set('_serialize', 'newQuark');
    }
}
