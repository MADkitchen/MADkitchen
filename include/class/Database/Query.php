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

namespace MADkitchen\Database;

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
    private $aggregate_func_list = [
        //COUNT(*) already covered in BerlinDB
        'sum',
        'min',
        'max',
        'average',
        'groutp_concat',
        'first',
        'last',
    ];
    private $count_flag = false;

    public function __construct($query = array()) {

        $this->table_alias = 'mk'.substr(md5($this->table_name), 0, 3);
        $this->item_name = strtolower($this->table_name) . '_record';
        $this->item_name_plural = strtolower($this->table_name) . '_records';

        /* BerlinDB does not have support for aggregate functions except COUNT, hence workaround
         * setting 'count' key=true and rewriting 'fields' key through provided hook is used.
         */

        if (array_intersect_key($query, array_flip($this->aggregate_func_list))) {
            if (isset($query['count'])) {
                $this->count_flag = (bool) $query['count'];
            }
            $query['count'] = true;
            //Filter is added only if aggregate function is present in the query, it is used in parent class __construct and removed immediately afterwards
            add_filter($this->item_name_plural . '_query_clauses', array($this, 'fields_override'));
        }

        $retval = parent::__construct($query);

        if (has_action($this->item_name_plural . '_query_clauses', array($this, 'fields_override')) !== false) {
            remove_filter($this->item_name_plural . '_query_clauses', array($this, 'fields_override'));
        }

        return $retval;
    }

    function fields_override($args) {
        $items = [];
        foreach (array_intersect_key($this->query_vars, array_flip($this->aggregate_func_list)) as $key => $value) {
            foreach (array_filter(array_map(array($this, 'parse_column_name'), $value)) as $item) {
                $items[] = strtoupper($key) . '(' . $item . ') AS ' . strtolower($key) . '_' . str_replace("{$this->table_alias}.", '', $item);
            }
        }

        /* BerlinDB in-built 'COUNT' support is used to trig all other aggregate functions.
         * If actual 'count' was not requested in original query, sentence is removed from 'fields'.
         * Ref. parent::parse_fields()
         */
        $sentence = [
            $this->count_flag ? $args['fields'] : $args['groupby'],
            implode(', ', $items),
        ];
        $args['fields'] = implode(', ', array_filter($sentence));

        /* BerlinDB casts results to int if not grouping counts. Adding space to 'groupby' if empty as a workaround.
         * Ref. parent::get_items()
         */
        if (empty($this->query_vars['groupby'])) {
            $this->query_vars['groupby'] = ' ';
        }

        return $args;
    }

    private function parse_column_name($data) {
        $r = new \ReflectionMethod(parent::class, 'parse_groupby');
        $r->setAccessible(true);
        return $r->invoke($this, $data);
    }

}
