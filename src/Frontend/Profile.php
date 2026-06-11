<?php

namespace WpOrg\Frontend;

use Dompdf\Dompdf;
use Dompdf\Options;
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
        $card_view_mode = isset($_GET['member_card_view']) ? sanitize_key(wp_unslash($_GET['member_card_view'])) : '';
        $card_data = $premium_status === 'active' ? $this->get_member_card_data($user_id) : null;
        $payment_banks = array_values(array_filter((array) get_option('wp_org_payment_banks', []), static function ($bank) {
            return !empty($bank['enabled']);
        }));

        if ($active_tab === 'premium' && in_array($card_view_mode, ['pdf', 'download'], true) && $card_data) {
            return $this->render_member_card_pdf_response($card_data, $card_view_mode === 'download');
        }

        ob_start();
        echo '<div class="wp-org-card wp-org-profile-shell">';
        echo '<div class="wp-org-profile-header">';
        echo '<div><p class="wp-org-eyebrow">Area Anggota</p><p class="wp-org-muted wp-org-profile-intro">Kelola data profil, status pendaftaran, dan akses premium Anda dalam satu halaman.</p></div>';
        echo '<div class="wp-org-profile-status"><span class="wp-org-profile-status-label">Status pendaftaran</span><span class="wp-org-status wp-org-status-' . esc_attr($status) . '">' . esc_html($statuses[$status] ?? $status) . '</span></div>';
        echo '</div>';
        echo '<nav class="wp-org-tabs">';
        echo '<a class="wp-org-tab ' . ($active_tab === 'profile' ? 'wp-org-tab-active' : '') . '" href="' . esc_url(add_query_arg('profile_tab', 'profile')) . '">Profil</a>';
        echo '<a class="wp-org-tab ' . ($active_tab === 'premium' ? 'wp-org-tab-active' : '') . '" href="' . esc_url(add_query_arg('profile_tab', 'premium')) . '">Member Premium</a>';
        echo '</nav>';

        if ($active_tab === 'premium') {
            echo '<div class="wp-org-profile-panel">';
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
                echo '<div class="wp-org-member-card wp-org-profile-section">';
                echo '<div class="wp-org-section-heading"><div><h3>Kartu Anggota Premium</h3><p class="wp-org-muted">Kartu anggota Anda sudah aktif dan dapat diunduh.</p></div></div>';
                echo '<div class="wp-org-member-card-preview-wrap">';
                echo '<iframe class="wp-org-member-card-frame" src="' . esc_url($this->get_member_card_pdf_url()) . '" title="Preview kartu anggota premium"></iframe>';
                echo '</div>';
                echo '</div>';
            }

            if ($proof_url) {
                echo '<div class="wp-org-proof-preview wp-org-profile-section"><p><strong>Bukti pembayaran terakhir</strong></p><p><a href="' . esc_url($proof_url) . '" target="_blank" rel="noopener"><img src="' . esc_url($proof_url) . '" alt="Bukti pembayaran premium"></a></p></div>';
            }

            if ($premium_status !== 'active' && $payment_banks) {
                echo '<div class="wp-org-profile-section">';
                echo '<div class="wp-org-section-heading"><div><h3>Ajukan Member Premium</h3><p class="wp-org-muted">Silakan transfer ke salah satu rekening berikut lalu upload bukti pembayaran.</p></div></div>';
                echo '<div class="wp-org-bank-list-wrap"><ul class="wp-org-bank-list">';
                foreach ($payment_banks as $bank) {
                    echo '<li><strong>' . esc_html($bank['bank_name'] ?? '') . '</strong><span>' . esc_html($bank['account_number'] ?? '') . ' a/n ' . esc_html($bank['account_name'] ?? '') . '</span></li>';
                }
                echo '</ul></div>';
                echo '<form class="wp-org-grid wp-org-premium-form" method="post" enctype="multipart/form-data">';
                wp_nonce_field('wp_org_premium_request', 'wp_org_premium_nonce');
                echo '<div class="wp-org-field"><label for="wp_org_premium_reference">Referensi Pembayaran</label><input id="wp_org_premium_reference" name="premium_reference" type="text" value="' . esc_attr((string) $premium_reference) . '" placeholder="Contoh: Transfer 10 Juni 2026 / 123456"></div>';
                echo '<div class="wp-org-field"><label for="wp_org_premium_proof">Foto Bukti Pembayaran</label><input id="wp_org_premium_proof" name="premium_proof" type="file" accept="image/jpeg,image/png,image/webp" required></div>';
                echo '<div class="wp-org-actions"><button class="wp-org-button" type="submit" name="wp_org_premium_submit" value="1">Kirim Pengajuan</button></div>';
                echo '</form></div>';
            }

            echo '</div>';
        } else {
            $profile_photo_field = null;
            $profile_form_fields = [];

            foreach ($fields as $field) {
                if ($field['key'] === 'member_photo') {
                    $profile_photo_field = $field;
                    continue;
                }

                $profile_form_fields[] = $field;
            }

            echo '<div class="wp-org-profile-panel">';
            echo '<div class="wp-org-section-heading"><div><h3>Data Profil</h3><p class="wp-org-muted">Perbarui informasi anggota Anda di bawah ini.</p></div></div>';
            echo '<form class="wp-org-grid wp-org-region-form wp-org-profile-form" method="post" enctype="multipart/form-data">';
            wp_nonce_field('wp_org_profile_action', 'wp_org_profile_nonce');

            if ($profile_photo_field) {
                $profile_photo_value = get_user_meta($user_id, 'wp_org_' . $profile_photo_field['key'], true);
                echo '<div class="wp-org-profile-photo-row">';
                echo $this->render_profile_photo_field($profile_photo_field, $profile_photo_value);
                echo '</div>';
            }

            foreach ($profile_form_fields as $field) {
                $value = get_user_meta($user_id, 'wp_org_' . $field['key'], true);
                echo $this->render_field($field, $value, $regions);
            }

            echo '<div class="wp-org-actions"><button class="wp-org-button" type="submit" name="wp_org_profile_submit" value="1">Simpan Profil</button></div>';
            echo '</form>';
            echo '</div>';
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
        echo '<div class="wp-org-card wp-org-profile-shell"><div class="wp-org-profile-header">';
        echo '<div><p class="wp-org-eyebrow">Portal Anggota</p><h2>Akses Anggota</h2><p class="wp-org-muted wp-org-profile-intro">Masuk ke akun atau daftar sebagai anggota baru.</p></div>';
        echo '</div>';
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
    private function get_member_card_data($user_id)
    {
        $display_name = get_user_meta($user_id, 'wp_org_full_name', true);
        if (!$display_name) {
            $user = wp_get_current_user();
            $display_name = $user->display_name;
        }

        $member_card_settings = get_option('wp_org_member_card_settings', []);
        $member_number = MemberData::get_member_number($user_id);
        $region = trim(get_user_meta($user_id, 'wp_org_city_name', true) . ', ' . get_user_meta($user_id, 'wp_org_province_name', true), ', ');
        $issued_at = get_user_meta($user_id, 'wp_org_premium_requested_at', true);
        if (!$issued_at) {
            $issued_at = current_time('mysql');
        }

        return [
            'organization_name' => sanitize_text_field($member_card_settings['organization_name'] ?? 'WP Org'),
            'background_url' => esc_url_raw($member_card_settings['background_url'] ?? ''),
            'logo_url' => esc_url_raw($member_card_settings['logo_url'] ?? ''),
            'name' => $display_name,
            'number' => $member_number,
            'region' => $region ?: 'Indonesia',
            'issued_at' => mysql2date('d M Y', $issued_at),
            'filename' => 'kartu-anggota-' . strtolower(sanitize_title($display_name ?: (string) $user_id)) . '.pdf',
        ];
    }

    /**
     * @param array<string, string> $data
     */
    private function render_member_card_preview($data)
    {
        $background_style = '';
        if (!empty($data['background_url'])) {
            $background_style = 'style="background-image:url(' . esc_url($data['background_url']) . ');"';
        }

        $logo = '';
        if (!empty($data['logo_url'])) {
            $logo = '<img class="wp-org-member-card-logo" src="' . esc_url($data['logo_url']) . '" alt="Logo organisasi">';
        }

        return '<div class="wp-org-member-card-preview" ' . $background_style . '>'
            . '<div class="wp-org-member-card-bg"></div>'
            . '<div class="wp-org-member-card-inner">'
            . $logo
            . '<div class="wp-org-member-card-copy">'
            . '<p class="wp-org-member-card-org">' . esc_html($data['organization_name']) . '</p>'
            . '<h3 class="wp-org-member-card-title">Kartu Anggota Premium</h3>'
            . '<div class="wp-org-member-card-grid">'
            . '<div>'
            . '<div class="wp-org-member-card-meta-label">Nomor Anggota</div>'
            . '<div class="wp-org-member-card-number">' . esc_html($data['number']) . '</div>'
            . '<div class="wp-org-member-card-meta-label" style="margin-top:18px">Nama Anggota</div>'
            . '<div class="wp-org-member-card-name">' . esc_html($data['name']) . '</div>'
            . '<div class="wp-org-member-card-meta-label" style="margin-top:18px">Wilayah</div>'
            . '<div class="wp-org-member-card-region">' . esc_html($data['region']) . '</div>'
            . '</div>'
            . '<div class="wp-org-member-card-meta">'
            . '<div class="wp-org-member-card-meta-label">Berlaku Sejak</div>'
            . '<div class="wp-org-member-card-meta-value">' . esc_html($data['issued_at']) . '</div>'
            . '<div class="wp-org-member-card-meta-label" style="margin-top:16px">Status</div>'
            . '<div class="wp-org-member-card-meta-value">AKTIF</div>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }

    private function render_member_card_print_view($data)
    {
        $this->render_member_card_pdf_response($data, false);
    }

    private function render_member_card_pdf_response($data, $download = false)
    {
        if (!class_exists(Dompdf::class)) {
            wp_die('Dompdf belum tersedia.');
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        nocache_headers();

        $filename = !empty($data['filename']) ? $data['filename'] : 'kartu-anggota.pdf';
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->render_member_card_pdf_html($data), 'UTF-8');
        $dompdf->setPaper([0, 0, 242.64, 152.74], 'landscape');
        $dompdf->render();

        $pdf = $dompdf->output();
        header('Content-Type: application/pdf');
        header('Content-Length: ' . strlen($pdf));
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
        echo $pdf;
        exit;
    }

    private function render_member_card_pdf_html($data)
    {
        $background_data_uri = $this->get_local_image_data_uri($data['background_url'] ?? '');
        $logo_data_uri = $this->get_local_image_data_uri($data['logo_url'] ?? '');

        $background_style = $background_data_uri !== '' ? 'background-image:url(' . $background_data_uri . ');' : '';
        $logo = $logo_data_uri !== '' ? '<img class="logo" src="' . $logo_data_uri . '" alt="Logo organisasi">' : '';

        return '<!doctype html><html><head><meta charset="UTF-8"><style>'
            . '@page{size:85.6mm 53.98mm;margin:0}'
            . 'body{margin:0;font-family:DejaVu Sans,Arial,sans-serif}'
            . '.card{position:relative;width:85.6mm;height:53.98mm;overflow:hidden;color:#fff;background:#135e96;background-image:linear-gradient(135deg,#0f3d5e 0%,#135e96 58%,#2e84be 100%)}'
            . '.bg{position:absolute;top:0;right:0;bottom:0;left:0;background-size:cover;background-position:center;opacity:.20;' . $background_style . '}'
            . '.shade{position:absolute;top:0;right:0;bottom:0;left:0;background:rgba(6,24,38,.28)}'
            . '.ring-top{position:absolute;top:-10mm;right:-8mm;width:34mm;height:34mm;border-radius:17mm;background:rgba(255,255,255,.08)}'
            . '.ring-bottom{position:absolute;bottom:-14mm;left:-10mm;width:42mm;height:42mm;border-radius:21mm;background:rgba(255,255,255,.06)}'
            . '.inner{position:relative;padding:3.5mm;z-index:2}'
            . '.header{height:12mm}'
            . '.logo-wrap{float:left;width:12mm;height:12mm}'
            . '.logo{display:block;width:9mm;height:9mm;object-fit:contain;background:rgba(255,255,255,.12);border:0.3mm solid rgba(255,255,255,.18);border-radius:1.8mm;padding:1.2mm}'
            . '.org{margin:3.2mm 0 0 13.5mm;font-size:9.6pt;line-height:2;font-weight:700}'
            . '.content{margin-top:2mm}'
            . '.left{float:left;width:47mm}'
            . '.right{float:right;width:24mm}'
            . '.label{font-size:4.1pt;line-height:1.15;text-transform:uppercase;letter-spacing:.45px;color:#d8efff}'
            . '.value{color:#fff;word-wrap:break-word}'
            . '.number{font-size:7.8pt;font-weight:700;line-height:1.15;margin-top:.6mm}'
            . '.name{font-size:11.8pt;font-weight:700;line-height:1.02;margin-top:.6mm}'
            . '.region{font-size:6.1pt;line-height:1.2;margin-top:.6mm}'
            . '.spacer{height:2.1mm}'
            . '.meta{background:rgba(255,255,255,.14);border:0.3mm solid rgba(255,255,255,.14);border-radius:2mm;padding:2.1mm 2.2mm}'
            . '.meta .value{font-size:6.3pt;font-weight:700;line-height:1.15;margin-top:.7mm}'
            . '.status{margin-top:3mm;padding-top:2.3mm;border-top:0.3mm solid rgba(255,255,255,.18)}'
            . '.footer{position:absolute;right:3.5mm;bottom:2.6mm;font-size:4pt;letter-spacing:.45px;text-transform:uppercase;color:rgba(255,255,255,.82)}'
            . '.clearfix{clear:both}'
            . '</style></head><body><div class="card"><div class="bg"></div><div class="shade"></div><div class="ring-top"></div><div class="ring-bottom"></div><div class="inner">'
            . '<div class="header">'
            . '<div class="logo-wrap">' . $logo . '</div>'
            . '<p class="org">' . esc_html($data['organization_name']) . '</p>'
            . '</div>'
            . '<div class="content">'
            . '<div class="left">'
            . '<div class="label">Nomor Anggota</div>'
            . '<div class="value number">' . esc_html($data['number']) . '</div>'
            . '<div class="spacer"></div>'
            . '<div class="label">Nama Anggota</div>'
            . '<div class="value name">' . esc_html($data['name']) . '</div>'
            . '<div class="spacer"></div>'
            . '<div class="label">Wilayah</div>'
            . '<div class="value region">' . esc_html($data['region']) . '</div>'
            . '</div>'
            . '<div class="right"><div class="meta">'
            . '<div class="label">Berlaku Sejak</div><div class="value">' . esc_html($data['issued_at']) . '</div>'
            . '<div class="status"><div class="label">Status</div><div class="value">AKTIF</div></div>'
            . '</div></div>'
            . '<div class="clearfix"></div>'
            . '</div>'
            . '<div class="footer">Verified Member</div>'
            . '</div></div></body></html>';
    }

    private function get_member_card_pdf_url()
    {
        return add_query_arg([
            'profile_tab' => 'premium',
            'member_card_view' => 'pdf',
        ], $this->get_current_profile_url());
    }

    private function get_member_card_pdf_download_url()
    {
        return add_query_arg([
            'profile_tab' => 'premium',
            'member_card_view' => 'download',
        ], $this->get_current_profile_url());
    }

    private function get_current_profile_url()
    {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
        return $request_uri !== '' ? home_url($request_uri) : home_url('/profile-org/');
    }

    private function get_local_image_path($url)
    {
        $url = esc_url_raw((string) $url);
        if ($url === '') {
            return '';
        }

        $attachment_id = attachment_url_to_postid($url);
        if ($attachment_id) {
            $path = get_attached_file($attachment_id);
            if ($path) {
                return $path;
            }
        }

        $uploads = wp_get_upload_dir();
        if (!empty($uploads['baseurl']) && !empty($uploads['basedir']) && strpos($url, $uploads['baseurl']) === 0) {
            $relative_path = ltrim(substr($url, strlen($uploads['baseurl'])), '/');
            return trailingslashit($uploads['basedir']) . str_replace('/', DIRECTORY_SEPARATOR, $relative_path);
        }

        return '';
    }

    private function get_local_image_data_uri($url)
    {
        $path = $this->get_local_image_path($url);
        if (!$path || !file_exists($path)) {
            return '';
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return '';
        }

        $mime = wp_check_filetype($path);
        $type = !empty($mime['type']) ? $mime['type'] : mime_content_type($path);
        if (!$type) {
            return '';
        }

        return 'data:' . $type . ';base64,' . base64_encode($contents);
    }

    private function render_profile_photo_field($field, $value)
    {
        $current = (string) $value;
        $html = '<div class="wp-org-profile-photo-card">';
        $html .= '<div class="wp-org-profile-photo-copy"><h4>' . esc_html($field['label']) . '</h4><p class="wp-org-muted">Foto profil ditampilkan terpisah agar form utama lebih ringkas.</p></div>';
        $html .= '<div class="wp-org-profile-photo-body">';

        if ($current !== '') {
            $html .= '<div class="wp-org-profile-photo-preview"><img src="' . esc_url($current) . '" alt="' . esc_attr($field['label']) . '"></div>';
        } else {
            $html .= '<div class="wp-org-profile-photo-empty">Belum ada foto</div>';
        }

        $html .= '<div class="wp-org-field wp-org-profile-photo-upload"><label for="' . esc_attr($field['key']) . '">Upload Foto Baru</label><input id="' . esc_attr($field['key']) . '" name="' . esc_attr($field['key']) . '" type="file" accept="image/jpeg,image/png,image/webp"></div>';
        $html .= '</div></div>';

        return $html;
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
            $html .= '<select id="' . esc_attr($key) . '" class="wp-org-province" name="' . esc_attr($key) . '" data-selected="' . esc_attr((string) $value) . '"' . $required . '><option value="">Pilih provinsi</option>';
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
