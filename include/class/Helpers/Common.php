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

namespace MADkitchen\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

class Common {

    public static function sanitize_key($key) {
        if (is_scalar($key)) {
            return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key));
        } else {
            return null;
        }
    }

    public static function ksort_by_array($array, $sort_list) {
        $array_bkp = $array;
        $sort_list=array_values($sort_list);
        if (uksort($array_bkp, function ($a, $b) use ($sort_list) {
                    return array_search($a, $sort_list) - array_search($b, $sort_list);
                }) === true) {
            return $array_bkp;
        } else {
            return $array;
        }
    }

}
