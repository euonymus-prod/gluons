<?php
namespace App\Controller;

use App\Controller\AppController;

use Cake\ORM\TableRegistry;
use Cake\Network\Exception\NotFoundException;

use Cake\Cache\Cache;
use Cake\Routing\Router;
use App\Utils\U;

/**
 * QuarkProperties Controller
 *
 * @property \App\Model\Table\SubjectsTable $Subjects
 */
class QuarkPropertiesController extends AppController
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
        $this->response->header("Access-Control-Allow-Origin: *");
    }

    public function index($quark_type_id = null)
    {
        $QtypeProperties = TableRegistry::get('QtypeProperties');
        $query = $QtypeProperties->find()->where(['quark_type_id' => $quark_type_id])->contain(['QuarkProperties']);
        $this->set('articles', $query->all());
        $this->set('_serialize', 'articles');
    }

}
