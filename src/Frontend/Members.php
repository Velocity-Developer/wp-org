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

        if (!$is_public && !is_user_logged_in()) {
            return '<div class="wp-org-card"><p>Daftar anggota hanya tersedia untuk pengguna yang login.</p></div>';
        }

        $search = isset($_GET['member_search']) ? sanitize_text_field(wp_unslash($_GET['member_search'])) : '';
        $city_code = isset($_GET['member_city']) ? sanitize_text_field(wp_unslash($_GET['member_city'])) : '';
        $cities = $this->get_available_cities();
        $meta_query = [];

        if ($city_code !== '') {
            $meta_query[] = [
                'key' => 'wp_org_city_code',
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
        echo '<div class="wp-org-field"><select id="member_city" name="member_city" aria-label="Kota/Kabupaten"><option value="">Pilih kota/kabupaten</option>';
        foreach ($cities as $code => $label) {
            echo '<option value="' . esc_attr($code) . '"' . selected($city_code, $code, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></div><div class="wp-org-actions"><button class="wp-org-button" type="submit">Filter</button></div></form>';
        echo '<table class="wp-org-table"><thead><tr><th>Nama</th><th>Email</th><th>Wilayah</th></tr></thead><tbody>';

        if (!$users) {
            echo '<tr><td colspan="3">Belum ada data anggota.</td></tr>';
        } else {
            foreach ($users as $user) {
                $region = trim(get_user_meta($user->ID, 'wp_org_city_name', true) . ', ' . get_user_meta($user->ID, 'wp_org_province_name', true), ', ');
                $premium_status = MemberData::get_premium_status($user->ID);
                $verified_badge = '';

                if ($premium_status === 'active') {
                    $verified_badge = '<small class="wp-org-verified-badge" aria-label="Verified">&#10003;</small>';
                }

                echo '<tr><td>' . esc_html($user->display_name) . $verified_badge . '</td><td>' . esc_html($user->user_email) . '</td><td>' . esc_html($region ?: '-') . '</td></tr>';
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
        $users = get_users([
            'role__in' => ['org_member', 'org_admin'],
            'number' => 500,
            'fields' => 'ID',
        ]);

        $cities = [];

        foreach ($users as $user_id) {
            $code = (string) get_user_meta($user_id, 'wp_org_city_code', true);
            $name = (string) get_user_meta($user_id, 'wp_org_city_name', true);

            if ($code !== '' && $name !== '') {
                $cities[$code] = $name;
            }
        }

        asort($cities);

        return $cities;
    }
}
