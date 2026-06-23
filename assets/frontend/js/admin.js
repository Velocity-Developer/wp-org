jQuery(function($) {
    function getRegionConfig() {
        return window.WpOrgAdmin || {
            ajaxUrl: '',
            labels: {
                cityPlaceholder: 'Pilih kota/kabupaten',
                districtPlaceholder: 'Pilih kecamatan'
            }
        };
    }

    function loadRegions(type, parent, target, selected) {
        var config = getRegionConfig();

        if (!config.ajaxUrl) {
            return;
        }

        $.get(config.ajaxUrl, { action: 'wp_org_regions', type: type, parent: parent }).done(function(response) {
            var items = response && response.success ? response.data : [];
            var placeholder = type === 'cities' ? config.labels.cityPlaceholder : config.labels.districtPlaceholder;
            var options = ['<option value="">' + placeholder + '</option>'];

            items.forEach(function(item) {
                var isSelected = selected && selected === item.code ? ' selected' : '';
                options.push('<option value="' + item.code + '"' + isSelected + '>' + item.name + '</option>');
            });

            $(target).html(options.join('')).prop('disabled', items.length === 0);
        });
    }

    function slugifyFieldKey(label) {
        return (label || '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '')
            .replace(/_+/g, '_');
    }

    function fieldTypeNeedsOptions(type) {
        return ['select', 'radio', 'checkbox'].indexOf(type) !== -1;
    }

    function syncFieldKey($row) {
        var $labelInput = $row.find('.wp-org-field-label');
        var $keyInput = $row.find('.wp-org-field-key');
        var $keyPreview = $row.find('.wp-org-field-key-preview');
        var key = $row.attr('data-key-locked') === '1'
            ? $keyInput.val()
            : slugifyFieldKey($labelInput.val());

        $keyInput.val(key);
        $keyPreview.text(key ? 'ID: ' + key : 'ID akan dibuat otomatis dari label');
    }

    $(document).on('click', '.wp-org-edit-key', function() {
        var $row = $(this).closest('tr');
        var $keyInput = $row.find('.wp-org-field-key');
        var $keyPreview = $row.find('.wp-org-field-key-preview');
        
        // Toggle visibility
        $keyInput.toggle();
        $keyPreview.toggle();
        
        // Also update the sync behavior
        if ($keyInput.is(':visible')) {
            $row.attr('data-key-locked', '1'); // Allow manual editing
        } else {
            $row.attr('data-key-locked', '0'); // Back to auto-generate
            syncFieldKey($row); // Update from label
        }
    });

    function syncOptionState($row) {
        var type = $row.find('.wp-org-field-type').val();
        var $optionsCell = $row.find('.wp-org-field-options-cell');
        var shouldShow = fieldTypeNeedsOptions(type);
        $optionsCell.toggleClass('is-hidden', !shouldShow);
    }

    function syncRowState($row) {
        var isEnabled = $row.find('.wp-org-field-enabled').is(':checked');
        $row.toggleClass('wp-org-field-row-disabled', !isEnabled);
        syncFieldKey($row);
        syncOptionState($row);
    }

    function reindexFieldRows() {
        $('.wp-org-fields-table tbody tr').each(function(index) {
            $(this).find('[name]').each(function() {
                var name = $(this).attr('name');

                if (!name || name.indexOf('fields[') !== 0) {
                    return;
                }

                $(this).attr('name', name.replace(/^fields\[[^\]]+\]/, 'fields[' + index + ']'));
            });
        });

        $('.wp-org-fields-table tbody').attr('data-next-index', $('.wp-org-fields-table tbody tr').length);
    }

    function syncSortableState() {
        var $tbody = $('.wp-org-fields-table tbody');

        if (!$tbody.length || typeof $.fn.sortable !== 'function') {
            return;
        }

        if ($tbody.hasClass('ui-sortable')) {
            $tbody.sortable('refresh');
            return;
        }

        $tbody.sortable({
            axis: 'y',
            handle: '.wp-org-field-drag-handle',
            items: '> tr',
            cancel: 'input, textarea, select, option, a, button:not(.wp-org-field-drag-handle), label',
            tolerance: 'pointer',
            distance: 4,
            helper: function(event, tr) {
                var $originals = tr.children();
                var $helper = tr.clone();
                $helper.children().each(function(index) {
                    $(this).width($originals.eq(index).outerWidth());
                });
                return $helper;
            },
            placeholder: 'wp-org-sortable-placeholder',
            start: function(event, ui) {
                ui.placeholder
                    .html('<td colspan="7">&nbsp;</td>')
                    .height(ui.helper.outerHeight());

                ui.placeholder.children().css('height', ui.helper.outerHeight());
            },
            stop: function() {
                $tbody.find('.wp-org-sortable-placeholder').remove();
                $tbody.children('tr').removeAttr('style');
            },
            beforeStop: function() {
                $tbody.find('.wp-org-sortable-placeholder').children().attr('colspan', 7);
            },
            update: function() {
                $tbody.find('.wp-org-sortable-placeholder').remove();
                reindexFieldRows();
            }
        });
    }

    $(document).on('mousedown', '.wp-org-field-drag-handle', function(event) {
        event.preventDefault();
    });

    $(document).on('click', '#wp-org-add-field', function() {
        var $tbody = $('.wp-org-fields-table tbody');
        var nextIndex = parseInt($tbody.attr('data-next-index'), 10) || 0;
        var template = $('#tmpl-wp-org-field-row').html().replace(/__index__/g, nextIndex);
        $tbody.append(template);
        reindexFieldRows();
        syncRowState($tbody.children('tr').last());
        syncSortableState();
    });

    $(document).on('click', '.wp-org-remove-field', function() {
        var $row = $(this).closest('tr');
        $row.find('.wp-org-field-delete').val('1');
        $row.remove();
        reindexFieldRows();
        syncSortableState();
    });

    $(document).on('change', '.wp-org-field-enabled', function() {
        syncRowState($(this).closest('tr'));
    });

    $(document).on('change', '.wp-org-field-type', function() {
        syncOptionState($(this).closest('tr'));
    });

    $(document).on('change', '.wp-org-province', function() {
        var province = $(this).val();
        var wrapper = $(this).closest('form');
        var $city = wrapper.find('.wp-org-city');
        var $district = wrapper.find('.wp-org-district');

        loadRegions('cities', province, $city, $city.data('selected') || '');
        $district.html('<option value="">' + getRegionConfig().labels.districtPlaceholder + '</option>').prop('disabled', true);
    });

    $(document).on('change', '.wp-org-city', function() {
        var city = $(this).val();
        var wrapper = $(this).closest('form');
        var $district = wrapper.find('.wp-org-district');

        loadRegions('districts', city, $district, $district.data('selected') || '');
    });

    $(document).on('input', '.wp-org-field-label', function() {
        syncFieldKey($(this).closest('tr'));
    });

    $('.wp-org-fields-table tbody tr').each(function() {
        syncRowState($(this));
    });

    $('.wp-org-region-form').each(function() {
        var $form = $(this);
        var $province = $form.find('.wp-org-province');
        var $city = $form.find('.wp-org-city');
        var $district = $form.find('.wp-org-district');
        var province = $province.data('selected') || $province.val();
        var city = $city.data('selected');
        var district = $district.data('selected');

        if (province) {
            loadRegions('cities', province, $city, city);

            if (city) {
                loadRegions('districts', city, $district, district);
            }
        }
    });

    reindexFieldRows();
    syncSortableState();

    // Initialize color pickers with alpha support
    $('.color-picker').wpColorPicker({
        alpha: true
    });

    $(document).on('click', '#wp-org-add-bank', function() {
        var $tbody = $('.wp-org-bank-table tbody');
        var nextIndex = parseInt($tbody.attr('data-next-index'), 10) || 0;
        var template = $('#tmpl-wp-org-bank-row').html().replace(/__index__/g, nextIndex);
        $tbody.append(template);
        $tbody.attr('data-next-index', nextIndex + 1);
    });

    $(document).on('click', '.wp-org-remove-bank', function() {
        var $row = $(this).closest('tr');
        $row.find('.wp-org-bank-delete').val('1');
        $row.remove();
    });

    $(document).on('click', '.wp-org-admin-open-modal', function() {
        var target = $(this).data('modal-target');
        $('#' + target).addClass('is-open').attr('aria-hidden', 'false');
        $('body').addClass('wp-org-modal-open');
    });

    $(document).on('click', '.wp-org-admin-modal-close', function() {
        $(this).closest('.wp-org-admin-modal').removeClass('is-open').attr('aria-hidden', 'true');
        $('body').removeClass('wp-org-modal-open');
    });

    $(document).on('click', '.wp-org-admin-modal', function(event) {
        if ($(event.target).is('.wp-org-admin-modal')) {
            $(this).removeClass('is-open').attr('aria-hidden', 'true');
            $('body').removeClass('wp-org-modal-open');
        }
    });

    $(document).on('keydown', function(event) {
        if (event.key === 'Escape') {
            $('.wp-org-admin-modal.is-open').removeClass('is-open').attr('aria-hidden', 'true');
            $('body').removeClass('wp-org-modal-open');
        }
    });
});
