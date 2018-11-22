<?php
namespace App\Controller;

use App\Controller\AppController;

use Cake\ORM\TableRegistry;
use Cake\Network\Exception\NotFoundException;

use Cake\Cache\Cache;
use Cake\Routing\Router;
use App\Utils\U;

class QuarkTypesController extends AppController
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
        $query = $this->QuarkTypes->find('list');
        $this->set('quary_types', $query->toArray());
        $this->set('_serialize', 'quary_types');
    }

}
