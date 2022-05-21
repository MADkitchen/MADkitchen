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

namespace MADkit\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

class Time {

    public static function get_years($year = null, $years_back = 5, $years_fwd = 0) {
        $date_obj = new \DateTime();
        $this_year = (int) $date_obj->format('Y');
        $year = is_null($year) ? $this_year : $year;

        $retval = array();
        for ($i = $this_year - $years_back; $i <= $this_year + $years_fwd; $i++) {
            $retval[$i]['selected'] = $i == $year;
            $retval[$i]['year'] = $i;
        }
        return $retval;
    }

    public static function get_weeks($year = null, $week = null) {
        $date_obj = new \DateTime();
        $this_year = (int) $date_obj->format('Y');
        $this_week = (int) $date_obj->format('W');
        $max_week = (int) $date_obj->setISODate($year, 0)->format('W');

        $week = is_null($week) ? $this_week : $week;
        $year = is_null($year) ? $this_year : $year;

        $retval = array();
        for ($i = 1; $i <= $max_week; $i++) {
            $start = explode(' ', $date_obj->setISODate($year, $i)->format('d F'));
            $end = explode(' ', $date_obj->setISODate($year, $i, 7)->format('d F'));

            if ($start[1] == $end[1]) {
                $retval[$i]['range'] = "$start[0] - $end[0] " . __($start[1]);
            } else {
                $retval[$i]['range'] = "$start[0] " . __($start[1]) . " - $end[0] " . __($end[1]);
            }
            $retval[$i]['selected'] = $i == $week;
        }

        return $retval;
    }

    public static function get_days($year = null, $week = null) {
        //$x=new DateTime()->setDate($year,12,31)->format('W');
        //new DateTime()->setISODate($year, $week,7)->format('Y-m-d');
        $date_obj = new \DateTime();
        $this_year = (int) $date_obj->format('Y');
        $this_week = (int) $date_obj->format('W');

        $week = is_null($week) ? $this_week : $week;
        $year = is_null($year) ? $this_year : $year;

        $start_day = $date_obj->setISODate($year, $week);

        $retval = array();
        for ($i = 0; $i < 7; $i++) {
            $retval[$i]['date'] = $date_obj->format('Y-m-d');
            $retval[$i]['name'] = __($date_obj->format('D'));
            $retval[$i]['number'] = __($date_obj->format('d'));
            $date_obj->modify('+1 day');
        }

        return $retval;
    }

}
