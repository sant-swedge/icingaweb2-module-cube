<?php

namespace Icinga\Module\Cube\Ido;

use Icinga\Module\Cube\DbCube;
use Icinga\Module\Monitoring\Backend\MonitoringBackend;

class IdoCube extends DbCube
{
    protected $factColumns;

    protected $dbName = 'icinga2';

    protected $availableFacts = array(
        'hosts_cnt'           => 'COUNT(*)',
        'hosts_nok'           => 'SUM(CASE WHEN hs.current_state = 0 THEN 0 ELSE 1 END)',
        'hosts_unhandled_nok' => 'SUM(CASE WHEN hs.current_state != 0 AND hs.problem_has_been_acknowledged = 0 AND hs.scheduled_downtime_depth = 0 THEN 1 ELSE 0 END) AS hosts_unhandled_nok',
    );

    public function setBackend(MonitoringBackend $backend)
    {
        $this->connection = $backend->getResource();
        $this->db = $connection->getDbAdapter();
        return $this;
    }

    public function chooseFacts($facts)
    {
        parent::chooseFacts($facts);

        $this->factColumns = array();
        foreach ($this->chosenFacts as $name) {
            $this->factColumns[$name] = $this->availableFacts[$name];
        }

        return $this;
    }

    public function prepareInnerQuery()
    {
        $this->requireBackend();

        $select = $this->db->select()->from(
            array('o' => $this->dbName . '.icinga_objects'),
            array()
        )->join(
            array('h' => $this->dbName . '.icinga_hosts'),
            'o.object_id = h.host_object_id AND o.is_active = 1',
            array()
        )->joinLeft(
            array('hs' => $this->dbName . '.icinga_hoststatus'),
            'hs.host_object_id = h.host_object_id',
            array()
        );

        $columns = array();
        foreach ($this->dimensions as $name => $dimension) {
            $dimension->addToQuery($select);
            $columns[$name] = $dimension->getColumnExpression();
        }

        $select->columns($columns + $this->factColumns);

        return $select;
    }

    public function setDbName($name)
    {
        $this->dbName = $name;
        return $this;
    }

    protected function requireBackend()
    {
        if ($this->backend === null) {
            $this->setBackend(MonitoringBackend::instance());
        }
    }
}