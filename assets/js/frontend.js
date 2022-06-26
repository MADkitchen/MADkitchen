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

jQuery(document).ready(function ($) {

    $.extend($.expr[':'].icontains = function (el, i, m) { // checks for substring (case insensitive)
        var search = m[3];
        if (!search)
            return false;

        var pattern = new RegExp(search, 'i');
        return pattern.test($(el).text());
    });
});

//TODO: generalize better
function one_word_find(item_sel = jQuery([]), grp_swc_sel = jQuery([]), grp_blk_sel = jQuery([]),src = '', close_on_exit=false, open_on_exit = false) {

    if (src !== '') {
        grp_swc_sel.hide();
        grp_blk_sel.show();
        item_sel.hide();
        item_sel.filter(":icontains(" + src + ")").show();
    } else {
        grp_swc_sel.show();
        grp_blk_sel.hide();
        item_sel.show();
        if (close_on_exit) close_on_exit.hide();
        if (open_on_exit) open_on_exit.show();
}
}

const randomNum = () => Math.floor(Math.random() * (231 + 1 - 52) + 52);
const randomRGB = () => `rgb(${randomNum()}, ${randomNum()}, ${randomNum()})`;

function get_random_rgb(count) {
    const data = [];
    for (i = 0; i < count; i++) {
        data.push(randomRGB());
    }
    return data;
}

function mk_get_spinner(extra_classes = '') {
    return '<div class="w3-center w3-spin ' + extra_classes + '">&ring;</div>';
}

function mk_round(num, places = 0) {
    return +(Math.round(num + "e+" + places) + "e-" + places);
}
