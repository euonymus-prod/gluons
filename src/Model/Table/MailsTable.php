<?php
namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Mails Model
 *
 * @property \Cake\ORM\Association\HasMany $Mails
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class MailsTable extends Table
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
            ->requirePresence('name', 'create')
            ->notEmpty('name');

        $validator
            ->requirePresence('organization', 'create')
            ->notEmpty('organization');

        $validator
            ->requirePresence('department', 'create')
            ->notEmpty('department');

        $validator
            ->requirePresence('email', 'create')
            ->notEmpty('email');

        $validator
            ->requirePresence('topic', 'create')
            ->notEmpty('topic');

        $validator
            ->requirePresence('body', 'create')
            ->notEmpty('body');

        return $validator;
    }
}
