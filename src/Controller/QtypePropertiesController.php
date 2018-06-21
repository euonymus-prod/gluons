<?php
namespace App\Controller;

use App\Controller\AppController;

use Cake\ORM\TableRegistry;
use Cake\Network\Exception\NotFoundException;

use Cake\Cache\Cache;
use Cake\Routing\Router;
use App\Utils\U;

class QtypePropertiesController extends AppController
{
    public function isAuthorized($user)
    {
        if (in_array($this->request->action, ['add', 'edit', 'confirm'])) {
            return true;
        }
        return parent::isAuthorized($user);
    }

    public function index()
    {
      $QtypeProperties = TableRegistry::get('QtypeProperties');
      $query = $QtypeProperties->find()->contain(['QuarkProperties']);
      $this->set('articles', $query->all());
      $this->set('_serialize', 'articles');
    }

}
