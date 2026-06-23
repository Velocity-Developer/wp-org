<?php

namespace WpOrg\Admin;

use WpOrg\Support\MemberData;
use WpOrg\Support\Regions;

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
        add_action('admin_post_wp_org_import_subscribers', [$this, 'handle_import_subscribers']);
        add_action('admin_post_wp_org_migrate_old_members', [$this, 'handle_migrate_old_members']);
        add_action('admin_post_wp_org_save_payment_banks', [$this, 'handle_save_payment_banks']);
        add_action('admin_post_wp_org_save_member_card_settings', [$this, 'handle_save_member_card_settings']);
        add_action('admin_post_wp_org_generate_pages', [$this, 'handle_generate_pages']);
        add_action('admin_post_wp_org_update_premium_status', [$this, 'handle_update_premium_status']);
        add_action('admin_post_wp_org_update_member_profile', [$this, 'handle_update_member_profile']);
        add_action('admin_post_wp_org_reset_member_numbers', [$this, 'handle_reset_member_numbers']);
        add_action('admin_post_wp_org_delete_member', [$this, 'handle_delete_member']);
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

        $search = isset($_GET['member_search']) ? sanitize_text_field(wp_unslash($_GET['member_search'])) : '';
        $member_status_filter = isset($_GET['member_status']) ? sanitize_key(wp_unslash($_GET['member_status'])) : '';
        $premium_status_filter = isset($_GET['premium_status']) ? sanitize_key(wp_unslash($_GET['premium_status'])) : '';
        $statuses = MemberData::get_all_statuses();
        $premium_statuses = MemberData::get_premium_statuses();
        $meta_query = [];

        if ($member_status_filter !== '' && isset($statuses[$member_status_filter])) {
            $meta_query[] = [
                'key' => 'wp_org_status',
                'value' => $member_status_filter,
            ];
        }

        if ($premium_status_filter !== '' && isset($premium_statuses[$premium_status_filter])) {
            $meta_query[] = [
                'key' => 'wp_org_premium_status',
                'value' => $premium_status_filter,
            ];
        }

        $user_args = [
            'role__in' => ['org_member', 'org_admin'],
            'number' => -1,
        ];

        if ($search !== '') {
            $user_args['search'] = '*' . $search . '*';
            $user_args['search_columns'] = ['user_login', 'user_email', 'display_name'];
        }

        if ($meta_query !== []) {
            $user_args['meta_query'] = $meta_query;
        }

        // Get actual total count
        $count_users = count_users();
        $total_org_member = isset($count_users['avail_roles']['org_member']) ? $count_users['avail_roles']['org_member'] : 0;
        $total_org_admin = isset($count_users['avail_roles']['org_admin']) ? $count_users['avail_roles']['org_admin'] : 0;
        $total_anggota = $total_org_member + $total_org_admin;

        $users = get_users($user_args);

        // Sort users: pending first, then by member number descending
        usort($users, function($a, $b) {
            $status_a = MemberData::get_status($a->ID);
            $status_b = MemberData::get_status($b->ID);
            
            // Pending first
            if ($status_a === 'pending' && $status_b !== 'pending') {
                return -1;
            }
            if ($status_a !== 'pending' && $status_b === 'pending') {
                return 1;
            }
            
            // If same status, sort by member number descending
            $num_a = (int) get_user_meta($a->ID, 'wp_org_member_number', true);
            $num_b = (int) get_user_meta($b->ID, 'wp_org_member_number', true);
            return $num_b - $num_a;
        });
        $status_totals = array_fill_keys(array_keys($statuses), 0);
        $premium_totals = array_fill_keys(array_keys($premium_statuses), 0);

        // Get all users without limit for calculating status totals
        $all_users_args = $user_args;
        $all_users_args['number'] = -1;
        $all_users = get_users($all_users_args);

        foreach ($all_users as $user) {
            $member_status = MemberData::get_status($user->ID);
            $premium_status = MemberData::get_premium_status($user->ID);

            if (isset($status_totals[$member_status])) {
                $status_totals[$member_status]++;
            }

            if (isset($premium_totals[$premium_status])) {
                $premium_totals[$premium_status]++;
            }
        }

        echo '<div class="wrap wp-org-admin">';
        echo '<div class="wp-org-admin-hero"><div><h1>Data Anggota</h1><p>Kelola status pendaftaran, premium membership, dan catatan internal anggota dalam satu tampilan.</p></div></div>';
        echo '<div class="wp-org-admin-summary">';
        echo '<div class="wp-org-admin-stat"><span class="wp-org-admin-stat-label">Total Anggota</span><strong>' . esc_html((string) $total_anggota) . '</strong></div>';
        echo '<div class="wp-org-admin-stat"><span class="wp-org-admin-stat-label">Approved</span><strong>' . esc_html((string) ($status_totals['approved'] ?? 0)) . '</strong></div>';
        echo '<div class="wp-org-admin-stat"><span class="wp-org-admin-stat-label">Pending</span><strong>' . esc_html((string) ($status_totals['pending'] ?? 0)) . '</strong></div>';
        echo '<div class="wp-org-admin-stat"><span class="wp-org-admin-stat-label">Premium Aktif</span><strong>' . esc_html((string) ($premium_totals['active'] ?? 0)) . '</strong></div>';
        echo '</div>';
        echo '<div class="wp-org-admin-card">';
        echo '<form method="get" class="wp-org-admin-filters">';
        echo '<input type="hidden" name="page" value="wp-org">';
        echo '<div class="wp-org-admin-filters-grid">';
        echo '<label class="wp-org-admin-filter-field"><span>Cari Anggota</span><input type="text" name="member_search" value="' . esc_attr($search) . '" placeholder="Nama, email, atau username"></label>';
        echo '<label class="wp-org-admin-filter-field"><span>Status Anggota</span><select name="member_status"><option value="">Semua status</option>';
        foreach ($statuses as $status_key => $status_label) {
            echo '<option value="' . esc_attr($status_key) . '"' . selected($member_status_filter, $status_key, false) . '>' . esc_html($status_label) . '</option>';
        }
        echo '</select></label>';
        echo '<label class="wp-org-admin-filter-field"><span>Status Premium</span><select name="premium_status"><option value="">Semua premium</option>';
        foreach ($premium_statuses as $premium_key => $premium_label) {
            echo '<option value="' . esc_attr($premium_key) . '"' . selected($premium_status_filter, $premium_key, false) . '>' . esc_html($premium_label) . '</option>';
        }
        echo '</select></label>';
        echo '</div>';
        echo '<div class="wp-org-admin-filter-actions"><button type="submit" class="button button-primary">Filter</button><a class="button button-secondary" href="' . esc_url(admin_url('admin.php?page=wp-org')) . '">Reset</a></div>';
        echo '</form></div>';
        echo '<div class="wp-org-admin-card wp-org-admin-table-card"><table class="widefat striped wp-org-admin-table"><thead><tr><th>Nama</th><th>No. Anggota</th><th>Email</th><th>Wilayah</th><th>Status</th><th>Premium</th><th>Tanggal Daftar</th><th>Catatan Admin</th><th>Aksi</th></tr></thead><tbody>';

        if (!$users) {
            echo '<tr><td colspan="9">Belum ada anggota.</td></tr>';
        }

        foreach ($users as $user) {
            $member_number = $this->get_member_number($user->ID);
            $status = MemberData::get_status($user->ID);
            $note = get_user_meta($user->ID, 'wp_org_admin_note', true);
            $premium_status = MemberData::get_premium_status($user->ID);
            $premium_ref = get_user_meta($user->ID, 'wp_org_premium_reference', true);
            $premium_proof_url = get_user_meta($user->ID, 'wp_org_premium_proof_url', true);
            $region = MemberData::get_user_region_summary($user->ID);
            $full_name = (string) get_user_meta($user->ID, 'wp_org_full_name', true);
            if ($full_name === '') {
                $full_name = $user->display_name;
            }
            echo '<tr><td><strong>' . esc_html($full_name) . '</strong></td><td><code>' . esc_html($member_number) . '</code></td><td><a href="mailto:' . esc_attr($user->user_email) . '">' . esc_html($user->user_email) . '</a></td><td>' . ($region !== '' ? esc_html($region) : '<span class="wp-org-admin-subtle">Belum diisi</span>') . '</td><td><span class="wp-org-admin-badge wp-org-admin-badge-' . esc_attr($status) . '">' . esc_html($statuses[$status] ?? $status) . '</span></td><td><span class="wp-org-admin-badge wp-org-admin-badge-premium-' . esc_attr($premium_status) . '">' . esc_html($premium_statuses[$premium_status] ?? $premium_status) . '</span>' . ($premium_ref ? '<br><small class="wp-org-admin-subtle">' . esc_html($premium_ref) . '</small>' : '') . ($premium_proof_url ? '<br><a class="wp-org-admin-link" href="' . esc_url($premium_proof_url) . '" target="_blank" rel="noopener">Lihat Bukti</a>' : '') . '</td><td>' . esc_html(get_user_meta($user->ID, 'wp_org_registered_at', true) ?: $user->user_registered) . '</td><td>' . ($note ? esc_html($note) : '<span class="wp-org-admin-subtle">Belum ada catatan</span>') . '</td><td><button type="button" class="button button-secondary wp-org-admin-open-modal" data-modal-target="wp-org-member-modal-' . esc_attr((string) $user->ID) . '">Kelola</button>' . $this->render_member_action_modal($user, $statuses, $status, $note, $premium_statuses, $premium_status) . '</td></tr>';
        }

        echo '</tbody></table></div>';
    }

    public function render_fields_page()
    {
        if (!current_user_can('wp_org_manage_settings')) {
            wp_die('Akses ditolak.');
        }

        $fields = MemberData::get_all_registration_fields();
        echo '<div class="wrap wp-org-admin"><div class="wp-org-admin-hero"><div><h1>Field Formulir</h1><p>Atur struktur form pendaftaran tanpa perlu mengubah kode frontend.</p></div></div><div class="wp-org-admin-summary">';
        echo '<div class="wp-org-admin-stat"><span class="wp-org-admin-stat-label">Total Field</span><strong>' . esc_html((string) count($fields)) . '</strong></div>';
        echo '<div class="wp-org-admin-stat"><span class="wp-org-admin-stat-label">Field Aktif</span><strong>' . esc_html((string) count(array_filter($fields, static function ($field) {
            return !empty($field['enabled']);
        }))) . '</strong></div>';
        echo '<div class="wp-org-admin-stat"><span class="wp-org-admin-stat-label">Field Wajib</span><strong>' . esc_html((string) count(array_filter($fields, static function ($field) {
            return !empty($field['required']);
        }))) . '</strong></div>';
        echo '</div><div class="wp-org-admin-card"><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('wp_org_save_fields');
        echo '<input type="hidden" name="action" value="wp_org_save_fields">';
        echo '<div class="wp-org-admin-fields-toolbar"><div><h2>Daftar Field</h2><p class="description">Tambah, hapus, aktifkan, atau nonaktifkan field pendaftaran. Field nonaktif tetap tersimpan tetapi tidak ditampilkan di frontend.</p><p class="description">Seret baris lewat handle di kolom paling kiri untuk mengubah urutan field.</p></div><button type="button" class="button button-secondary" id="wp-org-add-field">Tambah Field</button></div>';
        echo '<div class="wp-org-admin-note"><strong>Panduan cepat:</strong> isi <code>Label</code> seperti biasa, lalu sistem akan membuat <code>key</code> otomatis dalam format underscore. Kolom <code>opsi</code> hanya dipakai untuk field pilihan.</div>';
        echo '<div class="wp-org-admin-table-card"><table class="widefat striped wp-org-fields-table wp-org-admin-table"><thead><tr><th>Urut</th><th>Label</th><th>Tipe</th><th>Opsi</th><th>Wajib</th><th>Aktif</th><th>Aksi</th></tr></thead><tbody data-next-index="' . esc_attr((string) count($fields)) . '">';

        foreach ($fields as $index => $field) {
            echo $this->render_field_row($index, $field);
        }

        echo '</tbody></table></div>';
        echo '<script type="text/html" id="tmpl-wp-org-field-row">' . $this->render_field_row('__index__', [
            'key' => '',
            'label' => '',
            'type' => 'text',
            'required' => 0,
            'enabled' => 1,
            'options' => '',
        ]) . '</script>';
        submit_button('Simpan Field');
        echo '</form></div></div>';
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
        $payment_banks = array_values((array) get_option('wp_org_payment_banks', []));
        $login_redirect_page_id = !empty($general['login_redirect']) ? url_to_postid((string) $general['login_redirect']) : 0;

        $seed_message = isset($_GET['seeded']) ? absint($_GET['seeded']) : -1;
        $imported_subscribers = isset($_GET['imported_subscribers']) ? absint($_GET['imported_subscribers']) : -1;
        $generated = isset($_GET['generated']) ? absint($_GET['generated']) : -1;
        $subscriber_count = count(get_users([
            'role' => 'subscriber',
            'fields' => 'ids',
        ]));
        $member_card = get_option('wp_org_member_card_settings', []);
        $definitions = $this->get_generate_page_definitions();
        $page_settings = $this->get_generate_page_settings();

        echo '<div class="wrap wp-org-admin"><div class="wp-org-admin-hero"><div><h1>Pengaturan WP Org</h1><p>Seluruh konfigurasi plugin, generate data, kartu anggota, dan page shortcode ada dalam satu panel.</p></div></div>';
        echo '<div class="wp-org-admin-tab-group"><nav class="nav-tab-wrapper wp-org-admin-tabs">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=wp-org-settings&tab=general')) . '" class="nav-tab ' . ($active_tab === 'general' ? 'nav-tab-active' : '') . '">Umum</a>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=wp-org-settings&tab=data')) . '" class="nav-tab ' . ($active_tab === 'data' ? 'nav-tab-active' : '') . '">Data</a>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=wp-org-settings&tab=payment-banks')) . '" class="nav-tab ' . ($active_tab === 'payment-banks' ? 'nav-tab-active' : '') . '">Bank Pembayaran</a>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=wp-org-settings&tab=member-card')) . '" class="nav-tab ' . ($active_tab === 'member-card' ? 'nav-tab-active' : '') . '">Kartu Anggota</a>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=wp-org-settings&tab=page')) . '" class="nav-tab ' . ($active_tab === 'page' ? 'nav-tab-active' : '') . '">Page</a>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=wp-org-settings&tab=documentation')) . '" class="nav-tab ' . ($active_tab === 'documentation' ? 'nav-tab-active' : '') . '">Dokumentasi</a>';
        echo '</nav></div>';

        if ($active_tab === 'data') {
            if ($seed_message >= 0) {
                echo '<div class="notice notice-success is-dismissible"><p>Seeder anggota selesai. ' . esc_html((string) $seed_message) . ' anggota baru dibuat.</p></div>';
            }
            if ($imported_subscribers >= 0) {
                echo '<div class="notice notice-success is-dismissible"><p>Sinkronisasi selesai. ' . esc_html((string) $imported_subscribers) . ' subscriber baru ditambahkan sebagai anggota.</p></div>';
            }
            $migrated_count = isset($_GET['migrated']) ? absint($_GET['migrated']) : -1;
            if ($migrated_count >= 0) {
                echo '<div class="notice notice-success is-dismissible"><p>Migrasi selesai. ' . esc_html((string) $migrated_count) . ' anggota lama berhasil diupdate menjadi role org_member.</p></div>';
            }
            $reset = isset($_GET['reset']) ? absint($_GET['reset']) : -1;
            if ($reset >= 0) {
                echo '<div class="notice notice-success is-dismissible"><p>Nomor anggota berhasil direset dan ditetapkan ulang.</p></div>';
            }

            $um_anggota_count = count(get_users([
                'role' => 'um_anggota',
                'fields' => 'ids',
            ]));

            echo '<div class="wp-org-admin-card"><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('wp_org_seed_members');
            echo '<input type="hidden" name="action" value="wp_org_seed_members">';
            echo '<table class="form-table"><tbody>';
            echo '<tr><th scope="row">Jumlah Seeder</th><td><input class="small-text" type="number" min="1" max="100" name="seed_total" value="10"><p class="description">Membuat data anggota contoh dengan status approved dan field profil dasar.</p></td></tr>';
            echo '<tr><th scope="row">Password Default</th><td><input class="regular-text" type="text" name="seed_password" value="Member123!"><p class="description">Password ini dipakai untuk seluruh akun hasil seeder.</p></td></tr>';
            echo '</tbody></table>';
            submit_button('Jalankan Seeder Anggota');
            echo '</form></div>';
            echo '<div class="wp-org-admin-card"><h2>Import Subscriber</h2>';
            echo '<p>Tambahkan user WordPress dengan role <code>subscriber</code> ke daftar anggota tanpa menghapus role subscriber mereka.</p>';
            echo '<p><strong>' . esc_html((string) $subscriber_count) . '</strong> user subscriber ditemukan. User yang sudah memiliki role <code>org_member</code> atau <code>org_admin</code> akan dilewati.</p>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('wp_org_import_subscribers');
            echo '<input type="hidden" name="action" value="wp_org_import_subscribers">';
            submit_button('Import / Update Subscriber', 'secondary');
            echo '</form></div>';
            echo '<div class="wp-org-admin-card"><h2>Migrasi Anggota Lama</h2>';
            echo '<p>Ubah role anggota lama dari <code>um_anggota</code> menjadi <code>org_member</code> tanpa menghapus role yang lain.</p>';
            echo '<p><strong>' . esc_html((string) $um_anggota_count) . '</strong> user dengan role um_anggota ditemukan. User yang sudah memiliki role org_member atau org_admin akan dilewati.</p>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('wp_org_migrate_old_members');
            echo '<input type="hidden" name="action" value="wp_org_migrate_old_members">';
            submit_button('Migrasi Anggota Lama', 'secondary');
            echo '</form></div>';

            echo '<div class="wp-org-admin-card"><h2>Reset Nomor Anggota</h2>';
            echo '<p>Reset dan tetapkan nomor anggota secara berurutan berdasarkan tanggal pendaftaran (terlama pertama).</p>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('wp_org_reset_member_numbers');
            echo '<input type="hidden" name="action" value="wp_org_reset_member_numbers">';
            submit_button('Reset Nomor Anggota', 'delete');
            echo '</form></div>';
            echo '</div>';
            return;
        }

        if ($active_tab === 'payment-banks') {
            echo '<div class="wp-org-admin-card"><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('wp_org_save_payment_banks');
            echo '<input type="hidden" name="action" value="wp_org_save_payment_banks">';
            echo '<table class="form-table"><tbody>';
            echo '<tr><th scope="row">Biaya Premium</th><td><input class="regular-text" type="number" min="0" name="premium_fee" value="' . esc_attr((string) absint($general['premium_fee'] ?? 0)) . '"><p class="description">Biaya yang ditampilkan ke member saat upgrade premium.</p></td></tr>';
            echo '</tbody></table>';
            echo '<div class="wp-org-admin-table-card"><table class="widefat striped wp-org-bank-table wp-org-admin-table"><thead><tr><th>Bank</th><th>Nama Rekening</th><th>Nomor Rekening</th><th>Aktif</th><th>Aksi</th></tr></thead><tbody data-next-index="' . esc_attr((string) count($payment_banks)) . '">';
            foreach ($payment_banks as $index => $bank) {
                echo $this->render_bank_row($index, $bank);
            }
            echo '</tbody></table></div>';
            echo '<p><button type="button" class="button" id="wp-org-add-bank">Tambah Bank</button></p>';
            echo '<script type="text/html" id="tmpl-wp-org-bank-row">' . $this->render_bank_row('__index__', ['bank_name' => '', 'account_name' => '', 'account_number' => '', 'enabled' => 1]) . '</script>';
            submit_button('Simpan Bank Pembayaran');
            echo '</form></div>';
            return;
        }

        if ($active_tab === 'member-card') {
            echo '<div class="wp-org-admin-card"><form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('wp_org_save_member_card_settings');
            echo '<input type="hidden" name="action" value="wp_org_save_member_card_settings">';
            echo '<table class="form-table"><tbody>';
            echo '<tr><th scope="row">Nama Organisasi</th><td><input class="regular-text" type="text" name="member_card[organization_name]" value="' . esc_attr($member_card['organization_name'] ?? 'WP Org') . '"><p class="description">Nama ini tampil pada kartu anggota.</p></td></tr>';
            echo '<tr><th scope="row">Prefix Nomor Anggota</th><td><input class="regular-text" type="text" name="member_card[member_number_prefix]" value="' . esc_attr($member_card['member_number_prefix'] ?? 'ORG') . '" placeholder="Contoh: ORG"><p class="description">Dipakai sebagai awalan nomor anggota, misalnya <code>ORG-000001</code>.</p></td></tr>';
            echo '<tr><th scope="row">Background Kartu</th><td>';
            if (!empty($member_card['background_url'])) {
                echo '<p><img src="' . esc_url($member_card['background_url']) . '" alt="Background kartu" style="max-width:320px;height:auto;border:1px solid #dcdcde;border-radius:10px"></p>';
            }
            echo '<input class="regular-text" type="file" name="member_card_background" accept="image/jpeg,image/png,image/webp">';
            echo '<p class="description">Upload gambar background kartu. Kosongkan jika tidak ingin mengganti background saat ini.</p>';
            echo '</td></tr>';
            echo '<tr><th scope="row">Logo Organisasi</th><td>';
            if (!empty($member_card['logo_url'])) {
                echo '<p><img src="' . esc_url($member_card['logo_url']) . '" alt="Logo organisasi" style="max-width:140px;height:auto;border:1px solid #dcdcde;border-radius:10px;padding:8px;background:#fff"></p>';
            }
            echo '<input class="regular-text" type="file" name="member_card_logo" accept="image/jpeg,image/png,image/webp,image/svg+xml">';
            echo '<p class="description">Upload logo organisasi. Kosongkan jika tidak ingin mengganti logo saat ini.</p>';
            echo '</td></tr>';
            echo '<tr><th scope="row">Warna Background 1</th><td><input class="color-picker" type="text" name="member_card[background_color_1]" value="' . esc_attr($member_card['background_color_1'] ?? '#0f3d5e') . '" data-alpha-enabled="true" data-alpha-color-type="rgb"><p class="description">Warna pertama untuk gradient background kartu.</p></td></tr>';
            echo '<tr><th scope="row">Warna Background 2</th><td><input class="color-picker" type="text" name="member_card[background_color_2]" value="' . esc_attr($member_card['background_color_2'] ?? '#135e96') . '" data-alpha-enabled="true" data-alpha-color-type="rgb"><p class="description">Warna kedua untuk gradient background kartu.</p></td></tr>';
            echo '<tr><th scope="row">Warna Background 3</th><td><input class="color-picker" type="text" name="member_card[background_color_3]" value="' . esc_attr($member_card['background_color_3'] ?? '#2e84be') . '" data-alpha-enabled="true" data-alpha-color-type="rgb"><p class="description">Warna ketiga untuk gradient background kartu.</p></td></tr>';
            echo '</tbody></table>';
            submit_button('Simpan Pengaturan Kartu');
            echo '</form></div>';
            return;
        }

        if ($active_tab === 'page') {
            if ($generated >= 0) {
                echo '<div class="notice notice-success is-dismissible"><p>Generate page selesai. ' . esc_html((string) $generated) . ' halaman dibuat atau diperbarui.</p></div>';
            }

            echo '<div class="wp-org-admin-card">';
            echo '<h2>Daftar Halaman</h2>';
            echo '<p class="description">Isi judul lalu pilih halaman yang sudah ada jika ingin memakai page manual. Jika belum pilih page, plugin akan membuat halaman baru dengan judul tersebut.</p>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('wp_org_generate_pages');
            echo '<input type="hidden" name="action" value="wp_org_generate_pages">';
            echo '<div class="wp-org-admin-table-card"><table class="widefat striped wp-org-admin-table wp-org-generate-pages-table"><thead><tr><th>Fungsi</th><th>Judul</th><th>Select Page</th><th>Shortcode</th></tr></thead><tbody>';

            foreach ($definitions as $definition) {
                $config = $page_settings[$definition['key']] ?? [];
                $selected_page_id = absint($config['page_id'] ?? 0);
                $title = sanitize_text_field($config['title'] ?? $definition['title']);
                echo '<tr>';
                echo '<td><strong>' . esc_html($definition['label']) . '</strong><br><span class="wp-org-admin-subtle"><code>' . esc_html($definition['slug']) . '</code></span></td>';
                echo '<td><input type="text" name="pages[' . esc_attr($definition['key']) . '][title]" value="' . esc_attr($title) . '" placeholder="Judul halaman"></td>';
                echo '<td>';
                wp_dropdown_pages([
                    'name' => 'pages[' . $definition['key'] . '][page_id]',
                    'selected' => $selected_page_id,
                    'show_option_none' => 'Buat page baru',
                    'option_none_value' => '0',
                ]);
                if ($selected_page_id > 0) {
                    echo '<p class="description"><a href="' . esc_url(get_edit_post_link($selected_page_id)) . '">Edit page terpilih</a></p>';
                }
                echo '</td>';
                echo '<td><code>' . esc_html($definition['shortcode']) . '</code></td>';
                echo '</tr>';
            }

            echo '</tbody></table></div>';
            submit_button('Generate Pages');
            echo '</form></div>';
            return;
        }

        if ($active_tab === 'documentation') {
            echo '<div class="wp-org-admin-card wp-org-admin-docs">';
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

        echo '<div class="wp-org-admin-card"><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('wp_org_save_settings');
        echo '<input type="hidden" name="action" value="wp_org_save_settings">';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row">Butuh Approval Admin</th><td><input type="checkbox" name="general[require_approval]" value="1"' . checked(!empty($general['require_approval']), true, false) . '></td></tr>';
        echo '<tr><th scope="row">Daftar Anggota Publik</th><td><input type="checkbox" name="general[members_page_public]" value="1"' . checked(!empty($general['members_page_public']), true, false) . '></td></tr>';
        echo '<tr><th scope="row">Aktifkan Member Premium</th><td><input type="checkbox" name="general[premium_enabled]" value="1"' . checked(!empty($general['premium_enabled']) || !isset($general['premium_enabled']), true, false) . '><p class="description">Jika dimatikan, tab premium, form pengajuan, dan badge premium tidak akan ditampilkan ke member.</p></td></tr>';
        echo '<tr><th scope="row">Login Redirect Page</th><td>';
        wp_dropdown_pages([
            'name' => 'general[login_redirect_page]',
            'id' => 'wp-org-login-redirect-page',
            'selected' => $login_redirect_page_id,
            'show_option_none' => 'Pilih halaman',
            'option_none_value' => '0',
        ]);
        echo '<p class="description">Pilih halaman tujuan setelah login. Kosongkan untuk kembali ke homepage.</p></td></tr>';
        echo '<tr><th scope="row">Captcha</th><td>';
        echo $captcha_enabled ? '<p>Tersambung ke velocity-addons dan saat ini aktif dengan provider <strong>' . esc_html($captcha_provider) . '</strong>.</p>' : '<p>Captcha mengikuti pengaturan plugin velocity-addons dan saat ini belum aktif.</p>';
        echo '<p><a class="button-secondary" href="' . esc_url(admin_url('admin.php?page=velocity_captcha_settings')) . '">Buka Pengaturan Captcha Velocity Addons</a></p>';
        echo '</td></tr>';
        echo '</tbody></table>';
        submit_button('Simpan Pengaturan');
        echo '</form></div>';
    }

    public function render_generate_page()
    {
        wp_safe_redirect(admin_url('admin.php?page=wp-org-settings&tab=data'));
        exit;
    }

    public function render_generate_pages_page()
    {
        $_GET['tab'] = 'page';
        $this->render_generate_page();
    }

    public function handle_member_status()
    {
        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;

        if (!$user_id || !current_user_can('wp_org_approve_members') || !check_admin_referer('wp_org_update_member_status_' . $user_id)) {
            wp_die('Permintaan tidak valid.');
        }

        $status = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : 'pending';
        $note = isset($_POST['admin_note']) ? sanitize_text_field(wp_unslash($_POST['admin_note'])) : '';
        $old_status = MemberData::get_status($user_id);
        MemberData::update_status($user_id, $status);
        update_user_meta($user_id, 'wp_org_admin_note', $note);
        
        // Assign member number when status is changed to approved
        if ($status === 'approved' && $old_status !== 'approved') {
            MemberData::assign_member_number($user_id);
        }

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
        $seen_keys = [];

        foreach ($fields as $field) {
            if (!empty($field['_delete'])) {
                continue;
            }

            $prepared = MemberData::normalize_field($field);

            if (!$prepared || isset($seen_keys[$prepared['key']])) {
                continue;
            }

            $seen_keys[$prepared['key']] = true;
            $sanitized[] = $prepared;
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
            'premium_enabled' => !empty($general['premium_enabled']) ? 1 : 0,
            'login_redirect' => $this->get_login_redirect_url_from_settings($general),
        ]);

        wp_safe_redirect(admin_url('admin.php?page=wp-org-settings'));
        exit;
    }

    public function handle_generate_pages()
    {
        if (!current_user_can('wp_org_manage_settings') || !check_admin_referer('wp_org_generate_pages')) {
            wp_die('Permintaan tidak valid.');
        }

        $submitted_pages = isset($_POST['pages']) ? (array) wp_unslash($_POST['pages']) : [];
        $stored_pages = [];
        $processed = 0;

        foreach ($this->get_generate_page_definitions() as $definition) {
            $submitted = isset($submitted_pages[$definition['key']]) ? (array) $submitted_pages[$definition['key']] : [];
            $page_id = isset($submitted['page_id']) ? absint($submitted['page_id']) : 0;
            $title = sanitize_text_field($submitted['title'] ?? $definition['title']);

            $stored_pages[$definition['key']] = [
                'title' => $title !== '' ? $title : $definition['title'],
                'page_id' => $page_id,
            ];

            $postarr = [
                'post_title' => $title !== '' ? $title : $definition['title'],
                'post_content' => $definition['shortcode'],
                'post_status' => 'publish',
                'post_type' => 'page',
            ];

            if ($page_id > 0) {
                $existing_page = get_post($page_id);

                if ($existing_page instanceof \WP_Post && $existing_page->post_type === 'page') {
                    $postarr['ID'] = $page_id;
                    wp_update_post($postarr);
                    $processed++;
                    continue;
                }
            }

            $postarr['post_name'] = $definition['slug'];
            $new_page_id = wp_insert_post($postarr);

            if (!is_wp_error($new_page_id) && $new_page_id > 0) {
                $stored_pages[$definition['key']]['page_id'] = $new_page_id;
                $processed++;
            }
        }

        update_option('wp_org_generated_pages', $stored_pages);
        wp_safe_redirect(admin_url('admin.php?page=wp-org-settings&tab=page&generated=' . $processed));
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

    public function handle_save_member_card_settings()
    {
        if (!current_user_can('wp_org_manage_settings') || !check_admin_referer('wp_org_save_member_card_settings')) {
            wp_die('Permintaan tidak valid.');
        }

        $member_card = isset($_POST['member_card']) ? (array) wp_unslash($_POST['member_card']) : [];
        $existing = get_option('wp_org_member_card_settings', []);
        $background_url = $this->handle_member_card_upload('member_card_background', $existing['background_url'] ?? '', [
            'jpg|jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        ]);
        $logo_url = $this->handle_member_card_upload('member_card_logo', $existing['logo_url'] ?? '', [
            'jpg|jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
        ]);

        update_option('wp_org_member_card_settings', array_merge($existing, [
            'organization_name' => sanitize_text_field($member_card['organization_name'] ?? $existing['organization_name'] ?? 'WP Org'),
            'member_number_prefix' => sanitize_text_field($member_card['member_number_prefix'] ?? $existing['member_number_prefix'] ?? 'ORG'),
            'background_url' => $background_url,
            'logo_url' => $logo_url,
            'background_color_1' => $this->sanitize_color($member_card['background_color_1'] ?? $existing['background_color_1'] ?? '#0f3d5e'),
            'background_color_2' => $this->sanitize_color($member_card['background_color_2'] ?? $existing['background_color_2'] ?? '#135e96'),
            'background_color_3' => $this->sanitize_color($member_card['background_color_3'] ?? $existing['background_color_3'] ?? '#2e84be'),
        ]));

        wp_safe_redirect(admin_url('admin.php?page=wp-org-settings&tab=member-card'));
        exit;
    }

    /**
     * @param array<string, string> $mimes
     */
    private function handle_member_card_upload($field_name, $current_url, $mimes)
    {
        if (empty($_FILES[$field_name]) || empty($_FILES[$field_name]['name'])) {
            return esc_url_raw($current_url);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $uploaded = wp_handle_upload($_FILES[$field_name], [
            'test_form' => false,
            'mimes' => $mimes,
        ]);

        if (!empty($uploaded['error'])) {
            return esc_url_raw($current_url);
        }

        return esc_url_raw($uploaded['url']);
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

    public function handle_import_subscribers()
    {
        if (!current_user_can('wp_org_manage_settings') || !check_admin_referer('wp_org_import_subscribers')) {
            wp_die('Permintaan tidak valid.');
        }

        $regions = new \WpOrg\Support\Regions();
        $all_provinces = $regions->get_provinces();
        $all_cities = $regions->get_regions()['cities'] ?? [];
        $all_districts = $regions->get_regions()['districts'] ?? [];

        $users = get_users([
            'role' => 'subscriber',
            'fields' => 'all',
        ]);
        $imported = 0;

        foreach ($users as $user) {
            // Check if user has any of the old meta keys
            $has_old_meta = false;
            if (
                get_user_meta($user->ID, 'first_name', true) !== '' ||
                get_user_meta($user->ID, 'phone_number', true) !== '' ||
                get_user_meta($user->ID, 'kode_pos', true) !== '' ||
                get_user_meta($user->ID, 'prov_anggota', true) !== '' ||
                get_user_meta($user->ID, 'city_anggota', true) !== '' ||
                get_user_meta($user->ID, 'kec_anggota', true) !== ''
            ) {
                $has_old_meta = true;
            }

            if (in_array('org_member', (array) $user->roles, true) || in_array('org_admin', (array) $user->roles, true)) {
                if (!$has_old_meta) {
                    continue;
                }
            } else {
                $user->add_role('org_member');
            }

            MemberData::update_status($user->ID, 'approved');

            if (!get_user_meta($user->ID, 'wp_org_registered_at', true)) {
                update_user_meta($user->ID, 'wp_org_registered_at', $user->user_registered);
            }

            // Get full name from old meta keys
            $full_name = '';
            $full_name_old = (string) get_user_meta($user->ID, 'full_name', true);
            $first_name = (string) get_user_meta($user->ID, 'first_name', true);
            $last_name = (string) get_user_meta($user->ID, 'last_name', true);

            if ($full_name_old !== '') {
                $full_name = $full_name_old;
            } elseif ($first_name !== '' && $last_name !== '') {
                $full_name = trim($first_name . ' ' . $last_name);
            } elseif ($first_name !== '') {
                $full_name = $first_name;
            }

            if ($full_name !== '') {
                update_user_meta($user->ID, 'wp_org_full_name', $full_name);
            } elseif (!get_user_meta($user->ID, 'wp_org_full_name', true)) {
                // Fallback to display_name if no other name found
                update_user_meta($user->ID, 'wp_org_full_name', $user->display_name);
            }

            // Copy phone_number
            $phone_number = (string) get_user_meta($user->ID, 'phone_number', true);
            if ($phone_number !== '') {
                update_user_meta($user->ID, 'wp_org_phone', $phone_number);
            }

            // Copy kode_pos
            $kode_pos = (string) get_user_meta($user->ID, 'kode_pos', true);
            if ($kode_pos !== '') {
                update_user_meta($user->ID, 'wp_org_postal_code', $kode_pos);
            }

            // Handle province (ALWAYS update if old meta exists)
            $prov_anggota = (string) get_user_meta($user->ID, 'prov_anggota', true);
            $province_code = '';
            $province_name = '';
            if ($prov_anggota !== '') {
                foreach ($all_provinces as $province) {
                    if (strcasecmp($province['name'], $prov_anggota) === 0) {
                        $province_code = $province['code'];
                        $province_name = $province['name'];
                        break;
                    }
                }

                if ($province_code !== '') {
                    update_user_meta($user->ID, 'wp_org_province_code', $province_code);
                    update_user_meta($user->ID, 'wp_org_province_name', $province_name);
                }
            }

            // Handle city
            $city_anggota = (string) get_user_meta($user->ID, 'city_anggota', true);
            $city_code = '';
            $city_name = '';
            if ($city_anggota !== '') {
                foreach ($all_cities as $city) {
                    if (strcasecmp($city['name'], $city_anggota) === 0) {
                        $city_code = $city['code'];
                        $city_name = $city['name'];
                        break;
                    }
                }

                if ($city_code !== '') {
                    update_user_meta($user->ID, 'wp_org_city_code', $city_code);
                    update_user_meta($user->ID, 'wp_org_city_name', $city_name);
                }
            }

            // Handle district
            $kec_anggota = (string) get_user_meta($user->ID, 'kec_anggota', true);
            $district_code = '';
            $district_name = '';
            if ($kec_anggota !== '') {
                foreach ($all_districts as $district) {
                    if (strcasecmp($district['name'], $kec_anggota) === 0) {
                        $district_code = $district['code'];
                        $district_name = $district['name'];
                        break;
                    }
                }

                if ($district_code !== '') {
                    update_user_meta($user->ID, 'wp_org_district_code', $district_code);
                    update_user_meta($user->ID, 'wp_org_district_name', $district_name);
                }
            }

            $imported++;
        }

        wp_safe_redirect(admin_url('admin.php?page=wp-org-settings&tab=data&imported_subscribers=' . $imported));
        exit;
    }

    public function handle_migrate_old_members()
    {
        if (!current_user_can('wp_org_manage_settings') || !check_admin_referer('wp_org_migrate_old_members')) {
            wp_die('Permintaan tidak valid.');
        }

        $regions = new \WpOrg\Support\Regions();
        $all_provinces = $regions->get_provinces();
        $all_cities = $regions->get_regions()['cities'] ?? [];
        $all_districts = $regions->get_regions()['districts'] ?? [];

        $users = get_users([
            'role__in' => ['um_anggota', 'org_member', 'org_admin'], // Include all possible roles
            'fields' => 'all',
            'number' => -1,
        ]);
        $migrated = 0;

        foreach ($users as $user) {
            // Check if user has any of the old meta keys
            $has_old_meta = false;
            if (
                get_user_meta($user->ID, 'first_name', true) !== '' ||
                get_user_meta($user->ID, 'phone_number', true) !== '' ||
                get_user_meta($user->ID, 'kode_pos', true) !== '' ||
                get_user_meta($user->ID, 'prov_anggota', true) !== '' ||
                get_user_meta($user->ID, 'city_anggota', true) !== '' ||
                get_user_meta($user->ID, 'kec_anggota', true) !== '' ||
                in_array('um_anggota', (array) $user->roles, true)
            ) {
                $has_old_meta = true;
            }

            if (!$has_old_meta) {
                continue;
            }

            // Always ensure org_member role is present
            if (!in_array('org_member', (array) $user->roles, true) && !in_array('org_admin', (array) $user->roles, true)) {
                $user->add_role('org_member');
            }
            
            // Remove old role
            if (in_array('um_anggota', (array) $user->roles, true)) {
                $user->remove_role('um_anggota');
            }
            
            // Ensure approved status
            MemberData::update_status($user->ID, 'approved');

            // Always update registered at (if not present)
            if (!get_user_meta($user->ID, 'wp_org_registered_at', true)) {
                update_user_meta($user->ID, 'wp_org_registered_at', $user->user_registered);
            }

            // Get full name from old meta keys
            $full_name = '';
            $full_name_old = (string) get_user_meta($user->ID, 'full_name', true);
            $first_name = (string) get_user_meta($user->ID, 'first_name', true);
            $last_name = (string) get_user_meta($user->ID, 'last_name', true);

            if ($full_name_old !== '') {
                $full_name = $full_name_old;
            } elseif ($first_name !== '' && $last_name !== '') {
                $full_name = trim($first_name . ' ' . $last_name);
            } elseif ($first_name !== '') {
                $full_name = $first_name;
            }

            if ($full_name !== '') {
                update_user_meta($user->ID, 'wp_org_full_name', $full_name);
            } elseif (!get_user_meta($user->ID, 'wp_org_full_name', true)) {
                // Fallback to display_name if no other name found
                update_user_meta($user->ID, 'wp_org_full_name', $user->display_name);
            }

            // Copy phone_number
            $phone_number = (string) get_user_meta($user->ID, 'phone_number', true);
            if ($phone_number !== '') {
                update_user_meta($user->ID, 'wp_org_phone', $phone_number);
            }

            // Copy kode_pos
            $kode_pos = (string) get_user_meta($user->ID, 'kode_pos', true);
            if ($kode_pos !== '') {
                update_user_meta($user->ID, 'wp_org_postal_code', $kode_pos);
            }

            // Handle province (ALWAYS update if old meta exists)
            $prov_anggota = (string) get_user_meta($user->ID, 'prov_anggota', true);
            $province_code = '';
            $province_name = '';
            if ($prov_anggota !== '') {
                foreach ($all_provinces as $province) {
                    if (strcasecmp($province['name'], $prov_anggota) === 0) {
                        $province_code = $province['code'];
                        $province_name = $province['name'];
                        break;
                    }
                }

                if ($province_code !== '') {
                    update_user_meta($user->ID, 'wp_org_province_code', $province_code);
                    update_user_meta($user->ID, 'wp_org_province_name', $province_name);
                }
            }

            // Handle city
            $city_anggota = (string) get_user_meta($user->ID, 'city_anggota', true);
            $city_code = '';
            $city_name = '';
            if ($city_anggota !== '') {
                foreach ($all_cities as $city) {
                    if (strcasecmp($city['name'], $city_anggota) === 0) {
                        $city_code = $city['code'];
                        $city_name = $city['name'];
                        break;
                    }
                }

                if ($city_code !== '') {
                    update_user_meta($user->ID, 'wp_org_city_code', $city_code);
                    update_user_meta($user->ID, 'wp_org_city_name', $city_name);
                }
            }

            // Handle district
            $kec_anggota = (string) get_user_meta($user->ID, 'kec_anggota', true);
            $district_code = '';
            $district_name = '';
            if ($kec_anggota !== '') {
                foreach ($all_districts as $district) {
                    if (strcasecmp($district['name'], $kec_anggota) === 0) {
                        $district_code = $district['code'];
                        $district_name = $district['name'];
                        break;
                    }
                }

                if ($district_code !== '') {
                    update_user_meta($user->ID, 'wp_org_district_code', $district_code);
                    update_user_meta($user->ID, 'wp_org_district_name', $district_name);
                }
            }

            $migrated++;
        }

        wp_safe_redirect(admin_url('admin.php?page=wp-org-settings&tab=data&migrated=' . $migrated));
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

    public function handle_update_member_profile()
    {
        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;

        if (!$user_id || !current_user_can('wp_org_manage_members') || !check_admin_referer('wp_org_update_member_profile_' . $user_id)) {
            wp_die('Permintaan tidak valid.');
        }

        $user = get_user_by('id', $user_id);

        if (!$user) {
            wp_die('Anggota tidak ditemukan.');
        }

        $display_name = isset($_POST['display_name']) ? sanitize_text_field(wp_unslash($_POST['display_name'])) : '';
        $user_email = isset($_POST['user_email']) ? sanitize_email(wp_unslash($_POST['user_email'])) : '';

        if ($display_name === '') {
            $display_name = $user->display_name;
        }

        if (!$user_email || !is_email($user_email)) {
            wp_die('Email anggota tidak valid.');
        }

        $existing_user = get_user_by('email', $user_email);
        if ($existing_user && (int) $existing_user->ID !== $user_id) {
            wp_die('Email sudah digunakan oleh anggota lain.');
        }

        wp_update_user([
            'ID' => $user_id,
            'display_name' => $display_name,
            'user_email' => $user_email,
        ]);

        update_user_meta($user_id, 'wp_org_full_name', $display_name);

        MemberData::save_profile_fields_with_definitions($user_id, $_POST, MemberData::get_all_registration_fields());

        wp_safe_redirect(admin_url('admin.php?page=wp-org'));
        exit;
    }

    public function handle_reset_member_numbers()
    {
        if (!current_user_can('wp_org_manage_settings') || !check_admin_referer('wp_org_reset_member_numbers')) {
            wp_die('Permintaan tidak valid.');
        }

        MemberData::reset_member_numbers();

        wp_safe_redirect(admin_url('admin.php?page=wp-org-settings&tab=data&reset=1'));
        exit;
    }

    public function handle_delete_member()
    {
        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;

        if (!$user_id || !current_user_can('wp_org_manage_members') || !check_admin_referer('wp_org_delete_member_' . $user_id)) {
            wp_die('Permintaan tidak valid.');
        }

        // Don't allow deleting yourself
        if ($user_id === get_current_user_id()) {
            wp_die('Anda tidak dapat menghapus akun sendiri.');
        }

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

        // Also delete member card background/logo if they're linked to this user? Wait no, member card settings are site-wide, skip that.

        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($user_id);

        wp_safe_redirect(admin_url('admin.php?page=wp-org'));
        exit;
    }

    public function enqueue_admin_assets($hook_suffix)
    {
        if (!in_array($hook_suffix, ['toplevel_page_wp-org', 'wp-org_page_wp-org-fields', 'wp-org_page_wp-org-settings'], true)) {
            return;
        }

        wp_enqueue_style(
            'wp-org-admin',
            WP_ORG_URL . 'assets/frontend/css/admin.css',
            ['wp-admin', 'wp-color-picker'],
            WP_ORG_VERSION
        );
        wp_enqueue_script(
            'wp-color-picker-alpha',
            WP_ORG_URL . 'assets/frontend/js/wp-color-picker-alpha.js',
            ['wp-color-picker'],
            WP_ORG_VERSION,
            true
        );
        wp_enqueue_script(
            'wp-org-admin',
            WP_ORG_URL . 'assets/frontend/js/admin.js',
            ['jquery', 'jquery-ui-sortable', 'wp-color-picker', 'wp-color-picker-alpha'],
            WP_ORG_VERSION,
            true
        );
        wp_localize_script('wp-org-admin', 'WpOrgAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'labels' => [
                'cityPlaceholder' => 'Pilih kota/kabupaten',
                'districtPlaceholder' => 'Pilih kecamatan',
            ],
        ]);
    }

    private function render_member_action_modal($user, $statuses, $status, $note, $premium_statuses, $premium_status)
    {
        $modal_id = 'wp-org-member-modal-' . $user->ID;
        $member_number = $this->get_member_number($user->ID);
        $premium_note = (string) get_user_meta($user->ID, 'wp_org_premium_note', true);
        $full_name = (string) get_user_meta($user->ID, 'wp_org_full_name', true);
        if ($full_name === '') {
            $full_name = $user->display_name;
        }
        $html = '<div id="' . esc_attr($modal_id) . '" class="wp-org-admin-modal" aria-hidden="true"><div class="wp-org-admin-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="' . esc_attr($modal_id) . '-title">';
        $html .= '<div class="wp-org-admin-modal-header"><div><h3 id="' . esc_attr($modal_id) . '-title">Kelola ' . esc_html($full_name) . '</h3><p class="wp-org-admin-subtle" style="margin:0">Nomor anggota: <code>' . esc_html($member_number) . '</code><br>' . esc_html($user->user_email) . '</p></div><button type="button" class="wp-org-admin-modal-close" aria-label="Tutup">&times;</button></div>';
        $html .= '<div class="wp-org-admin-modal-grid">';
        $html .= $this->render_member_profile_section($user);
        $html .= '<div class="wp-org-admin-modal-section"><h4>Status Anggota</h4><form class="wp-org-admin-inline-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        ob_start();
        wp_nonce_field('wp_org_update_member_status_' . $user->ID);
        $html .= (string) ob_get_clean();
        $html .= '<input type="hidden" name="action" value="wp_org_update_member_status"><input type="hidden" name="user_id" value="' . esc_attr((string) $user->ID) . '"><select name="status">';
        foreach ($statuses as $key => $label) {
            $html .= '<option value="' . esc_attr($key) . '"' . selected($status, $key, false) . '>' . esc_html($label) . '</option>';
        }
        $html .= '</select><input type="text" name="admin_note" value="' . esc_attr($note) . '" placeholder="Catatan internal">';
        $html .= '<div class="wp-org-admin-modal-actions">';
        ob_start();
        submit_button('Simpan Status', 'secondary', 'submit', false);
        $html .= (string) ob_get_clean();
        $html .= '</div></form></div>';
        $html .= '<div class="wp-org-admin-modal-section"><h4>Member Premium</h4><form class="wp-org-admin-inline-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        ob_start();
        wp_nonce_field('wp_org_update_premium_status_' . $user->ID);
        $html .= (string) ob_get_clean();
        $html .= '<input type="hidden" name="action" value="wp_org_update_premium_status"><input type="hidden" name="user_id" value="' . esc_attr((string) $user->ID) . '"><select name="premium_status">';
        foreach ($premium_statuses as $key => $label) {
            $html .= '<option value="' . esc_attr($key) . '"' . selected($premium_status, $key, false) . '>' . esc_html($label) . '</option>';
        }
        $html .= '</select><input type="text" name="premium_note" value="' . esc_attr($premium_note) . '" placeholder="Catatan premium">';
        $html .= '<div class="wp-org-admin-modal-actions">';
        ob_start();
        submit_button('Update Premium', 'secondary', 'submit', false);
        $html .= (string) ob_get_clean();
        $html .= '</div></form></div>';
        $html .= '<div class="wp-org-admin-modal-section wp-org-admin-danger-zone"><h4>Hapus Anggota</h4><p class="wp-org-admin-subtle">Tindakan ini permanen dan tidak dapat dibatalkan. Semua data anggota akan dihapus.</p><button type="button" class="button button-danger wp-org-admin-open-modal" data-modal-target="wp-org-delete-member-' . esc_attr($user->ID) . '">Hapus Anggota</button></div>';
        
        // Delete confirmation modal
        $html .= '<div id="wp-org-delete-member-' . esc_attr($user->ID) . '" class="wp-org-admin-modal" aria-hidden="true"><div class="wp-org-admin-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="wp-org-delete-member-' . esc_attr($user->ID) . '-title">';
        $html .= '<div class="wp-org-admin-modal-header"><div><p class="wp-org-modal-eyebrow">Danger Zone</p><h3 id="wp-org-delete-member-' . esc_attr($user->ID) . '-title">Konfirmasi Hapus Anggota</h3><p class="wp-org-muted">Akun dan semua data anggota ' . esc_html($full_name) . ' akan dihapus permanen.</p></div><button type="button" class="wp-org-admin-modal-close" aria-label="Tutup">&times;</button></div>';
        $html .= '<div class="wp-org-delete-modal-note"><strong>Perlu diperhatikan:</strong> tindakan ini tidak dapat dibatalkan setelah dikonfirmasi.</div>';
        $html .= '<form class="wp-org-grid" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        ob_start();
        wp_nonce_field('wp_org_delete_member_' . $user->ID);
        $html .= (string) ob_get_clean();
        $html .= '<input type="hidden" name="action" value="wp_org_delete_member"><input type="hidden" name="user_id" value="' . esc_attr((string) $user->ID) . '">';
        $html .= '<div class="wp-org-actions"><button type="button" class="button button-secondary wp-org-admin-modal-close">Batal</button><button type="submit" class="button button-danger">Ya, Hapus Anggota</button></div>';
        $html .= '</form>';
        $html .= '</div></div>';
        $html .= '</div></div></div>';

        return $html;
    }

    private function get_login_redirect_url_from_settings($general)
    {
        $page_id = isset($general['login_redirect_page']) ? absint($general['login_redirect_page']) : 0;

        if ($page_id > 0) {
            $permalink = get_permalink($page_id);

            if ($permalink) {
                return esc_url_raw($permalink);
            }
        }

        return '';
    }

    private function get_generate_page_definitions()
    {
        return [
            [
                'key' => 'login',
                'label' => 'Login',
                'title' => 'Login',
                'slug' => 'login',
                'shortcode' => '[org_login]',
            ],
            [
                'key' => 'profile',
                'label' => 'Profile',
                'title' => 'Profile',
                'slug' => 'profile',
                'shortcode' => '[org_profile]',
            ],
            [
                'key' => 'members',
                'label' => 'Anggota',
                'title' => 'Anggota',
                'slug' => 'anggota',
                'shortcode' => '[org_members]',
            ],
            [
                'key' => 'register',
                'label' => 'Register',
                'title' => 'Register',
                'slug' => 'register',
                'shortcode' => '[org_register]',
            ],
        ];
    }

    private function get_generate_page_settings()
    {
        $saved = get_option('wp_org_generated_pages', []);
        $settings = [];

        foreach ($this->get_generate_page_definitions() as $definition) {
            $item = isset($saved[$definition['key']]) && is_array($saved[$definition['key']]) ? $saved[$definition['key']] : [];
            $settings[$definition['key']] = [
                'title' => sanitize_text_field($item['title'] ?? $definition['title']),
                'page_id' => absint($item['page_id'] ?? 0),
            ];
        }

        return $settings;
    }

    private function render_member_profile_section($user)
    {
        $fields = MemberData::get_all_registration_fields();
        $regions = new Regions();
        $html = '<div class="wp-org-admin-modal-section">';
        $html .= '<h4>Profil Anggota</h4>';
        $html .= '<form class="wp-org-admin-member-profile-form wp-org-region-form" method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">';
        ob_start();
        wp_nonce_field('wp_org_update_member_profile_' . $user->ID);
        $html .= (string) ob_get_clean();
        $html .= '<input type="hidden" name="action" value="wp_org_update_member_profile">';
        $html .= '<input type="hidden" name="user_id" value="' . esc_attr((string) $user->ID) . '">';
        $html .= '<div class="wp-org-admin-profile-grid">';
        $html .= '<div class="wp-org-admin-profile-field"><label>Username</label><input type="text" value="' . esc_attr($user->user_login) . '" readonly></div>';
        $html .= '<div class="wp-org-admin-profile-field"><label for="wp-org-display-name-' . esc_attr((string) $user->ID) . '">Nama Tampil</label><input id="wp-org-display-name-' . esc_attr((string) $user->ID) . '" type="text" name="display_name" value="' . esc_attr($user->display_name) . '"></div>';
        $html .= '<div class="wp-org-admin-profile-field"><label for="wp-org-email-' . esc_attr((string) $user->ID) . '">Email</label><input id="wp-org-email-' . esc_attr((string) $user->ID) . '" type="email" name="user_email" value="' . esc_attr($user->user_email) . '" required></div>';

        foreach ($fields as $field) {
            $value = get_user_meta($user->ID, 'wp_org_' . $field['key'], true);
            $html .= $this->render_member_profile_field($field, $value, $regions, $user->ID);
        }

        $html .= '</div>';
        $html .= '<div class="wp-org-admin-modal-actions">';
        ob_start();
        submit_button('Simpan Profil', 'secondary', 'submit', false);
        $html .= (string) ob_get_clean();
        $html .= '</div></form></div>';

        return $html;
    }

    private function get_member_number($user_id)
    {
        return MemberData::get_member_number($user_id);
    }

    private function render_member_profile_field($field, $value, Regions $regions, $user_id)
    {
        $key = $field['key'];
        $input_id = 'wp-org-member-' . $user_id . '-' . $key;
        $required = !empty($field['required']) ? ' required' : '';
        $options = MemberData::get_field_options($field);
        $html = '<div class="wp-org-admin-profile-field wp-org-admin-profile-field-' . esc_attr($field['type']) . '">';
        $html .= '<label for="' . esc_attr($input_id) . '">' . esc_html($field['label']) . '</label>';

        if ($field['type'] === 'textarea') {
            $html .= '<textarea id="' . esc_attr($input_id) . '" name="' . esc_attr($key) . '"' . $required . '>' . esc_textarea((string) $value) . '</textarea>';
        } elseif ($field['type'] === 'select') {
            $html .= '<select id="' . esc_attr($input_id) . '" name="' . esc_attr($key) . '"' . $required . '><option value="">Pilih opsi</option>';
            foreach ($options as $option) {
                $html .= '<option value="' . esc_attr($option) . '"' . selected($value, $option, false) . '>' . esc_html($option) . '</option>';
            }
            $html .= '</select>';
        } elseif ($field['type'] === 'radio') {
            $html .= '<div class="wp-org-admin-choice-list">';
            foreach ($options as $option) {
                $html .= '<label><input type="radio" name="' . esc_attr($key) . '" value="' . esc_attr($option) . '"' . checked($value, $option, false) . $required . '> ' . esc_html($option) . '</label>';
            }
            $html .= '</div>';
        } elseif ($field['type'] === 'checkbox') {
            $selected_values = is_array($value) ? $value : (array) $value;
            $html .= '<div class="wp-org-admin-choice-list">';
            foreach ($options as $option) {
                $html .= '<label><input type="checkbox" name="' . esc_attr($key) . '[]" value="' . esc_attr($option) . '"' . checked(in_array($option, $selected_values, true), true, false) . '> ' . esc_html($option) . '</label>';
            }
            $html .= '</div>';
        } elseif ($field['type'] === 'image') {
            if ((string) $value !== '') {
                $html .= '<div class="wp-org-admin-file-preview"><img src="' . esc_url((string) $value) . '" alt="' . esc_attr($field['label']) . '"></div>';
            }
            $html .= '<input id="' . esc_attr($input_id) . '" name="' . esc_attr($key) . '" type="file" accept="image/jpeg,image/png,image/webp"' . $required . '>';
        } elseif ($field['type'] === 'file') {
            if ((string) $value !== '') {
                $html .= '<p class="wp-org-admin-file-link"><a href="' . esc_url((string) $value) . '" target="_blank" rel="noopener">Lihat file saat ini</a></p>';
            }
            $html .= '<input id="' . esc_attr($input_id) . '" name="' . esc_attr($key) . '" type="file"' . $required . '>';
        } elseif ($field['type'] === 'region_province') {
            $html .= '<select id="' . esc_attr($input_id) . '" class="wp-org-province" name="' . esc_attr($key) . '" data-selected="' . esc_attr((string) $value) . '"' . $required . '><option value="">Pilih provinsi</option>';
            foreach ($regions->get_provinces() as $province) {
                $html .= '<option value="' . esc_attr($province['code']) . '"' . selected($value, $province['code'], false) . '>' . esc_html($province['name']) . '</option>';
            }
            $html .= '</select>';
        } elseif ($field['type'] === 'region_city') {
            $html .= '<select id="' . esc_attr($input_id) . '" class="wp-org-city" name="' . esc_attr($key) . '" data-selected="' . esc_attr((string) $value) . '"' . $required . '><option value="">Pilih kota/kabupaten</option></select>';
        } elseif ($field['type'] === 'region_district') {
            $html .= '<select id="' . esc_attr($input_id) . '" class="wp-org-district" name="' . esc_attr($key) . '" data-selected="' . esc_attr((string) $value) . '"' . $required . ' disabled><option value="">Pilih kecamatan</option></select>';
        } else {
            $input_type = in_array($field['type'], ['email', 'number', 'date'], true) ? $field['type'] : 'text';
            $html .= '<input id="' . esc_attr($input_id) . '" name="' . esc_attr($key) . '" type="' . esc_attr($input_type) . '" value="' . esc_attr(is_array($value) ? implode(', ', $value) : (string) $value) . '"' . $required . '>';
        }

        $html .= '</div>';

        return $html;
    }

    private function render_field_row($index, $field)
    {
        $types = [
            'text' => 'Text',
            'textarea' => 'Textarea',
            'email' => 'Email',
            'number' => 'Number',
            'date' => 'Date',
            'image' => 'Image',
            'file' => 'File',
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

        return '<tr' . $row_class . ' data-key-locked="' . ($field['key'] !== '' ? '1' : '0') . '">'
            . '<td class="wp-org-field-order-cell" data-label="Urut"><button type="button" class="wp-org-field-drag-handle" aria-label="Geser untuk ubah urutan" title="Geser untuk ubah urutan"><span></span><span></span><span></span></button></td>'
            . '<td class="wp-org-field-label-cell" data-label="Label"><input type="hidden" class="wp-org-field-delete" name="fields[' . esc_attr((string) $index) . '][_delete]" value="0"><input type="text" class="wp-org-field-key" name="fields[' . esc_attr((string) $index) . '][key]" value="' . esc_attr($field['key']) . '" style="display: none;"><input type="text" class="wp-org-field-label" name="fields[' . esc_attr((string) $index) . '][label]" value="' . esc_attr($field['label']) . '" placeholder="Label field"><p class="wp-org-admin-field-id wp-org-field-key-preview">ID: ' . esc_html($field['key']) . '</p></td>'
            . '<td class="wp-org-field-type-cell" data-label="Tipe"><select class="wp-org-field-type" name="fields[' . esc_attr((string) $index) . '][type]">' . $type_options . '</select></td>'
            . '<td class="wp-org-field-options-cell" data-label="Opsi"><div class="wp-org-field-options-wrap"><textarea name="fields[' . esc_attr((string) $index) . '][options]" placeholder="Satu opsi per baris">' . esc_textarea($field['options'] ?? '') . '</textarea><p class="wp-org-admin-help">Dipakai untuk select, radio, dan checkbox</p></div></td>'
            . '<td class="wp-org-field-flag-cell" data-label="Wajib"><label class="wp-org-admin-switch"><input type="checkbox" name="fields[' . esc_attr((string) $index) . '][required]" value="1"' . checked(!empty($field['required']), true, false) . '></label></td>'
            . '<td class="wp-org-field-flag-cell" data-label="Aktif"><label class="wp-org-admin-switch"><input type="checkbox" class="wp-org-field-enabled" name="fields[' . esc_attr((string) $index) . '][enabled]" value="1"' . checked(!empty($field['enabled']), true, false) . '></label></td>'
            . '<td class="wp-org-field-actions-cell" data-label="Aksi"><button type="button" class="button-link wp-org-edit-key">Edit ID</button> | <button type="button" class="button-link-delete wp-org-remove-field">Hapus</button></td>'
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
