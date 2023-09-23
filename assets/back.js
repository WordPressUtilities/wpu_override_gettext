document.addEventListener("DOMContentLoaded", function() {
    'use strict';
    var $table = document.getElementById('wp-list-table--wpu_override_gettext');
    if (!$table) {
        return;
    }
    var $table_lines = $table.querySelectorAll('tr[data-filter-text]'),
        $filter = document.getElementById('wpu_override_gettext__filter_results'),
        _current_filter = '';

    /* ----------------------------------------------------------
      Init
    ---------------------------------------------------------- */

    check_current_hash();

    window.addEventListener("hashchange", function(e) {
        check_current_hash();
    });

    function check_current_hash() {
        var _hash = location.hash.split(':');
        if (!_hash[0] || !_hash[1] || _hash[0] != '#filter') {
            return;
        }
        if (_hash[1] == _current_filter) {
            return;
        }
        _hash[1] = decodeURI(_hash[1]);
        filter_table(_hash[1]);
        $filter.value = _hash[1];
    }

    /* ----------------------------------------------------------
      Filter
    ---------------------------------------------------------- */

    var _timeout_keyup;
    $filter.addEventListener('keyup', function() {
        clearTimeout(_timeout_keyup);
        _timeout_keyup = setTimeout(function(){
            filter_table($filter.value);
        },200);
    }, 1);

    function filter_table(filter_value) {
        if (filter_value == _current_filter) {
            return;
        }
        if (!filter_value) {
            Array.prototype.forEach.call($table_lines, function(el) {
                el.setAttribute('data-visible', '1');
            });
            return;
        }
        filter_value = filter_value.toLowerCase().trim();
        _current_filter = filter_value;
        location.hash = 'filter:' + filter_value;
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
                    el.setAttribute('data-visible', '0');
                    return;
                }
            }
        });

    }
});
