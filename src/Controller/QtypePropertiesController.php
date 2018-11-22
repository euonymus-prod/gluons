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
        $query = $query->cache('qtype_properties_' . self::$lang);

        $map = [];
        foreach($query as $key => $val) {
            if (!array_key_exists($val['quark_type_id'], $map)) {
                $map[$val['quark_type_id']] = [];
            }
            $map[$val['quark_type_id']][] = $val;
        }
        $this->set('map', $map);
        $this->set('_serialize', 'map');
    }

}
