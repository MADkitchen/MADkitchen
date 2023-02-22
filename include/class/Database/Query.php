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
    private $count_flag = false;
    private $class_shortname = '';
    private $table_shortname = '';
    public $items_resolved = [];
    public $items_extended = [];

    //private $test_row = [];

    public function __construct($query = array()) {

        $this->table_alias = 'mk' . substr(md5($this->table_name), 0, 3);
        $this->item_name = strtolower($this->table_name) . '_record';
        $this->item_name_plural = strtolower($this->table_name) . '_records';
        $this->class_shortname = \MADkitchen\Modules\Handler::get_module_name(get_class($this));
        $this->table_shortname = str_replace(Handler::get_default_table_name($this->class_shortname) . "_", '', $this->table_name);
    }

    private function query_defaults_override($query = []) {
        $berlindb_defaults_overrides = [
            'number' => false,
            'order' => 'ASC'
        ];
        if (!empty($query)) {
            $query = array_merge($berlindb_defaults_overrides, $query);
        }

        return $query;
    }

    public function query($query = []) {

        //Check if it's a simple query which can be resolved with buffered lookup tables
        if (!empty($items = \MADkitchen\Database\Lookup::simple_lookup_query($this->class_shortname, $this->table_shortname, $query))) {
            //check other properties to override e.g. query_var
            $this->items = $items;
        } else {
            $this->query_direct($query);
        }
    }

    public function query_direct($query = []) {
        $query = $this->query_defaults_override($query);

        /* BerlinDB does not have support for aggregate functions except COUNT, hence workaround
         * setting 'count' key=true and rewriting 'fields' key through provided hook is used.
         */
        if (!empty($query) && array_intersect_key($query, array_flip(Handler::get_query_aggregate_func_list()))) {
            if (isset($query['count'])) {
                $this->count_flag = (bool) $query['count'];
            }
            $query['count'] = true;
            //Filter is added only if aggregate function is present in the query, it is used in parent class __construct and removed immediately afterwards
            add_filter($this->item_name_plural . '_query_clauses', array($this, 'fields_override'));
        }

        parent::__construct();
        if (!empty($query)) {
            parent::query($query);
        }
        if (!empty($this->items))
            $this->items_to_array();

        if (has_action($this->item_name_plural . '_query_clauses', array($this, 'fields_override')) !== false) {
            remove_filter($this->item_name_plural . '_query_clauses', array($this, 'fields_override'));
        }
    }

    private function items_to_array() {
        $test_row = reset($this->items);
        if (!$test_row)
            return;

        if ($test_row && gettype($test_row) === 'object') {
            $test_row_object_methods_keys = (array) new \BerlinDB\Database\Row();
            foreach ($this->items as $key => &$row) {
                $row = (array) $row;
                $row = array_diff_key($row, $test_row_object_methods_keys);
            }
        }
    }

    public function fields_override($args) {
        $items = [];
        foreach (array_intersect_key($this->query_vars, array_flip(Handler::get_query_aggregate_func_list())) as $key => $value) {
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

    //TODO: check if needed
    private function get_column_names() {
        $r = new \ReflectionMethod(parent::class, 'get_column_names');
        $r->setAccessible(true);
        return array_flip($r->invoke($this));
    }

    public function resolve_items(array $columns_filter = []) {
        if (empty($this->items))
            return;

        $target_items = empty($this->items_extended) ? $this->items : $this->items_extended; //TODO check if items_extended (not resolved extended table) will be implemented)

        $test_row = array_diff(reset($target_items), $columns_filter);

        // Build local lookup array
        $resolved_columns = new ColumnsResolver($this->class_shortname, $this->table_shortname, array_keys($test_row));
        $referral_queries = Handler::get_columns_array_from_rows($target_items, $resolved_columns->referral, true);
        $referral_lookup_query_objects = [];
        foreach ($resolved_columns->referral as $referral_key => $referral_column) {
            $primary_column_name = Handler::get_primary_key_column_name($this->class_shortname, $resolved_columns->referral_source[$referral_column]);
            $referral_lookup_query_objects[$referral_column] = \MADkitchen\Modules\Handler::$active_modules[$this->class_shortname]['class']->query($resolved_columns->referral_source[$referral_column],
                    [
                        $primary_column_name => $referral_queries[$referral_column],
                    ]
            );
            $referral_lookup_resolved[$referral_column] = [];
            // Creating all needed ItemResolver items from a single column query and save in a lookup array
            foreach ($referral_lookup_query_objects[$referral_column]->items as $this_row) {
                $referral_lookup_resolved[$referral_column][$this_row[$primary_column_name]] = Lookup::build_ItemResolver(
                                $this->class_shortname,
                                $referral_column,
                                $this_row, //always provide the row to avoid unneeded new query
                                $resolved_columns->referral_source[$referral_column],
                );
            }
        }

        // Resolve columns for each row based on local lookup array
        foreach ($target_items as $key => $row) {
            foreach ($resolved_columns->resolved as $local_column) {
                $this->items_resolved[$key][$local_column] = Lookup::build_ItemResolver(
                                $this->class_shortname,
                                $local_column,
                                $row, //always provide the row to avoid unneeded new query
                                $this->table_shortname,
                );
            }
            foreach ($resolved_columns->referral as $referral_key => $referral_column) {
                $this->items_resolved[$key][$referral_column] = $referral_lookup_resolved[$referral_column][$target_items[$key][$referral_column]];
            }
        }

        return $this->items_resolved;
    }

    public function append_external_columns(array $target_columns = []) {

        if (empty($this->items_resolved))
            $this->resolve_items();

        if (empty($target_columns) || empty($this->items_resolved))
            return;

        // Search array to contain only columns missing in current table
    $counter = (new ColumnsResolver($this->class_shortname, $this->table_shortname, $target_columns))->external;

        $count = count($counter);
        do {
            $test_row = reset($this->items_resolved); //check if reduce columns and retrieve just 1 row
            foreach ($counter as $target_column_key => $target_column_name) {
                // Test if $target_column_name is found in source table of one item in the first row.
                $lookup_column_name = false;
                foreach ($test_row as $test_column_name => $test_item) {
                    if ($test_item && key_exists($target_column_name, $test_item->row)) { //CHECK why is_resolved is needed
                        $lookup_column_name = $test_column_name;

                        $found_source_table = $test_row[$lookup_column_name]->source_table;
                        $search_query_key = Handler::get_primary_key_column_name($this->class_shortname, $found_source_table);
                        //First occurrence only.
                        // TODO: check if additional tests are applicable to select the right $test_key in case of multiple occurrencies.
                        break;
                    }
                }
                // Continue only if $target_column_name is found in source table of one item, otherwise target column cannot be found (in this loop at least)
                if ($lookup_column_name === false)
                    continue;

                // Query found lookup column source table to get correspondances with target column
                $query_object = \MADkitchen\Modules\Handler::$active_modules[$this->class_shortname]['class']->query($test_row[$lookup_column_name]->source_table,
                        [
                            $search_query_key => reset(Handler::get_columns_array_primary_keys_from_rows($this->items_resolved, [$lookup_column_name], true)),
                        ],
                );

                // Build local lookup array
                $query_object->resolve_items([$target_column_name, $lookup_column_name]);
                $external_column_rows_array = Handler::get_columns_array_from_rows($query_object->items_resolved, [$target_column_name, $lookup_column_name]);
                $external_column_lookup_array = array_combine(array_map(fn($x) => $x->primary_key, $external_column_rows_array[$lookup_column_name]), $external_column_rows_array[$target_column_name]);

                // Add target column to each row based on local lookup array
                foreach ($this->items_resolved as $this_items_key => &$this_items_row) {
                    $target_index = $this_items_row[$lookup_column_name]->primary_key;
                    if ($target_index)
                        $this_items_row[$target_column_name] = $external_column_lookup_array[$target_index];
                }

                // Found target column is removed from search array.
                unset($counter[$target_column_key]);
            }
            // Continue only if target columns list is reducing, otherwise break as search is stalled
            if ($count > count($counter)) {
                $count = count($counter);
            } else {
                break;
            }
        } while ($count);
    }

    private function get_chain_recursive(&$chain = []) {
        $found = false;

        //TODO: check if column exists?
        //TODO: Document only last element of chain is used as starting point

        if (Handler::is_column_reference($this->class_shortname, $this->table_shortname, end($chain)))
            return true;

        $ref_tables = Handler::get_referred_tables($this->class_shortname, end($chain));
        if (empty($ref_tables))
            return false;

        foreach ($ref_tables as $table) {
            $columns = new ColumnsResolver($this->class_shortname, $table);
            foreach ($columns->resolved as $column1) {
                //already visited
                if (in_array($column1, $chain))
                    continue;
                else
                    $chain[] = $column1;

                if ($this->get_chain_recursive($chain))
                    $found = true;


                if ($found === true)
                    return true;

                array_pop($chain);
            }
        }
        return false;
    }

    private function resolve_chain($chain, $initial_query) {
        $prev_key = array_shift($chain);
        $this_query = [$prev_key => $initial_query];
        //TODO: check if need to add is_scalar test

        foreach ($chain as $column_name) {
            $source_table = Handler::get_source_table($this->class_shortname, $column_name);
            $this_query_object = \MADkitchen\Modules\Handler::$active_modules[$this->class_shortname]['class']->query($source_table, $this_query);
            $this_query = [$column_name => reset(Handler::get_columns_array_from_rows($this_query_object->items, [Handler::get_primary_key_column_name($this->class_shortname, $source_table)], true))];
        }

        return $this_query;
    }

    public function translate_external_columns_queries($target_queries = []) {
        $translated_queries = [];
        foreach ($target_queries as $column_name => $query) {
            $translated_query = [$column_name];
            if (!$this->get_chain_recursive($translated_query))
                continue;

            $translated_queries = array_merge_recursive($translated_queries, $this->resolve_chain($translated_query, $query));
        }
        return $translated_queries;
    }

    public function separate_lookup_queries_to_array(array $tot_queried_columns) {
        $empty_query = empty($this->items);
        $retval = [];
        $tot_columns = $this->Lookup::build_ColumnsResolver($this->class_shortname, $this->table_shortname, $tot_queried_columns);
        $local_queried_columns = $tot_columns->get_local_no_aggregated(); //array_intersect($tot_queried_columns, $this->get_column_names());
        $external_queried_columns = $tot_columns->external;
        foreach ($local_queried_columns as $key => $local_column_name) {

            //single queries for each local column if no overall query has been requested previously
            if ($empty_query) {
                $this->query(
                        [
                            'number' => false,
                            'groupby' => [$local_column_name],
                        ],
                );
            }

            //corresponding query in this table for current column
            $unique_local_items = Handler::get_columns_array_from_rows($this->items, [$local_column_name], true);

            //try to resolve recursively external columns starting from this query items
            $extra_items = $this->append_external_columns($external_queried_columns);

            $retval = array_merge_recursive($retval, $unique_local_items, $extra_items);

            //remove from search list if already found. check if it is better tomkeep on searching and merge (only remove the following line)
            $external_queried_columns = array_intersect_key($external_queried_columns, array_keys($retval));

            if ($empty_query) {
                $this->items = [];
            }
        }
        return $retval;
    }

}
