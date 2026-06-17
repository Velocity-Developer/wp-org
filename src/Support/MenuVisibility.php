<?php

namespace WpOrg\Support;

class MenuVisibility
{
    private const META_KEY = '_wp_org_menu_visibility';

    public function register()
    {
        add_action('wp_nav_menu_item_custom_fields', [$this, 'render_field'], 10, 2);
        add_action('wp_update_nav_menu_item', [$this, 'save_field'], 10, 2);
        add_filter('wp_nav_menu_objects', [$this, 'filter_menu_items'], 10, 2);
    }

    public function render_field($item_id, $menu_item)
    {
        $value = get_post_meta($menu_item->ID, self::META_KEY, true);
        $value = is_string($value) && $value !== '' ? $value : 'show_all';

        echo '<p class="description description-wide wp-org-menu-visibility-field">';
        echo '<label for="edit-menu-item-wp-org-visibility-' . esc_attr((string) $menu_item->ID) . '">';
        echo 'WP Org Visibility<br>';
        echo '<select id="edit-menu-item-wp-org-visibility-' . esc_attr((string) $menu_item->ID) . '" name="menu-item-wp-org-visibility[' . esc_attr((string) $menu_item->ID) . ']">';

        foreach ($this->get_options() as $option_value => $label) {
            echo '<option value="' . esc_attr($option_value) . '"' . selected($value, $option_value, false) . '>' . esc_html($label) . '</option>';
        }

        echo '</select>';
        echo '<span class="description">Atur apakah item menu ini tampil untuk semua pengunjung, user login, user logout, member, atau non-member.</span>';
        echo '</label>';
        echo '</p>';
    }

    public function save_field($menu_id, $menu_item_db_id)
    {
        if (!current_user_can('edit_theme_options')) {
            return;
        }

        $raw = isset($_POST['menu-item-wp-org-visibility'][$menu_item_db_id])
            ? sanitize_key(wp_unslash($_POST['menu-item-wp-org-visibility'][$menu_item_db_id]))
            : 'show_all';

        $value = array_key_exists($raw, $this->get_options()) ? $raw : 'show_all';

        update_post_meta($menu_item_db_id, self::META_KEY, $value);
    }

    public function filter_menu_items($items, $args)
    {
        return array_values(array_filter($items, function ($item) {
            $visibility = get_post_meta($item->ID, self::META_KEY, true);
            $visibility = is_string($visibility) && $visibility !== '' ? $visibility : 'show_all';

            switch ($visibility) {
                case 'show_loggedin':
                    return is_user_logged_in();
                case 'show_loggedout':
                    return !is_user_logged_in();
                case 'show_member':
                    return $this->is_member();
                case 'show_nonmember':
                    return !$this->is_member();
                case 'show_all':
                default:
                    return true;
            }
        }));
    }

    private function is_member()
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();

        if (!$user || empty($user->roles)) {
            return false;
        }

        $roles = (array) $user->roles;

        return in_array('org_member', $roles, true) || in_array('org_admin', $roles, true);
    }

    private function get_options()
    {
        return [
            'show_all' => 'Show All',
            'show_loggedin' => 'Show Logged In',
            'show_loggedout' => 'Show Logged Out',
            'show_member' => 'Show Member',
            'show_nonmember' => 'Show Non-Member',
        ];
    }
}
