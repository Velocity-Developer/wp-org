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
        $status = isset($_GET['member_status']) ? sanitize_key(wp_unslash($_GET['member_status'])) : 'approved';
        $city_code = isset($_GET['member_city']) ? sanitize_text_field(wp_unslash($_GET['member_city'])) : '';
        $cities = $this->get_available_cities();
        $meta_query = [
            [
                'key' => 'wp_org_status',
                'value' => $status,
            ],
        ];

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
        $statuses = MemberData::get_all_statuses();

        ob_start();
        echo '<div class="wp-org-card"><h2>Daftar Anggota</h2>';
        echo '<form class="wp-org-grid wp-org-grid-2" method="get">';
        echo '<div class="wp-org-field"><label for="member_search">Cari anggota</label><input id="member_search" type="text" name="member_search" value="' . esc_attr($search) . '"></div>';
        echo '<div class="wp-org-field"><label for="member_status">Status</label><select id="member_status" name="member_status">';
        foreach ($statuses as $key => $label) {
            echo '<option value="' . esc_attr($key) . '"' . selected($status, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></div>';
        echo '<div class="wp-org-field"><label for="member_city">Kota/Kabupaten</label><select id="member_city" name="member_city"><option value="">Semua kota/kabupaten</option>';
        foreach ($cities as $code => $label) {
            echo '<option value="' . esc_attr($code) . '"' . selected($city_code, $code, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></div><div class="wp-org-actions"><button class="wp-org-button" type="submit">Filter</button></div></form>';
        echo '<table class="wp-org-table"><thead><tr><th>Nama</th><th>Email</th><th>Status</th><th>Wilayah</th></tr></thead><tbody>';

        if (!$users) {
            echo '<tr><td colspan="4">Belum ada data anggota.</td></tr>';
        } else {
            foreach ($users as $user) {
                $member_status = MemberData::get_status($user->ID);
                $region = trim(get_user_meta($user->ID, 'wp_org_city_name', true) . ', ' . get_user_meta($user->ID, 'wp_org_province_name', true), ', ');
                $premium_status = MemberData::get_premium_status($user->ID);
                $premium_label = MemberData::get_premium_statuses()[$premium_status] ?? $premium_status;
                echo '<tr><td>' . esc_html($user->display_name) . '<br><small>' . esc_html($premium_label) . '</small></td><td>' . esc_html($user->user_email) . '</td><td><span class="wp-org-status wp-org-status-' . esc_attr($member_status) . '">' . esc_html($statuses[$member_status] ?? $member_status) . '</span></td><td>' . esc_html($region ?: '-') . '</td></tr>';
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
