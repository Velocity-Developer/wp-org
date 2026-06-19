<?php

namespace WpOrg\Support;

class Regions
{
    /**
     * @var array<string, array<int, array<string, string>>>|null
     */
    private $regions;

    public function register()
    {
        add_action('wp_ajax_wp_org_regions', [$this, 'handle_ajax']);
        add_action('wp_ajax_nopriv_wp_org_regions', [$this, 'handle_ajax']);
    }

    public function handle_ajax()
    {
        $type = isset($_GET['type']) ? sanitize_key(wp_unslash($_GET['type'])) : '';
        $parent = isset($_GET['parent']) ? sanitize_text_field(wp_unslash($_GET['parent'])) : '';

        if ($type === 'cities') {
            wp_send_json_success($this->get_cities($parent));
        }

        if ($type === 'districts') {
            wp_send_json_success($this->get_districts($parent));
        }

        wp_send_json_success($this->get_provinces());
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function get_provinces()
    {
        $regions = $this->get_regions();

        return $regions['provinces'];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function get_cities($province_code)
    {
        $regions = $this->get_regions();

        return array_values(array_filter($regions['cities'], static function ($city) use ($province_code) {
            return $city['province_code'] === $province_code;
        }));
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function get_districts($city_code)
    {
        $regions = $this->get_regions();

        return array_values(array_filter($regions['districts'], static function ($district) use ($city_code) {
            return $district['city_code'] === $city_code;
        }));
    }

    public function get_province_name($code)
    {
        return $this->find_name('provinces', 'code', $code);
    }

    public function get_city_name($code)
    {
        return $this->find_name('cities', 'code', $code);
    }

    public function get_district_name($code)
    {
        return $this->find_name('districts', 'code', $code);
    }

    /**
     * @return array<string, array<int, array<string, string>>>
     */
    public function get_regions()
    {
        if ($this->regions !== null) {
            return $this->regions;
        }

        $path = WP_ORG_PATH . 'data/regions.json';
        if (!file_exists($path)) {
            $this->regions = [
                'provinces' => [],
                'cities' => [],
                'districts' => [],
            ];

            return $this->regions;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        $this->regions = [
            'provinces' => isset($decoded['provinces']) && is_array($decoded['provinces']) ? $decoded['provinces'] : [],
            'cities' => isset($decoded['cities']) && is_array($decoded['cities']) ? $decoded['cities'] : [],
            'districts' => isset($decoded['districts']) && is_array($decoded['districts']) ? $decoded['districts'] : [],
        ];

        return $this->regions;
    }

    private function find_name($bucket, $key, $value)
    {
        $regions = $this->get_regions();

        foreach ($regions[$bucket] as $item) {
            if ($item[$key] === $value) {
                return $item['name'];
            }
        }

        return '';
    }
}
