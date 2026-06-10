<?php

namespace WpOrg\Frontend;

use WpOrg\Support\Captcha;
use WpOrg\Support\MemberData;
use WpOrg\Support\Regions;

class Auth
{
    public function register()
    {
        add_shortcode('org_register', [$this, 'render_register_shortcode']);
        add_shortcode('org_login', [$this, 'render_login_shortcode']);
        add_action('init', [$this, 'handle_post_actions']);
    }

    public function handle_post_actions()
    {
        if (isset($_POST['wp_org_register_submit'])) {
            $this->handle_register();
        }

        if (isset($_POST['wp_org_login_submit'])) {
            $this->handle_login();
        }

        if (isset($_GET['wp_org_logout'])) {
            wp_logout();
            wp_safe_redirect(remove_query_arg('wp_org_logout'));
            exit;
        }
    }

    public function render_register_shortcode()
    {
        if (is_user_logged_in()) {
            return $this->render_notice('Anda sudah login.', 'success');
        }

        $fields = MemberData::get_registration_fields();
        $captcha = new Captcha();
        $regions = new Regions();

        ob_start();
        echo '<div class="wp-org-card">';
        echo '<h2>Daftar Anggota</h2>';
        $this->output_flash();
        echo '<form class="wp-org-grid wp-org-region-form" method="post">';
        wp_nonce_field('wp_org_register_action', 'wp_org_register_nonce');
        echo '<div class="wp-org-grid wp-org-grid-2">';
        $this->render_field('Nama Pengguna', 'username', 'text', true);
        $this->render_field('Email', 'email', 'email', true);
        $this->render_field('Password', 'password', 'password', true);
        echo '</div>';

        foreach ($fields as $field) {
            $value = isset($_POST[$field['key']]) ? wp_unslash($_POST[$field['key']]) : '';
            echo $this->render_dynamic_field($field, $value, $regions);
        }

        echo $captcha->render('org_register');
        echo '<div class="wp-org-actions"><button class="wp-org-button" type="submit" name="wp_org_register_submit" value="1">Kirim Pendaftaran</button></div>';
        echo '</form></div>';

        return (string) ob_get_clean();
    }

    public function render_login_shortcode()
    {
        if (is_user_logged_in()) {
            return $this->render_notice('Anda sudah login. <a href="' . esc_url(add_query_arg('wp_org_logout', '1')) . '">Logout</a>', 'success');
        }

        ob_start();
        echo '<div class="wp-org-card">';
        echo '<h2>Login Anggota</h2>';
        $this->output_flash();
        echo '<form class="wp-org-grid" method="post">';
        wp_nonce_field('wp_org_login_action', 'wp_org_login_nonce');
        echo $this->render_field('Username atau Email', 'log', 'text', true, false);
        echo $this->render_field('Password', 'pwd', 'password', true, false);
        echo '<div class="wp-org-actions"><button class="wp-org-button" type="submit" name="wp_org_login_submit" value="1">Login</button></div>';
        echo '</form></div>';

        return (string) ob_get_clean();
    }

    private function handle_register()
    {
        if (!isset($_POST['wp_org_register_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wp_org_register_nonce'])), 'wp_org_register_action')) {
            $this->set_flash('error', 'Permintaan tidak valid.');
            return;
        }

        $captcha = new Captcha();
        $captcha_result = $captcha->verify_submission();
        if (is_wp_error($captcha_result)) {
            $this->set_flash('error', $captcha_result->get_error_message());
            return;
        }

        $errors = MemberData::validate_submission($_POST);
        if ($errors->has_errors()) {
            $this->set_flash('error', implode(' ', $errors->get_error_messages()));
            return;
        }

        $user_id = wp_insert_user([
            'user_login' => sanitize_user(wp_unslash($_POST['username'])),
            'user_email' => sanitize_email(wp_unslash($_POST['email'])),
            'user_pass' => (string) wp_unslash($_POST['password']),
            'display_name' => sanitize_text_field(wp_unslash($_POST['full_name'] ?? $_POST['username'])),
            'role' => 'org_member',
        ]);

        if (is_wp_error($user_id)) {
            $this->set_flash('error', $user_id->get_error_message());
            return;
        }

        MemberData::save_profile_fields($user_id, $_POST);
        MemberData::update_status($user_id, $this->requires_approval() ? 'pending' : 'approved');
        update_user_meta($user_id, 'wp_org_registered_at', current_time('mysql'));

        $message = $this->requires_approval() ? 'Pendaftaran berhasil dikirim dan menunggu approval admin.' : 'Pendaftaran berhasil. Anda dapat login.';
        $this->set_flash('success', $message);
        wp_safe_redirect(wp_get_referer() ? wp_get_referer() : home_url('/'));
        exit;
    }

    private function handle_login()
    {
        if (!isset($_POST['wp_org_login_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wp_org_login_nonce'])), 'wp_org_login_action')) {
            $this->set_flash('error', 'Permintaan login tidak valid.');
            return;
        }

        $login = sanitize_text_field(wp_unslash($_POST['log'] ?? ''));
        $password = (string) wp_unslash($_POST['pwd'] ?? '');
        $user = get_user_by('email', $login);
        if (!$user) {
            $user = get_user_by('login', $login);
        }

        $credentials = [
            'user_login' => $user ? $user->user_login : $login,
            'user_password' => $password,
            'remember' => true,
        ];

        $signed_in = wp_signon($credentials, is_ssl());
        if (is_wp_error($signed_in)) {
            $this->set_flash('error', 'Login gagal. Periksa kembali kredensial Anda.');
            return;
        }

        if ($this->requires_approval() && MemberData::get_status($signed_in->ID) !== 'approved' && !current_user_can('wp_org_manage_members')) {
            wp_logout();
            $this->set_flash('error', 'Akun Anda belum disetujui admin.');
            return;
        }

        $settings = get_option('wp_org_general_settings', []);
        $redirect = !empty($settings['login_redirect']) ? $settings['login_redirect'] : home_url('/');
        wp_safe_redirect($redirect);
        exit;
    }

    private function render_field($label, $name, $type, $required, $echo = true)
    {
        $value = isset($_POST[$name]) ? esc_attr(wp_unslash($_POST[$name])) : '';
        $required_attr = $required ? ' required' : '';
        $html = '<div class="wp-org-field"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label><input id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" type="' . esc_attr($type) . '" value="' . $value . '"' . $required_attr . '></div>';

        if ($echo) {
            echo $html;
        }

        return $html;
    }

    private function render_dynamic_field($field, $value, Regions $regions)
    {
        $key = $field['key'];
        $label = $field['label'];
        $required = !empty($field['required']) ? ' required' : '';
        $html = '<div class="wp-org-field"><label for="' . esc_attr($key) . '">' . esc_html($label) . '</label>';
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
        } elseif ($field['type'] === 'region_province') {
            $html .= '<select id="' . esc_attr($key) . '" class="wp-org-province" name="' . esc_attr($key) . '"' . $required . '><option value="">Pilih provinsi</option>';
            foreach ($regions->get_provinces() as $province) {
                $selected = selected($value, $province['code'], false);
                $html .= '<option value="' . esc_attr($province['code']) . '"' . $selected . '>' . esc_html($province['name']) . '</option>';
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

    private function set_flash($type, $message)
    {
        if (!session_id()) {
            session_start();
        }

        $_SESSION['wp_org_flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    private function output_flash()
    {
        if (!session_id()) {
            session_start();
        }

        if (empty($_SESSION['wp_org_flash'])) {
            return;
        }

        $flash = $_SESSION['wp_org_flash'];
        unset($_SESSION['wp_org_flash']);

        echo $this->render_notice($flash['message'], $flash['type']);
    }

    private function render_notice($message, $type)
    {
        return '<div class="wp-org-notice wp-org-notice-' . esc_attr($type === 'success' ? 'success' : 'error') . '">' . wp_kses_post($message) . '</div>';
    }

    private function requires_approval()
    {
        $settings = get_option('wp_org_general_settings', []);

        return !empty($settings['require_approval']);
    }
}
