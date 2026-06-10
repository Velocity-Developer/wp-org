<?php

namespace WpOrg\Core;

class Installer
{
    public static function activate()
    {
        self::create_roles();
        self::seed_options();
    }

    private static function create_roles()
    {
        $roles = new Roles();
        $roles->register();
    }

    private static function seed_options()
    {
        if (!get_option('wp_org_registration_fields')) {
            update_option('wp_org_registration_fields', [
                ['key' => 'member_photo', 'label' => 'Foto Anggota', 'type' => 'image', 'required' => 0, 'enabled' => 1],
                ['key' => 'full_name', 'label' => 'Nama Lengkap', 'type' => 'text', 'required' => 1, 'enabled' => 1],
                ['key' => 'phone', 'label' => 'Nomor HP', 'type' => 'text', 'required' => 1, 'enabled' => 1],
                ['key' => 'province_code', 'label' => 'Provinsi', 'type' => 'region_province', 'required' => 1, 'enabled' => 1],
                ['key' => 'city_code', 'label' => 'Kota/Kabupaten', 'type' => 'region_city', 'required' => 1, 'enabled' => 1],
                ['key' => 'district_code', 'label' => 'Kecamatan', 'type' => 'region_district', 'required' => 1, 'enabled' => 1],
                ['key' => 'address_detail', 'label' => 'Alamat Detail', 'type' => 'textarea', 'required' => 1, 'enabled' => 1],
                ['key' => 'postal_code', 'label' => 'Kode Pos', 'type' => 'text', 'required' => 0, 'enabled' => 1],
            ]);
        }

        if (!get_option('wp_org_general_settings')) {
            update_option('wp_org_general_settings', [
                'require_approval' => 1,
                'members_page_public' => 0,
                'login_redirect' => '',
                'premium_fee' => 150000,
            ]);
        }

        if (!get_option('wp_org_payment_banks')) {
            update_option('wp_org_payment_banks', [
                [
                    'bank_name' => 'BCA',
                    'account_name' => 'Organisasi Contoh',
                    'account_number' => '1234567890',
                    'enabled' => 1,
                ],
            ]);
        }

        if (!get_option('wp_org_member_card_settings')) {
            update_option('wp_org_member_card_settings', [
                'organization_name' => 'WP Org',
                'background_url' => '',
                'logo_url' => '',
            ]);
        }
    }
}
