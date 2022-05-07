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

class Table extends \BerlinDB\Database\Table {

    /**
     * Database version.
     *
     * @since 0.1.0
     * @var   mixed
     */
    protected $version = '1.0.0';

    /**
     * Key => value array of versions => methods.
     *
     * @since 0.1.0
     * @var   array
     */
    //TODO: extend functionality to module
    protected $upgrades = array();

    /**
     * Table name, without the global table prefix.
     *
     * @since 0.1.0
     * @var   string
     */
    public $name = '';

    /**
     * Database version key (saved in _options or _sitemeta)
     *
     * @since 0.1.0
     * @var   string
     */
    protected $db_version_key = '';

    public function __construct() {

        $this->db_version_key = strtolower($this->name) . '_version';

        parent::__construct();

        if (!$this->exists()) {
            $this->install();
        }
    }

    protected function set_schema() {}

}
