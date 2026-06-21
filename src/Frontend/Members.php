<?php

namespace WpOrg\Frontend;

use WpOrg\Support\MemberData;

class Members
{
    public function register()
    {
        add_shortcode('org_members', [$this, 'render_shortcode']);
        add_shortcode('total-anggota', [$this, 'render_total_anggota_shortcode']);
        add_shortcode('daftar-anggota', [$this, 'render_daftar_anggota_shortcode']);
        add_shortcode('statistik-anggota', [$this, 'render_statistik_anggota_shortcode']);
    }

    public function render_statistik_anggota_shortcode($atts = [])
    {
        $settings = get_option('wp_org_general_settings', []);
        $is_public = !empty($settings['members_page_public']);

        // if (!$is_public && !is_user_logged_in()) {
        //     return '<div class="wp-org-card"><p>Statistik anggota hanya tersedia untuk pengguna yang login.</p></div>';
        // }

        $defaults = [
            'type' => 'provinsi',
        ];
        $atts = shortcode_atts($defaults, $atts, 'statistik-anggota');
        $type = sanitize_key($atts['type']);
        if (!in_array($type, ['provinsi', 'kabupaten', 'kecamatan'], true)) {
            $type = 'provinsi';
        }

        $args = [
            'role__in' => ['org_member', 'org_admin'],
            'number' => -1,
            'meta_query' => [
                [
                    'key' => 'wp_org_status',
                    'value' => 'approved',
                    'compare' => '=',
                ],
            ],
        ];

        $users = get_users($args);
        $counts = [];

        foreach ($users as $user) {
            $name = '';
            switch ($type) {
                case 'kabupaten':
                    $name = (string) get_user_meta($user->ID, 'wp_org_city_name', true);
                    break;
                case 'kecamatan':
                    $name = (string) get_user_meta($user->ID, 'wp_org_district_name', true);
                    break;
                case 'provinsi':
                default:
                    $name = (string) get_user_meta($user->ID, 'wp_org_province_name', true);
                    break;
            }

            if ($name === '') {
                $name = 'Tidak Diketahui';
            }

            if (!isset($counts[$name])) {
                $counts[$name] = 0;
            }
            $counts[$name]++;
        }

        arsort($counts);

        ob_start();
        echo '<div class="wp-org-statistik-anggota">';
        echo '<div class="wp-org-provinsi-list">';

        if (empty($counts)) {
            echo '<div class="wp-org-card"><p>Belum ada data anggota.</p></div>';
        } else {
            foreach ($counts as $name => $count) {
                echo '<div class="wp-org-provinsi-item">';
                echo '<div class="wp-org-provinsi-info">';
                echo '<span class="wp-org-provinsi-nama">' . esc_html($name) . '</span>';
                echo '<span class="wp-org-provinsi-jumlah">' . esc_html($count) . ' anggota</span>';
                echo '</div>';
                echo '<div class="wp-org-provinsi-bar">';
                $max_count = max($counts);
                $percentage = ($count / $max_count) * 100;
                echo '<div class="wp-org-provinsi-bar-fill" style="width: ' . esc_attr((string) $percentage) . '%;"></div>';
                echo '</div>';
                echo '</div>';
            }
        }

        echo '</div>';
        echo '</div>';

        return (string) ob_get_clean();
    }

    public function render_daftar_anggota_shortcode($atts = [])
    {
        $settings = get_option('wp_org_general_settings', []);
        $is_public = !empty($settings['members_page_public']);
        $premium_enabled = MemberData::is_premium_enabled();
        
        // Process shortcode attributes
        $atts = shortcode_atts([
            'filter' => 'prov', // Options: 'prov', 'kab/kota', 'kecamatan'
        ], $atts, 'daftar-anggota');
        
        $filter_type = sanitize_key($atts['filter']);
        
        // Determine region field and labels based on filter type
        $region_field_type = '';
        $region_label = '';
        $region_meta_code_key = '';
        $region_meta_name_key = '';
        $card_region_name = '';
        
        switch ($filter_type) {
            case 'kab/kota':
            case 'kab':
            case 'kota':
                $region_field_type = 'region_city';
                $region_label = 'Kota/Kabupaten';
                $city_field = MemberData::get_first_field_by_type('region_city');
                $region_meta_code_key = $city_field['key'] ?? 'city_code';
                $region_meta_name_key = 'wp_org_city_name';
                break;
            case 'kecamatan':
            case 'kec':
                $region_field_type = 'region_district';
                $region_label = 'Kecamatan';
                $district_field = MemberData::get_first_field_by_type('region_district');
                $region_meta_code_key = $district_field['key'] ?? 'district_code';
                $region_meta_name_key = 'wp_org_district_name';
                break;
            case 'prov':
            case 'provinsi':
            default:
                $region_field_type = 'region_province';
                $region_label = 'Provinsi';
                $province_field = MemberData::get_first_field_by_type('region_province');
                $region_meta_code_key = $province_field['key'] ?? 'province_code';
                $region_meta_name_key = 'wp_org_province_name';
                $filter_type = 'prov';
                break;
        }

        // if (!$is_public && !is_user_logged_in()) {
        //     return '<div class="wp-org-card"><p>Daftar anggota hanya tersedia untuk pengguna yang login.</p></div>';
        // }

        $search = isset($_GET['member_search']) ? sanitize_text_field(wp_unslash($_GET['member_search'])) : '';
        $region_code = isset($_GET['member_region']) ? sanitize_text_field(wp_unslash($_GET['member_region'])) : '';
        $paged = max(1, get_query_var('paged', 1));
        $per_page = 12;
        $regions = new \WpOrg\Support\Regions();
        $available_regions = $this->get_available_regions($region_field_type);
        $meta_query = [
            [
                'key' => 'wp_org_status',
                'value' => 'approved',
                'compare' => '=',
            ],
        ];

        if ($region_code !== '') {
            $meta_query[] = [
                'key' => 'wp_org_' . $region_meta_code_key,
                'value' => $region_code,
            ];
        }

        // Get total count first
        $count_args = [
            'role__in' => ['org_member', 'org_admin'],
            'count_total' => true,
            'fields' => 'ID',
            'search' => $search ? '*' . $search . '*' : '',
            'search_columns' => ['user_login', 'user_email', 'display_name'],
            'meta_query' => $meta_query,
        ];
        $total_users = get_users($count_args);
        $total = is_array($total_users) ? count($total_users) : 0;

        // Get users for current page
        $args = [
            'role__in' => ['org_member', 'org_admin'],
            'number' => $per_page,
            'paged' => $paged,
            'search' => $search ? '*' . $search . '*' : '',
            'search_columns' => ['user_login', 'user_email', 'display_name'],
            'meta_query' => $meta_query,
        ];
        $users = get_users($args);

        ob_start();
        echo '<form class="wp-org-grid wp-org-grid-2" method="get">';
        
        // Preserve existing query parameters
        foreach ($_GET as $key => $value) {
            if (!in_array($key, ['member_search', 'member_region', 'paged'], true)) {
                if (is_array($value)) {
                    foreach ($value as $v) {
                        echo '<input type="hidden" name="' . esc_attr($key) . '[]" value="' . esc_attr($v) . '">';
                    }
                } else {
                    echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
                }
            }
        }
        
        echo '<div class="wp-org-field"><input id="member_search" type="text" name="member_search" value="' . esc_attr($search) . '" placeholder="Cari anggota"></div>';
        echo '<div class="wp-org-field"><select id="member_region" name="member_region" aria-label="' . esc_attr($region_label) . '"><option value="">Pilih ' . esc_html(strtolower($region_label)) . '</option>';
        foreach ($available_regions as $code => $label) {
            echo '<option value="' . esc_attr($code) . '"' . selected($region_code, $code, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></div><div class="wp-org-actions"><button class="wp-org-button" type="submit">Filter</button></div></form>';
        
        echo '<div class="wp-org-daftar-anggota-grid">';

        if (!$users) {
            echo '<div class="wp-org-card"><p>Belum ada data anggota.</p></div>';
        } else {
            foreach ($users as $user) {
                $member_number = MemberData::get_member_number($user->ID);
                $premium_status = MemberData::get_premium_status($user->ID);
                $province_name = (string) get_user_meta($user->ID, 'wp_org_province_name', true);
                $city_name = (string) get_user_meta($user->ID, 'wp_org_city_name', true);
                $district_name = (string) get_user_meta($user->ID, 'wp_org_district_name', true);
                $full_name = (string) get_user_meta($user->ID, 'wp_org_full_name', true);
                $member_photo = (string) get_user_meta($user->ID, 'wp_org_member_photo', true);
                if ($full_name === '') {
                    $full_name = $user->display_name;
                }
                $avatar = get_avatar_url($user->ID, ['size' => 100]);
                $modal_id = 'wp-org-modal-' . $user->ID;
                
                // Determine which region to display on card
                $card_region = '';
                switch ($filter_type) {
                    case 'kab/kota':
                    case 'kab':
                    case 'kota':
                        $card_region = $city_name;
                        break;
                    case 'kecamatan':
                    case 'kec':
                        $card_region = $district_name;
                        break;
                    case 'prov':
                    case 'provinsi':
                    default:
                        $card_region = $province_name;
                        break;
                }

                $premium_class = $premium_enabled && $premium_status === 'active' ? 'wp-org-anggota-premium' : '';
                $premium_badge = '';

                if ($premium_enabled && $premium_status === 'active') {
                    $premium_badge = '<small class="wp-org-premium-badge">Premium</small>';
                }

                $name_parts = explode(' ', trim($full_name));
                $display_name = $name_parts[0];
                if (count($name_parts) > 1) {
                    $display_name .= ' ' . substr($name_parts[count($name_parts) - 1], 0, 1);
                }

                echo '<div class="wp-org-anggota-card ' . esc_attr($premium_class) . '" data-modal-target="' . esc_attr($modal_id) . '">';
                echo '<div class="wp-org-anggota-avatar-wrapper">';
                echo $premium_badge;
                echo '<div class="wp-org-anggota-avatar">';
                if ($member_photo) {
                    echo '<img src="' . esc_url($member_photo) . '" alt="' . esc_attr($full_name) . '">';
                } elseif ($avatar) {
                    echo '<img src="' . esc_url($avatar) . '" alt="' . esc_attr($full_name) . '">';
                } else {
                    echo '<div class="wp-org-anggota-avatar-placeholder">' . esc_html(substr($full_name, 0, 1)) . '</div>';
                }
                echo '</div>';
                echo '</div>';
                echo '<div class="wp-org-anggota-info">';
                echo '<h4 class="wp-org-anggota-name">' . esc_html($display_name) . '</h4>';
                echo '<p class="wp-org-anggota-number">' . esc_html($member_number) . '</p>';
                echo '<p class="wp-org-anggota-province">' . esc_html($card_region ?: '-') . '</p>';
                echo '</div>';
                echo '</div>';

                echo '<div id="' . esc_attr($modal_id) . '" class="wp-org-member-modal">';
                echo '<div class="wp-org-member-modal-overlay" data-modal-close="' . esc_attr($modal_id) . '"></div>';
                echo '<div class="wp-org-member-modal-content">';
                echo '<button class="wp-org-member-modal-close" data-modal-close="' . esc_attr($modal_id) . '" aria-label="Tutup">&times;</button>';
                echo '<div class="wp-org-member-modal-avatar">';
                if ($member_photo) {
                    echo '<img src="' . esc_url($member_photo) . '" alt="' . esc_attr($full_name) . '">';
                } elseif ($avatar) {
                    echo '<img src="' . esc_url($avatar) . '" alt="' . esc_attr($full_name) . '">';
                } else {
                    echo '<div class="wp-org-member-modal-avatar-placeholder">' . esc_html(substr($full_name, 0, 1)) . '</div>';
                }
                echo '</div>';
                echo '<div class="wp-org-member-modal-info">';
                echo '<h3 class="wp-org-member-modal-name">' . esc_html($full_name) . '</h3>';
                echo '<p class="wp-org-member-modal-number">' . esc_html($member_number) . '</p>';
                echo '<div class="wp-org-member-modal-address">';
                if ($district_name) {
                    echo '<p><strong>Kecamatan:</strong> ' . esc_html($district_name) . '</p>';
                }
                if ($city_name) {
                    echo '<p><strong>Kabupaten/Kota:</strong> ' . esc_html($city_name) . '</p>';
                }
                if ($province_name) {
                    echo '<p><strong>Provinsi:</strong> ' . esc_html($province_name) . '</p>';
                }
                echo '</div>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
            }
        }

        echo '</div>';

        // Add pagination
        if ($total > $per_page) {
            $big = 999999999;
            $pagination_args = [
                'base' => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
                'format' => '?paged=%#%',
                'current' => $paged,
                'total' => ceil($total / $per_page),
                'prev_text' => '&larr;',
                'next_text' => '&rarr;',
                'add_args' => [],
            ];
            
            // Preserve filter parameters
            if ($search !== '') {
                $pagination_args['add_args']['member_search'] = $search;
            }
            if ($region_code !== '') {
                $pagination_args['add_args']['member_region'] = $region_code;
            }
            
            echo '<div class="wp-org-pagination">';
            echo paginate_links($pagination_args);
            echo '</div>';
        }

        return (string) ob_get_clean();
    }

    public function render_total_anggota_shortcode($atts)
    {
        $atts = shortcode_atts([
            'prefix' => '',
            'suffix' => '',
            'icon' => '',
        ], $atts);

        $users = get_users([
            'role__in' => ['org_member', 'org_admin'],
            'fields' => 'ID',
        ]);

        $total = count($users);
        $html = '';

        if (!empty($atts['icon'])) {
            $html .= '<i class="fa ' . esc_attr($atts['icon']) . '"></i> ';
        }

        if (!empty($atts['prefix'])) {
            $html .= esc_html($atts['prefix']);
        }

        $html .= number_format($total);

        if (!empty($atts['suffix'])) {
            $html .= ' ' . esc_html($atts['suffix']);
        }

        return $html;
    }

    public function render_shortcode()
    {
        $settings = get_option('wp_org_general_settings', []);
        $is_public = !empty($settings['members_page_public']);
        $premium_enabled = MemberData::is_premium_enabled();
        $city_field = MemberData::get_first_field_by_type('region_city');
        $city_field_key = $city_field['key'] ?? 'city_code';
        $city_field_label = $city_field['label'] ?? 'Kota/Kabupaten';

        // if (!$is_public && !is_user_logged_in()) {
        //     return '<div class="wp-org-card"><p>Daftar anggota hanya tersedia untuk pengguna yang login.</p></div>';
        // }

        $search = isset($_GET['member_search']) ? sanitize_text_field(wp_unslash($_GET['member_search'])) : '';
        $city_code = isset($_GET['member_city']) ? sanitize_text_field(wp_unslash($_GET['member_city'])) : '';
        $paged = max(1, get_query_var('paged', 1));
        $per_page = 50;
        $cities = $this->get_available_cities();
        $meta_query = [];

        if ($city_code !== '') {
            $meta_query[] = [
                'key' => 'wp_org_' . $city_field_key,
                'value' => $city_code,
            ];
        }

        // Get total count first
        $count_args = [
            'role__in' => ['org_member', 'org_admin'],
            'count_total' => true,
            'fields' => 'ID',
            'search' => $search ? '*' . $search . '*' : '',
            'search_columns' => ['user_login', 'user_email', 'display_name'],
            'meta_query' => $meta_query,
        ];
        $total_users = get_users($count_args);
        $total = is_array($total_users) ? count($total_users) : 0;

        // Get users for current page
        $args = [
            'role__in' => ['org_member', 'org_admin'],
            'number' => $per_page,
            'paged' => $paged,
            'search' => $search ? '*' . $search . '*' : '',
            'search_columns' => ['user_login', 'user_email', 'display_name'],
            'meta_query' => $meta_query,
        ];
        $users = get_users($args);

        ob_start();
        echo '<form class="wp-org-grid wp-org-grid-2" method="get">';
        
        // Preserve existing query parameters
        foreach ($_GET as $key => $value) {
            if (!in_array($key, ['member_search', 'member_city', 'paged'], true)) {
                if (is_array($value)) {
                    foreach ($value as $v) {
                        echo '<input type="hidden" name="' . esc_attr($key) . '[]" value="' . esc_attr($v) . '">';
                    }
                } else {
                    echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
                }
            }
        }
        
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

        echo '</tbody></table>';

        // Add pagination
        if ($total > $per_page) {
            $big = 999999999;
            $pagination_args = [
                'base' => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
                'format' => '?paged=%#%',
                'current' => $paged,
                'total' => ceil($total / $per_page),
                'prev_text' => '&larr;',
                'next_text' => '&rarr;',
                'add_args' => [],
            ];
            
            // Preserve filter parameters
            if ($search !== '') {
                $pagination_args['add_args']['member_search'] = $search;
            }
            if ($city_code !== '') {
                $pagination_args['add_args']['member_city'] = $city_code;
            }
            
            echo '<div class="wp-org-pagination">';
            echo paginate_links($pagination_args);
            echo '</div>';
        }

        return (string) ob_get_clean();
    }

    /**
     * @return array<string, string>
     */
    private function get_available_regions($region_field_type = 'region_city')
    {
        $regions = new \WpOrg\Support\Regions();
        $users = get_users([
            'role__in' => ['org_member', 'org_admin'],
            'number' => 500,
            'fields' => 'ID',
        ]);

        $available_regions = [];

        foreach ($users as $user_id) {
            $name = '';
            $code = '';
            $default_code_key = '';
            $default_name_key = '';
            $get_region_name_method = '';

            // Set defaults based on region type
            switch ($region_field_type) {
                case 'region_province':
                    $default_code_key = 'wp_org_province_code';
                    $default_name_key = 'wp_org_province_name';
                    $get_region_name_method = 'get_province_name';
                    break;
                case 'region_district':
                    $default_code_key = 'wp_org_district_code';
                    $default_name_key = 'wp_org_district_name';
                    $get_region_name_method = 'get_district_name';
                    break;
                case 'region_city':
                default:
                    $default_code_key = 'wp_org_city_code';
                    $default_name_key = 'wp_org_city_name';
                    $get_region_name_method = 'get_city_name';
                    break;
            }

            // First check registration fields
            foreach (MemberData::get_all_registration_fields() as $field) {
                if ($field['type'] !== $region_field_type) {
                    continue;
                }

                $code = (string) get_user_meta($user_id, 'wp_org_' . $field['key'], true);
                $name = (string) get_user_meta($user_id, 'wp_org_' . MemberData::get_region_name_key($field['key']), true);
                break;
            }

            // Fallback to default keys
            if ($code === '') {
                $code = (string) get_user_meta($user_id, $default_code_key, true);
            }

            if ($name === '') {
                $name = (string) get_user_meta($user_id, $default_name_key, true);
            }

            // Try to get name from regions class if we have code but no name
            if ($name === '' && $code !== '' && method_exists($regions, $get_region_name_method)) {
                $name = $regions->$get_region_name_method($code);
            }

            if ($code !== '' && $name !== '') {
                $available_regions[$code] = $name;
            }
        }

        asort($available_regions);

        return $available_regions;
    }

    /**
     * @return array<string, string>
     */
    private function get_available_cities()
    {
        return $this->get_available_regions('region_city');
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
