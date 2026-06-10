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
        wp_register_style('wp-org-frontend', false, [], WP_ORG_VERSION);
        wp_enqueue_style('wp-org-frontend');
        wp_add_inline_style('wp-org-frontend', $this->get_css());

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

    private function get_css()
    {
        return '.wp-org-card{margin:0px auto;padding:24px;border-radius:16px;background:#fff;box-shadow:0 10px 30px rgba(0,0,0,.04)}.wp-org-grid{display:grid;gap:16px}.wp-org-grid-2{grid-template-columns:repeat(auto-fit,minmax(220px,1fr))}.wp-org-field label{display:block;font-weight:600;margin-bottom:6px}.wp-org-field input,.wp-org-field select,.wp-org-field textarea{line-height:1;width:100%;padding:10px 12px;border:1px solid #8c8f94;border-radius:10px}.wp-org-actions{display:flex;gap:12px;align-items:center;flex-wrap:wrap;}.wp-org-button{display:inline-block;padding:8px 18px;border:0;border-radius:10px;background:#135e96;color:#fff;font-weight:600;cursor:pointer;text-decoration:none}.wp-org-muted{color:#646970}.wp-org-notice{padding:12px 14px;border-radius:10px;margin-bottom:16px}.wp-org-notice-error{background:#fbeaea;color:#8a2424}.wp-org-notice-success{background:#ebf7ed;color:#166534}.wp-org-table{width:100%;border-collapse:collapse}.wp-org-table th,.wp-org-table td{padding:12px;border-bottom:1px solid #e2e4e7;text-align:left}.wp-org-status{display:inline-block;padding:4px 10px;border-radius:999px;background:#f0f0f1}.wp-org-status-approved{background:#dcfce7;color:#166534}.wp-org-status-rejected{background:#fee2e2;color:#991b1b}.wp-org-status-pending{background:#fef3c7;color:#92400e}.wp-org-verified-badge{display:inline-flex;align-items:center;gap:4px;margin-top:4px;padding:3px 8px;border-radius:999px;background:#dcfce7;color:#166534;font-size:12px;font-weight:700;line-height:1.2}.wp-org-tabs{display:flex;gap:10px;flex-wrap:wrap;margin:0 0 18px}.wp-org-tab{display:inline-block;padding:10px 14px;border:1px solid #dcdcde;border-radius:999px;background:#f6f7f7;color:#1d2327;text-decoration:none}.wp-org-tab-active{background:#135e96;border-color:#135e96;color:#fff}.wp-org-proof-preview img{max-width:240px;height:auto;border-radius:12px;border:1px solid #dcdcde}.wp-org-member-card{margin-top:18px}.wp-org-member-card-preview{position:relative;overflow:hidden;border-radius:24px;border:1px solid #d7e3ee;box-shadow:0 18px 40px rgba(19,94,150,.14);background:linear-gradient(135deg,#0f3d5e,#135e96);color:#fff;min-height:420px}.wp-org-member-card-bg{position:absolute;inset:0;background-size:cover;background-position:center;opacity:.24}.wp-org-member-card-inner{position:relative;display:flex;gap:20px;align-items:flex-start;padding:28px}.wp-org-member-card-logo{width:120px;min-width:120px;height:120px;object-fit:contain;background:rgba(255,255,255,.16);border-radius:18px;padding:12px;backdrop-filter:blur(8px)}.wp-org-member-card-copy{flex:1}.wp-org-member-card-title{margin:0 0 6px;font-size:30px;letter-spacing:2px;text-transform:uppercase}.wp-org-member-card-org{margin:0 0 16px;font-size:18px;letter-spacing:3px;opacity:.9}.wp-org-member-card-grid{display:grid;grid-template-columns:1fr 220px;gap:16px;align-items:end;margin-top:22px}.wp-org-member-card-number{font-size:32px;font-weight:700;margin:6px 0 0}.wp-org-member-card-name{font-size:44px;font-weight:700;line-height:1.1;margin:6px 0 0}.wp-org-member-card-region{font-size:28px;margin:6px 0 0}.wp-org-member-card-meta{background:rgba(255,255,255,.16);border-radius:18px;padding:18px}.wp-org-member-card-meta-label{font-size:14px;opacity:.8;text-transform:uppercase;letter-spacing:1px}.wp-org-member-card-meta-value{font-size:24px;font-weight:700;margin-top:8px}.wp-org-member-card-actions{margin-top:16px;display:flex;gap:12px;flex-wrap:wrap}@media print{.wp-org-card{box-shadow:none;border:0;margin:0;max-width:none;padding:0}.wp-org-member-card-actions,.wp-org-tabs,.wp-org-notice,.wp-org-proof-preview,.wp-org-actions,.wp-org-card>h2{display:none !important}.wp-org-member-card-preview{min-height:0;box-shadow:none;border:0;border-radius:0}}';
    }

    private function get_js()
    {
        return <<<'JS'
(function($){
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
})(jQuery);
JS;
    }
}
