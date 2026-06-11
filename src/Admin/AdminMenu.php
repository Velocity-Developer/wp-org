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
        add_action('admin_post_wp_org_save_member_card_settings', [$this, 'handle_save_member_card_settings']);
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
            'number' => 100,
            'orderby' => 'registered',
            'order' => 'DESC',
        ];

        if ($search !== '') {
            $user_args['search'] = '*' . $search . '*';
            $user_args['search_columns'] = ['user_login', 'user_email', 'display_name'];
        }

        if ($meta_query !== []) {
            $user_args['meta_query'] = $meta_query;
        }

        $users = get_users($user_args);
        $status_totals = array_fill_keys(array_keys($statuses), 0);
        $premium_totals = array_fill_keys(array_keys($premium_statuses), 0);

        foreach ($users as $user) {
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
        echo '<div class="wp-org-admin-stat"><span class="wp-org-admin-stat-label">Total Anggota</span><strong>' . esc_html((string) count($users)) . '</strong></div>';
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
        echo '<div class="wp-org-admin-card wp-org-admin-table-card"><table class="widefat striped wp-org-admin-table"><thead><tr><th>Nama</th><th>No. Anggota</th><th>Email</th><th>Status</th><th>Premium</th><th>Tanggal Daftar</th><th>Catatan Admin</th><th>Aksi</th></tr></thead><tbody>';

        if (!$users) {
            echo '<tr><td colspan="8">Belum ada anggota.</td></tr>';
        }

        foreach ($users as $user) {
            $member_number = $this->get_member_number($user->ID);
            $status = MemberData::get_status($user->ID);
            $note = get_user_meta($user->ID, 'wp_org_admin_note', true);
            $premium_status = MemberData::get_premium_status($user->ID);
            $premium_ref = get_user_meta($user->ID, 'wp_org_premium_reference', true);
            $premium_proof_url = get_user_meta($user->ID, 'wp_org_premium_proof_url', true);
            echo '<tr><td><strong>' . esc_html($user->display_name) . '</strong></td><td><code>' . esc_html($member_number) . '</code></td><td><a href="mailto:' . esc_attr($user->user_email) . '">' . esc_html($user->user_email) . '</a></td><td><span class="wp-org-admin-badge wp-org-admin-badge-' . esc_attr($status) . '">' . esc_html($statuses[$status] ?? $status) . '</span></td><td><span class="wp-org-admin-badge wp-org-admin-badge-premium-' . esc_attr($premium_status) . '">' . esc_html($premium_statuses[$premium_status] ?? $premium_status) . '</span>' . ($premium_ref ? '<br><small class="wp-org-admin-subtle">' . esc_html($premium_ref) . '</small>' : '') . ($premium_proof_url ? '<br><a class="wp-org-admin-link" href="' . esc_url($premium_proof_url) . '" target="_blank" rel="noopener">Lihat Bukti</a>' : '') . '</td><td>' . esc_html(get_user_meta($user->ID, 'wp_org_registered_at', true) ?: $user->user_registered) . '</td><td>' . ($note ? esc_html($note) : '<span class="wp-org-admin-subtle">Belum ada catatan</span>') . '</td><td><button type="button" class="button button-secondary wp-org-admin-open-modal" data-modal-target="wp-org-member-modal-' . esc_attr((string) $user->ID) . '">Kelola</button>' . $this->render_member_action_modal($user, $statuses, $status, $note, $premium_statuses, $premium_status) . '</td></tr>';
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
        echo '<div class="wp-org-admin-fields-toolbar"><div><h2>Daftar Field</h2><p class="description">Tambah, hapus, aktifkan, atau nonaktifkan field pendaftaran. Field nonaktif tetap tersimpan tetapi tidak ditampilkan di frontend.</p></div><button type="button" class="button button-secondary" id="wp-org-add-field">Tambah Field</button></div>';
        echo '<div class="wp-org-admin-note"><strong>Panduan cepat:</strong> isi <code>Label</code> seperti biasa, lalu sistem akan membuat <code>key</code> otomatis dalam format underscore. Kolom <code>opsi</code> hanya dipakai untuk field pilihan.</div>';
        echo '<div class="wp-org-admin-table-card"><table class="widefat striped wp-org-fields-table wp-org-admin-table"><thead><tr><th>Label</th><th>Tipe</th><th>Opsi</th><th>Wajib</th><th>Aktif</th><th>Aksi</th></tr></thead><tbody data-next-index="' . esc_attr((string) count($fields)) . '">';

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
        $seed_message = isset($_GET['seeded']) ? absint($_GET['seeded']) : -1;
        $payment_banks = array_values((array) get_option('wp_org_payment_banks', []));
        $member_card = get_option('wp_org_member_card_settings', []);

        echo '<div class="wrap wp-org-admin"><div class="wp-org-admin-hero"><div><h1>Pengaturan WP Org</h1><p>Seluruh konfigurasi utama plugin dikumpulkan dalam panel yang lebih terstruktur.</p></div></div>';
        echo '<nav class="nav-tab-wrapper wp-org-admin-tabs">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=wp-org-settings&tab=general')) . '" class="nav-tab ' . ($active_tab === 'general' ? 'nav-tab-active' : '') . '">Umum</a>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=wp-org-settings&tab=data')) . '" class="nav-tab ' . ($active_tab === 'data' ? 'nav-tab-active' : '') . '">Data</a>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=wp-org-settings&tab=payment-banks')) . '" class="nav-tab ' . ($active_tab === 'payment-banks' ? 'nav-tab-active' : '') . '">Bank Pembayaran</a>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=wp-org-settings&tab=member-card')) . '" class="nav-tab ' . ($active_tab === 'member-card' ? 'nav-tab-active' : '') . '">Kartu Anggota</a>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=wp-org-settings&tab=documentation')) . '" class="nav-tab ' . ($active_tab === 'documentation' ? 'nav-tab-active' : '') . '">Dokumentasi</a>';
        echo '</nav>';

        if ($active_tab === 'data') {
            if ($seed_message >= 0) {
                echo '<div class="notice notice-success is-dismissible"><p>Seeder anggota selesai. ' . esc_html((string) $seed_message) . ' anggota baru dibuat.</p></div>';
            }

            echo '<div class="wp-org-admin-card"><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('wp_org_seed_members');
            echo '<input type="hidden" name="action" value="wp_org_seed_members">';
            echo '<table class="form-table"><tbody>';
            echo '<tr><th scope="row">Jumlah Seeder</th><td><input class="small-text" type="number" min="1" max="100" name="seed_total" value="10"><p class="description">Membuat data anggota contoh dengan status approved dan field profil dasar.</p></td></tr>';
            echo '<tr><th scope="row">Password Default</th><td><input class="regular-text" type="text" name="seed_password" value="Member123!"><p class="description">Password ini dipakai untuk seluruh akun hasil seeder.</p></td></tr>';
            echo '</tbody></table>';
            submit_button('Jalankan Seeder Anggota');
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
            echo '</tbody></table>';
            submit_button('Simpan Pengaturan Kartu');
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

        update_option('wp_org_member_card_settings', [
            'organization_name' => sanitize_text_field($member_card['organization_name'] ?? 'WP Org'),
            'member_number_prefix' => sanitize_text_field($member_card['member_number_prefix'] ?? 'ORG'),
            'background_url' => $background_url,
            'logo_url' => $logo_url,
        ]);

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
        if (!in_array($hook_suffix, ['toplevel_page_wp-org', 'wp-org_page_wp-org-fields', 'wp-org_page_wp-org-settings'], true)) {
            return;
        }

        wp_enqueue_style(
            'wp-org-admin',
            WP_ORG_URL . 'assets/frontend/css/admin.css',
            ['wp-admin'],
            WP_ORG_VERSION
        );
        wp_add_inline_script(
            'jquery-core',
            <<<'JS'
jQuery(function($){
    function slugifyFieldKey(label) {
        return (label || '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '')
            .replace(/_+/g, '_');
    }

    function fieldTypeNeedsOptions(type) {
        return ['select', 'radio', 'checkbox'].indexOf(type) !== -1;
    }

    function syncFieldKey($row) {
        var $labelInput = $row.find('.wp-org-field-label');
        var $keyInput = $row.find('.wp-org-field-key');
        var $keyPreview = $row.find('.wp-org-field-key-preview');
        var key = slugifyFieldKey($labelInput.val());

        $keyInput.val(key);
        $keyPreview.text(key ? 'ID: ' + key : 'ID akan dibuat otomatis dari label');
    }

    function syncOptionState($row) {
        var type = $row.find('.wp-org-field-type').val();
        var $optionsCell = $row.find('.wp-org-field-options-cell');
        var shouldShow = fieldTypeNeedsOptions(type);
        $optionsCell.toggleClass('is-hidden', !shouldShow);
    }

    function syncRowState($row) {
        var isEnabled = $row.find('.wp-org-field-enabled').is(':checked');
        $row.toggleClass('wp-org-field-row-disabled', !isEnabled);
        syncFieldKey($row);
        syncOptionState($row);
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

    $(document).on('change', '.wp-org-field-type', function() {
        syncOptionState($(this).closest('tr'));
    });

    $(document).on('input', '.wp-org-field-label', function() {
        syncFieldKey($(this).closest('tr'));
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

    $(document).on('click', '.wp-org-admin-open-modal', function() {
        var target = $(this).data('modal-target');
        $('#' + target).addClass('is-open').attr('aria-hidden', 'false');
        $('body').addClass('wp-org-modal-open');
    });

    $(document).on('click', '.wp-org-admin-modal-close', function() {
        $(this).closest('.wp-org-admin-modal').removeClass('is-open').attr('aria-hidden', 'true');
        $('body').removeClass('wp-org-modal-open');
    });

    $(document).on('click', '.wp-org-admin-modal', function(e) {
        if ($(e.target).is('.wp-org-admin-modal')) {
            $(this).removeClass('is-open').attr('aria-hidden', 'true');
            $('body').removeClass('wp-org-modal-open');
        }
    });

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('.wp-org-admin-modal.is-open').removeClass('is-open').attr('aria-hidden', 'true');
            $('body').removeClass('wp-org-modal-open');
        }
    });
});
JS
        );
    }

    private function render_member_action_modal($user, $statuses, $status, $note, $premium_statuses, $premium_status)
    {
        $modal_id = 'wp-org-member-modal-' . $user->ID;
        $member_number = $this->get_member_number($user->ID);
        $premium_note = (string) get_user_meta($user->ID, 'wp_org_premium_note', true);
        $html = '<div id="' . esc_attr($modal_id) . '" class="wp-org-admin-modal" aria-hidden="true"><div class="wp-org-admin-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="' . esc_attr($modal_id) . '-title">';
        $html .= '<div class="wp-org-admin-modal-header"><div><h3 id="' . esc_attr($modal_id) . '-title">Kelola ' . esc_html($user->display_name) . '</h3><p class="wp-org-admin-subtle" style="margin:0">Nomor anggota: <code>' . esc_html($member_number) . '</code><br>' . esc_html($user->user_email) . '</p></div><button type="button" class="wp-org-admin-modal-close" aria-label="Tutup">&times;</button></div>';
        $html .= '<div class="wp-org-admin-modal-grid">';
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
        $html .= '</div></div></div>';

        return $html;
    }

    private function get_member_number($user_id)
    {
        return MemberData::get_member_number($user_id);
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

        return '<tr' . $row_class . '>'
            . '<td class="wp-org-field-label-cell" data-label="Label"><input type="hidden" class="wp-org-field-delete" name="fields[' . esc_attr((string) $index) . '][_delete]" value="0"><input type="hidden" class="wp-org-field-key" name="fields[' . esc_attr((string) $index) . '][key]" value="' . esc_attr($field['key']) . '"><input type="text" class="wp-org-field-label" name="fields[' . esc_attr((string) $index) . '][label]" value="' . esc_attr($field['label']) . '" placeholder="Label field"><p class="wp-org-admin-field-id wp-org-field-key-preview">ID: ' . esc_html($field['key']) . '</p></td>'
            . '<td class="wp-org-field-type-cell" data-label="Tipe"><select class="wp-org-field-type" name="fields[' . esc_attr((string) $index) . '][type]">' . $type_options . '</select></td>'
            . '<td class="wp-org-field-options-cell" data-label="Opsi"><div class="wp-org-field-options-wrap"><textarea name="fields[' . esc_attr((string) $index) . '][options]" placeholder="Satu opsi per baris">' . esc_textarea($field['options'] ?? '') . '</textarea><p class="wp-org-admin-help">Dipakai untuk select, radio, dan checkbox</p></div></td>'
            . '<td class="wp-org-field-flag-cell" data-label="Wajib"><label class="wp-org-admin-switch"><input type="checkbox" name="fields[' . esc_attr((string) $index) . '][required]" value="1"' . checked(!empty($field['required']), true, false) . '></label></td>'
            . '<td class="wp-org-field-flag-cell" data-label="Aktif"><label class="wp-org-admin-switch"><input type="checkbox" class="wp-org-field-enabled" name="fields[' . esc_attr((string) $index) . '][enabled]" value="1"' . checked(!empty($field['enabled']), true, false) . '></label></td>'
            . '<td class="wp-org-field-actions-cell" data-label="Aksi"><button type="button" class="button-link-delete wp-org-remove-field">Hapus</button></td>'
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
