<?php
namespace App\Controller;
use Cake\Event\Event;
use App\Form\ContactForm;

class ContactsController extends AppController
{
    public function beforeFilter(Event $event)
    {
        parent::beforeFilter($event);
        $this->Auth->allow(['index', 'send']);
    }

    public function index()
    {
        $contact = new ContactForm();

        if ($this->request->is('post')) {
            if ($contact->execute($this->request->data)) {
                $this->_setFlash(__('The email has been sent.')); 
            } else {
                $this->_setFlash(__('Validation error.'), true); 
            }
        }
	$title = $this->LangMngr->txt('Contact us', 'お問い合わせ');

        $this->set(compact('contact', 'title'));
        $this->set('_serialize', ['subject']);
   }

    public function send()
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