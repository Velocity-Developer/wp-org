<?php

namespace WpOrg\Frontend;

class Assets
{
    public function register()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue()
    {
        wp_enqueue_style(
            'wp-org-frontend',
            WP_ORG_URL . 'assets/frontend/css/public.css',
            [],
            WP_ORG_VERSION
        );

        wp_register_script('wp-org-frontend', false, ['jquery'], WP_ORG_VERSION, true);
        wp_enqueue_script('wp-org-frontend');
        wp_add_inline_script('wp-org-frontend', $this->get_js());
        wp_localize_script('wp-org-frontend', 'WpOrgFrontend', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'labels' => [
                'cityPlaceholder' => 'Pilih kota/kabupaten',
                'districtPlaceholder' => 'Pilih kecamatan',
            ],
        ]);
    }

    private function get_js()
    {
        return <<<'JS'
(function($){
    function initRecaptcha(context) {
        if (typeof window.grecaptcha === 'undefined' || typeof window.grecaptcha.render !== 'function') {
            return;
        }

        $(context || document).find('.wp-org-recaptcha').each(function() {
            var container = this;
            var $container = $(container);
            var siteKey = $container.data('siteKey');
            var form = $container.closest('form');

            if (!siteKey || $container.data('widgetRendered')) {
                return;
            }

            var ensureResponseField = function(token) {
                var responseField = form.find('textarea[name="g-recaptcha-response"]');

                if (!responseField.length) {
                    responseField = $('<textarea>', {
                        name: 'g-recaptcha-response'
                    }).css('display', 'none');
                    form.append(responseField);
                }

                responseField.val(token || '');
            };

            window.grecaptcha.render(container, {
                sitekey: siteKey,
                callback: function(token) {
                    ensureResponseField(token);
                },
                'expired-callback': function() {
                    ensureResponseField('');
                },
                'error-callback': function() {
                    ensureResponseField('');
                }
            });

            $container.data('widgetRendered', true);
        });
    }

    function initPasswordToggles(context) {
        $(context || document).find('.wp-org-password-field').each(function() {
            var wrapper = $(this);
            var input = wrapper.find('input[type="password"], input[type="text"]').first();
            var button = wrapper.find('.wp-org-password-toggle');

            if (!input.length || !button.length || wrapper.data('passwordToggleReady')) {
                return;
            }

            wrapper.data('passwordToggleReady', true);

            button.on('click', function() {
                var isVisible = input.attr('type') === 'text';
                var nextType = isVisible ? 'password' : 'text';
                var nextLabel = isVisible ? button.data('showLabel') : button.data('hideLabel');

                input.attr('type', nextType);
                button.attr('aria-pressed', String(!isVisible));
                button.attr('aria-label', nextLabel);
                wrapper.toggleClass('is-visible', !isVisible);
            });
        });
    }

    function initFrontendModals() {
        $(document).on('click', '.wp-org-open-modal, .wp-org-anggota-card', function() {
            var target = $(this).data('modalTarget');
            if (target) {
                $('#' + target).addClass('is-open').attr('aria-hidden', 'false');
                $('body').addClass('wp-org-modal-open');
            }
        });

        $(document).on('click', '.wp-org-modal-close, .wp-org-modal-close-inline, [data-modal-close]', function() {
            var modal = $(this).closest('.wp-org-modal, .wp-org-member-modal');
            modal.removeClass('is-open').attr('aria-hidden', 'true');
            $('body').removeClass('wp-org-modal-open');
        });

        $(document).on('click', '.wp-org-modal, .wp-org-member-modal', function(event) {
            if ($(event.target).is('.wp-org-modal, .wp-org-member-modal, .wp-org-member-modal-overlay')) {
                $(this).removeClass('is-open').attr('aria-hidden', 'true');
                $('body').removeClass('wp-org-modal-open');
            }
        });

        $(document).on('keydown', function(event) {
            if (event.key === 'Escape') {
                $('.wp-org-modal.is-open, .wp-org-member-modal.is-open').removeClass('is-open').attr('aria-hidden', 'true');
                $('body').removeClass('wp-org-modal-open');
            }
        });
    }

    function loadRegions(type, parent, target, selected) {
        $.get(WpOrgFrontend.ajaxUrl, { action: 'wp_org_regions', type: type, parent: parent }).done(function(response) {
            var items = response && response.success ? response.data : [];
            var placeholder = type === 'cities' ? WpOrgFrontend.labels.cityPlaceholder : WpOrgFrontend.labels.districtPlaceholder;
            var options = ['<option value="">' + placeholder + '</option>'];

            items.forEach(function(item) {
                var isSelected = selected && selected === item.code ? ' selected' : '';
                options.push('<option value="' + item.code + '"' + isSelected + '>' + item.name + '</option>');
            });

            $(target).html(options.join('')).prop('disabled', items.length === 0);
        });
    }

    $(document).on('change', '.wp-org-province', function() {
        var province = $(this).val();
        var wrapper = $(this).closest('form');
        loadRegions('cities', province, wrapper.find('.wp-org-city'), wrapper.find('.wp-org-city').data('selected') || '');
        wrapper.find('.wp-org-district').html('<option value="">' + WpOrgFrontend.labels.districtPlaceholder + '</option>').prop('disabled', true);
    });

    $(document).on('change', '.wp-org-city', function() {
        var city = $(this).val();
        var wrapper = $(this).closest('form');
        loadRegions('districts', city, wrapper.find('.wp-org-district'), '');
    });

    $('.wp-org-region-form').each(function() {
        var form = $(this);
        var province = form.find('.wp-org-province').data('selected') || form.find('.wp-org-province').val();
        var city = form.find('.wp-org-city').data('selected');
        var district = form.find('.wp-org-district').data('selected');

        if (province) {
            loadRegions('cities', province, form.find('.wp-org-city'), city);

            if (city) {
                loadRegions('districts', city, form.find('.wp-org-district'), district);
            }
        }
    });

    initRecaptcha(document);
    $(window).on('load', function() {
        initRecaptcha(document);
    });

    initPasswordToggles(document);
    initFrontendModals();
})(jQuery);
JS;
    }
}
