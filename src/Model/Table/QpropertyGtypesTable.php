<?php
namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * QpropertyGtypes Model
 *
 * @property \Cake\ORM\Association\BelongsTo $QuarkProperties
 * @property \Cake\ORM\Association\BelongsTo $GluonTypes
 *
 * @method \App\Model\Entity\QpropertyGtype get($primaryKey, $options = [])
 * @method \App\Model\Entity\QpropertyGtype newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\QpropertyGtype[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\QpropertyGtype|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\QpropertyGtype patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\QpropertyGtype[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\QpropertyGtype findOrCreate($search, callable $callback = null)
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class QpropertyGtypesTable extends Table
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

        $this->table('qproperty_gtypes');
        $this->displayField('id');
        $this->primaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('QuarkProperties', [
            'foreignKey' => 'quark_property_id',
            'joinType' => 'INNER'
        ]);
        $this->belongsTo('GluonTypes', [
            'foreignKey' => 'gluon_type_id',
            'joinType' => 'INNER'
        ]);
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
            ->boolean('is_passive')
            ->requirePresence('is_passive', 'create')
            ->notEmpty('is_passive');

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
        $rules->add($rules->existsIn(['quark_property_id'], 'QuarkProperties'));
        $rules->add($rules->existsIn(['gluon_type_id'], 'GluonTypes'));

        return $rules;
    }
}
