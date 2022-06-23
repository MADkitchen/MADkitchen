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

function one_word_find(src = '', item_sel = '', grp_swc_sel = '', grp_blk_sel = '') {

    if (src !== '') {
        jQuery(grp_swc_sel).hide();
        jQuery(grp_blk_sel).show();
        jQuery('[id$="item"]').hide();
        jQuery(item_sel + ":icontains(" + src + ")").show();
    } else {
        jQuery(grp_swc_sel).show();
        jQuery(grp_blk_sel).hide();
        jQuery(item_sel).show();
}
}

const randomNum = () => Math.floor(Math.random() * (231+1-52)+52);
const randomRGB = () => `rgb(${randomNum()}, ${randomNum()}, ${randomNum()})`;

function get_random_rgb(count) {
    const data = [];
    for (i = 0; i < count; i++) {
        data.push(randomRGB());
    }
    return data;
}
