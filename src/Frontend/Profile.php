<?php

namespace WpOrg\Frontend;

use WpOrg\Support\MemberData;
use WpOrg\Support\Regions;

class Profile
{
    public function register()
    {
        add_shortcode('org_profile', [$this, 'render_shortcode']);
        add_action('init', [$this, 'handle_update']);
        add_action('init', [$this, 'handle_premium_request']);
    }

    public function handle_update()
    {
        if (!isset($_POST['wp_org_profile_submit']) || !is_user_logged_in()) {
            return;
        }

        if (!isset($_POST['wp_org_profile_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wp_org_profile_nonce'])), 'wp_org_profile_action')) {
            return;
        }

        $errors = MemberData::validate_submission($_POST, true);
        if ($errors->has_errors()) {
            return;
        }

        MemberData::save_profile_fields(get_current_user_id(), $_POST);
        wp_safe_redirect($this->get_profile_redirect_url('profile'));
        exit;
    }

    public function render_shortcode()
    {
        if (!is_user_logged_in()) {
            return $this->render_guest_tabs();
        }

        $user_id = get_current_user_id();
        $active_tab = isset($_GET['profile_tab']) ? sanitize_key(wp_unslash($_GET['profile_tab'])) : 'profile';
        $fields = MemberData::get_registration_fields();
        $regions = new Regions();
        $statuses = MemberData::get_all_statuses();
        $status = MemberData::get_status($user_id);
        $general = get_option('wp_org_general_settings', []);
        $premium_fee = absint($general['premium_fee'] ?? 0);
        $premium_status = MemberData::get_premium_status($user_id);
        $premium_labels = MemberData::get_premium_statuses();
        $premium_note = get_user_meta($user_id, 'wp_org_premium_note', true);
        $premium_reference = get_user_meta($user_id, 'wp_org_premium_reference', true);
        $proof_url = get_user_meta($user_id, 'wp_org_premium_proof_url', true);
        $member_card_settings = get_option('wp_org_member_card_settings', []);
        $card_data = $premium_status === 'active' ? $this->get_member_card_asset($user_id) : null;
        $payment_banks = array_values(array_filter((array) get_option('wp_org_payment_banks', []), static function ($bank) {
            return !empty($bank['enabled']);
        }));

        ob_start();
        echo '<div class="wp-org-card"><h2>Profil Anggota</h2>';
        echo '<nav class="wp-org-tabs">';
        echo '<a class="wp-org-tab ' . ($active_tab === 'profile' ? 'wp-org-tab-active' : '') . '" href="' . esc_url(add_query_arg('profile_tab', 'profile')) . '">Profil</a>';
        echo '<a class="wp-org-tab ' . ($active_tab === 'premium' ? 'wp-org-tab-active' : '') . '" href="' . esc_url(add_query_arg('profile_tab', 'premium')) . '">Member Premium</a>';
        echo '</nav>';
        echo '<p class="wp-org-muted">Status pendaftaran: <span class="wp-org-status wp-org-status-' . esc_attr($status) . '">' . esc_html($statuses[$status] ?? $status) . '</span></p>';

        if ($active_tab === 'premium') {
            echo '<div class="wp-org-notice wp-org-notice-success"><strong>Status Premium:</strong> ' . esc_html($premium_labels[$premium_status] ?? $premium_status);
            if ($premium_fee > 0) {
                echo '<br>Biaya premium: Rp ' . esc_html(number_format_i18n($premium_fee, 0));
            }
            if ($premium_reference) {
                echo '<br>Referensi pembayaran: ' . esc_html($premium_reference);
            }
            if ($premium_note) {
                echo '<br>Catatan admin: ' . esc_html($premium_note);
            }
            echo '</div>';

            if ($card_data) {
                echo '<div class="wp-org-member-card">';
                echo '<h3>Kartu Anggota Premium</h3>';
                echo '<p>Kartu anggota Anda sudah aktif dan dapat diunduh.</p>';
                echo '<p><img src="' . esc_attr($card_data['data_uri']) . '" alt="Kartu anggota premium"></p>';
                echo '<div class="wp-org-actions"><a class="wp-org-button" href="' . esc_attr($card_data['data_uri']) . '" download="' . esc_attr($card_data['filename']) . '">Download Kartu Anggota</a></div>';
                echo '</div>';
            }

            if ($proof_url) {
                echo '<div class="wp-org-proof-preview"><p><strong>Bukti pembayaran terakhir</strong></p><p><a href="' . esc_url($proof_url) . '" target="_blank" rel="noopener"><img src="' . esc_url($proof_url) . '" alt="Bukti pembayaran premium"></a></p></div>';
            }

            if ($premium_status !== 'active' && $payment_banks) {
                echo '<div style="margin-top:16px">';
                echo '<h3>Ajukan Member Premium</h3>';
                echo '<p>Silakan transfer ke salah satu rekening berikut lalu upload foto bukti pembayaran.</p><ul>';
                foreach ($payment_banks as $bank) {
                    echo '<li><strong>' . esc_html($bank['bank_name'] ?? '') . '</strong> - ' . esc_html($bank['account_number'] ?? '') . ' a/n ' . esc_html($bank['account_name'] ?? '') . '</li>';
                }
                echo '</ul>';
                echo '<form method="post" enctype="multipart/form-data">';
                wp_nonce_field('wp_org_premium_request', 'wp_org_premium_nonce');
                echo '<div class="wp-org-field"><label for="wp_org_premium_reference">Referensi Pembayaran</label><input id="wp_org_premium_reference" name="premium_reference" type="text" value="' . esc_attr((string) $premium_reference) . '" placeholder="Contoh: Transfer 10 Juni 2026 / 123456"></div>';
                echo '<div class="wp-org-field"><label for="wp_org_premium_proof">Foto Bukti Pembayaran</label><input id="wp_org_premium_proof" name="premium_proof" type="file" accept="image/jpeg,image/png,image/webp" required></div>';
                echo '<div class="wp-org-actions"><button class="wp-org-button" type="submit" name="wp_org_premium_submit" value="1">Kirim Pengajuan</button></div>';
                echo '</form></div>';
            }
        } else {
            echo '<form class="wp-org-grid wp-org-region-form" method="post" enctype="multipart/form-data">';
            wp_nonce_field('wp_org_profile_action', 'wp_org_profile_nonce');

            foreach ($fields as $field) {
                $value = get_user_meta($user_id, 'wp_org_' . $field['key'], true);
                echo $this->render_field($field, $value, $regions);
            }

            echo '<div class="wp-org-actions"><button class="wp-org-button" type="submit" name="wp_org_profile_submit" value="1">Simpan Profil</button></div>';
            echo '</form>';
        }

        echo '</div>';

        return (string) ob_get_clean();
    }

    private function render_guest_tabs()
    {
        $active_tab = isset($_GET['profile_tab']) ? sanitize_key(wp_unslash($_GET['profile_tab'])) : 'login';
        if (!in_array($active_tab, ['login', 'register'], true)) {
            $active_tab = 'login';
        }

        $auth = new Auth();

        ob_start();
        echo '<div class="wp-org-card"><h2>Akses Anggota</h2>';
        echo '<nav class="wp-org-tabs">';
        echo '<a class="wp-org-tab ' . ($active_tab === 'login' ? 'wp-org-tab-active' : '') . '" href="' . esc_url(add_query_arg('profile_tab', 'login')) . '">Login</a>';
        echo '<a class="wp-org-tab ' . ($active_tab === 'register' ? 'wp-org-tab-active' : '') . '" href="' . esc_url(add_query_arg('profile_tab', 'register')) . '">Register</a>';
        echo '</nav>';

        if ($active_tab === 'register') {
            echo $auth->render_register_shortcode();
        } else {
            echo $auth->render_login_shortcode();
        }

        echo '</div>';

        return (string) ob_get_clean();
    }

    public function handle_premium_request()
    {
        if (!isset($_POST['wp_org_premium_submit']) || !is_user_logged_in()) {
            return;
        }

        if (!isset($_POST['wp_org_premium_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wp_org_premium_nonce'])), 'wp_org_premium_request')) {
            return;
        }

        $user_id = get_current_user_id();
        $reference = isset($_POST['premium_reference']) ? sanitize_text_field(wp_unslash($_POST['premium_reference'])) : '';
        $proof_url = $this->handle_premium_proof_upload();

        if (is_wp_error($proof_url)) {
            wp_safe_redirect($this->get_profile_redirect_url('premium'));
            exit;
        }

        MemberData::update_premium_status($user_id, 'pending');
        update_user_meta($user_id, 'wp_org_premium_reference', $reference);
        update_user_meta($user_id, 'wp_org_premium_requested_at', current_time('mysql'));
        update_user_meta($user_id, 'wp_org_premium_proof_url', $proof_url);
        delete_user_meta($user_id, 'wp_org_premium_note');

        wp_safe_redirect($this->get_profile_redirect_url('premium'));
        exit;
    }

    private function get_profile_redirect_url($tab)
    {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';

        if ($request_uri !== '') {
            $current_url = home_url($request_uri);

            return add_query_arg('profile_tab', $tab, $current_url);
        }

        return add_query_arg('profile_tab', $tab, home_url('/'));
    }

    private function handle_premium_proof_upload()
    {
        if (empty($_FILES['premium_proof']) || empty($_FILES['premium_proof']['name'])) {
            return new \WP_Error('premium_proof_required', 'Bukti pembayaran wajib diupload.');
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $uploaded = wp_handle_upload($_FILES['premium_proof'], [
            'test_form' => false,
            'mimes' => [
                'jpg|jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
            ],
        ]);

        if (!empty($uploaded['error'])) {
            return new \WP_Error('premium_proof_upload_failed', sanitize_text_field($uploaded['error']));
        }

        return esc_url_raw($uploaded['url']);
    }

    /**
     * @return array<string, string>|null
     */
    private function get_member_card_asset($user_id)
    {
        $display_name = get_user_meta($user_id, 'wp_org_full_name', true);
        if (!$display_name) {
            $user = wp_get_current_user();
            $display_name = $user->display_name;
        }

        $member_card_settings = get_option('wp_org_member_card_settings', []);
        $member_number = 'ORG-' . str_pad((string) $user_id, 6, '0', STR_PAD_LEFT);
        $region = trim(get_user_meta($user_id, 'wp_org_city_name', true) . ', ' . get_user_meta($user_id, 'wp_org_province_name', true), ', ');
        $issued_at = get_user_meta($user_id, 'wp_org_premium_requested_at', true);
        if (!$issued_at) {
            $issued_at = current_time('mysql');
        }

        $svg = $this->build_member_card_svg([
            'name' => $display_name,
            'number' => $member_number,
            'region' => $region ?: 'Indonesia',
            'issued_at' => mysql2date('d M Y', $issued_at),
            'organization_name' => sanitize_text_field($member_card_settings['organization_name'] ?? 'WP Org'),
            'background_url' => esc_url_raw($member_card_settings['background_url'] ?? ''),
            'logo_url' => esc_url_raw($member_card_settings['logo_url'] ?? ''),
        ]);

        return [
            'data_uri' => 'data:image/svg+xml;base64,' . base64_encode($svg),
            'filename' => 'kartu-anggota-' . strtolower(sanitize_title($display_name ?: (string) $user_id)) . '.svg',
        ];
    }

    /**
     * @param array<string, string> $data
     */
    private function build_member_card_svg($data)
    {
        $name = $this->escape_svg_text($data['name']);
        $number = $this->escape_svg_text($data['number']);
        $region = $this->escape_svg_text($data['region']);
        $issued_at = $this->escape_svg_text($data['issued_at']);
        $organization_name = $this->escape_svg_text($data['organization_name']);
        $background_data_uri = $this->get_local_image_data_uri($data['background_url'] ?? '');
        $logo_data_uri = $this->get_local_image_data_uri($data['logo_url'] ?? '');
        $background_markup = $background_data_uri !== '' ? '<image href="' . $this->escape_svg_attr($background_data_uri) . '" x="0" y="0" width="1080" height="680" preserveAspectRatio="xMidYMid slice" opacity="0.28"/>' : '';
        $logo_markup = $logo_data_uri !== '' ? '<image href="' . $this->escape_svg_attr($logo_data_uri) . '" x="858" y="78" width="140" height="140" preserveAspectRatio="xMidYMid meet"/>' : '';

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1080" height="680" viewBox="0 0 1080 680">
  <defs>
    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#0f3d5e" />
      <stop offset="55%" stop-color="#135e96" />
      <stop offset="100%" stop-color="#5ea3d6" />
    </linearGradient>
    <linearGradient id="panel" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#ffffff" stop-opacity="0.18" />
      <stop offset="100%" stop-color="#ffffff" stop-opacity="0.06" />
    </linearGradient>
  </defs>
  <rect width="1080" height="680" rx="36" fill="url(#bg)"/>
  {$background_markup}
  <circle cx="920" cy="120" r="150" fill="#ffffff" fill-opacity="0.08"/>
  <circle cx="180" cy="580" r="200" fill="#ffffff" fill-opacity="0.06"/>
  <rect x="48" y="48" width="984" height="584" rx="30" fill="url(#panel)" stroke="#ffffff" stroke-opacity="0.2"/>
  <text x="84" y="120" fill="#d8efff" font-size="28" font-family="Arial, sans-serif" letter-spacing="4">{$organization_name}</text>
  {$logo_markup}
  <text x="84" y="184" fill="#ffffff" font-size="62" font-family="Arial, sans-serif" font-weight="700">KARTU ANGGOTA PREMIUM</text>
  <text x="84" y="258" fill="#d7ebfb" font-size="24" font-family="Arial, sans-serif">Nomor Anggota</text>
  <text x="84" y="304" fill="#ffffff" font-size="40" font-family="Arial, sans-serif" font-weight="700">{$number}</text>
  <text x="84" y="390" fill="#d7ebfb" font-size="24" font-family="Arial, sans-serif">Nama Anggota</text>
  <text x="84" y="446" fill="#ffffff" font-size="48" font-family="Arial, sans-serif" font-weight="700">{$name}</text>
  <text x="84" y="522" fill="#d7ebfb" font-size="24" font-family="Arial, sans-serif">Wilayah</text>
  <text x="84" y="564" fill="#ffffff" font-size="30" font-family="Arial, sans-serif">{$region}</text>
  <rect x="760" y="430" width="220" height="120" rx="24" fill="#ffffff" fill-opacity="0.16"/>
  <text x="790" y="476" fill="#d7ebfb" font-size="20" font-family="Arial, sans-serif">Berlaku Sejak</text>
  <text x="790" y="520" fill="#ffffff" font-size="32" font-family="Arial, sans-serif" font-weight="700">{$issued_at}</text>
  <text x="790" y="584" fill="#d7ebfb" font-size="18" font-family="Arial, sans-serif">Status: AKTIF</text>
</svg>
SVG;
    }

    private function escape_svg_text($value)
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function escape_svg_attr($value)
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function get_local_image_data_uri($url)
    {
        $url = esc_url_raw((string) $url);
        if ($url === '') {
            return '';
        }

        $attachment_id = attachment_url_to_postid($url);
        $path = '';

        if ($attachment_id) {
            $path = get_attached_file($attachment_id);
        }

        if (!$path) {
            $uploads = wp_get_upload_dir();
            if (!empty($uploads['baseurl']) && !empty($uploads['basedir']) && strpos($url, $uploads['baseurl']) === 0) {
                $relative_path = ltrim(substr($url, strlen($uploads['baseurl'])), '/');
                $path = trailingslashit($uploads['basedir']) . str_replace('/', DIRECTORY_SEPARATOR, $relative_path);
            }
        }

        if (!$path || !file_exists($path)) {
            return '';
        }

        $mime = wp_check_filetype($path);
        $type = !empty($mime['type']) ? $mime['type'] : mime_content_type($path);
        if (!$type) {
            return '';
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return '';
        }

        return 'data:' . $type . ';base64,' . base64_encode($contents);
    }

    private function render_field($field, $value, Regions $regions)
    {
        $key = $field['key'];
        $required = !empty($field['required']) ? ' required' : '';
        $html = '<div class="wp-org-field"><label for="' . esc_attr($key) . '">' . esc_html($field['label']) . '</label>';
        $options = MemberData::get_field_options($field);

        if ($field['type'] === 'textarea') {
            $html .= '<textarea id="' . esc_attr($key) . '" name="' . esc_attr($key) . '"' . $required . '>' . esc_textarea((string) $value) . '</textarea>';
        } elseif ($field['type'] === 'select') {
            $html .= '<select id="' . esc_attr($key) . '" name="' . esc_attr($key) . '"' . $required . '><option value="">Pilih opsi</option>';
            foreach ($options as $option) {
                $html .= '<option value="' . esc_attr($option) . '"' . selected($value, $option, false) . '>' . esc_html($option) . '</option>';
            }
            $html .= '</select>';
        } elseif ($field['type'] === 'radio') {
            foreach ($options as $option) {
                $html .= '<label><input type="radio" name="' . esc_attr($key) . '" value="' . esc_attr($option) . '"' . checked($value, $option, false) . $required . '> ' . esc_html($option) . '</label> ';
            }
        } elseif ($field['type'] === 'checkbox') {
            $selected_values = is_array($value) ? $value : (array) $value;
            foreach ($options as $option) {
                $html .= '<label><input type="checkbox" name="' . esc_attr($key) . '[]" value="' . esc_attr($option) . '"' . checked(in_array($option, $selected_values, true), true, false) . '> ' . esc_html($option) . '</label> ';
            }
        } elseif ($field['type'] === 'image') {
            $current = (string) $value;
            if ($current !== '') {
                $html .= '<p><img src="' . esc_url($current) . '" alt="' . esc_attr($field['label']) . '" style="max-width:180px;height:auto;border:1px solid #dcdcde;border-radius:12px"></p>';
            }
            $html .= '<input id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" type="file" accept="image/jpeg,image/png,image/webp"' . $required . '>';
        } elseif ($field['type'] === 'file') {
            $current = (string) $value;
            if ($current !== '') {
                $html .= '<p><a href="' . esc_url($current) . '" target="_blank" rel="noopener">Lihat file saat ini</a></p>';
            }
            $html .= '<input id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" type="file"' . $required . '>';
        } elseif ($field['type'] === 'region_province') {
            $html .= '<select id="' . esc_attr($key) . '" class="wp-org-province" name="' . esc_attr($key) . '"' . $required . '><option value="">Pilih provinsi</option>';
            foreach ($regions->get_provinces() as $province) {
                $html .= '<option value="' . esc_attr($province['code']) . '"' . selected($value, $province['code'], false) . '>' . esc_html($province['name']) . '</option>';
            }
            $html .= '</select>';
        } elseif ($field['type'] === 'region_city') {
            $html .= '<select id="' . esc_attr($key) . '" class="wp-org-city" name="' . esc_attr($key) . '" data-selected="' . esc_attr((string) $value) . '"' . $required . '><option value="">Pilih kota/kabupaten</option></select>';
        } elseif ($field['type'] === 'region_district') {
            $html .= '<select id="' . esc_attr($key) . '" class="wp-org-district" name="' . esc_attr($key) . '" data-selected="' . esc_attr((string) $value) . '"' . $required . ' disabled><option value="">Pilih kecamatan</option></select>';
        } else {
            $input_type = in_array($field['type'], ['email', 'number', 'date'], true) ? $field['type'] : 'text';
            $html .= '<input id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" type="' . esc_attr($input_type) . '" value="' . esc_attr((string) $value) . '"' . $required . '>';
        }

        $html .= '</div>';

        return $html;
    }
}
