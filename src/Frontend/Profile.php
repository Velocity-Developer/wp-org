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
        add_action('init', [$this, 'handle_account_update']);
        add_action('init', [$this, 'handle_account_delete']);
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
        wp_safe_redirect($this->get_profile_redirect_url('edit-profile'));
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
        $premium_enabled = MemberData::is_premium_enabled();
        $allowed_tabs = ['profile', 'edit-profile', 'edit-account'];
        if ($premium_enabled) {
            $allowed_tabs[] = 'premium';
        }
        if (!in_array($active_tab, $allowed_tabs, true)) {
            $active_tab = 'profile';
        }
        $premium_fee = absint($general['premium_fee'] ?? 0);
        $premium_status = MemberData::get_premium_status($user_id);
        $premium_labels = MemberData::get_premium_statuses();
        $premium_note = get_user_meta($user_id, 'wp_org_premium_note', true);
        $premium_reference = get_user_meta($user_id, 'wp_org_premium_reference', true);
        $proof_url = get_user_meta($user_id, 'wp_org_premium_proof_url', true);
        $member_card_settings = get_option('wp_org_member_card_settings', []);
        $card_view_mode = isset($_GET['member_card_view']) ? sanitize_key(wp_unslash($_GET['member_card_view'])) : '';
        $account_notice = isset($_GET['account_notice']) ? sanitize_key(wp_unslash($_GET['account_notice'])) : '';
        $card_data = $premium_status === 'active' ? $this->get_member_card_data($user_id) : null;
        $logout_url = wp_logout_url($this->get_current_profile_url());
        $payment_banks = array_values(array_filter((array) get_option('wp_org_payment_banks', []), static function ($bank) {
            return !empty($bank['enabled']);
        }));

        if ($premium_enabled && $active_tab === 'premium' && in_array($card_view_mode, ['pdf', 'download'], true) && $card_data) {
            return $this->render_member_card_pdf_response($card_data, $card_view_mode === 'download');
        }

        ob_start();
        echo '<div class="wp-org-card wp-org-profile-shell">';
        echo '<div class="wp-org-profile-header">';
        echo '<div><p class="wp-org-eyebrow">Area Anggota</p><p class="wp-org-muted wp-org-profile-intro">Kelola data profil, status pendaftaran, dan akses premium Anda dalam satu halaman.</p></div>';
        echo '<div class="wp-org-profile-header-actions"><div class="wp-org-profile-status"><span class="wp-org-profile-status-label">Status pendaftaran</span><span class="wp-org-status wp-org-status-' . esc_attr($status) . '">' . esc_html($statuses[$status] ?? $status) . '</span></div></div>';
        echo '</div>';
        echo '<nav class="wp-org-tabs">';
        echo '<a class="wp-org-tab ' . ($active_tab === 'profile' ? 'wp-org-tab-active' : '') . '" href="' . esc_url(add_query_arg('profile_tab', 'profile')) . '">' . $this->render_nav_icon('user') . '<span>Profil</span></a>';
        echo '<a class="wp-org-tab ' . ($active_tab === 'edit-profile' ? 'wp-org-tab-active' : '') . '" href="' . esc_url(add_query_arg('profile_tab', 'edit-profile')) . '">' . $this->render_nav_icon('square-pen') . '<span>Edit Profile</span></a>';
        echo '<a class="wp-org-tab ' . ($active_tab === 'edit-account' ? 'wp-org-tab-active' : '') . '" href="' . esc_url(add_query_arg('profile_tab', 'edit-account')) . '">' . $this->render_nav_icon('lock') . '<span>Edit Akun</span></a>';
        if ($premium_enabled) {
            echo '<a class="wp-org-tab ' . ($active_tab === 'premium' ? 'wp-org-tab-active' : '') . '" href="' . esc_url(add_query_arg('profile_tab', 'premium')) . '">' . $this->render_nav_icon('badge-check') . '<span>Member Premium</span></a>';
        }
        echo '<a class="wp-org-tab wp-org-tab-logout" href="' . esc_url($logout_url) . '">' . $this->render_nav_icon('log-out') . '<span>Logout</span></a>';
        echo '</nav>';

        if ($account_notice !== '') {
            echo $this->render_account_notice($account_notice);
        }

        if ($active_tab === 'premium' && $premium_enabled) {
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
        } elseif ($active_tab === 'edit-profile') {
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
        } elseif ($active_tab === 'edit-account') {
            echo $this->render_account_panel();
        } else {
            echo '<div class="wp-org-profile-panel">';
            echo '<div class="wp-org-section-heading"><div><h3>Ringkasan Profil</h3><p class="wp-org-muted">Lihat data anggota Anda di bawah ini.</p></div></div>';
            echo $this->render_profile_summary($user_id, $fields);
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

        if (!MemberData::is_premium_enabled()) {
            wp_safe_redirect($this->get_profile_redirect_url('profile'));
            exit;
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

    public function handle_account_update()
    {
        if (!isset($_POST['wp_org_account_password_submit']) || !is_user_logged_in()) {
            return;
        }

        if (!isset($_POST['wp_org_account_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wp_org_account_nonce'])), 'wp_org_account_action')) {
            return;
        }

        $current_password = (string) wp_unslash($_POST['current_password'] ?? '');
        $new_password = (string) wp_unslash($_POST['new_password'] ?? '');
        $confirm_password = (string) wp_unslash($_POST['confirm_password'] ?? '');
        $user = wp_get_current_user();

        if (!$user || !$user->ID) {
            return;
        }

        if (!wp_check_password($current_password, $user->user_pass, $user->ID)) {
            wp_safe_redirect($this->get_account_notice_url('password_invalid'));
            exit;
        }

        if (strlen($new_password) < 8) {
            wp_safe_redirect($this->get_account_notice_url('password_short'));
            exit;
        }

        if ($new_password !== $confirm_password) {
            wp_safe_redirect($this->get_account_notice_url('password_mismatch'));
            exit;
        }

        wp_set_password($new_password, $user->ID);
        wp_set_auth_cookie($user->ID);
        wp_safe_redirect($this->get_account_notice_url('password_updated'));
        exit;
    }

    public function handle_account_delete()
    {
        if (!isset($_POST['wp_org_account_delete_submit']) || !is_user_logged_in()) {
            return;
        }

        if (!isset($_POST['wp_org_account_delete_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wp_org_account_delete_nonce'])), 'wp_org_account_delete_action')) {
            return;
        }

        $confirmed = !empty($_POST['delete_account_confirm']);

        if (!$confirmed) {
            wp_safe_redirect($this->get_account_notice_url('delete_confirmation_required'));
            exit;
        }

        $user_id = get_current_user_id();

        // Delete all media attachments associated with this user
        $all_meta = get_user_meta($user_id);
        $upload_fields = [];
        $fields = \WpOrg\Support\MemberData::get_all_registration_fields();
        foreach ($fields as $field) {
            if (\WpOrg\Support\MemberData::is_upload_field($field)) {
                $upload_fields[] = 'wp_org_' . $field['key'];
            }
        }
        $upload_fields[] = 'wp_org_premium_proof_url'; // Also include premium proof

        foreach ($all_meta as $meta_key => $meta_values) {
            if (in_array($meta_key, $upload_fields, true)) {
                foreach ($meta_values as $url) {
                    $attachment_id = attachment_url_to_postid($url);
                    if ($attachment_id) {
                        wp_delete_attachment($attachment_id, true); // Force delete permanently
                    }
                }
            }
        }

        require_once ABSPATH . 'wp-admin/includes/user.php';

        wp_logout();
        wp_delete_user($user_id);
        wp_safe_redirect(home_url('/'));
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

    private function get_account_notice_url($notice)
    {
        return add_query_arg([
            'profile_tab' => 'edit-account',
            'account_notice' => $notice,
        ], $this->get_current_profile_url());
    }

    private function handle_premium_proof_upload()
    {
        if (empty($_FILES['premium_proof']) || empty($_FILES['premium_proof']['name'])) {
            return new \WP_Error('premium_proof_required', 'Bukti pembayaran wajib diupload.');
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

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

        // Insert as attachment to show in Media Library
        $attachment = [
            'post_mime_type' => $uploaded['type'],
            'post_title'     => sanitize_file_name($_FILES['premium_proof']['name']),
            'post_content'   => '',
            'post_status'    => 'inherit'
        ];
        $attachment_id = wp_insert_attachment($attachment, $uploaded['file']);

        if (!is_wp_error($attachment_id)) {
            // Generate attachment metadata
            $attachment_data = wp_generate_attachment_metadata($attachment_id, $uploaded['file']);
            wp_update_attachment_metadata($attachment_id, $attachment_data);
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
            $user = get_userdata($user_id);
            $display_name = $user->display_name ?? '';
        }

        $member_card_settings = get_option('wp_org_member_card_settings', []);
        $member_number = MemberData::get_member_number($user_id);
        $region = MemberData::get_user_region_summary($user_id);
        
        // Get registration date
        $registered_at = get_user_meta($user_id, 'wp_org_registered_at', true);
        if (!$registered_at) {
            $user = get_userdata($user_id);
            $registered_at = $user->user_registered ?? current_time('mysql');
        }

        return [
            'organization_name' => sanitize_text_field($member_card_settings['organization_name'] ?? 'WP Org'),
            'background_url' => esc_url_raw($member_card_settings['background_url'] ?? ''),
            'logo_url' => esc_url_raw($member_card_settings['logo_url'] ?? ''),
            'background_color_1' => $this->sanitize_color($member_card_settings['background_color_1'] ?? '#0f3d5e'),
            'background_color_2' => $this->sanitize_color($member_card_settings['background_color_2'] ?? '#135e96'),
            'background_color_3' => $this->sanitize_color($member_card_settings['background_color_3'] ?? '#2e84be'),
            'name' => $display_name,
            'number' => $member_number,
            'region' => $region ?: 'Indonesia',
            'issued_at' => mysql2date('d M Y', $registered_at),
            'filename' => 'kartu-anggota-' . strtolower(sanitize_title($display_name ?: (string) $user_id)) . '.pdf',
        ];
    }

    private function sanitize_color($color)
    {
        if (empty($color)) {
            return '';
        }
        $color = trim($color);

        // Allow 'transparent' keyword
        if (strtolower($color) === 'transparent') {
            return 'transparent';
        }

        // Allow hex colors (3, 6, or 8 digits with alpha)
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $color)) {
            return $color;
        }

        // Allow rgb() and rgba()
        if (preg_match('/^rgba?\s*\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*(0?\.\d+|\d{1,2})\s*)?\)$/', $color)) {
            return $color;
        }

        // Fallback to sanitize_hex_color for safety
        return sanitize_hex_color($color);
    }

    /**
     * @param array<string, string> $data
     */
    private function render_member_card_preview($data)
    {
        $background_style = '';
        if (!empty($data['background_url'])) {
            $background_style = 'background-image:url(' . esc_url($data['background_url']) . ');';
        }

        // Helper to fix transparent colors for preview
        $fix_color = function($color, $default) {
            if (empty($color) || strtolower($color) === 'transparent') {
                return $default;
            }
            // Check for rgba with alpha exactly 0
            if (preg_match('/rgba\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*(0|0\.0+)\s*\)/', $color)) {
                return $default;
            }
            return $color;
        };

        $color1 = $fix_color($data['background_color_1'] ?? '#0f3d5e', '#0f3d5e');
        $color2 = $fix_color($data['background_color_2'] ?? '#135e96', '#135e96');
        $color3 = $fix_color($data['background_color_3'] ?? '#2e84be', '#2e84be');

        $gradient_style = 'background:' . $color1 . ';background-image:linear-gradient(135deg,' . $color2 . ' 0%,' . $color1 . ' 58%,' . $color3 . ' 100%);';

        $logo = '';
        if (!empty($data['logo_url'])) {
            $logo = '<img class="wp-org-member-card-logo" src="' . esc_url($data['logo_url']) . '" alt="Logo organisasi">';
        }

        return '<div class="wp-org-member-card-preview" style="' . $background_style . $gradient_style . '">'
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

        // Sanitize colors to avoid transparent issues with domPDF
        $color1 = $data['background_color_1'] ?? '#0f3d5e';
        $color2 = $data['background_color_2'] ?? '#135e96';
        $color3 = $data['background_color_3'] ?? '#2e84be';

        // Helper to fix only completely transparent colors for domPDF
        $fix_color = function($color, $default) {
            if (empty($color) || strtolower($color) === 'transparent') {
                return $default;
            }
            // Check for rgba with alpha exactly 0
            if (preg_match('/rgba\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*(0|0\.0+)\s*\)/', $color)) {
                return $default;
            }
            return $color;
        };

        $color1 = $fix_color($color1, '#0f3d5e');
        $color2 = $fix_color($color2, '#135e96');
        $color3 = $fix_color($color3, '#2e84be');

        return '<!doctype html><html><head><meta charset="UTF-8"><style>'
            . '@page{size:85.6mm 53.98mm;margin:0}'
            . 'body{margin:0;font-family:DejaVu Sans,Arial,sans-serif;background:#fff}'
            . '.card{position:relative;width:85.6mm;height:53.98mm;overflow:hidden;color:#fff;background:' . $color1 . ';background-image:linear-gradient(135deg,' . $color2 . ' 0%,' . $color1 . ' 58%,' . $color3 . ' 100%)}'
            . '.bg{position:absolute;top:0;right:0;bottom:0;left:0;background-size:cover;background-position:center;opacity:0.9;' . $background_style . '}'
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
            . '.name{font-size:7.8pt;font-weight:700;line-height:1.02;margin-top:.6mm}'
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

    private function render_profile_summary($user_id, $fields)
    {
        $html = '';
        $photo_url = (string) get_user_meta($user_id, 'wp_org_member_photo', true);

        if ($photo_url !== '') {
            $html .= '<div class="wp-org-profile-summary-photo"><img src="' . esc_url($photo_url) . '" alt="Foto profil anggota"></div>';
        }

        $html .= '<div class="wp-org-profile-summary">';

        foreach ($fields as $field) {
            $key = $field['key'];
            $value = get_user_meta($user_id, 'wp_org_' . $key, true);

            if ($key === 'member_photo') {
                continue;
            }

            if (is_array($value)) {
                $value = implode(', ', array_filter(array_map('sanitize_text_field', $value)));
            }

            if (in_array($field['type'], ['region_province', 'region_city', 'region_district'], true)) {
                $suffix = str_replace('_code', '_name', $key);
                $named_value = get_user_meta($user_id, 'wp_org_' . $suffix, true);
                if ($named_value) {
                    $value = $named_value;
                }
            }

            if ($field['type'] === 'image') {
                $value = $value ? '<a href="' . esc_url((string) $value) . '" target="_blank" rel="noopener">Lihat gambar</a>' : '<span class="wp-org-muted">Belum diisi</span>';
            } elseif ($field['type'] === 'file') {
                $value = $value ? '<a href="' . esc_url((string) $value) . '" target="_blank" rel="noopener">Lihat file</a>' : '<span class="wp-org-muted">Belum diisi</span>';
            } else {
                $value = $value !== '' ? esc_html((string) $value) : '<span class="wp-org-muted">Belum diisi</span>';
            }

            $html .= '<div class="wp-org-profile-summary-item"><span class="wp-org-profile-summary-label">' . esc_html($field['label']) . '</span><div class="wp-org-profile-summary-value">' . $value . '</div></div>';
        }

        $html .= '</div>';

        return $html;
    }

    private function render_nav_icon($name)
    {
        $icons = [
            'user' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 21a7 7 0 0 0-14 0"/><circle cx="12" cy="8" r="4"/></svg>',
            'square-pen' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="m16 3 5 5"/><path d="M13 20h8"/><path d="M16.5 3.5a2.1 2.1 0 1 1 3 3L8 18l-4 1 1-4Z"/></svg>',
            'lock' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V8a5 5 0 0 1 10 0v3"/></svg>',
            'badge-check' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 15 2 2 4-4"/><path d="M8.5 4.5 10 2l2 2 2-2 1.5 2.5L18.5 5l-.5 3 2 2-2 2 .5 3-3 .5L14 22l-2-2-2 2-1.5-2.5-3-.5.5-3-2-2 2-2-.5-3Z"/></svg>',
            'log-out' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/><path d="M13 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h8"/></svg>',
        ];

        if (!isset($icons[$name])) {
            return '';
        }

        return '<span class="wp-org-tab-icon">' . $icons[$name] . '</span>';
    }

    private function render_account_notice($notice)
    {
        $messages = [
            'password_updated' => ['success', 'Password berhasil diperbarui.'],
            'password_invalid' => ['error', 'Password saat ini tidak sesuai.'],
            'password_short' => ['error', 'Password baru minimal 8 karakter.'],
            'password_mismatch' => ['error', 'Konfirmasi password baru tidak cocok.'],
            'delete_confirmation_required' => ['error', 'Centang persetujuan sebelum menghapus akun.'],
        ];

        if (!isset($messages[$notice])) {
            return '';
        }

        return '<div class="wp-org-notice wp-org-notice-' . esc_attr($messages[$notice][0]) . '">' . esc_html($messages[$notice][1]) . '</div>';
    }

    private function render_account_panel()
    {
        ob_start();
        echo '<div class="wp-org-profile-panel">';
        echo '<div class="wp-org-profile-section">';
        echo '<div class="wp-org-section-heading"><div><h3>Ubah Password</h3><p class="wp-org-muted">Gunakan password baru minimal 8 karakter.</p></div></div>';
        echo '<form class="wp-org-grid wp-org-account-form" method="post">';
        wp_nonce_field('wp_org_account_action', 'wp_org_account_nonce');
        echo '<div class="wp-org-field wp-org-account-field-wide">';
        echo '<label for="wp_org_current_password">Password Saat Ini</label>';
        echo '<div class="wp-org-password-field"><input id="wp_org_current_password" name="current_password" type="password" required><button class="wp-org-password-toggle" type="button" aria-label="Tampilkan password" aria-pressed="false" data-show-label="Tampilkan password" data-hide-label="Sembunyikan password"><span class="wp-org-password-toggle-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg></span></button></div>';
        echo '</div>';
        echo '<div class="wp-org-field">';
        echo '<label for="wp_org_new_password">Password Baru</label>';
        echo '<div class="wp-org-password-field"><input id="wp_org_new_password" name="new_password" type="password" required><button class="wp-org-password-toggle" type="button" aria-label="Tampilkan password" aria-pressed="false" data-show-label="Tampilkan password" data-hide-label="Sembunyikan password"><span class="wp-org-password-toggle-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg></span></button></div>';
        echo '</div>';
        echo '<div class="wp-org-field">';
        echo '<label for="wp_org_confirm_password">Konfirmasi Password Baru</label>';
        echo '<div class="wp-org-password-field"><input id="wp_org_confirm_password" name="confirm_password" type="password" required><button class="wp-org-password-toggle" type="button" aria-label="Tampilkan password" aria-pressed="false" data-show-label="Tampilkan password" data-hide-label="Sembunyikan password"><span class="wp-org-password-toggle-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg></span></button></div>';
        echo '</div>';
        echo '<div class="wp-org-actions"><button class="wp-org-button" type="submit" name="wp_org_account_password_submit" value="1">Perbarui Password</button></div>';
        echo '</form>';
        echo '</div>';
        echo '<div class="wp-org-profile-section wp-org-danger-zone">';
        echo '<div class="wp-org-section-heading"><div><h3>Hapus Akun</h3><p class="wp-org-muted">Tindakan ini permanen dan tidak bisa dibatalkan.</p></div></div>';
        echo '<div class="wp-org-danger-zone-actions"><button class="wp-org-button wp-org-button-danger wp-org-open-modal" type="button" data-modal-target="wp_org_delete_account_modal">Hapus Akun</button></div>';
        echo '<div id="wp_org_delete_account_modal" class="wp-org-modal" aria-hidden="true"><div class="wp-org-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="wp_org_delete_account_modal_title">';
        echo '<div class="wp-org-modal-header"><div><p class="wp-org-modal-eyebrow">Danger Zone</p><h3 id="wp_org_delete_account_modal_title">Konfirmasi Hapus Akun</h3><p class="wp-org-muted">Akun dan akses login Anda akan dihapus permanen.</p></div><button type="button" class="wp-org-modal-close" aria-label="Tutup">&times;</button></div>';
        echo '<div class="wp-org-delete-modal-note"><strong>Perlu diperhatikan:</strong> tindakan ini tidak dapat dibatalkan setelah dikonfirmasi.</div>';
        echo '<form class="wp-org-grid wp-org-account-form wp-org-delete-account-form" method="post">';
        wp_nonce_field('wp_org_account_delete_action', 'wp_org_account_delete_nonce');
        echo '<div class="wp-org-field wp-org-account-field-wide">';
        echo '<label class="wp-org-account-checkbox"><input id="wp_org_delete_account_confirm" name="delete_account_confirm" type="checkbox" value="1" required><span>Saya memahami akun akan dihapus permanen dan tidak dapat dipulihkan.</span></label>';
        echo '</div>';
        echo '<div class="wp-org-actions"><button class="wp-org-button wp-org-button-secondary wp-org-modal-close-inline" type="button">Batal</button><button class="wp-org-button wp-org-button-danger" type="submit" name="wp_org_account_delete_submit" value="1">Ya, Hapus Akun</button></div>';
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        return (string) ob_get_clean();
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
