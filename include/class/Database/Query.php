<?php

/*
 * Copyright (C) 2022 Giovanni Cascione <ing.cascione@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace MADkit\Database;

if (!defined('ABSPATH')) {
    exit;
}

class Query extends \BerlinDB\Database\Query {

    protected $table_name = '';
    protected $table_alias = '';
    protected $table_schema = '';
    protected $item_name = '';
    protected $item_name_plural = '';
    protected $item_shape = '';
    private $math_functions = array();

    public function __construct($query = array()) {

        //$this->table_name = \MADkit\Modules\Handler::get_default_table_name($module_name);
        $this->table_alias = substr(md5($this->table_name), 0, 3);
        //$this->table_schema = '\\'.$namespace . '\\'.$module_name. '\Schema';
        $this->item_name = strtolower($this->table_name) . '_record';
        $this->item_name_plural = strtolower($this->table_name) . '_records';
        //$this->item_shape = class_exists('\\'.$namespace . '\\'.$module_name.'\Row')?'\\'.$namespace . '\\'.$module_name.'\Row':'';

        $this->math_functions['sum'] = false;

        /* BerlinDB does not have support for SUM, hence workaround using COUNT
         * and final query request overwrite through provided hook is used.
         * Number of SUM terms is stored in the property math_functions['sum'] and
         * relevant columns are prepended in 'groupby' array.
         * In hook callback SUM terms are retrieved from 'groupby' and removed, and
         * used to build the SUM sentence to overwrite the COUNT one.
         * After query is executed, math_functions['sum'] is set back to false for
         * normal use
         */

        if (isset($query['sum'])) {
            $this->math_functions['sum'] = count($query['sum']);
            if (!isset($query['groupby']))
                $query['groupby'] = array();
            array_unshift($query['groupby'], implode(',', $query['sum']));
            $query['count'] = true;
            add_filter($this->item_name_plural . '_query_clauses', array($this, 'tb_query_override'));
        }

        $retval = parent::__construct($query);

        if (isset($query['sum'])) {
            remove_filter($this->item_name_plural . '_query_clauses', array($this, 'tb_query_override'));
            $this->math_functions['sum'] = false;
        }

        return $retval;
    }

    function tb_query_override($args) {
        if ($this->math_functions['sum']) {
            $sum_columns = array();
            $as_sentence = __('Total', 'time_beans');
            $groupby = explode(',', $args['groupby']);

            for ($i = 0; $i < $this->math_functions['sum']; $i++) {
                $sum_columns[] = 'SUM(' . array_shift($groupby) . ')';
            }

            $sum_sentence = implode(',', $sum_columns) . ' AS ' . $as_sentence;
            $args['groupby'] = implode(',', $groupby);
            $group_sentence = $args['groupby'] ? $args['groupby'] . ',' : '';
            $args['fields'] = $group_sentence . $sum_sentence;
        }
        return $args;
    }
}
