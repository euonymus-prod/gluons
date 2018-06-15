<?php
namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

use Cake\ORM\TableRegistry;
use Cake\Core\Configure;

use App\Utils\U;
use App\Utils\GlobalDataSet;
use App\Utils\Wikipedia;

/**
 * Relations Model
 *
 * @property \Cake\ORM\Association\BelongsTo $Actives
 * @property \Cake\ORM\Association\BelongsTo $Passives
 *
 * @method \App\Model\Entity\Relation get($primaryKey, $options = [])
 * @method \App\Model\Entity\Relation newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\Relation[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Relation|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Relation patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Relation[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\Relation findOrCreate($search, callable $callback = null)
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class RelationsTable extends AppTable
{
    public $privacyMode = \App\Controller\AppController::PRIVACY_PUBLIC;

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config)
    {
        parent::initialize($config);

        $this->table(self::$relations);
        $this->displayField('id');
        $this->primaryKey('id');

        $this->addBehavior('Timestamp');

	$this->belongsTo('GluonTypes', [
            'foreignKey' => 'gluon_type_id',
        ]);

        $this->privacyMode = Configure::read('Belongsto.privacyMode');
	$this->belongsToActives();
	$this->belongsToPassives();
    }

    public function belongsToActives()
    {
        $options = [
            'foreignKey' => 'active_id',
            'joinType' => 'INNER'
        ];

	$Actives = TableRegistry::get('Actives');
	$conditions = $Actives->wherePrivacy();
	$options['conditions'] = $conditions;

        $this->belongsTo('Actives', $options);
    }
    public function belongsToPassives()
    {
        $options = [
            'foreignKey' => 'passive_id',
            'joinType' => 'INNER'
        ];

	$Passives = TableRegistry::get('Passives');
	$conditions = $Passives->wherePrivacy();
	$options['conditions'] = $conditions;

        $this->belongsTo('Passives', $options);
    }

    public function formToEntity($arr)
    {
      $ret = $this->newEntity($arr);
      $ret->id = U::buildGuid(); // varchar 36 フィールドのinsertには必要。
      return $ret;
    }

    public function formToSaving($form)
    {
      if (!is_array($form)) return false;
      return $this->formToEntity($form);
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
            ->allowEmpty('id', 'create');

        $validator
            ->requirePresence('relation', 'create')
            ->notEmpty('relation');

        $validator
	  // なぜか validation errorが起きるので停止。
	  // ->dateTime('start')
            ->allowEmpty('start');

        $validator
	  // なぜか validation errorが起きるので停止。
	  // ->dateTime('end')
            ->allowEmpty('end');

        $validator
            ->allowEmpty('start_accuracy');

        $validator
            ->allowEmpty('end_accuracy');

        $validator
            ->boolean('is_momentary')
            ->requirePresence('is_momentary', 'create')
            ->notEmpty('is_momentary');

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
        $rules->add($rules->existsIn(['active_id'], 'Actives'));
        $rules->add($rules->existsIn(['passive_id'], 'Passives'));

        return $rules;
    }

    public function getByBaryon($baryon_id, $contain = NULL)
    {
      return $this->findByBaryonId($baryon_id)->contain($contain);
    }

    /*******************************************************/
    /* where                                               */
    /*******************************************************/
    public static function whereNoRecord()
    {
      return ['Relations.id' => false];
    }
    public static function whereAllRecord()
    {
      return [true];
    }

    public static function whereActivePassivePair($active_id, $passive_id)
    {
      return ['Relations.active_id' => $active_id, 
	      'Relations.passive_id' => $passive_id];
    }
    public static function whereNoGluonTypeId()
    {
      return ['Relations.gluon_type_id is NULL'];
    }

    // $gluon_sides is 'active' or 'passive'
    public static function whereByNoQuarkProperty($quark_id, $gluon_sides)
    {
      if (empty($quark_id)) {
	return self::whereNoRecord();
      }
      
      $Subjects = TableRegistry::get('Subjects');
      $subject = $Subjects->findById($quark_id)->contain(['QuarkProperties'])->first();
      if (empty($subject)) {
	return self::whereNoRecord();
      }

      $quark_property_ids = [];
      foreach($subject->quark_properties as $key => $val) {
	$quark_property_ids[] = $val->id;
      }
      if (empty($quark_property_ids)) {
	if ($gluon_sides == 'active') {
	  $ret = ['Relations.active_id' => $quark_id];
	} elseif ($gluon_sides == 'passive') {
	  $ret = ['Relations.passive_id' => $quark_id];
	} else {
	  return self::whereNoRecord();
	}
	return $ret;
      }
      
      $QpropertyGtypes = TableRegistry::get('QpropertyGtypes');
      $q_property_g_type_query = $QpropertyGtypes->find()->where(['QpropertyGtypes.quark_property_id in' => $quark_property_ids]);
      if ($q_property_g_type_query->count() == 0) {
      	return self::whereNoRecord();
      }

      $gluon_type_ids = [];
      foreach($q_property_g_type_query as $key => $val) {
	$gluon_type_ids[] = $val->gluon_type_id;
      }
      $gluon_type_ids = array_unique($gluon_type_ids);

      if ($gluon_sides == 'active') {
	$ret = [
		'Relations.active_id' => $quark_id,
		'or' => [
			 'Relations.gluon_type_id IS' => NULL,
			 'Relations.gluon_type_id NOT IN' => $gluon_type_ids,
			 ]
		];
      } elseif ($gluon_sides == 'passive') {
	$ret = [
		'Relations.passive_id' => $quark_id,
		'or' => [
			 'Relations.gluon_type_id IS' => NULL,
			 'Relations.gluon_type_id NOT IN' => $gluon_type_ids
			 ]
		];
      } else {
	return self::whereNoRecord();
      }
      return $ret;
    }

    public static function whereByQuarkProperty($quark_id, $quark_property_id)
    {
      if (empty($quark_id)) {
	return self::whereNoRecord();
      }
      $QpropertyGtypes = TableRegistry::get('QpropertyGtypes');
      $query = $QpropertyGtypes->find()->where(['QpropertyGtypes.quark_property_id' => $quark_property_id]);
      if ($query->count() == 0) {
      	return self::whereNoRecord();
      }


      $active_gluon_types = [];
      $passive_gluon_types = [];
      $bothsides_gluon_types = [];
      foreach($query as $key => $val) {
	if ($val->sides == 0) {
	  $bothsides_gluon_types[] = $val->gluon_type_id;
	} elseif ($val->sides == 1) {
	  $active_gluon_types[] = $val->gluon_type_id;
	} elseif ($val->sides == 2) {
	  $passive_gluon_types[] = $val->gluon_type_id;
	}
      }

      $active = false;
      $passive = false;
      $bothsides = false;
      if (!empty($active_gluon_types)) {
	$active = ['Relations.active_id' => $quark_id,
		   'Relations.gluon_type_id in' => $active_gluon_types];
      }
      if (!empty($passive_gluon_types)) {
	$passive = ['Relations.passive_id' => $quark_id,
		   'Relations.gluon_type_id in' => $passive_gluon_types];
      }
      if (!empty($bothsides_gluon_types)) {
	$bothsides = [

			      'or' => ['Relations.active_id' => $quark_id, 'Relations.passive_id' => $quark_id],
			      'Relations.gluon_type_id in' => $bothsides_gluon_types
		    ];
      }
      if ($active && $passive && $bothsides) {
	$where = ['or' => [$active, $passive, $bothsides]];
      } elseif ($active && $passive) {
	$where = ['or' => [$active, $passive]];
      } elseif ($bothsides) {
	$where = $bothsides;
      } elseif ($active) {
	$where = $active;
      } elseif ($passive) {
	$where = $passive;
      } else {
	return self::whereNoRecord();
      }
      return $where;
    }
    
    /*******************************************************/
    /* batch                                               */
    /*******************************************************/
    public function saveGluonsFromWikipedia($subject, $options =[])
    {
      $Subjects = TableRegistry::get('Subjects');

      //$query = U::removeAllSpaces($subject->name);
      $query = str_replace(' ', '_', $subject->name);
      $relations = Wikipedia::readPageForGluons($query);
      if (!$relations) return false;

      // backup before change it
      $contentType_bk = Wikipedia::$contentType;

      $ret = false;
      if (array_key_exists('relatives', $relations) && $relations['relatives']) {
	Wikipedia::$contentType = Wikipedia::CONTENT_TYPE_PERSON;


debug("-----------------------\n" . $subject->name . "\n-----------------------");
	// treat relatives
	foreach($relations['relatives'] as $val) {
	  if (!is_array($val) || !array_key_exists('main', $val)) continue;
	  $subject2 = $Subjects->forceGetQuark($val['main']);
	  if (!$subject2) continue;

	  $gluon = self::constRelativeGluon($subject, $subject2, $val);
	  if (!$gluon) continue;

	  // if the relation already exists, skip it.
	  if ($this->checkRelationExists($gluon['active_id'], $gluon['passive_id'])) continue;

	  $saving = $this->formToEntity($gluon);
	  $saving->user_id = 1;
	  $saving->last_modified_user = 1;

	  $saved = $this->save($saving, $options);
debug($val);
	}
	$ret = true;
      }
      if (array_key_exists('scenario_writers', $relations) && $relations['scenario_writers']) {
	Wikipedia::$contentType = Wikipedia::CONTENT_TYPE_PERSON;

	foreach($relations['scenario_writers'] as $val) {
	  if (!is_string($val)) continue;
	  $subject2 = $Subjects->forceGetQuark($val);
	  if (!$subject2) continue;

	  $gluon = self::constGluonSub2OnSub1($subject, $subject2, 'の脚本を手がけた');
	  if (!$gluon) continue;

	  // if the relation already exists, skip it.
	  if ($this->checkRelationExists($gluon['active_id'], $gluon['passive_id'])) continue;

	  $saving = $this->formToEntity($gluon);
	  $saving->user_id = 1;
	  $saving->last_modified_user = 1;
	  $saved = $this->save($saving, $options);
	}
	$ret = true;
      }
      if (array_key_exists('original_authors', $relations) && $relations['original_authors']) {
	Wikipedia::$contentType = Wikipedia::CONTENT_TYPE_PERSON;

	foreach($relations['original_authors'] as $val) {
	  if (!is_string($val)) continue;
	  $subject2 = $Subjects->forceGetQuark($val);
	  if (!$subject2) continue;

	  $gluon = self::constGluonSub2OnSub1($subject, $subject2, 'の原作者');
	  if (!$gluon) continue;

	  // if the relation already exists, skip it.
	  if ($this->checkRelationExists($gluon['active_id'], $gluon['passive_id'])) continue;

	  $saving = $this->formToEntity($gluon);
	  $saving->user_id = 1;
	  $saving->last_modified_user = 1;
	  $saved = $this->save($saving, $options);
	}
	$ret = true;
      }
      if (array_key_exists('actors', $relations) && $relations['actors']) {
	Wikipedia::$contentType = Wikipedia::CONTENT_TYPE_PERSON;

	foreach($relations['actors'] as $val) {
	  if (!is_string($val)) continue;
	  $subject2 = $Subjects->forceGetQuark($val);
	  if (!$subject2) continue;

	  $gluon = self::constGluonSub2OnSub1($subject, $subject2, 'に出演した');
	  if (!$gluon) continue;

	  // if the relation already exists, skip it.
	  if ($this->checkRelationExists($gluon['active_id'], $gluon['passive_id'])) continue;

	  $saving = $this->formToEntity($gluon);
	  $saving->user_id = 1;
	  $saving->last_modified_user = 1;
	  $saved = $this->save($saving, $options);
	}
	$ret = true;
      }
      if (array_key_exists('directors', $relations) && $relations['directors']) {
	Wikipedia::$contentType = Wikipedia::CONTENT_TYPE_PERSON;

	foreach($relations['directors'] as $val) {
	  if (!is_string($val)) continue;
	  $subject2 = $Subjects->forceGetQuark($val);
	  if (!$subject2) continue;

	  $gluon = self::constGluonSub2OnSub1($subject, $subject2, 'の監督');
	  if (!$gluon) continue;

	  // if the relation already exists, skip it.
	  if ($this->checkRelationExists($gluon['active_id'], $gluon['passive_id'])) continue;

	  $saving = $this->formToEntity($gluon);
	  $saving->user_id = 1;
	  $saving->last_modified_user = 1;
	  $saved = $this->save($saving, $options);
	}
	$ret = true;
      }

      Wikipedia::$contentType = $contentType_bk;
      return $ret;
    }

    // $relation = [active_name, passive_name, relation, start, end, is_momentary]
    // sample:   ['foo', 'bar', 'の父親', '2017-10-16', NULL, true]
    public function saveGluonByRelation($relation, $options =[])
    {
      if (!is_array($relation) || count($relation) < 3) return false;

      $Subjects = TableRegistry::get('Subjects');
      $subject1 = $Subjects->getOneWithSearch($relation[0]);
      if (!$subject1 || is_array($subject1)) return false;
      $subject2 = $Subjects->getOneWithSearch($relation[1]);
      if (!$subject2 || is_array($subject2)) return false;

      $rel3 = NULL;
      $rel4 = NULL;
      $rel5 = false;
      if (array_key_exists(3, $relation)) {
	$rel3 = $relation[3];
      }
      if (array_key_exists(4, $relation)) {
	$rel4 = $relation[4];
      }
      if (array_key_exists(5, $relation)) {
	$rel5 = $relation[5];
      }
      return $this->saveGluon($subject1, $subject2, $relation[2], $rel3, $rel4, $rel5, $options);
    }

    public function saveGluon($subject1, $subject2, $relation, $start, $end, $is_momentary, $options =[])
    {
      $gluon = self::constGluon($subject1->id, $subject2->id, $relation, $start, $end, $is_momentary);
      if (!$gluon) return false;

      // if the relation already exists, skip it.
      if ($this->checkRelationExists($gluon['active_id'], $gluon['passive_id'])) return false;

      $saving = $this->formToEntity($gluon);
      $saving->user_id = 1;
      $saving->last_modified_user = 1;

      return $this->save($saving, $options);
    }
    public static function constGluon($active_id, $passive_id, $relation, $start, $end, $is_momentary)
    {
      return [
	      'active_id'      => $active_id,
	      'passive_id'     => $passive_id,
	      'relation'       => $relation,
	      'start'          => $start,
	      'end'            => $end,
	      'is_momentary'   => $is_momentary,
      ];
    }

    /*******************************************************/
    /* Tools                                               */
    /*******************************************************/
    public function checkRelationExists($active_id, $passive_id)
    {
      $where = self::whereActivePassivePair($active_id, $passive_id);
      $data = $this->find()->where($where)->first();
      return !!$data;
    }

    public static function constRelativeGluon($subject1, $subject2, $relative)
    {
      if (!self::checkRelativeInfoFormat($relative)) return false;
      if (GlobalDataSet::isYoungerRelativeType($relative['relative_type'])) {
	$active_id      = $subject2->id;
	$passive_id     = $subject1->id;
	$relation       = 'の' . $relative['relative_type'];
	$start          = $subject2->start ? $subject2->start->format('Y-m-d H:i:s') : NULL;
	$start_accuracy = $subject2->start_accuracy;
      } elseif (GlobalDataSet::isOlderRelativeType($relative['relative_type'])) {
	$active_id      = $subject1->id;
	$passive_id     = $subject2->id;
	$relation       = 'を' . $relative['relative_type'] . 'に持つ';
	$start          = $subject1->start ? $subject1->start->format('Y-m-d H:i:s') : NULL;
	$start_accuracy = $subject1->start_accuracy;
      } else return false;


      if (array_key_exists('source', $relative) && $relative['source']) {
	$source = $relative['source'];
      } else {
	$source = NULL;
      }

      return [
	      'active_id'      => $active_id,
	      'passive_id'     => $passive_id,
	      'relation'       => $relation,
	      'start'          => $start,
	      'start_accuracy' => $start_accuracy,
	      'is_momentary'   => true,
	      'source'         => $source,
      ];
    }
    public static function constGluonSub2OnSub1($subject1, $subject2, $relation)
    {
      $active_id      = $subject2->id;
      $passive_id     = $subject1->id;
      $relation       = $relation;
      $start          = $subject1->start ? $subject1->start->format('Y-m-d H:i:s') : NULL;
      $start_accuracy = $subject1->start_accuracy;

      return [
	      'active_id'      => $active_id,
	      'passive_id'     => $passive_id,
	      'relation'       => $relation,
	      'start'          => $start,
	      'start_accuracy' => $start_accuracy,
	      'is_momentary'   => true,
      ];
    }
    public static function checkRelativeInfoFormat($relative)
    {
      return (is_array($relative) && array_key_exists('main', $relative) && array_key_exists('relative_type', $relative));
    }
}
