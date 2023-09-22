document.addEventListener("DOMContentLoaded", function() {
    'use strict';
    var $table = document.getElementById('wp-list-table--wpu_override_gettext');
    if (!$table) {
        return;
    }
    var $table_lines = $table.querySelectorAll('tr[data-filter-text]'),
        $filter = document.getElementById('wpu_override_gettext__filter_results');
    $filter.addEventListener('keyup', function() {
        filter_table($filter.value);
    }, 1);

    function filter_table(filter_value) {
        if (!filter_value) {
            Array.prototype.forEach.call($table_lines, function(el) {
                el.setAttribute('data-visible', '1');
            });
            return;
        }
        filter_value = filter_value.toLowerCase();
        var value_words = filter_value.split(' ');
        Array.prototype.forEach.call($table_lines, function(el) {
            var _search_string = el.getAttribute('data-filter-text');

            /* Visible by default */
            el.setAttribute('data-visible', '1');
            for (var i = 0, len = value_words.length; i < len; i++) {
                /* Ignore empty parts */
                if (!value_words[i]) {
                    continue;
                }
                /* Hide if a part is not present */
                if (_search_string.indexOf(value_words[i]) < 0) {
                    console.log(value_words[i]);
                    el.setAttribute('data-visible', '0');
                    return;
                }
            }
        });

    }
});
