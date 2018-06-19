<?php
namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

use Cake\Auth\DefaultPasswordHasher;
use Cake\Utility\Security;
use Cake\Event\Event;



/**
 * Users Model
 *
 * @method \App\Model\Entity\User get($primaryKey, $options = [])
 * @method \App\Model\Entity\User newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\User[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\User|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\User patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\User[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\User findOrCreate($search, callable $callback = null)
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class UsersTable extends Table
{

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config)
    {
        parent::initialize($config);

        $this->table('users');
        $this->displayField('id');
        $this->primaryKey('id');

        $this->addBehavior('Timestamp');
    }

    public function beforeSave(Event $event)
    {
      $entity = $event->getData('entity');

      if ($entity->isNew()) {
	$entity = self::addApiKey($entity);
      }
      return true;
    }

    public static function addApiKey($data) {
	$apiKeys = self::generateApiKey();
	$data->api_key_plain = $apiKeys['api_key_plain'];
	$data->api_key = $apiKeys['api_key'];
	return $data;
    }
    public static function generateApiKey() {
      // API の 'トークン' を生成
      $api_key_plain = Security::hash(Security::randomBytes(32), 'sha256', false);

      // ログインの際に BasicAuthenticate がチェックする
      // トークンを Bcrypt で暗号化
      $hasher = new DefaultPasswordHasher();
      $api_key = $hasher->hash($api_key_plain);
      return ['api_key' => $api_key, 'api_key_plain' => $api_key_plain];
    }


    public function isOwnedBy($id, $userId)
    {
        return ($id == $userId);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator)
    {
        $validator
            ->integer('id')
            ->allowEmpty('id', 'create');

        $validator
            ->requirePresence('username', 'create')
            ->notEmpty('username', 'A username is required')
            ->add('username', 'unique', ['rule' => 'validateUnique', 'provider' => 'table']);

        $validator
            ->requirePresence('password', 'create')
            ->notEmpty('password', 'A password is required');

        $validator
	  //->requirePresence('role', 'create')
            ->notEmpty('role', 'A role is required')
            ->add('role', 'inList', [
                'rule' => ['inList', ['admin', 'author']],
                'message' => 'Please enter a valid role'
            ]);
        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules)
    {
        $rules->add($rules->isUnique(['username']));

        return $rules;
    }
}
