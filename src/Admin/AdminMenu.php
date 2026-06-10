<?php

namespace WpOrg\Admin;

use WpOrg\Support\MemberData;

class AdminMenu
{
    public function register()
    {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_post_wp_org_update_member_status', [$this, 'handle_member_status']);
        add_action('admin_post_wp_org_save_fields', [$this, 'handle_save_fields']);
        add_action('admin_post_wp_org_save_settings', [$this, 'handle_save_settings']);
        add_action('admin_post_wp_org_seed_members', [$this, 'handle_seed_members']);
        add_action('admin_post_wp_org_save_payment_banks', [$this, 'handle_save_payment_banks']);
        add_action('admin_post_wp_org_update_premium_status', [$this, 'handle_update_premium_status']);
    }

    public function add_menu()
    {
        add_menu_page('WP Org', 'WP Org', 'wp_org_manage_members', 'wp-org', [$this, 'render_members_page'], 'dashicons-groups');
        add_submenu_page('wp-org', 'Anggota', 'Anggota', 'wp_org_manage_members', 'wp-org', [$this, 'render_members_page']);
        add_submenu_page('wp-org', 'Field Form', 'Field Form', 'wp_org_manage_settings', 'wp-org-fields', [$this, 'render_fields_page']);
        add_submenu_page('wp-org', 'Pengaturan', 'Pengaturan', 'wp_org_manage_settings', 'wp-org-settings', [$this, 'render_settings_page']);
    }

    public function render_members_page()
    {
        if (!current_user_can('wp_org_manage_members')) {
            wp_die('Akses ditolak.');
        }

        $users = get_users([
            'role__in' => ['org_member', 'org_admin'],
            'number' => 100,
            'orderby' => 'registered',
            'order' => 'DESC',
        ]);
        $statuses = MemberData::get_all_statuses();

        $premium_statuses = MemberData::get_premium_statuses();
        echo '<div class="wrap"><h1>Data Anggota</h1><table class="widefat striped"><thead><tr><th>Nama</th><th>Email</th><th>Status</th><th>Premium</th><th>Tanggal Daftar</th><th>Catatan Admin</th><th>Aksi</th></tr></thead><tbody>';

        if (!$users) {
            echo '<tr><td colspan="7">Belum ada anggota.</td></tr>';
        }

        foreach ($users as $user) {
            $status = MemberData::get_status($user->ID);
            $note = get_user_meta($user->ID, 'wp_org_admin_note', true);
            $premium_status = MemberData::get_premium_status($user->ID);
            $premium_ref = get_user_meta($user->ID, 'wp_org_premium_reference', true);
            $premium_proof_url = get_user_meta($user->ID, 'wp_org_premium_proof_url', true);
            echo '<tr><td>' . esc_html($user->display_name) . '</td><td>' . esc_html($user->user_email) . '</td><td>' . esc_html($statuses[$status] ?? $status) . '</td><td>' . esc_html($premium_statuses[$premium_status] ?? $premium_status) . '<br><small>' . esc_html($premium_ref) . '</small>' . ($premium_proof_url ? '<br><a href="' . esc_url($premium_proof_url) . '" target="_blank" rel="noopener">Lihat Bukti</a>' : '') . '</td><td>' . esc_html(get_user_meta($user->ID, 'wp_org_registered_at', true) ?: $user->user_registered) . '</td><td>' . esc_html($note) . '</td><td>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('wp_org_update_member_status_' . $user->ID);
            echo '<input type="hidden" name="action" value="wp_org_update_member_status">';
            echo '<input type="hidden" name="user_id" value="' . esc_attr((string) $user->ID) . '">';
            echo '<select name="status">';
            foreach ($statuses as $key => $label) {
                echo '<option value="' . esc_attr($key) . '"' . selected($status, $key, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select> ';
            echo '<input type="text" name="admin_note" value="' . esc_attr($note) . '" placeholder="Catatan internal"> ';
            submit_button('Simpan', 'secondary', 'submit', false);
            echo '</form><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:8px">';
            wp_nonce_field('wp_org_update_premium_status_' . $user->ID);
            echo '<input type="hidden" name="action" value="wp_org_update_premium_status">';
            echo '<input type="hidden" name="user_id" value="' . esc_attr((string) $user->ID) . '">';
            echo '<select name="premium_status">';
            foreach ($premium_statuses as $key => $label) {
                echo '<option value="' . esc_attr($key) . '"' . selected($premium_status, $key, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select> ';
            echo '<input type="text" name="premium_note" value="' . esc_attr((string) get_user_meta($user->ID, 'wp_org_premium_note', true)) . '" placeholder="Catatan premium"> ';
            submit_button('Update Premium', 'secondary', 'submit', false);
            echo '</form></td></tr>';
        }

        echo '</tbody></table></div>';
    }

    public function render_fields_page()
    {
        if (!current_user_can('wp_org_manage_settings')) {
            wp_die('Akses ditolak.');
        }

        $fields = MemberData::get_all_registration_fields();
        echo '<div class="wrap"><h1>Field Formulir</h1><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('wp_org_save_fields');
        echo '<input type="hidden" name="action" value="wp_org_save_fields">';
        echo '<p>Tambah, hapus, aktifkan, atau nonaktifkan field pendaftaran. Field nonaktif tetap tersimpan tetapi tidak ditampilkan di frontend.</p>';
        echo '<table class="widefat striped wp-org-fields-table"><thead><tr><th>Key</th><th>Label</th><th>Tipe</th><th>Opsi</th><th>Wajib</th><th>Aktif</th><th>Aksi</th></tr></thead><tbody data-next-index="' . esc_attr((string) count($fields)) . '">';

        foreach ($fields as $index => $field) {
            echo $this->render_field_row($index, $field);
        }

        echo '</tbody></table>';
        echo '<p><button type="button" class="button" id="wp-org-add-field">Tambah Field</button></p>';
        echo '<script type="text/html" id="tmpl-wp-org-field-row">' . $this->render_field_row('__index__', [
            'key' => '',
            'label' => '',
            'type' => 'text',
            'required' => 0,
            'enabled' => 1,
            'options' => '',
        ]) . '</script>';
        submit_button('Simpan Field');
        echo '</form></div>';
    }

    public function render_settings_page()
    {
        if (!current_user_can('wp_org_manage_settings')) {
            wp_die('Akses ditolak.');
        }

        $active_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'general';
        $general = get_option('wp_org_general_settings', []);
        $velocity_captcha = get_option('captcha_velocity', []);
        $captcha_enabled = !empty($velocity_captcha['aktif']);
        $captcha_provider = sanitize_text_field($velocity_captcha['provider'] ?? 'google');
        $seed_message = isset($_GET['seeded']) ? absint($_GET['seeded']) : -1;
        $payment_banks = array_values((array) get_option('wp_org_payment_banks', []));

        echo '<div class="wrap"><h1>Pengaturan WP Org</h1>';
        echo '<nav class="nav-tab-wrapper">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=wp-org-settings&tab=general')) . '" class="nav-tab ' . ($active_tab === 'general' ? 'nav-tab-active' : '') . '">Umum</a>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=wp-org-settings&tab=data')) . '" class="nav-tab ' . ($active_tab === 'data' ? 'nav-tab-active' : '') . '">Data</a>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=wp-org-settings&tab=payment-banks')) . '" class="nav-tab ' . ($active_tab === 'payment-banks' ? 'nav-tab-active' : '') . '">Bank Pembayaran</a>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=wp-org-settings&tab=documentation')) . '" class="nav-tab ' . ($active_tab === 'documentation' ? 'nav-tab-active' : '') . '">Dokumentasi</a>';
        echo '</nav>';

        if ($active_tab === 'data') {
            if ($seed_message >= 0) {
                echo '<div class="notice notice-success is-dismissible"><p>Seeder anggota selesai. ' . esc_html((string) $seed_message) . ' anggota baru dibuat.</p></div>';
            }

            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('wp_org_seed_members');
            echo '<input type="hidden" name="action" value="wp_org_seed_members">';
            echo '<table class="form-table"><tbody>';
            echo '<tr><th scope="row">Jumlah Seeder</th><td><input class="small-text" type="number" min="1" max="100" name="seed_total" value="10"><p class="description">Membuat data anggota contoh dengan status approved dan field profil dasar.</p></td></tr>';
            echo '<tr><th scope="row">Password Default</th><td><input class="regular-text" type="text" name="seed_password" value="Member123!"><p class="description">Password ini dipakai untuk seluruh akun hasil seeder.</p></td></tr>';
            echo '</tbody></table>';
            submit_button('Jalankan Seeder Anggota');
            echo '</form>';
            echo '</div>';

            return;
        }

        if ($active_tab === 'payment-banks') {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('wp_org_save_payment_banks');
            echo '<input type="hidden" name="action" value="wp_org_save_payment_banks">';
            echo '<table class="form-table"><tbody>';
            echo '<tr><th scope="row">Biaya Premium</th><td><input class="regular-text" type="number" min="0" name="premium_fee" value="' . esc_attr((string) absint($general['premium_fee'] ?? 0)) . '"><p class="description">Biaya yang ditampilkan ke member saat upgrade premium.</p></td></tr>';
            echo '</tbody></table>';
            echo '<table class="widefat striped wp-org-bank-table"><thead><tr><th>Bank</th><th>Nama Rekening</th><th>Nomor Rekening</th><th>Aktif</th><th>Aksi</th></tr></thead><tbody data-next-index="' . esc_attr((string) count($payment_banks)) . '">';
            foreach ($payment_banks as $index => $bank) {
                echo $this->render_bank_row($index, $bank);
            }
            echo '</tbody></table>';
            echo '<p><button type="button" class="button" id="wp-org-add-bank">Tambah Bank</button></p>';
            echo '<script type="text/html" id="tmpl-wp-org-bank-row">' . $this->render_bank_row('__index__', ['bank_name' => '', 'account_name' => '', 'account_number' => '', 'enabled' => 1]) . '</script>';
            submit_button('Simpan Bank Pembayaran');
            echo '</form></div>';
            return;
        }

        if ($active_tab === 'documentation') {
            echo '<div class="card" style="max-width:960px;padding:20px 24px">';
            echo '<h2>Cara Penggunaan Plugin WP Org</h2>';
            echo '<p>Plugin ini dipakai untuk registrasi anggota, login frontend, pengelolaan profil anggota, daftar anggota, dan pengajuan member premium.</p>';

            echo '<h3>1. Setup Awal</h3>';
            echo '<ol>';
            echo '<li>Buka menu <strong>WP Org &gt; Field Form</strong> untuk mengatur field registrasi yang aktif.</li>';
            echo '<li>Buka menu <strong>WP Org &gt; Pengaturan &gt; Umum</strong> untuk mengatur approval admin, visibilitas daftar anggota, dan redirect login.</li>';
            echo '<li>Jika memakai premium membership, buka tab <strong>Bank Pembayaran</strong> untuk mengisi biaya premium dan rekening tujuan transfer.</li>';
            echo '<li>Captcha mengikuti pengaturan plugin <strong>velocity-addons</strong>.</li>';
            echo '</ol>';

            echo '<h3>2. Shortcode Frontend</h3>';
            echo '<table class="widefat striped"><thead><tr><th>Shortcode</th><th>Fungsi</th></tr></thead><tbody>';
            echo '<tr><td><code>[org_register]</code></td><td>Menampilkan form pendaftaran anggota baru.</td></tr>';
            echo '<tr><td><code>[org_login]</code></td><td>Menampilkan form login anggota.</td></tr>';
            echo '<tr><td><code>[org_members]</code></td><td>Menampilkan daftar anggota sesuai pengaturan akses.</td></tr>';
            echo '<tr><td><code>[org_profile]</code></td><td>Menampilkan halaman profil anggota beserta tab premium.</td></tr>';
            echo '</tbody></table>';

            echo '<h3>3. Alur Registrasi Anggota</h3>';
            echo '<ol>';
            echo '<li>Pengunjung mengisi form dari shortcode <code>[org_register]</code>.</li>';
            echo '<li>Sistem membuat akun WordPress dengan role <code>org_member</code>.</li>';
            echo '<li>Status anggota akan menjadi <code>pending</code> atau <code>approved</code> sesuai pengaturan.</li>';
            echo '<li>Admin memverifikasi anggota dari menu <strong>WP Org &gt; Anggota</strong>.</li>';
            echo '</ol>';

            echo '<h3>4. Alur Member Premium</h3>';
            echo '<ol>';
            echo '<li>Member login lalu buka halaman profil dari shortcode <code>[org_profile]</code>.</li>';
            echo '<li>Pada tab <strong>Member Premium</strong>, member melihat biaya premium dan daftar rekening pembayaran aktif.</li>';
            echo '<li>Member transfer, mengisi referensi pembayaran, lalu upload foto bukti pembayaran.</li>';
            echo '<li>Admin memeriksa pengajuan premium dari menu <strong>WP Org &gt; Anggota</strong> lalu update status premium menjadi <code>active</code>, <code>pending</code>, atau <code>rejected</code>.</li>';
            echo '</ol>';

            echo '<h3>5. Seeder Data</h3>';
            echo '<p>Tab <strong>Data</strong> di pengaturan menyediakan seeder anggota contoh untuk pengujian awal.</p>';

            echo '<h3>6. Data Wilayah</h3>';
            echo '<p>Provinsi, kabupaten/kota, dan kecamatan memakai dataset lokal dari referensi <code>cahyadsn/wilayah</code>.</p>';
            echo '</div></div>';
            return;
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('wp_org_save_settings');
        echo '<input type="hidden" name="action" value="wp_org_save_settings">';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row">Butuh Approval Admin</th><td><input type="checkbox" name="general[require_approval]" value="1"' . checked(!empty($general['require_approval']), true, false) . '></td></tr>';
        echo '<tr><th scope="row">Daftar Anggota Publik</th><td><input type="checkbox" name="general[members_page_public]" value="1"' . checked(!empty($general['members_page_public']), true, false) . '></td></tr>';
        echo '<tr><th scope="row">Login Redirect URL</th><td><input class="regular-text" type="url" name="general[login_redirect]" value="' . esc_attr($general['login_redirect'] ?? '') . '"></td></tr>';
        echo '<tr><th scope="row">Captcha</th><td>';
        echo $captcha_enabled ? '<p>Tersambung ke velocity-addons dan saat ini aktif dengan provider <strong>' . esc_html($captcha_provider) . '</strong>.</p>' : '<p>Captcha mengikuti pengaturan plugin velocity-addons dan saat ini belum aktif.</p>';
        echo '<p><a class="button-secondary" href="' . esc_url(admin_url('admin.php?page=velocity_captcha_settings')) . '">Buka Pengaturan Captcha Velocity Addons</a></p>';
        echo '</td></tr>';
        echo '</tbody></table>';
        submit_button('Simpan Pengaturan');
        echo '</form></div>';
    }

    public function handle_member_status()
    {
        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;

        if (!$user_id || !current_user_can('wp_org_approve_members') || !check_admin_referer('wp_org_update_member_status_' . $user_id)) {
            wp_die('Permintaan tidak valid.');
        }

        $status = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : 'pending';
        $note = isset($_POST['admin_note']) ? sanitize_text_field(wp_unslash($_POST['admin_note'])) : '';
        MemberData::update_status($user_id, $status);
        update_user_meta($user_id, 'wp_org_admin_note', $note);

        wp_safe_redirect(admin_url('admin.php?page=wp-org'));
        exit;
    }

    public function handle_save_fields()
    {
        if (!current_user_can('wp_org_manage_settings') || !check_admin_referer('wp_org_save_fields')) {
            wp_die('Permintaan tidak valid.');
        }

        $fields = isset($_POST['fields']) ? (array) wp_unslash($_POST['fields']) : [];
        $sanitized = [];

        foreach ($fields as $field) {
            if (!empty($field['_delete'])) {
                continue;
            }

            $prepared = MemberData::normalize_field($field);

            if ($prepared) {
                $sanitized[] = $prepared;
            }
        }

        update_option('wp_org_registration_fields', $sanitized);
        wp_safe_redirect(admin_url('admin.php?page=wp-org-fields'));
        exit;
    }

    public function handle_save_settings()
    {
        if (!current_user_can('wp_org_manage_settings') || !check_admin_referer('wp_org_save_settings')) {
            wp_die('Permintaan tidak valid.');
        }

        $general = isset($_POST['general']) ? (array) wp_unslash($_POST['general']) : [];

        update_option('wp_org_general_settings', [
            'require_approval' => !empty($general['require_approval']) ? 1 : 0,
            'members_page_public' => !empty($general['members_page_public']) ? 1 : 0,
            'login_redirect' => esc_url_raw($general['login_redirect'] ?? ''),
        ]);

        wp_safe_redirect(admin_url('admin.php?page=wp-org-settings'));
        exit;
    }

    public function handle_save_payment_banks()
    {
        if (!current_user_can('wp_org_manage_settings') || !check_admin_referer('wp_org_save_payment_banks')) {
            wp_die('Permintaan tidak valid.');
        }

        $banks = isset($_POST['banks']) ? (array) wp_unslash($_POST['banks']) : [];
        $premium_fee = isset($_POST['premium_fee']) ? absint($_POST['premium_fee']) : 0;
        $sanitized = [];

        foreach ($banks as $bank) {
            if (!empty($bank['_delete'])) {
                continue;
            }

            $bank_name = sanitize_text_field($bank['bank_name'] ?? '');
            $account_name = sanitize_text_field($bank['account_name'] ?? '');
            $account_number = sanitize_text_field($bank['account_number'] ?? '');

            if ($bank_name === '' || $account_name === '' || $account_number === '') {
                continue;
            }

            $sanitized[] = [
                'bank_name' => $bank_name,
                'account_name' => $account_name,
                'account_number' => $account_number,
                'enabled' => !empty($bank['enabled']) ? 1 : 0,
            ];
        }

        $general = get_option('wp_org_general_settings', []);
        $general['premium_fee'] = $premium_fee;
        update_option('wp_org_general_settings', $general);
        update_option('wp_org_payment_banks', $sanitized);

        wp_safe_redirect(admin_url('admin.php?page=wp-org-settings&tab=payment-banks'));
        exit;
    }

    public function handle_seed_members()
    {
        if (!current_user_can('wp_org_manage_settings') || !check_admin_referer('wp_org_seed_members')) {
            wp_die('Permintaan tidak valid.');
        }

        $total = isset($_POST['seed_total']) ? absint($_POST['seed_total']) : 10;
        $total = max(1, min(100, $total));
        $password = isset($_POST['seed_password']) ? (string) wp_unslash($_POST['seed_password']) : 'Member123!';

        if (strlen($password) < 8) {
            $password = 'Member123!';
        }

        $created = 0;
        $provinces = [
            ['code' => '31', 'name' => 'DKI Jakarta', 'city_code' => '31.01', 'city_name' => 'Kota Jakarta Pusat', 'district_code' => '31.01.01', 'district_name' => 'Gambir'],
            ['code' => '32', 'name' => 'Jawa Barat', 'city_code' => '32.01', 'city_name' => 'Kota Bandung', 'district_code' => '32.01.01', 'district_name' => 'Coblong'],
            ['code' => '35', 'name' => 'Jawa Timur', 'city_code' => '35.01', 'city_name' => 'Kota Surabaya', 'district_code' => '35.01.01', 'district_name' => 'Tegalsari'],
        ];

        for ($i = 1; $i <= $total; $i++) {
            $seed = wp_generate_password(6, false, false);
            $username = 'member_' . strtolower($seed);
            $email = $username . '@example.org';
            $region = $provinces[($i - 1) % count($provinces)];

            if (username_exists($username) || email_exists($email)) {
                continue;
            }

            $user_id = wp_insert_user([
                'user_login' => $username,
                'user_pass' => $password,
                'user_email' => $email,
                'display_name' => 'Anggota Contoh ' . $i,
                'role' => 'org_member',
            ]);

            if (is_wp_error($user_id)) {
                continue;
            }

            update_user_meta($user_id, 'wp_org_full_name', 'Anggota Contoh ' . $i);
            update_user_meta($user_id, 'wp_org_phone', '08123456' . str_pad((string) $i, 4, '0', STR_PAD_LEFT));
            update_user_meta($user_id, 'wp_org_province_code', $region['code']);
            update_user_meta($user_id, 'wp_org_city_code', $region['city_code']);
            update_user_meta($user_id, 'wp_org_district_code', $region['district_code']);
            update_user_meta($user_id, 'wp_org_address_detail', 'Alamat contoh nomor ' . $i);
            update_user_meta($user_id, 'wp_org_postal_code', '1234' . ($i % 10));
            update_user_meta($user_id, 'wp_org_province_name', $region['name']);
            update_user_meta($user_id, 'wp_org_city_name', $region['city_name']);
            update_user_meta($user_id, 'wp_org_district_name', $region['district_name']);
            update_user_meta($user_id, 'wp_org_registered_at', current_time('mysql'));
            MemberData::update_status($user_id, 'approved');

            $created++;
        }

        wp_safe_redirect(admin_url('admin.php?page=wp-org-settings&tab=data&seeded=' . $created));
        exit;
    }

    public function handle_update_premium_status()
    {
        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;

        if (!$user_id || !current_user_can('wp_org_approve_members') || !check_admin_referer('wp_org_update_premium_status_' . $user_id)) {
            wp_die('Permintaan tidak valid.');
        }

        $premium_status = isset($_POST['premium_status']) ? sanitize_key(wp_unslash($_POST['premium_status'])) : 'none';
        $premium_note = isset($_POST['premium_note']) ? sanitize_text_field(wp_unslash($_POST['premium_note'])) : '';

        MemberData::update_premium_status($user_id, $premium_status);
        update_user_meta($user_id, 'wp_org_premium_note', $premium_note);

        wp_safe_redirect(admin_url('admin.php?page=wp-org'));
        exit;
    }

    public function enqueue_admin_assets($hook_suffix)
    {
        if (!in_array($hook_suffix, ['wp-org_page_wp-org-fields', 'wp-org_page_wp-org-settings'], true)) {
            return;
        }

        wp_add_inline_style('wp-admin', '.wp-org-fields-table input[type="text"],.wp-org-fields-table select,.wp-org-fields-table textarea,.wp-org-bank-table input[type="text"]{width:100%}.wp-org-fields-table textarea{min-height:64px}.wp-org-field-row-disabled{opacity:.6}');
        wp_add_inline_script('jquery-core', <<<'JS'
jQuery(function($){
    function syncRowState($row) {
        var isEnabled = $row.find('.wp-org-field-enabled').is(':checked');
        $row.toggleClass('wp-org-field-row-disabled', !isEnabled);
    }

    $(document).on('click', '#wp-org-add-field', function() {
        var $tbody = $('.wp-org-fields-table tbody');
        var nextIndex = parseInt($tbody.attr('data-next-index'), 10) || 0;
        var template = $('#tmpl-wp-org-field-row').html().replace(/__index__/g, nextIndex);
        $tbody.append(template);
        $tbody.attr('data-next-index', nextIndex + 1);
    });

    $(document).on('click', '.wp-org-remove-field', function() {
        var $row = $(this).closest('tr');
        $row.find('.wp-org-field-delete').val('1');
        $row.remove();
    });

    $(document).on('change', '.wp-org-field-enabled', function() {
        syncRowState($(this).closest('tr'));
    });

    $('.wp-org-fields-table tbody tr').each(function() {
        syncRowState($(this));
    });

    $(document).on('click', '#wp-org-add-bank', function() {
        var $tbody = $('.wp-org-bank-table tbody');
        var nextIndex = parseInt($tbody.attr('data-next-index'), 10) || 0;
        var template = $('#tmpl-wp-org-bank-row').html().replace(/__index__/g, nextIndex);
        $tbody.append(template);
        $tbody.attr('data-next-index', nextIndex + 1);
    });

    $(document).on('click', '.wp-org-remove-bank', function() {
        var $row = $(this).closest('tr');
        $row.find('.wp-org-bank-delete').val('1');
        $row.remove();
    });
});
JS
);
    }

    private function render_field_row($index, $field)
    {
        $types = [
            'text' => 'Text',
            'textarea' => 'Textarea',
            'email' => 'Email',
            'number' => 'Number',
            'date' => 'Date',
            'select' => 'Select',
            'radio' => 'Radio',
            'checkbox' => 'Checkbox',
            'region_province' => 'Provinsi',
            'region_city' => 'Kota/Kabupaten',
            'region_district' => 'Kecamatan',
        ];

        $type_options = '';
        foreach ($types as $value => $label) {
            $type_options .= '<option value="' . esc_attr($value) . '"' . selected($field['type'], $value, false) . '>' . esc_html($label) . '</option>';
        }

        $row_class = !empty($field['enabled']) ? '' : ' class="wp-org-field-row-disabled"';

        return '<tr' . $row_class . '>'
            . '<td><input type="hidden" class="wp-org-field-delete" name="fields[' . esc_attr((string) $index) . '][_delete]" value="0"><input type="text" name="fields[' . esc_attr((string) $index) . '][key]" value="' . esc_attr($field['key']) . '" placeholder="mis. full_name"></td>'
            . '<td><input type="text" name="fields[' . esc_attr((string) $index) . '][label]" value="' . esc_attr($field['label']) . '" placeholder="Label field"></td>'
            . '<td><select name="fields[' . esc_attr((string) $index) . '][type]">' . $type_options . '</select></td>'
            . '<td><textarea name="fields[' . esc_attr((string) $index) . '][options]" placeholder="Satu opsi per baris">' . esc_textarea($field['options'] ?? '') . '</textarea></td>'
            . '<td><input type="checkbox" name="fields[' . esc_attr((string) $index) . '][required]" value="1"' . checked(!empty($field['required']), true, false) . '></td>'
            . '<td><input type="checkbox" class="wp-org-field-enabled" name="fields[' . esc_attr((string) $index) . '][enabled]" value="1"' . checked(!empty($field['enabled']), true, false) . '></td>'
            . '<td><button type="button" class="button-link-delete wp-org-remove-field">Hapus</button></td>'
            . '</tr>';
    }

    private function render_bank_row($index, $bank)
    {
        return '<tr>'
            . '<td><input type="hidden" class="wp-org-bank-delete" name="banks[' . esc_attr((string) $index) . '][_delete]" value="0"><input type="text" name="banks[' . esc_attr((string) $index) . '][bank_name]" value="' . esc_attr($bank['bank_name'] ?? '') . '" placeholder="Nama bank"></td>'
            . '<td><input type="text" name="banks[' . esc_attr((string) $index) . '][account_name]" value="' . esc_attr($bank['account_name'] ?? '') . '" placeholder="Nama pemilik rekening"></td>'
            . '<td><input type="text" name="banks[' . esc_attr((string) $index) . '][account_number]" value="' . esc_attr($bank['account_number'] ?? '') . '" placeholder="Nomor rekening"></td>'
            . '<td><input type="checkbox" name="banks[' . esc_attr((string) $index) . '][enabled]" value="1"' . checked(!empty($bank['enabled']), true, false) . '></td>'
            . '<td><button type="button" class="button-link-delete wp-org-remove-bank">Hapus</button></td>'
            . '</tr>';
    }
}
