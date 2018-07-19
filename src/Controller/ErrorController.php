<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         3.3.4
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace App\Controller;

use Cake\Event\Event;

/**
 * Error Handling Controller
 *
 * Controller used by ExceptionRenderer to render error responses.
 */
class ErrorController extends AppController
{
    /**
     * Initialization hook method.
     *
     * @return void
     */
    public function initialize()
    {
        $this->loadComponent('RequestHandler');

	/* Added for API ******************************************/
	$this->RequestHandler->renderAs($this, 'json');
	$this->response->type('application/json');
	$this->response->header("Access-Control-Allow-Origin: *");
	/**********************************************************/

        $this->loadComponent('Auth', [
            'authorize' => ['Controller'],
            'loginRedirect' => [
                'controller' => null,
                'action' => 'index'
            ],
            'logoutRedirect' => [
                'controller' => null,
                'action' => 'index'
            ]
        ]);
    }

    /**
     * beforeFilter callback.
     *
     * @param \Cake\Event\Event $event Event.
     * @return \Cake\Network\Response|null|void
     */
    public function beforeFilter(Event $event)
    {
        $title = '404: Not Found';
	$auth = $this->Auth;

        $this->set(compact(['auth']));
    }

    /**
     * beforeRender callback.
     *
     * @param \Cake\Event\Event $event Event.
     * @return \Cake\Network\Response|null|void
     */
    public function beforeRender(Event $event)
    {
        parent::beforeRender($event);

        // CORS 対策
        // https://book.cakephp.org/3.0/ja/controllers/request-response.html
        $this->setCorsHeaders();

        $this->viewBuilder()->templatePath('Error');
    }

    /**
     * afterFilter callback.
     *
     * @param \Cake\Event\Event $event Event.
     * @return \Cake\Network\Response|null|void
     */
    public function afterFilter(Event $event)
    {
    }

    private function setCorsHeaders() {
      $this->response->cors($this->request)
        ->allowOrigin(['*'])
        ->allowMethods(['GET,PUT,POST,DELETE,PATCH,OPTIONS'])
        ->allowHeaders(['x-xsrf-token', 'Origin', 'Content-Type', 'X-Auth-Token', 'Authorization'])
        ->allowCredentials(['true'])
        ->exposeHeaders(['Link'])
        ->maxAge(300)
        ->build();
    }
}
