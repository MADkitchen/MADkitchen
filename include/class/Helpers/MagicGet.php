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

trait MagicGet {

    public function __get($key) {
        // Class method to try and call
        $method = "get_{$key}";

        // Return property if exists
        if (method_exists($this, $method)) {
            return call_user_func(array($this, $method));

            // Return get method results if exists
        } elseif (property_exists($this, $key)) {
            return $this->{$key};
        }

        // Return null if not exists
        return null;
    }

}