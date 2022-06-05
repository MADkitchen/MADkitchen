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

class w3css extends \MADkitchen\Modules\Module {

    public function load_module() {
        parent::load_module();
        if (!wp_style_is('w3css', 'registered')) {
            wp_register_style('w3css', plugin_dir_url(__FILE__) . 'data/w3.css');
        }

        if (!wp_style_is('w3css', 'enqueued')) {
            wp_enqueue_style('w3css');
        }
    }

}
