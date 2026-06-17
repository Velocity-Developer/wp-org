<?php

namespace WpOrg\Support;

class MemberData
{
    public static function get_all_registration_fields()
    {
        $fields = get_option('wp_org_registration_fields', []);

        if (!is_array($fields)) {
            return [];
        }

        $normalized = [];
        $seen_keys = [];

        foreach ($fields as $field) {
            $prepared = self::normalize_field($field);

            if (!$prepared || isset($seen_keys[$prepared['key']])) {
                continue;
            }

            $seen_keys[$prepared['key']] = true;
            $normalized[] = $prepared;
        }

        if (!isset($seen_keys['member_photo'])) {
            array_unshift($normalized, [
                'key' => 'member_photo',
                'label' => 'Foto Anggota',
                'type' => 'image',
                'required' => 0,
                'enabled' => 1,
                'options' => '',
            ]);
        }

        return array_values($normalized);
    }

    public static function get_registration_fields()
    {
        return array_values(array_filter(self::get_all_registration_fields(), static function ($field) {
            return !empty($field['enabled']);
        }));
    }

    public static function get_all_statuses()
    {
        return [
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
        ];
    }

    public static function get_status($user_id)
    {
        $status = get_user_meta($user_id, 'wp_org_status', true);
        return self::normalize_status($status);
    }

    public static function update_status($user_id, $status)
    {
        update_user_meta($user_id, 'wp_org_status', self::normalize_status($status));
    }

    public static function get_premium_statuses()
    {
        return [
            'none' => 'Belum Premium',
            'pending' => 'Menunggu Verifikasi',
            'active' => 'Premium Aktif',
            'rejected' => 'Ditolak',
        ];
    }

    public static function get_premium_status($user_id)
    {
        $status = get_user_meta($user_id, 'wp_org_premium_status', true);

        return $status ? $status : 'none';
    }

    public static function is_premium_enabled()
    {
        $settings = get_option('wp_org_general_settings', []);

        return !isset($settings['premium_enabled']) || !empty($settings['premium_enabled']);
    }

    public static function update_premium_status($user_id, $status)
    {
        update_user_meta($user_id, 'wp_org_premium_status', $status);
    }

    public static function get_member_number_prefix()
    {
        $settings = get_option('wp_org_member_card_settings', []);
        $prefix = sanitize_text_field($settings['member_number_prefix'] ?? 'ORG');
        $prefix = trim($prefix);

        return $prefix !== '' ? $prefix : 'ORG';
    }

    public static function get_member_number($user_id)
    {
        return self::get_member_number_prefix() . '-' . str_pad((string) $user_id, 6, '0', STR_PAD_LEFT);
    }

    public static function save_profile_fields($user_id, $data)
    {
        self::save_profile_fields_with_definitions($user_id, $data, self::get_registration_fields());
    }

    public static function save_profile_fields_with_definitions($user_id, $data, array $fields)
    {
        $regions = new Regions();
        $full_name = '';
        $region_codes = [
            'province' => '',
            'city' => '',
            'district' => '',
        ];

        foreach ($fields as $field) {
            $key = $field['key'];

            if (self::is_upload_field($field)) {
                $uploaded_url = self::handle_upload_field($key);
                if ($uploaded_url) {
                    update_user_meta($user_id, 'wp_org_' . $key, $uploaded_url);
                }
                continue;
            }

            $value = isset($data[$key]) ? $data[$key] : '';

            if ($field['type'] === 'checkbox') {
                $value = is_array($value) ? array_map('sanitize_text_field', wp_unslash($value)) : [];
            } else {
                $value = sanitize_textarea_field(wp_unslash((string) $value));
            }

            update_user_meta($user_id, 'wp_org_' . $key, $value);

            if ($key === 'full_name' && is_string($value)) {
                $full_name = $value;
            }

            if ($field['type'] === 'region_province' && is_string($value)) {
                $region_codes['province'] = $value;
                update_user_meta($user_id, 'wp_org_' . self::get_region_name_key($key), $regions->get_province_name($value));
            }

            if ($field['type'] === 'region_city' && is_string($value)) {
                $region_codes['city'] = $value;
                update_user_meta($user_id, 'wp_org_' . self::get_region_name_key($key), $regions->get_city_name($value));
            }

            if ($field['type'] === 'region_district' && is_string($value)) {
                $region_codes['district'] = $value;
                update_user_meta($user_id, 'wp_org_' . self::get_region_name_key($key), $regions->get_district_name($value));
            }
        }

        update_user_meta($user_id, 'wp_org_province_name', $regions->get_province_name($region_codes['province']));
        update_user_meta($user_id, 'wp_org_city_name', $regions->get_city_name($region_codes['city']));
        update_user_meta($user_id, 'wp_org_district_name', $regions->get_district_name($region_codes['district']));

        if ($full_name !== '') {
            wp_update_user([
                'ID' => $user_id,
                'display_name' => $full_name,
            ]);
        }
    }

    public static function validate_submission($data, $is_update = false)
    {
        $errors = new \WP_Error();
        $fields = self::get_registration_fields();
        $regions = new Regions();

        foreach ($fields as $field) {
            $key = $field['key'];
            $required = !empty($field['required']);
            $value = isset($data[$key]) ? $data[$key] : '';

            if (self::is_upload_field($field)) {
                $file_name = isset($_FILES[$key]['name']) ? (string) $_FILES[$key]['name'] : '';

                if ($required && $file_name === '' && !$is_update) {
                    $errors->add($key . '_required', sprintf('%s wajib diupload.', $field['label']));
                }

                continue;
            }

            if ($required && $value === '') {
                $errors->add($key . '_required', sprintf('%s wajib diisi.', $field['label']));
            }
        }

        if (!$is_update) {
            $email = isset($data['email']) ? sanitize_email(wp_unslash($data['email'])) : '';
            $username = isset($data['username']) ? sanitize_user(wp_unslash($data['username'])) : '';
            $password = isset($data['password']) ? (string) wp_unslash($data['password']) : '';

            if (!$email || !is_email($email)) {
                $errors->add('email_invalid', 'Email tidak valid.');
            } elseif (email_exists($email)) {
                $errors->add('email_exists', 'Email sudah digunakan.');
            }

            if (!$username) {
                $errors->add('username_required', 'Username wajib diisi.');
            } elseif (username_exists($username)) {
                $errors->add('username_exists', 'Username sudah digunakan.');
            }

            if (strlen($password) < 8) {
                $errors->add('password_length', 'Password minimal 8 karakter.');
            }
        }

        $province_code = isset($data['province_code']) ? sanitize_text_field(wp_unslash($data['province_code'])) : '';
        $city_code = isset($data['city_code']) ? sanitize_text_field(wp_unslash($data['city_code'])) : '';
        $district_code = isset($data['district_code']) ? sanitize_text_field(wp_unslash($data['district_code'])) : '';

        if ($province_code && !$regions->get_province_name($province_code)) {
            $errors->add('province_invalid', 'Provinsi tidak valid.');
        }

        if ($city_code) {
            $city_valid = false;
            foreach ($regions->get_cities($province_code) as $city) {
                if ($city['code'] === $city_code) {
                    $city_valid = true;
                    break;
                }
            }

            if (!$city_valid) {
                $errors->add('city_invalid', 'Kota/Kabupaten tidak sesuai dengan provinsi yang dipilih.');
            }
        }

        if ($district_code) {
            $district_valid = false;
            foreach ($regions->get_districts($city_code) as $district) {
                if ($district['code'] === $district_code) {
                    $district_valid = true;
                    break;
                }
            }

            if (!$district_valid) {
                $errors->add('district_invalid', 'Kecamatan tidak sesuai dengan kota/kabupaten yang dipilih.');
            }
        }

        return $errors;
    }

    public static function normalize_field($field)
    {
        $key = sanitize_key($field['key'] ?? '');
        $label = sanitize_text_field($field['label'] ?? '');
        $type = sanitize_key($field['type'] ?? 'text');

        if ($key === 'foto_anggota' && $type === 'image' && sanitize_title($label) === 'foto-anggota') {
            $key = 'member_photo';
        }

        if ($key === '' || $label === '' || $type === '') {
            return null;
        }

        return [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'required' => !empty($field['required']) ? 1 : 0,
            'enabled' => !empty($field['enabled']) ? 1 : 0,
            'options' => self::sanitize_field_options($field['options'] ?? ''),
        ];
    }

    public static function sanitize_field_options($options)
    {
        if (is_array($options)) {
            $options = implode("\n", $options);
        }

        $options = (string) $options;
        $lines = preg_split('/\r\n|\r|\n/', $options);
        $sanitized = [];

        foreach ($lines as $line) {
            $line = sanitize_text_field($line);

            if ($line !== '') {
                $sanitized[] = $line;
            }
        }

        return implode("\n", $sanitized);
    }

    public static function get_field_options($field)
    {
        $options = preg_split('/\r\n|\r|\n/', (string) ($field['options'] ?? ''));

        return array_values(array_filter(array_map('trim', $options), static function ($option) {
            return $option !== '';
        }));
    }

    public static function is_upload_field($field)
    {
        return in_array($field['type'], ['image', 'file'], true);
    }

    public static function normalize_status($status)
    {
        $status = sanitize_key((string) $status);

        if (in_array($status, ['approved', 'approve', 'aprove'], true)) {
            return 'approved';
        }

        if (in_array($status, ['rejected', 'reject'], true)) {
            return 'rejected';
        }

        if ($status === 'pending' || $status === '') {
            return 'pending';
        }

        return 'pending';
    }

    public static function get_user_region_summary($user_id)
    {
        $fields = self::get_all_registration_fields();
        $regions = new Regions();
        $province = '';
        $city = '';
        $province_code = '';
        $city_code = '';

        foreach ($fields as $field) {
            $key = $field['key'];

            if ($field['type'] === 'region_province' && $province === '') {
                $province = (string) get_user_meta($user_id, 'wp_org_' . self::get_region_name_key($key), true);
                $province_code = (string) get_user_meta($user_id, 'wp_org_' . $key, true);
            }

            if ($field['type'] === 'region_city' && $city === '') {
                $city = (string) get_user_meta($user_id, 'wp_org_' . self::get_region_name_key($key), true);
                $city_code = (string) get_user_meta($user_id, 'wp_org_' . $key, true);
            }
        }

        if ($province === '') {
            $province = (string) get_user_meta($user_id, 'wp_org_province_name', true);
        }

        if ($city === '') {
            $city = (string) get_user_meta($user_id, 'wp_org_city_name', true);
        }

        if ($province_code === '') {
            $province_code = (string) get_user_meta($user_id, 'wp_org_province_code', true);
        }

        if ($city_code === '') {
            $city_code = (string) get_user_meta($user_id, 'wp_org_city_code', true);
        }

        if ($province === '' && $province_code !== '') {
            $province = $regions->get_province_name($province_code);
        }

        if ($city === '' && $city_code !== '') {
            $city = $regions->get_city_name($city_code);
        }

        return trim($city . ', ' . $province, ', ');
    }

    public static function get_region_name_key($key)
    {
        return str_ends_with($key, '_code') ? substr($key, 0, -5) . '_name' : $key . '_name';
    }

    private static function handle_upload_field($key)
    {
        if (empty($_FILES[$key]) || empty($_FILES[$key]['name'])) {
            return '';
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $uploaded = wp_handle_upload($_FILES[$key], [
            'test_form' => false,
            'mimes' => [
                'jpg|jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                'pdf' => 'application/pdf',
            ],
        ]);

        if (!empty($uploaded['error'])) {
            return '';
        }

        return esc_url_raw($uploaded['url']);
    }
}
