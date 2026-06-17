<?php

namespace WpOrg\Frontend;

use WpOrg\Support\MemberData;

class Members
{
    public function register()
    {
        add_shortcode('org_members', [$this, 'render_shortcode']);
    }

    public function render_shortcode()
    {
        $settings = get_option('wp_org_general_settings', []);
        $is_public = !empty($settings['members_page_public']);
        $premium_enabled = MemberData::is_premium_enabled();
        $city_field = MemberData::get_first_field_by_type('region_city');
        $city_field_key = $city_field['key'] ?? 'city_code';
        $city_field_label = $city_field['label'] ?? 'Kota/Kabupaten';

        if (!$is_public && !is_user_logged_in()) {
            return '<div class="wp-org-card"><p>Daftar anggota hanya tersedia untuk pengguna yang login.</p></div>';
        }

        $search = isset($_GET['member_search']) ? sanitize_text_field(wp_unslash($_GET['member_search'])) : '';
        $city_code = isset($_GET['member_city']) ? sanitize_text_field(wp_unslash($_GET['member_city'])) : '';
        $cities = $this->get_available_cities();
        $meta_query = [];

        if ($city_code !== '') {
            $meta_query[] = [
                'key' => 'wp_org_' . $city_field_key,
                'value' => $city_code,
            ];
        }

        $args = [
            'role__in' => ['org_member', 'org_admin'],
            'number' => 50,
            'search' => $search ? '*' . $search . '*' : '',
            'search_columns' => ['user_login', 'user_email', 'display_name'],
            'meta_query' => $meta_query,
        ];

        $users = get_users($args);

        ob_start();
        echo '<form class="wp-org-grid wp-org-grid-2" method="get">';
        echo '<div class="wp-org-field"><input id="member_search" type="text" name="member_search" value="' . esc_attr($search) . '" placeholder="Cari anggota"></div>';
        echo '<div class="wp-org-field"><select id="member_city" name="member_city" aria-label="' . esc_attr($city_field_label) . '"><option value="">Pilih ' . esc_html(strtolower($city_field_label)) . '</option>';
        foreach ($cities as $code => $label) {
            echo '<option value="' . esc_attr($code) . '"' . selected($city_code, $code, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></div><div class="wp-org-actions"><button class="wp-org-button" type="submit">Filter</button></div></form>';
        echo '<table class="wp-org-table"><thead><tr><th>Nama</th><th>Email</th><th>Wilayah</th></tr></thead><tbody>';

        if (!$users) {
            echo '<tr><td colspan="3">Belum ada data anggota.</td></tr>';
        } else {
            foreach ($users as $user) {
                $region = MemberData::get_user_region_summary($user->ID);
                $premium_status = MemberData::get_premium_status($user->ID);
                $verified_badge = '';

                if ($premium_enabled && $premium_status === 'active') {
                    $verified_badge = '<small class="wp-org-verified-badge" aria-label="Verified">&#10003;</small>';
                }

                echo '<tr><td>' . esc_html($user->display_name) . $verified_badge . '</td><td>' . esc_html($this->mask_email($user->user_email)) . '</td><td>' . esc_html($region ?: '-') . '</td></tr>';
            }
        }

        echo '</tbody></table></div>';

        return (string) ob_get_clean();
    }

    /**
     * @return array<string, string>
     */
    private function get_available_cities()
    {
        $regions = new \WpOrg\Support\Regions();
        $users = get_users([
            'role__in' => ['org_member', 'org_admin'],
            'number' => 500,
            'fields' => 'ID',
        ]);

        $cities = [];

        foreach ($users as $user_id) {
            $name = '';
            $code = '';

            foreach (MemberData::get_all_registration_fields() as $field) {
                if ($field['type'] !== 'region_city') {
                    continue;
                }

                $code = (string) get_user_meta($user_id, 'wp_org_' . $field['key'], true);
                $name = (string) get_user_meta($user_id, 'wp_org_' . MemberData::get_region_name_key($field['key']), true);
                break;
            }

            if ($code === '') {
                $code = (string) get_user_meta($user_id, 'wp_org_city_code', true);
            }

            if ($name === '') {
                $name = (string) get_user_meta($user_id, 'wp_org_city_name', true);
            }

            if ($name === '' && $code !== '') {
                $name = $regions->get_city_name($code);
            }

            if ($code !== '' && $name !== '') {
                $cities[$code] = $name;
            }
        }

        asort($cities);

        return $cities;
    }

    private function mask_email($email)
    {
        $email = sanitize_email((string) $email);

        if ($email === '' || strpos($email, '@') === false) {
            return '***';
        }

        [$local, $domain] = explode('@', $email, 2);
        $local_length = strlen($local);

        if ($local_length <= 2) {
            $masked_local = substr($local, 0, 1) . '***';
        } else {
            $masked_local = substr($local, 0, 2) . str_repeat('*', max(3, $local_length - 2));
        }

        return $masked_local . '@' . $domain;
    }
}
