<?php
/**
 * @package Newscoop
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

require_once __DIR__ . '/AbstractController.php';
require_once($GLOBALS['g_campsiteDir'].'/classes/ArticleTopic.php');

/**
 */
class Api_LunchgateController extends AbstractController 
{
    const API_VERSION = 1;
    const BASE_URL = '/api/lunchgate';

    const PUBLICATION = 1;
    const LANGUAGE = 5;
    const EV_SECTION = 73; // restaurants
    const EV_TYPE = 'restaurant';

    CONST IMAGE_THUMBNAIL_WIDTH_NORMAL = '70';
    CONST IMAGE_THUMBNAIL_HEIGHT_NORMAL = '70';
    CONST IMAGE_WIDTH_NORMAL = '940';
    CONST IMAGE_HEIGHT_NORMAL = '350';

    CONST IMAGE_THUMBNAIL_WIDTH_RETINA = '140';
    CONST IMAGE_THUMBNAIL_HEIGHT_RETINA = '140';
    CONST IMAGE_WIDTH_RETINA = '940';
    CONST IMAGE_HEIGHT_RETINA = '350';

    CONST IMAGE_WIDTH_RESTRICT = '940,';

    CONST DISTANCE_RADIUS_KM = 10.0;
    //CONST DISTANCE_RADIUS_KM = 0.200;
    CONST COUNT_LIMIT = 40;


    /** @var Zend_Controller_Request_Http */
    private $request;

    /** @var array */
    private $m_client;

    /** @var Newscoop\Services\AgendaService */
    private $m_service;

    private $req_date;

    private $client_image_thumbnail_height;
    private $client_image_thumbnail_width;
    private $client_image_height;
    private $client_image_width;

    private $m_pano_hash;
    private $client_pano_width;
    private $client_pano_height;

    private $m_center;
    private $m_square_set;
    private $m_lat_min;
    private $m_lat_max;
    private $m_lon_min;
    private $m_lon_max;

    private $m_limit_distance;
    private $m_limit_count;

    private $m_image_width_restrict;

    /**
     *
     */
    public function init()
    {
        global $Campsite;

        $this->url = $Campsite['WEBSITE_URL'];

        $this->m_service = $this->_helper->service('agenda');

        $this->client_image_thumbnail_width = self::IMAGE_THUMBNAIL_WIDTH_NORMAL;
        $this->client_image_thumbnail_height = self::IMAGE_THUMBNAIL_HEIGHT_NORMAL;
        $this->client_image_width = self::IMAGE_WIDTH_NORMAL;
        $this->client_image_height = self::IMAGE_HEIGHT_NORMAL;

        $param_client = $this->_request->getParam('client');
        if (in_array($param_client, array('iphone_retina', 'ipad_retina'))) {
            $this->client_image_thumbnail_width = self::IMAGE_THUMBNAIL_WIDTH_RETINA;
            $this->client_image_thumbnail_height = self::IMAGE_THUMBNAIL_HEIGHT_RETINA;
            $this->client_image_width = self::IMAGE_WIDTH_RETINA;
            $this->client_image_height = self::IMAGE_HEIGHT_RETINA;
        }

        $this->client_pano_width = 640;
        $this->client_pano_height = 240;
        $this->client_pano_width = $this->client_image_width;
        $this->client_pano_height = $this->client_image_height;

        $this->m_pano_hash = '';
        require_once($GLOBALS['g_campsiteDir'].DIRECTORY_SEPARATOR.'classes'.DIRECTORY_SEPARATOR.'SystemPref.php');
        $panorama_hash_key = SystemPref::Get('RestaurantPanoramaHashKey');
        if (!empty($panorama_hash_key)) {
            $this->m_pano_hash = $panorama_hash_key;
        }

        $this->m_center = null;
        $this->m_square_set = false;
        $this->m_lat_min = 0;
        $this->m_lat_max = 0;
        $this->m_lon_min = 0;
        $this->m_lon_max = 0;
        $pi = pi();


        $use_distance_radius_km = self::DISTANCE_RADIUS_KM;
        if (array_key_exists('distance', $this->_request->getParams())) {
            $use_distance_radius_km = 0 + $this->_request->getParam('distance');
        }

        if (array_key_exists('longitude', $this->_request->getParams()) && array_key_exists('latitude', $this->_request->getParams())) {
            $param_longitude = 0 + $this->_request->getParam('longitude');
            $param_latitude = 0 + $this->_request->getParam('latitude');
            $this->m_center = array('longitude' => $param_longitude, 'latitude' => $param_latitude);

            $dist_lat_per_radius = $use_distance_radius_km / (6378 * $pi / 180);
            $this->m_lat_min = $param_latitude - $dist_lat_per_radius;
            $this->m_lat_max = $param_latitude + $dist_lat_per_radius;
            if (-90 > $this->m_lat_min) {
                $this->m_lat_min = -90;
            }
            if (90 < $this->m_lat_max) {
                $this->m_lat_max = 90;
            }

            $this->m_lon_min = -180;
            $this->m_lon_max = 180;
            $param_lat_coef = cos($param_latitude * $pi / 180);
            if (0.000000001 < $param_lat_coef) { // taking an arbitrary cut-off that is small enough for any practical purpose
                $dist_lon_per_radius = (1.0 / $param_lat_coef) * $use_distance_radius_km / (6378 * $pi / 180);
                if (180 > $dist_lon_per_radius) {
                    $this->m_lon_min = $param_longitude - $dist_lon_per_radius;
                    $this->m_lon_max = $param_longitude + $dist_lon_per_radius;
                    if (-180 > $this->m_lon_min) {
                        $this->m_lon_min += 360;
                    }
                    if (180 < $this->m_lon_max) {
                        $this->m_lon_max -= 360;
                    }
                }
            }

            $this->m_square_set = true;
        }

        $this->m_image_width_restrict = array();
        foreach (explode(',', self::IMAGE_WIDTH_RESTRICT) as $one_allowed_width) {
            $one_allowed_width = 0 + trim($one_allowed_width);
            if (!empty($one_allowed_width)) {
                $this->m_image_width_restrict[] = $one_allowed_width;
            }
        }

        //$this->_helper->layout->disableLayout();
        //$this->params = $this->getRequest()->getParams();
        //$this->url = $Campsite['WEBSITE_URL'];
    }

    /**
     */
    public function indexAction()
    {
        $this->_forward('list');
    }

    /**
     * Serve list of restaurants.
     */
    public function listAction()
    {
        $basically_correct = true;

        $param_version = $this->_request->getParam('version');
        $param_client = $this->_request->getParam('client');
        if (empty($param_version) || empty($param_client)) {
            $basically_correct = false;
        }
        if (!in_array($param_client, array('iphone', 'iphone_retina', 'ipad', 'ipad_retina'))) {
            $basically_correct = false;
        }

        if (!$basically_correct) {

            $output_data = array();
            //$output_json = json_encode($output_data);
            $output_json = Zend_Json::encode($output_data);

            header('Content-Type: application/json; charset=utf-8');
            header('Content-Length: ' . strlen($output_json));

            echo $output_json;
            exit(0);

        }

        $cuisine_types_used = array();
        $cuisine_type_other = $this->m_service->getCuisineTypeOther(array('country' => 'ch'));
        $cuisine_type_other_used = false;

        $cuis_list_rev = array();
        foreach ($this->m_service->getCuisineTypeList(array('country' => 'ch')) as $cur_cuis) {
            $cuis_list_rev[$cur_cuis['label']] = $cur_cuis['outer'];
        }

        $event_list = $this->_innerListProcess();
//        echo count($event_list);

        //$cur_date = date('Y-m-d');
        //$cur_date_time = date('Y-m-d H:i:s');

        $event_list_pre = array();
        foreach ($event_list as $one_event) {

            $one_latitude = null;
            $one_longitude = null;

            $one_map = $one_event->getMap();
            if (is_object($one_map) && $one_map->exists()) {
                $one_location_set = $one_map->getLocations();
                if (!empty($one_location_set)) {
                    $one_location = $one_location_set[0];
                    $one_latitude = $one_location->getLatitude();
                    $one_longitude = $one_location->getLongitude();
                }
            }

            $event_list_pre[] = array(
                'article' => $one_event,

                'geo' => array(
                    'latitude' => $one_latitude,
                    'longitude' => $one_longitude,
                    'distance' => null,
                ),
            );
        }

        unset($event_list);

        $event_list_use = array();
        $event_list_use_set = false;
        if (!empty($this->m_center)) {
            $event_list_pre = $this->m_service->sortByDistance($event_list_pre, $this->m_center);
            if (0 < $this->m_limit_distance) {
                $event_list_use_set = true;
                foreach ($event_list_pre as $one_event_pre) {
                    if ($one_event_pre['distance'] > $this->m_limit_distance) {
                        continue;
                    }
                    $event_list_use[] = $one_event_pre;
                }
            }
            if (0 < $this->m_limit_count) {
                $event_list_use_set = true;
                $event_list_use = array_slice($event_list_use, 0, $this->m_limit_count);
            }
        }
        if (!$event_list_use_set) {
            $event_list_use = $event_list_pre;
        }


        $event_list_data = array();
        foreach ($event_list_use as $one_event_use) {
            $one_event = $one_event_use['article'];
            $one_geo = $one_event_use['geo'];

            $one_event_types = array();
            $one_data = $one_event->getArticleData();

            $one_description = $one_data->getProperty('Fdescription');

            $one_phone = $one_data->getProperty('Fphone');
            if (empty($one_phone)) {
                $one_phone = null;
            }
            $one_web = $one_data->getProperty('Fweb');
            if (empty($one_web)) {
                $one_web = null;
            }
            $one_email = $one_data->getProperty('Femail');
            if (empty($one_email)) {
                $one_email = null;
            }

            $one_event_id = urlencode($one_data->getProperty('Fevent_id'));

            $one_pano_available = false;
            try {
                $one_pano_count = $one_data->getProperty('Frest_panorama_count');
                if (!empty($one_pano_count)) {
                    $one_pano_available = true;
                }
            }
            catch (\Exception $exc) {
            }

            $one_image_first = null;
            $one_image_preview = null;
            $one_image_ids = array();
            $one_image_rank = 0;
            while (true) {
                $one_image_rank += 1;
                $articleImage = new \ArticleImage($one_event->getArticleNumber(), null, $one_image_rank);
                if ($articleImage->exists()) {
                    $cur_image_id = $articleImage->getImageId();
                    $cur_image = new \Image($cur_image_id);
                    $cur_image_path = $cur_image->getImageStorageLocation();
                    $cur_image_sizes = getimagesize($cur_image_path);
                    $cur_image_width = 0 + $cur_image_sizes[0];
                    $cur_image_height = 0 + $cur_image_sizes[1];

                    // this 'if' should be removed after a new loading
                    if (400 == $cur_image_width) {
                        $one_pano_available = true;
                    }

                    if (!$one_image_first) {
                        $one_image_first = $cur_image_id;
                    }

                    if (empty($one_image_preview)) {
                        if ($cur_image_width == $cur_image_height) {
                            $one_image_preview = $cur_image_id;
                        }
                    }

                    if (!empty($this->m_image_width_restrict)) {
                        if (!in_array($cur_image_width, $this->m_image_width_restrict)) {
                            continue;
                        }
                    }

                    $one_image_ids[] = $cur_image_id;
                }
                else {
                    break;
                }
            }

            if ((!$one_image_preview) && $one_image_first) {
                $one_image_preview = $one_image_first;
            }

            $one_image_url_preview = null;
            if ($one_image_preview) {
                $one_image_url_preview = $this->url . '/get_img?ImageHeight=' . $this->client_image_thumbnail_height . '&ImageWidth=' . $this->client_image_thumbnail_width . '&ImageCrop=center&ImageId=' . $one_image_preview;
            }

            $one_image_urls = null;
            if (!empty($one_image_ids)) {
                $one_image_urls = array();
                foreach ($one_image_ids as $cur_one_img_id) {
                    $one_image_urls[] = $this->url . '/get_img?ImageHeight=' . $this->client_image_height . '&ImageWidth=' . $this->client_image_width . '&ImageCrop=center&ImageId=' . $cur_one_img_id;
                }
            }

            $one_pano_width = $this->client_pano_width;
            $one_pano_height = $this->client_pano_height;
            $one_pano_hash = $this->m_pano_hash;

            $one_panorama_url = null;
            if ($one_pano_available) {
                //$one_panorama_url = 'http://www.lunchgate.ch/embed.php?name=' . $one_data->getProperty('Fevent_id') . '&w=' . $one_pano_width . '&h=' . $one_pano_height . '&hash=' . $one_pano_hash . '&wmode=transparent';
                $one_panorama_url = 'http://www.lunchgate.ch/embed.php?name=' . $one_data->getProperty('Fevent_id') . '&hash=' . $one_pano_hash . '&wmode=transparent';
            }

            $one_reserv_url = $this->url . self::BASE_URL . '/reservation?rest-id=' . $one_data->getProperty('Flocation_id') . '&response=redirect';

            $one_desc_main = strip_tags('' . $one_description);
            $one_desc_other = strip_tags(str_replace(array("\n", "\r"), ' ', '' . $one_data->getProperty('Fother')));
            $one_descs_data = array();
            if (!empty($one_desc_main)) {
                $one_descs_data[] = $one_desc_main;
            }
            if (!empty($one_desc_other)) {
                $one_descs_data[] = $one_desc_other;
            }
            
            $one_descriptions_all = null;
            if (!empty($one_descs_data)) {
                $one_descriptions_all = implode("\n\n", $one_descs_data);
            }

            $one_title = '';
            try {
                $one_title = $this->m_service->entities2Utf8($one_data->getProperty('Fheadline'));
            }
            catch (\Exception $exc) {
                $one_title = '';
            }

            $one_cuisine_filter = array();
            $one_cuisine_other = array();
            $one_other_filter_used = false;
            foreach ((array) $this->m_service->takeBodyList($one_data->getProperty('Frest_cuisine')) as $cur_one_cuisine) {
                if (isset($cuis_list_rev[$cur_one_cuisine])) {
                    $one_cuisine_filter[$cuis_list_rev[$cur_one_cuisine]] = $cur_one_cuisine;
                    $cuisine_types_used[$cuis_list_rev[$cur_one_cuisine]] = $cur_one_cuisine;
                }
                else {
                    $one_cuisine_other[] = $cur_one_cuisine;
                    $one_other_filter_used = true;
                    $cuisine_type_other_used = true;
                }
            }
            if (empty($one_cuisine_filter)) {
                $one_other_filter_used = true;
                $cuisine_type_other_used = true;
            }

            asort($one_cuisine_filter);
            $one_cuisine_filter_display = array();
            foreach ($one_cuisine_filter as $one_cuisine_filter_id => $one_cuisine_filter_name) {
                $one_cuisine_filter_display[] = array('cuisine_id' => $one_cuisine_filter_id, 'cuisine_name' => $one_cuisine_filter_name);
            }
            if ($one_other_filter_used) {
                $one_cuisine_filter_display[] = array('cuisine_id' => $cuisine_type_other['outer'], 'cuisine_name' => $cuisine_type_other['label']);
            }

            sort($one_cuisine_other);
            if (empty($one_cuisine_other)) {
                $one_cuisine_other = null;
            }

            $one_gaultmillau = str_replace('.0', '', ''.$one_data->getProperty('Frest_gaultmillau'));
            if (empty($one_gaultmillau)) {
                $one_gaultmillau = null;
            }

            $one_date_time_info = $this->m_service->getRestTimeText($one_data->getProperty('Fdate_time_text'));
            $one_open_info = null;
            $one_holiday_info = null;
            if (isset($one_date_time_info['open']) && (!empty($one_date_time_info['open']))) {
                $one_open_info = $one_date_time_info['open'];
            }
            if (isset($one_date_time_info['holiday']) && (!empty($one_date_time_info['holiday']))) {
                $one_holiday_info = $one_date_time_info['holiday'];
            }

            $daily_hours = null;
            if (isset($one_date_time_info['open_list']) && (!empty($one_date_time_info['open_list']))) {
                if (isset($one_date_time_info['holiday_list'])) {
                    $daily_hours = $this->m_service->getDayHoursList($one_date_time_info['open_list'], $one_date_time_info['holiday_list'], $this->req_date);
                }
            }
            if (empty($daily_hours)) {
                $daily_hours = null;
            }

            $closed_message = $this->m_service->getRestDaysNotice($one_data->getProperty('Fdate_time_text'), $this->req_date, 1);
            if (empty($closed_message)) {
                $closed_message = null;
            }

            //$one_speciality = $this->m_service->getRestMenuText(''.$one_data->getProperty('Frest_speciality'));
            //if (empty($one_speciality)) {
            //    $one_speciality = null;
            //}

            $one_menu_all = $this->url . self::BASE_URL . '/menus?url-name=' . $one_data->getProperty('Fevent_id') . '&menu-type=all';

            $one_menucards_common = $this->url . self::BASE_URL . '/menucards?url-name=' . $one_data->getProperty('Fevent_id') . '&menucard-type=';

            $one_rest_fb_url = $one_data->getProperty('Frest_fb_url');
            if ($one_rest_fb_url) {
                if (substr_compare($one_rest_fb_url, 'http', 0, strlen('http'))) {
                    $one_rest_fb_url = 'http://' . $one_rest_fb_url;
                }
            }
            $one_rest_twitter = $one_data->getProperty('Frest_twitteraccount');
            if ($one_rest_twitter) {
                if (substr_compare($one_rest_twitter, 'http', 0, strlen('http'))) {
                    $one_rest_twitter = null;
                }
            }

            $social_array = array();
            if (!empty($one_rest_twitter)) {
                $social_array[] = array('name' => 'twitter', 'display_name' => 'Twitter', 'type' => 'web', 'value' => $one_rest_twitter);
            }
            if (!empty($one_rest_fb_url)) {
                $social_array[] = array('name' => 'facebook', 'display_name' => 'Facebook', 'type' => 'web', 'value' => $one_rest_fb_url);
            }
            if (empty($social_array)) {
                $social_array = null;
            }

            $types_array = array();
            $types_array_ambiance = $this->m_service->takeBodyList($one_data->getProperty('Frest_ambiance'));
            if (!empty($types_array_ambiance)) {
                $types_array[] = array('name' => 'ambiance', 'display_name' => 'Ambiente', 'value' => $types_array_ambiance);
            }
            $types_array_services = $this->m_service->takeBodyList($one_data->getProperty('Frest_services'));
            if (!empty($types_array_services)) {
                $types_array[] = array('name' => 'services', 'display_name' => 'Services', 'value' => $types_array_services);
            }
            $types_array_preparation = $this->m_service->takeBodyList($one_data->getProperty('Frest_preparation'));
            if (!empty($types_array_preparation)) {
                $types_array[] = array('name' => 'preparation', 'display_name' => 'Zubereitung', 'value' => $types_array_preparation);
            }
            $types_array_paymentmethods = $this->m_service->takeBodyList($one_data->getProperty('Frest_paymentmethods'));
            if (!empty($types_array_paymentmethods)) {
                $types_array[] = array('name' => 'paymentmethods', 'display_name' => 'Zahlungsmöglichkeiten', 'value' => $types_array_paymentmethods);
            }
            if (empty($types_array)) {
                $types_array = null;
            }

            $event_list_data[] = array(
                'name' => $one_title,
                'uid' => $one_data->getProperty('Flocation_id'),
                'url_name' => $one_data->getProperty('Fevent_id'),

                'cuisine' => $one_cuisine_filter_display,
                'cuisine_other' => $one_cuisine_other,

                //'prices' => array(
                //    array('min' => 0, 'max' => 40, 'value' => null),
                //    array('min' => 40, 'max' => 100, 'value' => null),
                //    array('min' => 100, 'max' => null, 'value' => null),
                //),

                'address' => array(
                    'street' => $one_data->getProperty('Fstreet'),
                    'town' => $one_data->getProperty('Ftown'),
                    'country' => ('ch' == strtolower($one_data->getProperty('Fcountry'))) ? 'Schweiz' : $one_data->getProperty('Fcountry'),
                    'zipcode' => $one_data->getProperty('Fzipcode'),
                ),
                'geo' => $one_geo,
                'contact' => array(
                    'phone' => $one_phone,
                    'web' => $one_web,
                    'email' => $one_email,
                    'reservation_url' => $one_reserv_url,
                ),

                'media' => array(
                    'preview_image_url' => $one_image_url_preview,
                    'panorama_url' => $one_panorama_url,
                    'images' => $one_image_urls,
                ),

                'types' => $types_array,

                'seats' => array(
                    array('name' => 'inner', 'display_name' => 'innen', 'value' => $one_data->getProperty('Frest_seats_in')),
                    array('name' => 'outer', 'display_name' => 'aussen', 'value' => $one_data->getProperty('Frest_seats_out')),
                ),

                //'smoke' => $one_data->getProperty('Frest_smoke'),
                'smoke' => null,
                'gaultmillau' => $one_gaultmillau,
                'description' => $one_descriptions_all,

                'menus' => $one_menu_all,
                'menucards' => array(
                    array('name' => 'menucard_pdf', 'display_name' => 'Komplette Menükarte (PDF)', 'type' => 'pdf', 'value' => $one_menucards_common.'menucard-pdf'),
                    array('name' => 'winecard_pdf', 'display_name' => 'Weinkarte (PDF)', 'type' => 'pdf', 'value' => $one_menucards_common.'winecard-pdf'),
                    array('name' => 'kidscard_pdf', 'display_name' => 'Kindermenüs (PDF)', 'type' => 'pdf', 'value' => $one_menucards_common.'kidscard-pdf'),
                ),

                'social' => $social_array,

                'open' => array(
                    'week_hours' => $one_open_info,
                    'holiday' => $one_holiday_info,
                    'closed_message' => $closed_message,
                    'daily_hours' => $daily_hours,
                ),
            );
        }

        unset($event_list_use);

        $output_regions = array();
        $output_region_rank = -1;
        foreach ($this->m_service->getRegionList(array('country' => 'ch')) as $region_key => $region_info) {
            $output_region_rank += 1;
            $output_regions[] = array(
                'region_id' => $region_key,
                'region_name' => $region_info['label'],
                'rank' => $output_region_rank,
            );
        }

        $output_types = array();
        $output_type_rank = -1;
        asort($cuisine_types_used);
        foreach ($cuisine_types_used as $one_cuisine_label => $one_cuisine_name) {
            $output_type_rank += 1;
            $output_types[] = array(
                'cuisine_id' => $one_cuisine_label,
                'cuisine_name' => $one_cuisine_name,
                'rank' => $output_type_rank,
            );
        }
        if ($cuisine_type_other_used) {
            $output_type_rank += 1;
            $output_types[] = array(
                'cuisine_id' => $cuisine_type_other['outer'],
                'cuisine_name' => $cuisine_type_other['label'],
                'rank' => $output_type_rank,
            );
        }

        if (empty($output_types)) {
            $output_types = null;
        }

        if (empty($event_list_data)) {
            $event_list_data = null;
        }

        $use_date = $this->req_date;
        if (empty($use_date)) {
            $use_date = date('Y-m-d');
        }

        $last_modified_regions = '2012-06-19 18:00:00';
        $output_data = array(
            'date' => $use_date,
            'regions_last_modified' => $last_modified_regions,
            'regions' => $output_regions,
            'cuisines' => $output_types,
            'restaurants' => $event_list_data,
        );

        $output_json = Zend_Json::encode($output_data);

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Length: ' . strlen($output_json));

        echo $output_json;

        exit(0);

    }

    /**
     * Serve a restaurant menu.
     */
    public function menusAction()
    {
        $basically_correct = true;

        $param_rest_url_name = $this->_request->getParam('url-name');
        $param_rest_menu_type = $this->_request->getParam('menu-type');

        if (empty($param_rest_url_name)) {
            $basically_correct = false;
        }
        if (empty($param_rest_menu_type)) {
            $param_rest_menu_type = 'all';
        }
        if (!in_array($param_rest_menu_type, array('all', 'daily'))) {
            $basically_correct = false;
        }

        if (!$basically_correct) {
            $this->sendError('Invalid request.');
        }

        $rest_params = array(
            'rest_url_name' => $param_rest_url_name,
            'rest_menu_type' => $param_rest_menu_type,
            'language_id' => self::LANGUAGE,
            'publication_id' => self::PUBLICATION,
            'section_number' => self::EV_SECTION,
            'article_type' => self::EV_TYPE,
        );

        $menu_data = $this->m_service->takeRestMenuData($rest_params);

        if (empty($menu_data)) {
            $this->sendError('Menu not found.', 404);
        }

        $menu_json = Zend_Json::encode($menu_data);

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Length: ' . strlen($menu_json));

        echo $menu_json;

        exit(0);

    }

    /**
     * Serve a restaurant menucard.
     */
    public function menucardsAction()
    {
        $basically_correct = true;

        $param_rest_url_name = $this->_request->getParam('url-name');
        $param_rest_menucard_type = $this->_request->getParam('menucard-type');

        if (empty($param_rest_url_name)) {
            $basically_correct = false;
        }
        if (empty($param_rest_menucard_type)) {
            $param_rest_menucard_type = 'menucard-pdf';
        }
        if (!in_array($param_rest_menucard_type, array('menucard-pdf', 'winecard-pdf', 'kidscard-pdf'))) {
            $basically_correct = false;
        }

        if (!$basically_correct) {

            $output_data = null;
            //$output_json = json_encode($output_data);
            $output_json = Zend_Json::encode($output_data);

            header('Content-Type: application/json; charset=utf-8');
            header('Content-Length: ' . strlen($output_json));

            echo $output_json;
            exit(0);

        }

        $rest_params = array(
            'rest_url_name' => $param_rest_url_name,
            'rest_menucard_type' => 'all', // $param_rest_menucard_type,
            'language_id' => self::LANGUAGE,
            'publication_id' => self::PUBLICATION,
            'section_number' => self::EV_SECTION,
            'article_type' => self::EV_TYPE,
        );
        $menucard_links = $this->m_service->takeRestMenucardLinks($rest_params);

        if ((isset($menucard_links[$param_rest_menucard_type])) && (!empty($menucard_links[$param_rest_menucard_type]))) {
            $this->getResponse()->setRedirect($menucard_links[$param_rest_menucard_type], 307);
            $this->getResponse()->sendHeaders();
            exit(0);
        }

        $this->getResponse()->setHttpResponseCode(500);
        $this->getResponse()->sendHeaders();
        exit(0);

    }

    /**
     * Serve a restaurant reservation url.
     */
    public function reservationAction()
    {
        $basically_correct = true;

        $param_rest_id = $this->_request->getParam('rest-id');
        $param_response = $this->_request->getParam('response');

        if (empty($param_rest_id)) {
            $basically_correct = false;
        }

        if (empty($param_response)) {
            $param_response = 'redirect';
        }
        if (!in_array($param_response, array('redirect'))) {
            $basically_correct = false;
        }

        if (!$basically_correct) {

            $this->getResponse()->setHeader('Status', '404', true);
            $this->getResponse()->sendHeaders();
            exit(0);

        }

        $rest_params = array(
            'rest_id' => $param_rest_id,
            'language_id' => self::LANGUAGE,
            'publication_id' => self::PUBLICATION,
            'section_number' => self::EV_SECTION,
            'article_type' => self::EV_TYPE,
        );
        $reservation_url = $this->m_service->takeRestReservationURL($rest_params);

        if (!empty($reservation_url)) {

            //$this->getResponse()->setHeader('Status', '307', true);
            //$this->getResponse()->setHeader('Location', $reservation_url, true);
            $this->getResponse()->setRedirect($reservation_url, 307);
            $this->getResponse()->sendHeaders();
            exit(0);

        }

        $this->getResponse()->setHttpResponseCode(500);
        $this->getResponse()->sendHeaders();
        exit(0);
    }

    /**
     * Serve list of sections.
     */
    private function _innerListProcess()
    {
        $empty_res = array();

        $param_square = null;
        $param_date = $this->_request->getParam('date');
        $param_region = $this->_request->getParam('region');
        //$param_type = $this->_request->getParam('cuisine'); // not required
        $param_type = null;
        if (empty($param_date)) {
            $param_date = date('Y-m-d');
        }
        $this->req_date = $this->m_service->getRequestDate($param_date);
        if (!$this->req_date) {
            $this->req_date = date('Y-m-d');
        }

        if (empty($param_region)) {
            if (!$this->m_square_set) {
                $param_region = 'kanton-basel-stadt';
            }
        }

        if ($this->m_square_set) {
            $param_square = "{$this->m_lat_min} {$this->m_lon_min}, {$this->m_lat_max} {$this->m_lon_max}";
        }

        $req_region = null;
        if (!empty($param_region)) {
            $region_list = $this->m_service->getRegionList(array('country' => 'ch'));
            foreach ($region_list as $region_key => $region_info) {
                if ($region_key == $param_region) {
                    $req_region = $region_info['topic'];
                    break;
                }
            }
            if (!$req_region) {
                return $empty_res;
            }
        }
        $req_type = null;

        $params = array();

        $params['event_date'] = $this->req_date;
        $params['omit_multidate'] = true;
        if (!empty($req_region)) {
            $params['event_region'] = $req_region;
        }
        if ($req_type) {
            $params['event_type'] = $req_type;
        }
        if ($param_square) {
            $params['location'] = $param_square;
            $this->m_limit_distance = self::DISTANCE_RADIUS_KM;
            $this->m_limit_count = self::COUNT_LIMIT;

            if (array_key_exists('distance', $this->_request->getParams())) {
                $this->m_limit_distance = 0 + $this->_request->getParam('distance');
            }
            if (array_key_exists('count', $this->_request->getParams())) {
                $this->m_limit_count = 0 + $this->_request->getParam('count');
            }

        }

        $params['publication'] = self::PUBLICATION;
        $params['language'] = self::LANGUAGE;
        $params['section'] = self::EV_SECTION;
        $params['article_type'] = self::EV_TYPE;

        if (!$this->m_square_set) {
            $params['order'] = array(array('field' => 'byName', 'dir' => 'ASC'));
        }

        $events = $this->m_service->getEventList($params);

        return $events;

    }

}

