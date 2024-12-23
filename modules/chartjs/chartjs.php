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

namespace MADkitchen\Module;

// Exit if accessed directly
defined('ABSPATH') || exit;

class chartjs extends \MADkitchen\Modules\Module {

    public function load_module() {
        $this->scripts = [
            ['chartjs', plugin_dir_url(__FILE__) . 'data/chart.min.js'],
            ['chartjs_datalabels', plugin_dir_url(__FILE__) . 'plugins/chartjs-plugin-datalabels.min.js'],
            ['chartjs_plugins_loader', plugin_dir_url(__FILE__) . 'plugins/loader.js'],
        ];

        parent::load_module();
    }

}
