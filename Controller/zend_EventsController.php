<?php
/**
 * @package Newscoop
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

require_once($GLOBALS['g_campsiteDir'].'/classes/ArticleTopic.php');

/**
 */
class Api_EventsController extends Zend_Controller_Action
{
    const API_VERSION = 1;
    const BASE_URL = '/api/events';

    const PUBLICATION = 1;
    const LANGUAGE = 5;
    const EV_SECTION = 71; // events
    const EV_TYPE = 'event';

    CONST IMAGE_THUMBNAIL_WIDTH_NORMAL = '105';
    CONST IMAGE_THUMBNAIL_HEIGHT_NORMAL = '70';
    CONST IMAGE_WIDTH_NORMAL = '210';
    CONST IMAGE_HEIGHT_NORMAL = '300';

    CONST IMAGE_THUMBNAIL_WIDTH_RETINA = '210';
    CONST IMAGE_THUMBNAIL_HEIGHT_RETINA = '140';
    CONST IMAGE_WIDTH_RETINA = '210';
    CONST IMAGE_HEIGHT_RETINA = '300';

    CONST DESKTOP_CLIENTS = 'web,';

    /** @var Zend_Controller_Request_Http */
    private $request;

    /** @var array */
    private $m_client;
    private $m_desktop_client;
    private $m_event_detail;

    /** @var Newscoop\Services\AgendaService */
    private $m_service;

    private $req_date;

    private $client_image_thumbnail_height;
    private $client_image_thumbnail_width;
    private $client_image_height;
    private $client_image_width;

    private $m_url;
    private $m_section_url;

    /**
     *
     */
    public function init()
    {
        global $Campsite;

        $this->m_url = $Campsite['WEBSITE_URL'];

        $this->m_service = $this->_helper->service('agenda');

        $param_client = strtolower($this->_request->getParam('client'));

        $this->m_client = $param_client;
        $this->m_desktop_client = false;
        if (in_array($param_client, explode(',', self::DESKTOP_CLIENTS))) {
            $this->m_desktop_client = true;
            $this->m_service->setOnBrowser(true);
        }

        $this->m_event_detail = false;

        $this->client_image_thumbnail_width = self::IMAGE_THUMBNAIL_WIDTH_NORMAL;
        $this->client_image_thumbnail_height = self::IMAGE_THUMBNAIL_HEIGHT_NORMAL;
        $this->client_image_width = self::IMAGE_WIDTH_NORMAL;
        $this->client_image_height = self::IMAGE_HEIGHT_NORMAL;

        if (in_array($param_client, array('iphone_retina', 'ipad_retina'))) {
            $this->client_image_thumbnail_width = self::IMAGE_THUMBNAIL_WIDTH_RETINA;
            $this->client_image_thumbnail_height = self::IMAGE_THUMBNAIL_HEIGHT_RETINA;
            $this->client_image_width = self::IMAGE_WIDTH_RETINA;
            $this->client_image_height = self::IMAGE_HEIGHT_RETINA;
        }

    }

    /**
     */
    public function indexAction()
    {
        $this->_forward('list');
    }

    /**
     * Serve list of sections.
     */
    public function listAction()
    {
        $basically_correct = true;

        $param_version = $this->_request->getParam('version');
        $param_client = $this->_request->getParam('client');
        if (empty($param_version) || empty($param_client)) {
            $basically_correct = false;
        }
        if (!in_array($param_client, array('iphone', 'iphone_retina', 'ipad', 'ipad_retina', 'web'))) {
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


        $event_list = $this->_innerListProcess();
//        echo count($event_list);

        $this->_outerListProcess($event_list);

    }

    private function _outerListProcess($event_list)
    {

        $event_types_reversed = array();
        foreach ($this->m_service->getEventTypeList(array('country' => 'ch')) as $type_key => $type_info) {
            $event_types_reversed[$type_info['topic']] = $type_info['outer']; // $type_key;
        }

        $cantons_reversed = array();
        foreach ($this->m_service->getRegionList(array('country' => 'ch')) as $type_key => $type_info) {
            $cantons_reversed[$type_info['topic']] = $type_key;
        }

        $event_list_data = array();
        foreach ($event_list as $one_event) {
            $one_event_type = null;
            $one_event_canton = null;
            $one_data = $one_event->getArticleData();
            $one_event_topics = \ArticleTopic::GetArticleTopics($one_event->getArticleNumber());
            foreach ($one_event_topics as $one_topic) {
                $one_topic_name = $one_topic->getName(self::LANGUAGE);
                if (array_key_exists($one_topic_name, $event_types_reversed)) {
                    $one_event_type = $event_types_reversed[$one_topic_name];
                }
                if (array_key_exists($one_topic_name, $cantons_reversed)) {
                    $canton_key = $cantons_reversed[$one_topic_name];
                    if ('kanton' == substr($canton_key, 0, 6)) {
                        $one_event_canton = $canton_key;
                    }
                }
            }

            $one_date_time = null;
            $one_canceled = false;
            $one_description = '';

            //$one_dates_all = null;

            $one_description_default = $one_data->getProperty('Fdescription');

            $one_date_info = $this->m_service->getEventDateInfo($one_event, $this->req_date);

            if ($one_date_info['found']) {
                $one_date_time = $one_date_info['date'] . ' ' . $one_date_info['time'];
                $one_canceled = $one_date_info['canceled'];
                $one_description = $one_date_info['about'];
            }
            if (empty($one_description)) {
                $one_description = $one_description_default;
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

            $one_image_ids = array();
            $one_image_rank = 0;
            while (true) {
                $one_image_rank += 1;
                $articleImage = new \ArticleImage($one_event->getArticleNumber(), null, $one_image_rank);
                if ($articleImage->exists()) {
                    //$one_image_ids[] = str_pad($articleImage->getImageId(), 9, '0', STR_PAD_LEFT);
                    $one_image_ids[] = $articleImage->getImageId();
                }
                else {
                    break;
                }
            }

            $one_image_thumbnail_url = null;
            $one_image_urls = null;
            if (!empty($one_image_ids)) {
                //$one_thumbnail_url = '/images/cache/' . $this->client_res_thumb . '/crop/images%7Ccms-image-' . $one_image_ids[0] . '.png';
                $one_image_thumbnail_url = $this->m_url . '/get_img?ImageHeight=' . $this->client_image_thumbnail_height . '&ImageWidth=' . $this->client_image_thumbnail_width . '&ImageCrop=center&ImageId=' . $one_image_ids[0];
                $one_image_urls = array();
                foreach ($one_image_ids as $cur_one_img_id) {
                    $one_image_urls[] = $this->m_url . '/get_img?ImageHeight=' . $this->client_image_height . '&ImageWidth=' . $this->client_image_width . '&ImageCrop=center&ImageId=' . $cur_one_img_id;
                }
            }

            $one_descs_data = $this->m_service->splitDescs($one_description ."\n<br>\n". $one_data->getProperty('Fother'));

            $one_synopsis = null;
            $one_descriptions_all = null;
            if (!empty($one_descs_data['texts'])) {
                $one_synopsis = $one_descs_data['texts'][0];
                $one_descriptions_all = implode("\n\n", $one_descs_data['texts']);
            }
            $one_links = null;
            if (!empty($one_descs_data['links'])) {
                $one_links = array();
                foreach ($one_descs_data['links'] as $one_link_info) {
                    $one_links[] = $one_link_info['link'];
                }
            }
            try {
                $one_critik_link = $this->m_service->entities2Utf8($one_data->getProperty('Fevent_tk_reviewed'));
                if (!empty($one_critik_link)) {
                    if (empty($one_links)) {
                        $one_links = array();
                    }
                    $one_links[] = 'http://www.theaterkritik.ch/';
                }
            }
            catch (\Exception $exc) {
            }

            $one_title = '';
            try {
                $one_title = $this->m_service->entities2Utf8($one_data->getProperty('Fheadline'));
            }
            catch (\Exception $exc) {
                $one_title = '';
            }

            $one_location_name = $this->m_service->nullOnEmpty($this->m_service->entities2Utf8($one_data->getProperty('Fevent_location_name')));

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

            $cur_event_data = array(
                'title' => $one_title,
                'description' => $one_descriptions_all,
                'description_links' => $one_links,
                'organizer' => $this->m_service->nullOnEmpty($this->m_service->entities2Utf8($one_data->getProperty('Forganizer'))),
                'street' => $this->m_service->nullOnEmpty($one_data->getProperty('Fstreet')),
                'town' => $this->m_service->nullOnEmpty($one_data->getProperty('Ftown')),
                'country' => ('ch' == strtolower($one_data->getProperty('Fcountry'))) ? 'Schweiz' : $this->m_service->nullOnEmpty($one_data->getProperty('Fcountry')),
                'zipcode' => $this->m_service->nullOnEmpty($one_data->getProperty('Fzipcode')),
                'date_time' => $one_date_time,
                'web' => $one_web,
                //'event_detail_url' => self::BASE_URL . '/event?id=' . $one_event_id . '&date=' . urlencode($this->req_date),
                'event_image_thumbnail_url' => $one_image_thumbnail_url,
                'event_image_urls' => $one_image_urls,
                'email' => $one_email,
                'canceled' => $one_canceled,
                'latitude' => $one_latitude,
                'longitude' => $one_longitude,
                'event_location_name' => $one_location_name,
            );

            if ($this->m_desktop_client) {
                $cur_event_data['event_key'] = $one_data->getProperty('Fevent_id');
                $cur_event_data['genre'] = $this->m_service->nullOnEmpty($one_data->getProperty('Fgenre'));
                $cur_event_data['type'] = $one_event_type;
                $cur_event_data['canton'] = $one_event_canton;
            }

            $event_list_data[] = $cur_event_data;

        }

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
            'events' => $event_list_data,
        );

        //$output_json = json_encode($output_data);
        $output_json = Zend_Json::encode($output_data);

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Length: ' . strlen($output_json));

        echo $output_json;

        exit(0);

    }


    /**
     * Serves detail of an event.
     */
    public function detailAction()
    {
        $basically_correct = true;

        $this->m_event_detail = true;
        $param_event_key = $this->_request->getParam('event_key');
        if (empty($param_event_key)) {
            $basically_correct = false;
        }

        $param_version = $this->_request->getParam('version');
        $param_client = $this->_request->getParam('client');
        if (empty($param_version) || empty($param_client)) {
            $basically_correct = false;
        }
        if (!in_array($param_client, array('iphone', 'iphone_retina', 'ipad', 'ipad_retina', 'web'))) {
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


        $event_list = $this->_innerDetailProcess();
//        echo count($event_list);

        $event_types_reversed = array();
        foreach ($this->m_service->getEventTypeList(array('country' => 'ch')) as $type_key => $type_info) {
            $event_types_reversed[$type_info['topic']] = $type_info['outer']; // $type_key;
        }

        $cantons_reversed = array();
        foreach ($this->m_service->getRegionList(array('country' => 'ch')) as $type_key => $type_info) {
            $cantons_reversed[$type_info['topic']] = $type_key;
        }

        $event_list_data = array();
        foreach ($event_list as $one_event) {
            $one_event_type = null;
            $one_event_canton = null;
            $one_data = $one_event->getArticleData();
            $one_event_topics = \ArticleTopic::GetArticleTopics($one_event->getArticleNumber());
            foreach ($one_event_topics as $one_topic) {
                $one_topic_name = $one_topic->getName(self::LANGUAGE);
                if (array_key_exists($one_topic_name, $event_types_reversed)) {
                    $one_event_type = $event_types_reversed[$one_topic_name];
                }
                if (array_key_exists($one_topic_name, $cantons_reversed)) {
                    $canton_key = $cantons_reversed[$one_topic_name];
                    if ('kanton' == substr($canton_key, 0, 6)) {
                        $one_event_canton = $canton_key;
                    }
                }
            }

            $one_date_time = null;
            $one_canceled = false;
            $one_postponed = false;
            $one_description = '';

            $one_dates_all = null;

            $one_desc_other = '' . $one_data->getProperty('Fother');

            $one_descs_data_default = $this->m_service->splitDescs($one_data->getProperty('Fdescription') ."\n<br>\n". $one_desc_other);

            $one_descriptions_all_default = null;
            if (!empty($one_descs_data_default['texts'])) {
                $one_descriptions_all_default = implode("\n\n", $one_descs_data_default['texts']);
            }
            $one_links_default = null;
            if (!empty($one_descs_data_default['links'])) {
                $one_links_default = array();
                foreach ($one_descs_data_default['links'] as $one_link_info_default) {
                    $one_links_default[] = $one_link_info_default['link'];
                }
            }
            $use_kritik_link = null;
            try {
                $one_critik_link = $this->m_service->entities2Utf8($one_data->getProperty('Fevent_tk_reviewed'));
                if (!empty($one_critik_link)) {
                    $use_kritik_link = 'http://www.theaterkritik.ch/';
                }
            }
            catch (\Exception $exc) {
                $use_kritik_link = null;
            }
            if (!empty($use_kritik_link)) {
                if (empty($one_links_default)) {
                    $one_links_default = array();
                }
                $one_links_default[] = $use_kritik_link;
            }

            $all_dates_info = $this->m_service->getEventDatesAll($one_event, $this->req_date);

            $one_descriptions_all = null;
            $one_links = null;

            $one_use_date = null;

            if ($all_dates_info['chosen']) {
                $one_use_date = $all_dates_info['chosen'];
                $one_use_date_data = null;

                if ($all_dates_info['is_regular']) {
                    $one_use_date_data = $all_dates_info['regular_dates'][$one_use_date];
                    $one_canceled = false;
                }
                else {
                    $one_use_date_data = $all_dates_info['cancelled_dates'][$one_use_date];
                    $one_canceled = true;
                }

                $one_date_time = $one_use_date . ' ' . $one_use_date_data['time'];

            }

            $one_dates_all = null;
            if (!empty($all_dates_info['regular_dates'])) {
                $one_dates_all = array();

                foreach ($all_dates_info['regular_dates'] as $one_event_date => $one_event_date_info) {
                    $one_date_desc_text = null;
                    $one_date_desc_links = null;

                    $one_date_desc = $one_event_date_info['comment'];
                    if (!empty($one_date_desc)) {
                        $one_date_desc_data = $this->m_service->splitDescs($one_date_desc ."\n<br>\n". $one_desc_other);

                        if(!empty($one_date_desc_data['texts'])) {
                            $one_date_desc_text = implode("\n\n", $one_date_desc_data['texts']);
                        }

                        if (!empty($one_date_desc_data['links'])) {
                            $one_date_desc_links = array();
                            foreach ($one_date_desc_data['links'] as $one_date_desc_data_link_info) {
                                $one_date_desc_links[] = $one_date_desc_data_link_info['link'];
                            }
                        }
                        if (!empty($use_kritik_link)) {
                            if (empty($one_date_desc_links)) {
                                $one_date_desc_links = array();
                            }
                            $one_date_desc_links[] = $use_kritik_link;
                        }

                    }
                    else {
                        $one_date_desc_text = $one_descriptions_all_default;
                        $one_date_desc_links = $one_links_default;
                    }

                    $one_dates_all[] = array('date' => $one_event_date, 'time' => $one_event_date_info['time'], 'description' => $one_date_desc_text, 'links' => $one_date_desc_links, 'postponed' => $one_event_date_info['postponed']);

                    if ($one_event_date == $one_use_date) {
                        $one_descriptions_all = $one_date_desc_text;
                        $one_links = $one_date_desc_links;
                        $one_postponed = $one_event_date_info['postponed'];
                    }

                }
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

            $one_image_ids = array();
            $one_image_rank = 0;
            while (true) {
                $one_image_rank += 1;
                $articleImage = new \ArticleImage($one_event->getArticleNumber(), null, $one_image_rank);
                if ($articleImage->exists()) {
                    //$one_image_ids[] = str_pad($articleImage->getImageId(), 9, '0', STR_PAD_LEFT);
                    $one_image_ids[] = $articleImage->getImageId();
                }
                else {
                    break;
                }
            }

            $one_image_thumbnail_url = null;
            $one_image_urls = null;
            if (!empty($one_image_ids)) {
                //$one_thumbnail_url = '/images/cache/' . $this->client_res_thumb . '/crop/images%7Ccms-image-' . $one_image_ids[0] . '.png';
                $one_image_thumbnail_url = $this->m_url . '/get_img?ImageHeight=' . $this->client_image_thumbnail_height . '&ImageWidth=' . $this->client_image_thumbnail_width . '&ImageCrop=center&ImageId=' . $one_image_ids[0];
                $one_image_urls = array();
                foreach ($one_image_ids as $cur_one_img_id) {
                    $one_image_urls[] = $this->m_url . '/get_img?ImageHeight=' . $this->client_image_height . '&ImageWidth=' . $this->client_image_width . '&ImageCrop=center&ImageId=' . $cur_one_img_id;
                }
            }



            $one_title = '';
            try {
                $one_title = $this->m_service->entities2Utf8($one_data->getProperty('Fheadline'));
            }
            catch (\Exception $exc) {
                $one_title = '';
            }


            $one_event_minimal_age = null;
            try {
                $one_event_minimal_age = $this->m_service->entities2Utf8($one_data->getProperty('Fminimal_age'));
            }
            catch (\Exception $exc) {
                $one_event_minimal_age = null;
            }
            if (empty($one_event_minimal_age)) {
                $one_event_minimal_age = null;
            }

            $one_event_langs = null;
            try {
                $one_event_langs = $this->m_service->entities2Utf8($one_data->getProperty('Flanguages'));
            }
            catch (\Exception $exc) {
                $one_event_langs = null;
            }
            if (empty($one_event_langs)) {
                $one_event_langs = null;
            }

            $one_event_tk_rev = null;
            try {
                $one_event_tk_rev = $this->m_service->entities2Utf8($one_data->getProperty('event_tk_reviewed'));
            }
            catch (\Exception $exc) {
                $one_event_tk_rev = null;
            }
            if (empty($one_event_tk_rev)) {
                $one_event_tk_rev = null;
            }

            $one_location_name = $this->m_service->nullOnEmpty($this->m_service->entities2Utf8($one_data->getProperty('Fevent_location_name')));

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

            $cur_event_data = array(
                'title' => $one_title,
                'description' => $one_descriptions_all,
                'description_links' => $one_links,
                'organizer' => $this->m_service->nullOnEmpty($this->m_service->entities2Utf8($one_data->getProperty('Forganizer'))),
                'street' => $this->m_service->nullOnEmpty($one_data->getProperty('Fstreet')),
                'town' => $this->m_service->nullOnEmpty($one_data->getProperty('Ftown')),
                'country' => ('ch' == strtolower($one_data->getProperty('Fcountry'))) ? 'Schweiz' : $this->m_service->nullOnEmpty($one_data->getProperty('Fcountry')),
                'zipcode' => $this->m_service->nullOnEmpty($one_data->getProperty('Fzipcode')),
                'date_time' => $one_date_time,
                'web' => $one_web,
                //'event_detail_url' => self::BASE_URL . '/event?id=' . $one_event_id . '&date=' . urlencode($this->req_date),
                'event_image_thumbnail_url' => $one_image_thumbnail_url,
                'event_image_urls' => $one_image_urls,
                'email' => $one_email,
                'latitude' => $one_latitude,
                'longitude' => $one_longitude,
                'event_location_name' => $one_location_name,
            );

            if ($this->m_desktop_client) {
                $cur_event_data['event_key'] = $one_data->getProperty('Fevent_id');
                $cur_event_data['genre'] = $this->m_service->nullOnEmpty($one_data->getProperty('Fgenre'));
                $cur_event_data['type'] = $one_event_type;
                $cur_event_data['canton'] = $one_event_canton;

                $cur_event_data['canceled'] = $one_canceled;
                $cur_event_data['postponed'] = $one_postponed;

                $cur_event_data['minimal_age'] = $one_event_minimal_age;
                $cur_event_data['languages'] = $one_event_langs;
                $cur_event_data['tk_reviewed'] = $one_event_tk_rev;

            }
            else {
                $cur_event_data['canceled'] = $one_canceled || $one_postponed;
            }

            if ($this->m_event_detail) {
                $cur_event_data['dates_all'] = $one_dates_all;
            }

            $event_list_data[] = $cur_event_data;

        }

        $event_data = null;

        if (!empty($event_list_data)) {
            $event_data = $event_list_data[0];
        }

        $use_date = $this->req_date;
        if (empty($use_date)) {
            $use_date = date('Y-m-d');
        }

        $output_data = null;
        if ($event_data) {
            $output_data = array('event' => $event_data);
        }


        //$output_json = json_encode($output_data);
        $output_json = Zend_Json::encode($output_data);

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Length: ' . strlen($output_json));

        echo $output_json;

        exit(0);

    }

    /**
     * Provides a teaser on events.
     */
    public function teaserAction()
    {
        // params: region, event type, date, count
        // for Z+, it is: zentralschweiz_region, null, current_date, 1

        $basically_correct = true;

        $param_pictures = $this->_request->getParam('pictures');
        $param_version = $this->_request->getParam('version');
        $param_client = $this->_request->getParam('client');
        if (empty($param_version) || empty($param_client)) {
            $basically_correct = false;
        }
        if (!in_array($param_client, array('iphone', 'iphone_retina', 'ipad', 'ipad_retina', 'web'))) {
            $basically_correct = false;
        }

        $param_count = $this->_request->getParam('count');

        $param_count = (int) $param_count;
        if (empty($param_count) || (0 >= $param_count)) {
            $param_count = 1;
        }

        $event_list = null;
        if ($basically_correct) {
            $event_list = $this->_innerTeaserProcess();
        }

        if (empty($event_list)) {

            $output_data = array();
            //$output_json = json_encode($output_data);
            $output_json = Zend_Json::encode($output_data);

            header('Content-Type: application/json; charset=utf-8');
            header('Content-Length: ' . strlen($output_json));

            echo $output_json;
            exit(0);

        }


        if (empty($param_pictures)) {

            shuffle($event_list);

            if ($param_count < count($event_list)) {
                $event_list = array_slice($event_list, 0, $param_count);
            }

            $this->_outerListProcess($event_list);
            return;
        }


        $event_list_pictured = array();
        $event_list_other = array();

        foreach ($event_list as $one_event) {
            $one_image_count = (int) \ArticleImage::GetImagesByArticleNumber($one_event->getArticleNumber(), true);

            if (0 < $one_image_count) {
                $event_list_pictured[] = $one_event;
                continue;
            }

            $event_list_other[] = $one_event;
        }

        $event_list_use = array();

        shuffle($event_list_pictured);
        $count_pictured = count($event_list_pictured);

        if ($param_count >= $count_pictured) {
            $event_list_use = $event_list_pictured;
        }

        if ($param_count < $count_pictured) {
            $event_list_use = array_slice($event_list_pictured, 0, $param_count);
        }

        if ($param_count > $count_pictured) {
            $param_count_rest = $param_count - $count_pictured;
            $count_other = count($event_list_other);

            shuffle($event_list_other);

            if ($param_count_rest >= $count_other) {
                $event_list_use = array_merge($event_list_use, $event_list_other);
            }

            if ($param_count_rest < $count_other) {
                $event_list_use = array_merge($event_list_use, array_slice($event_list_other, 0, $param_count_rest));
            }

        }

        $this->_outerListProcess($event_list_use);

    }

    /**
     * Serve list of sections.
     */
    private function _innerListProcess()
    {
        $empty_res = array();

        $param_date = $this->_request->getParam('date');
        $param_region = $this->_request->getParam('region');
        $param_type = $this->_request->getParam('genre'); // $this->_request->getParam('type'); // not required ?
        if (empty($param_date)) {
            $param_date = date('Y-m-d');
        }
        $this->req_date = $this->m_service->getRequestDate($param_date);
        if (!$this->req_date) {
            $this->req_date = date('Y-m-d');
        }

        if (empty($param_region)) {
            $param_region = 'kanton-basel-stadt';
        }

        $req_region = null;
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

        $req_type = null;
        if (!empty($param_type)) {
            $req_type = $this->m_service->getRequestEventType($param_type);
            if (!$req_type) {
                $req_type = null;
            }
        }

        $params = array();

        $params['event_date'] = $this->req_date;
        $params['event_region'] = $req_region;
        if ($req_type) {
            $params['event_type'] = $req_type;
        }
        $params['publication'] = self::PUBLICATION;
        $params['language'] = self::LANGUAGE;
        $params['section'] = self::EV_SECTION;
        $params['article_type'] = self::EV_TYPE;

        $params['order'] = array(array('field' => 'byname', 'dir' => 'asc'));

        $events = $this->m_service->getEventList($params);

        return $events;

/*
        $this->getHelper('contextSwitch')->addActionContext('list', 'json')->initContext();
        print Zend_Json::encode($events);
*/
    }


    /**
     * Serve detail of an event.
     */
    private function _innerDetailProcess()
    {
        $empty_res = array();

        $param_date = $this->_request->getParam('date');
        $param_region = $this->_request->getParam('region');

        if (empty($param_date)) {
            $param_date = date('Y-m-d');
        }
        $this->req_date = $this->m_service->getRequestDate($param_date);
        if (!$this->req_date) {
            $this->req_date = date('Y-m-d');
        }

        $param_event_key = $this->_request->getParam('event_key');
        if (!$param_event_key) {
            return $empty_res;
        }

        $req_region = null;
        $region_list = $this->m_service->getRegionList(array('country' => 'ch'));
        foreach ($region_list as $region_key => $region_info) {
            if ($region_key == $param_region) {
                $req_region = $region_info['topic'];
                break;
            }
        }

        $params = array();

        $params['event_date'] = $this->req_date;

        if ($req_region) {
            $params['event_region'] = $req_region;
        }

        $params['publication'] = self::PUBLICATION;
        $params['language'] = self::LANGUAGE;
        $params['section'] = self::EV_SECTION;
        $params['article_type'] = self::EV_TYPE;

        $params['event_key'] = $param_event_key;
        $params['omit_multidate'] = true;

        $events = $this->m_service->getEventList($params);

        return $events;

/*
        $this->getHelper('contextSwitch')->addActionContext('list', 'json')->initContext();
        print Zend_Json::encode($events);
*/
    }



    /**
     * Serves teasers.
     */
    private function _innerTeaserProcess()
    {
        // params: region, event type, date, count
        // for Z+, it is: zentralschweiz_region, null, current_date, 1
        $empty_res = array();

        $param_region = $this->_request->getParam('region');
        $param_type = $this->_request->getParam('genre');
        $param_date = $this->_request->getParam('date');

        if (empty($param_region)) {
            $param_region = null;
        }

        $req_region = null;
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

        $req_type = null;
        if (!empty($param_type)) {
            $req_type = $this->m_service->getRequestEventType($param_type);
            if (!$req_type) {
                $req_type = null;
            }
        }

        if (empty($param_date)) {
            $param_date = date('Y-m-d');
        }
        $this->req_date = $this->m_service->getRequestDate($param_date);
        if (!$this->req_date) {
            $this->req_date = date('Y-m-d');
        }

        $params = array();

        $params['event_date'] = $this->req_date;
        $params['event_region'] = $req_region;
        if ($req_type) {
            $params['event_type'] = $req_type;
        }
        $params['publication'] = self::PUBLICATION;
        $params['language'] = self::LANGUAGE;
        $params['section'] = self::EV_SECTION;
        $params['article_type'] = self::EV_TYPE;

        //$params['order'] = array(array('field' => 'byname', 'dir' => 'asc'));

        $events = $this->m_service->getEventList($params);

        return $events;

/*
        $this->getHelper('contextSwitch')->addActionContext('list', 'json')->initContext();
        print Zend_Json::encode($events);
*/
    }


/*
    private function initClient($client)
    {
        $type = null;
        if (strstr($client, 'ipad')) {
            $type = 'ipad';
        } elseif (strstr($client, 'iphone')) {
            $type = 'iphone';
        }

        $this->client = array(
            'name' => $client,
            'type' => $type,
        );
    }
*/

}

