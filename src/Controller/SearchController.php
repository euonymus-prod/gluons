<?php
namespace App\Controller;

use App\Controller\AppController;

use Cake\ORM\TableRegistry;
use Cake\Network\Exception\NotFoundException;

use Cake\Cache\Cache;
use Cake\Routing\Router;
use App\Utils\U;

/**
 * Search Controller
 */
class SearchController extends AppController
{

    public function isAuthorized($user)
    {
        if (in_array($this->request->action, ['add', 'edit', 'confirm'])) {
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

    public function index()
    {
      if (!array_key_exists('keywords', $this->request->query)) {
	$subjects = [];
      } else {
	\App\Model\Table\SubjectsTable::$cachedRead = true;
	$Subjects = TableRegistry::get('Subjects');
	$subjects = $Subjects->searchForApi($this->request->query['keywords']);
      }

      $this->set('subjects', $subjects);
      $this->set('_serialize', 'subjects');
    }
}
