<?php
/**
* phpCan - http://idc.anavallasuiza.com/
*
* phpCan is released under the GNU Affero GPL version 3
*
* More information at license.txt
*/

namespace ANS\PHPCan\Data\Relations;

defined('ANS') or die();

class Relation_1_x extends Relations implements Irelations
{
    public $unique = true;

    /**
     * public static function extend (array $settings)
     */
    public static function extend ($settings)
    {
        $return = array();

        $settings = self::basicSettings($settings, false);

        $return['relations'][] = $settings;

        //Inverted relation
        $settings2 = $settings;
        $settings2['mode'] = 'x 1';
        $settings2['join'] = array_reverse($settings2['join']);
        $settings2['direction'] = array_reverse($settings2['direction']);
        $settings2['tables'] = array_reverse($settings2['tables']);

        $return['relations'][] = $settings2;

        //Relation without direction
        if ($settings['direction'] && ($settings['tables'][0] == $settings['tables'][1])) {
            $settings2 = $settings;
            $settings2['direction'] = array();

            $return['relations'][] = $settings2;
        }

        //New fields
        $return['new_fields'] = array(
            $settings['tables'][1] => array(
                $settings['join'][0] => array('format' => 'id_relation')
            )
        );

        return $return;
    }

    /**
     * public function removeDependent ()
     *
     * return boolean
     */
    public function removeDependent ()
    {
        if ($this->settings['dependent']) {
            return true;
        }

        return false;
    }

    /**
     * public function unrelateDependent ()
     *
     * return boolean
     */
    public function unrelateDependent ()
    {
        if ($this->settings['auto']) {
            return false;
        }

        return true;
    }

    /**
     * public function selectConditions (string $renamed_table0, string $renamed_table1, array $condition)
     *
     * return array
     */
    public function selectConditions ($renamed_table0, $renamed_table1, $condition)
    {
        $conditions = array();
        $relation_field = array();
        $relation_conditions = array();

        //Add the main condition
        if ($condition['field']) {
            $conditions[$condition['num'].$renamed_table1.'.'.$condition['field'].$condition['condition']] = $condition['value'];

            $relation_field[$this->getTable($this->settings['tables'][1], $renamed_table1)] = array('id[id_prev_table]');
        }

        //Autorelated tables with direction
        if ($this->settings['direction'] && ($this->settings['tables'][0] == $this->settings['tables'][1])) {
            $relation_conditions[] = '`'.$renamed_table0.'`.'.$this->settings['join'][1].' = `'.$renamed_table1.'`.`id`';

        //Different joins with no direction
        } else if ($this->settings['auto'] && empty($this->settings['direction']) && ($this->settings['join'][0] != $this->settings['join'][1])) {
            $relation_conditions[] = '(`'.$renamed_table0.'`.`id` = `'.$renamed_table1.'`.'.$this->settings['join'][0]
                                    .' OR `'.$renamed_table0.'`.`id` = `'.$renamed_table1.'`.'.$this->settings['join'][1].')';

        //Add normal relation condition
        } else {
            $relation_conditions[] = '`'.$renamed_table0.'`.`id` = `'.$renamed_table1.'`.'.$this->settings['join'][0];
        }

        return array(
            'relation_conditions' => $relation_conditions,
            'relation_field' => $relation_field,
            'relation_table' => array(),
            'conditions' => $conditions
        );
    }

    /**
     * public function relate (array $operations_table0, array $operations_table1, [array $options])
     *
     * return boolean
     */
    public function relate ($operations_table0, $operations_table1, $options = array())
    {
        if (empty($operations_table0['conditions']) || empty($operations_table1['conditions'])) {
            $this->Debug->error('db', __('For security, conditions param is needed to relate function. If you want relate all rows, use conditions = "all"'));

            return false;
        }

        $id_table0 = $this->getIds($this->settings['tables'][0], $operations_table0);
        $id_table0 = end($id_table0);

        if (empty($id_table0)) {
            return true;
        }

        $ids_table1 = $this->getIds($this->settings['tables'][1], $operations_table1);
        $limit_table1 = count($ids_table1);

        $ok = $this->Db->update(array(
            'table' => $this->settings['tables'][1],
            'data' => array($this->settings['join'][0] => $id_table0),
            'table_events' => false,
            'conditions' => array('id' => $ids_table1),
            'limit' => $limit_table1,
            'comment' => __('Relating the table %s with %s', $this->settings['tables'][1], $this->settings['tables'][0])
        ));

        if ($ok === false) {
            return false;
        }

        return true;
    }

    /**
     * public function unrelate (array $operations_table0, array $operations_table1, [array $options])
     *
     * return boolean
     */
    public function unrelate ($operations_table0, $operations_table1, $options = array())
    {
        if (empty($operations_table0['conditions']) || empty($operations_table1['conditions'])) {
            $this->Debug->error('db', __('For security, conditions param is needed to unrelate function. If you want unrelate all rows, use conditions = "all"'));

            return false;
        }

        $conditions = array(
            $this->settings['join'][0] => $this->getIds($this->settings['tables'][0], $operations_table0)
        );

        if ($operations_table1['conditions'] && ($operations_table1['conditions'] !== 'all')) {
            $conditions['id'] = $this->getIds($this->settings['tables'][1], $operations_table1);
        }

        $ok = $this->Db->update(array(
            'table' => $this->settings['tables'][1],
            'data' => array(
                $this->settings['join'][0] => 0
            ),
            'table_events' => false,
            'conditions' => $conditions,
            'comment' => __('Unrelating the table %s with %s', $this->settings['tables'][1], $this->settings['tables'][0])
        ));

        if ($ok === false) {
            return false;
        }

        return true;
    }
}
