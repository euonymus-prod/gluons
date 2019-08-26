<?php
namespace App\Controller;
use Cake\Event\Event;
use App\Form\ContactForm;
use Cake\ORM\TableRegistry;
use Cake\Log\Log;

class ContactsController extends AppController
{
    public function beforeFilter(Event $event)
    {
        parent::beforeFilter($event);
        $this->Auth->allow(['index']);
    }

    public function index()
    {
        $res = ['status' => 0];
        $Mails = TableRegistry::get('Mails');
        $user = $Mails->newEntity();
        $res = [];
        if ($this->request->is('post')) {
            $mail = $Mails->patchEntity($user, $this->request->data);
            if ($saved = $Mails->save($user)) {
                $res['status'] = 1;
                $res['message'] = 'The email has been sent.';
            } else {
                $res['message'] = 'The email could not be sent. Please, try again.';
            }
        }
        $title = $this->LangMngr->txt('Contact us', 'お問い合わせ');

        $this->set('mailsent', $res);
        $this->set('_serialize', 'mailsent');
    }
}
