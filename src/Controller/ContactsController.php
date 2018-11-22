<?php
namespace App\Controller;
use Cake\Event\Event;
use App\Form\ContactForm;

class ContactsController extends AppController
{
    public function beforeFilter(Event $event)
    {
        parent::beforeFilter($event);
        $this->Auth->allow(['index']);
    }

    public function index()
    {
        $res = ['status' => 0, 'message' => 'Not accepted'];

        $contact = new ContactForm();

        if ($this->request->is('post')) {
            if ($contact->execute($this->request->data)) {
                $res['status'] = 1;
                $res['message'] = 'The email has been sent.';
            } else {
                $res['message'] = 'Validation error.';
            }
        }
        $title = $this->LangMngr->txt('Contact us', 'お問い合わせ');

        $this->set('mailsent', $res);
        $this->set('_serialize', 'mailsent');
    }
}
