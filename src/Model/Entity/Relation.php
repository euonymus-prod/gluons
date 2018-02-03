<?php
namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Relation Entity
 *
 * @property string $id
 * @property string $active_id
 * @property string $passive_id
 * @property string $relation
 * @property \Cake\I18n\Time $start
 * @property \Cake\I18n\Time $end
 * @property string $start_accuracy
 * @property string $end_accuracy
 * @property bool $is_momentary
 * @property \Cake\I18n\Time $created
 * @property \Cake\I18n\Time $modified
 *
 * @property \App\Model\Entity\Active $active
 * @property \App\Model\Entity\Passive $passive
 */
class Relation extends Entity
{

    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array
     */
    protected $_accessible = [
        '*' => true,
        'id' => false
    ];
}
