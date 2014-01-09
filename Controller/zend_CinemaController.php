<?php
/**
 * @package Newscoop
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

require_once($GLOBALS['g_campsiteDir'].'/classes/ArticleTopic.php');
require_once($GLOBALS['g_campsiteDir'].'/classes/ArticleImage.php');

require_once($GLOBALS['g_campsiteDir'].'/classes/Language.php');
require_once($GLOBALS['g_campsiteDir'].'/classes/Issue.php');
require_once($GLOBALS['g_campsiteDir'].'/classes/Section.php');


/**
 */
class Api_CinemaController extends Zend_Controller_Action
{
    const API_VERSION = 1;
    const BASE_URL = '/api/cinema';

    const PUBLICATION = 1;
    const LANGUAGE = 5;
    const EV_SECTION = 72; // movies
    const EV_TYPE = 'screening';

    CONST POSTER_THUMBNAIL_WIDTH_NORMAL = '95';
    CONST POSTER_THUMBNAIL_HEIGHT_NORMAL = '135';
    CONST POSTER_WIDTH_NORMAL = '198';
    CONST POSTER_HEIGHT_NORMAL = '283';
    CONST IMAGE_WIDTH_NORMAL = '300';
    CONST IMAGE_HEIGHT_NORMAL = '450';

    CONST POSTER_THUMBNAIL_WIDTH_RETINA = '190';
    CONST POSTER_THUMBNAIL_HEIGHT_RETINA = '270';
    CONST POSTER_WIDTH_RETINA = '198';
    CONST POSTER_HEIGHT_RETINA = '283';
    CONST IMAGE_WIDTH_RETINA = '300';
    CONST IMAGE_HEIGHT_RETINA = '450';

    CONST DESKTOP_CLIENTS = 'web,';

    /** @var Zend_Controller_Request_Http */
    private $request;

    /** @var array */
    private $m_client;
    private $m_desktop_client;
    private $m_movie_detail;

    /** @var Newscoop\Services\AgendaService */
    private $m_service;

    private $req_date;

    private $client_poster_thumbnail_height;
    private $client_poster_thumbnail_width;
    private $client_poster_height;
    private $client_poster_width;
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

        $language_obj = new \Language(self::LANGUAGE);
        $language_url_name = $language_obj->getCode();
        $issue_obj = \Issue::GetCurrentIssue(self::PUBLICATION, self::LANGUAGE);
        $issue_url_name = $issue_obj->getUrlName();
        $section_obj = new \Section(self::PUBLICATION, $issue_obj->getIssueNumber(), self::LANGUAGE, self::EV_SECTION);
        $section_url_name = $section_obj->getUrlName();

        $this->m_section_url = '/' . $language_url_name . '/' . $issue_url_name . '/' . $section_url_name . '/';

        $this->m_service = $this->_helper->service('agenda');

        $param_client = strtolower($this->_request->getParam('client'));

        $this->m_client = $param_client;
        $this->m_desktop_client = false;
        if (in_array($param_client, explode(',', self::DESKTOP_CLIENTS))) {
            $this->m_desktop_client = true;
            $this->m_service->setOnBrowser(true);
        }

        $this->m_event_detail = false;

        $this->client_poster_thumbnail_width = self::POSTER_THUMBNAIL_WIDTH_NORMAL;
        $this->client_poster_thumbnail_height = self::POSTER_THUMBNAIL_HEIGHT_NORMAL;
        $this->client_poster_width = self::POSTER_WIDTH_NORMAL;
        $this->client_poster_height = self::POSTER_HEIGHT_NORMAL;
        $this->client_image_width = self::IMAGE_WIDTH_NORMAL;
        $this->client_image_height = self::IMAGE_HEIGHT_NORMAL;

        if (in_array($param_client, array('iphone_retina', 'ipad_retina'))) {
            $this->client_poster_thumbnail_width = self::POSTER_THUMBNAIL_WIDTH_RETINA;
            $this->client_poster_thumbnail_height = self::POSTER_THUMBNAIL_HEIGHT_RETINA;
            $this->client_poster_width = self::POSTER_WIDTH_RETINA;
            $this->client_poster_height = self::POSTER_HEIGHT_RETINA;
            $this->client_image_width = self::IMAGE_WIDTH_RETINA;
            $this->client_image_height = self::IMAGE_HEIGHT_RETINA;
        }

        //$this->_helper->layout->disableLayout();
        //$this->params = $this->getRequest()->getParams();
        //$this->m_url = $Campsite['WEBSITE_URL'];
/*
        if (empty($this->params['client'])) {
            print Zend_Json::encode(array());
            exit;
        }
        $this->initClient($this->params['client']);
        if (is_null($this->client['type'])) {
            print Zend_Json::encode(array());
            exit;
        }
*/
    }

    /**
     */
    public function indexAction()
    {
        $this->_forward('list');
    }

    /**
     * Serves detail of a movie.
     */
    public function detailAction()
    {
        $this->m_movie_detail = true;

        $basically_correct = true;

        $param_movie_key = $this->_request->getParam('movie_key');
        if (empty($param_movie_key)) {
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

        $this->processList();
    }

    /**
     * Serve list of sections.
     */
    public function listAction()
    {
        $this->processList();
    }

    /**
     * Provides a teaser on movies.
     */
    public function teaserAction()
    {
        // params: region, event type, date, count
        // for Z+, it is: zentralschweiz_region, null, current_date, 1

        $this->m_movie_detail = false;

        $basically_correct = true;

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

        $movie_list = null;
        if ($basically_correct) {
            $movie_list = $this->_innerTeaserProcess();
        }

        if (empty($movie_list)) {

            $output_data = array();
            //$output_json = json_encode($output_data);
            $output_json = Zend_Json::encode($output_data);

            header('Content-Type: application/json; charset=utf-8');
            header('Content-Length: ' . strlen($output_json));

            echo $output_json;
            exit(0);

        }

        $movie_list_use = array();
        $movie_keys = array();

        $movie_list_use = array();

        shuffle($movie_list);

        $used_movies = 0;
        foreach ($movie_list as $one_movie) {

            $one_data = $one_movie->getArticleData();
            $one_movie_key = $one_data->getProperty('Fmovie_key');

            if (in_array($one_movie_key, $movie_keys)) {
                continue;
            }

            $movie_list_use[] = $one_movie;
            $movie_keys[] = $one_movie_key;

            $used_movies += 1;
            if ($used_movies >= $param_count) {
                break;
            }
        }

        $this->_outerListProcess($movie_list_use, false);

    }


    /**
     * Common processing of the requested list
     */
    private function processList()
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


        $movie_list = $this->_innerListProcess();
//        echo count($event_list);

        $this->_outerListProcess($movie_list, true);


    }

    private function _outerListProcess($movie_list, $with_screenings = true)
    {

        $last_movie_name = '';
        $last_movie_key = '';

        $movie_types_reversed = array();
        foreach ($this->m_service->getMovieTypeList(array('country' => 'ch')) as $type_key => $type_info) {
            $movie_types_reversed[$type_info['topic']] = $type_info['outer']; // $type_key;
        }

        $cantons_reversed = array();
        foreach ($this->m_service->getRegionList(array('country' => 'ch')) as $type_key => $type_info) {
            $cantons_reversed[$type_info['topic']] = $type_key;
        }

        $movie_list_data = array();
        $movie_locations_used = array();
        $movie_locations_data = array();
        foreach ($movie_list as $one_movie) {
            $one_movie_types = array();
            $one_cinema_canton = null;
            $one_data = $one_movie->getArticleData();
            $one_movie_topics = \ArticleTopic::GetArticleTopics($one_movie->getArticleNumber());
            foreach ($one_movie_topics as $one_topic) {
                $one_topic_name = $one_topic->getName(self::LANGUAGE);
                if (array_key_exists($one_topic_name, $movie_types_reversed)) {
                    $one_movie_types[] = $movie_types_reversed[$one_topic_name];
                }
                if (array_key_exists($one_topic_name, $cantons_reversed)) {
                    $canton_key = $cantons_reversed[$one_topic_name];
                    if ('kanton' == substr($canton_key, 0, 6)) {
                        $one_cinema_canton = $canton_key;
                    }
                }
            }
            if (empty($one_movie_types)) {
                $one_movie_types = null;
            }

            $one_date_time = null;
            $one_canceled = false;

            $one_web = $one_data->getProperty('Fweb');
            if (empty($one_web)) {
                $one_web = null;
            }
            $one_email = $one_data->getProperty('Femail');
            if (empty($one_email)) {
                $one_email = null;
            }

            $one_location_id = null;
            try {
                $one_location_id = $one_data->getProperty('Fmovie_cinema_key');
            }
            catch (\InvalidPropertyException $exc) {
                $one_location_id = null;
            }
            if (!$one_location_id) {
                $one_location_id = $one_data->getProperty('Flocation_id');
            }

            $one_phone = null;
            $one_phone_costinformation = null;
            $one_phone_info = $this->m_service->entities2Utf8('' . $one_data->getProperty('Fphone'));
            $one_phone_info_arr = $this->m_service->getPhoneParts($one_phone_info);
            if (!empty($one_phone_info_arr['phone'])) {
                $one_phone = $one_phone_info_arr['phone'];
            }
            if (!empty($one_phone_info_arr['cost'])) {
                $one_phone_costinformation = $one_phone_info_arr['cost'];
            }

            if ($with_screenings && (!isset($movie_locations_used[$one_location_id]))) {
                $movie_locations_used[$one_location_id] = true;

                $one_latitude = null;
                $one_longitude = null;

                $one_map = $one_movie->getMap();
                if (is_object($one_map) && $one_map->exists()) {
                    $one_location_set = $one_map->getLocations();
                    if (!empty($one_location_set)) {
                        $one_location = $one_location_set[0];
                        $one_latitude = $one_location->getLatitude();
                        $one_longitude = $one_location->getLongitude();
                    }
                }

                $one_cinema_locations_data = array(
                    'location_id' => $one_location_id,
                    'location_name' => $this->m_service->nullOnEmpty($this->m_service->entities2Utf8($one_data->getProperty('Forganizer'))),
                    'zipcode' => $this->m_service->nullOnEmpty($one_data->getProperty('Fzipcode')),
                    'town' => $this->m_service->nullOnEmpty($one_data->getProperty('Ftown')),
                    'street' => $this->m_service->nullOnEmpty($one_data->getProperty('Fstreet')),
                    'web' => $one_web,
                    //'email' => $one_email,
                    'phone' => $one_phone,
                    'phone_costinformation' => $one_phone_costinformation,
                    'latitude' => $one_latitude,
                    'longitude' => $one_longitude,
                );

                if ($this->m_movie_detail) {
                    $one_cinema_locations_data['canton'] = $one_cinema_canton;
                }

                $movie_locations_data[] = $one_cinema_locations_data;

            }

            $the_same_movie = false;
            if (!$this->m_movie_detail) {
                if ($last_movie_name == $one_data->getProperty('Fheadline')) {
                    $the_same_movie = true;
                }
            }
            else {
                if ($last_movie_key == $one_data->getProperty('Fmovie_key')) {
                    $the_same_movie = true;
                }
            }

            if (!$the_same_movie) {
                $one_suisa = $one_data->getProperty('Fmovie_suisa');
                if (!$one_suisa) {
                    $one_suisa = null;
                }

                $one_min_age = '' . $one_data->getProperty('Fminimal_age_category');
                if ((!$one_min_age) || ('99' == $one_min_age)) {
                    $one_min_age = '16';
                }
                $one_min_age = ltrim($one_min_age, '0');
                $one_min_age_cat = (int) $one_min_age;
                if ('' == $one_min_age) {
                    $one_min_age = '3-6 Jahre';
                }
                else {
                    $one_min_age = 'ab ' . $one_min_age . ' Jahren';
                }

                $one_movie_trailer_url = null;
                $one_movie_trailer_id = $one_data->getProperty('Fmovie_trailer_vimeo');
                if (!$one_movie_trailer_id) {
                    $one_movie_trailer_id = null;
                }
                if ($one_movie_trailer_id) {
                    $one_movie_trailer_url = $this->m_url . $this->m_section_url . '?vimeo=' . $one_movie_trailer_id . '&target=app';
                }

                $one_movie_director = $one_data->getProperty('Fmovie_director');
                if (!$one_movie_director) {
                    $one_movie_director = null;
                }
                else {
                    $one_movie_director = str_replace(array(','), array(', '), $one_movie_director);
                }

                $one_movie_cast = $one_data->getProperty('Fmovie_cast');
                if (!$one_movie_cast) {
                    $one_movie_cast = null;
                }
                else {
                    $one_movie_cast = str_replace(array(','), array(', '), $one_movie_cast);
                }

                $one_year = $one_data->getProperty('Fmovie_year');
                if (!$one_year) {
                    $one_year = null;
                }
                else {
                    $one_year = '' . $one_year;
                }

                $one_duration = $one_data->getProperty('Fmovie_duration');
                if (empty($one_duration)) {
                    $one_duration = null;
                }
                else {
                    $one_duration = '' . $one_duration . ' Minuten';
                }

                $one_distributor = $one_data->getProperty('Fmovie_distributor');
                if (!$one_distributor) {
                    $one_distributor = null;
                }

                $one_rating = $one_data->getProperty('Fmovie_rating_wv');
                if (empty($one_rating)) {
                    $one_rating = null;
                }
                else {
                    $one_rating = 0 + $one_rating;
                }

/*
                $one_image_id = null;
                $articleImage = new \ArticleImage($one_movie->getArticleNumber(), null, 1);
                if ($articleImage->exists()) {
                    $one_image_id = $articleImage->getImageId();
                }

                $one_image_url = null;
                if (!empty($one_image_id)) {
                    $one_image_url = $this->m_url . '/get_img?ImageHeight=300&ImageId=' . $one_image_id;
                }
*/

                $one_poster_ids = array();
                $one_image_ids = array();
                $one_image_rank = 0;
                while (true) {
                    $one_image_rank += 1;
                    $articleImage = new \ArticleImage($one_movie->getArticleNumber(), null, $one_image_rank);
                    if ($articleImage->exists()) {
                        $cur_image_id = $articleImage->getImageId();
                        $cur_image = new \Image($cur_image_id);
                        $cur_image_path = $cur_image->getImageStorageLocation();
                        $cur_image_sizes = getimagesize($cur_image_path);
                        $cur_image_width = $cur_image_sizes[0];
                        $cur_image_height = $cur_image_sizes[1];

                        if (1 == $one_image_rank) {
                            if ((450 == $cur_image_width) && (300 == $cur_image_height)) {
                                $one_image_ids[] = $cur_image_id;
                            }
                            else {
                                $one_poster_ids[] = $cur_image_id;
                            }
                        }
                        else {
                            if ((210 == $cur_image_width) && (300 == $cur_image_height)) {
                                $one_poster_ids[] = $cur_image_id;
                            }
                            else {
                                $one_image_ids[] = $cur_image_id;
                            }
                        }

                    }
                    else {
                        break;
                    }
                }

                $one_poster_thumbnail_url = null;
                $one_poster_url = null;
                $one_image_urls = null;
                if (!empty($one_poster_ids)) {
                    $one_poster_thumbnail_url = $this->m_url . '/get_img?ImageHeight=' . $this->client_poster_thumbnail_height . '&ImageWidth=' . $this->client_poster_thumbnail_width . '&ImageCrop=center&ImageId=' . $one_poster_ids[0];
                    $one_poster_url = $this->m_url . '/get_img?ImageHeight=' . $this->client_poster_height . '&ImageWidth=' . $this->client_poster_width . '&ImageCrop=center&ImageId=' . $one_poster_ids[0];
                }

                if (!empty($one_image_ids)) {
                    $one_image_urls = array();
                    foreach ($one_image_ids as $cur_one_img_id) {
                        $one_image_urls[] = $this->m_url . '/get_img?ImageHeight=' . $this->client_image_height . '&ImageWidth=' . $this->client_image_width . '&ImageCrop=center&ImageId=' . $cur_one_img_id;
                    }
                }

                $link_part_str = '';
                $link_parts = array();
                $cur_link = trim('' . $one_data->getProperty('Fmovie_link'));
                if ($cur_link) {
                    $link_parts[] = $cur_link;
                }
                $cur_link = trim('' . $one_data->getProperty('Fmovie_distributor_link'));
                if ($cur_link) {
                    $link_parts[] = $cur_link;
                }
                foreach ($link_parts as $one_cur_link) {
                    $link_part_str .= "\n<br>\n" . '<a href="' . $one_cur_link . '">' . $one_cur_link . '</a>';
                }

                $one_descs_data = $this->m_service->splitDescs($one_data->getProperty('Fdescription') ."\n<br>\n". $one_data->getProperty('Fother') . $link_part_str);

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

                $one_title = null;
                try {
                    $one_title = $this->m_service->entities2Utf8($one_data->getProperty('Fheadline'));
                }
                catch (\Exception $exc) {
                    $one_title = null;
                }

                $one_copyright = null;
                try {
                    $one_copyright = $this->m_service->entities2Utf8($one_data->getProperty('Fmovie_distributor_copyright'));
                }
                catch (\Exception $exc) {
                    $one_copyright = null;
                }
                if (empty($one_copyright)) {
                    try {
                        $one_copyright = 'Copyright by ' . $this->m_service->entities2Utf8($one_data->getProperty('Fmovie_distributor'));
                    }
                    catch (\Exception $exc) {
                        $one_copyright = null;
                    }
                }
                if (empty($one_copyright)) {
                    $one_copyright = 'Copyrighted';
                }

                $one_movie_data = array(
                    'suisa' => $one_suisa,
                    'title' => $one_title,
                    'synopsis' => $one_descriptions_all,
                    //'links' => $one_links,
                    //'organizer' => $one_data->getProperty('Forganizer'),
                    'genres' => $one_movie_types,
                    'minimal_age' => $one_min_age,
                    'trailer_url' => $one_movie_trailer_url,
                    'vimeo_id' => $one_movie_trailer_id,
                    //'image_url' => $one_image_url, // 'http://www.tageswoche.ch/images/cache/555x370/crop/images%7Ccms-image-000042058.png',
                    //'movie_detail_url' => self::BASE_URL . '/movie?key=' . urlencode('' . $one_data->getProperty('Fmovie_key')),
                    'movie_poster_thumbnail_url' => $one_poster_thumbnail_url,
                    'movie_poster_url' => $one_poster_url,
                    'movie_image_urls' => $one_image_urls,
                    'director' => $one_movie_director,
                    'actors' => $one_movie_cast,
                    'year' => $one_year,
                    'duration' => $one_duration,
                    'distributor' => $one_distributor,
                    'rating' => $one_rating,
                );

                if ($with_screenings) {
                    $one_movie_data['screenings'] = array();
                }

                if ($this->m_desktop_client) {
                    $one_movie_data['movie_key'] = $this->m_service->nullOnEmpty($one_data->getProperty('Fmovie_key'));
                    $one_movie_data['minimal_age_category'] = $one_min_age_cat;
                    $one_movie_data['image_copyright'] = $one_copyright;
                    $one_movie_data['language_classes'] = null;
                }

                $movie_list_data[] = $one_movie_data;

            }

            $last_movie_name = $one_data->getProperty('Fheadline');
            $last_movie_key = $one_data->getProperty('Fmovie_key');

            if (!$with_screenings) {
                continue;
            }

            // to add screening info
            $last_movie_data = array_pop($movie_list_data);
            $one_movie_screen_info = array(
                'location_id' => $one_location_id,
                'times' => array(),
            );

            $scr_date = $this->req_date;
            if ($this->m_movie_detail) {
                $scr_date = null; // for a movie detail, taking all dates - when a cinema taken
            }

            $one_date_screening_info = $this->m_service->getMovieDateInfo($one_movie, $scr_date);

            $current_language_classes = array();

            foreach ($one_date_screening_info as $one_date_info) {
                $one_lang_str = $one_date_info['lang'];

                $one_has_d = false; // german
                $one_has_k = false; // dialect
                $one_has_f = false; // french
                $one_has_t = false; // original with german subtitles

                if ($this->m_desktop_client && (0 < strlen($one_lang_str))) {
                    if (('D' == substr($one_lang_str,0,1)) && ('Di' != substr($one_lang_str,0,2))) {
                        $one_has_d = true;
                    }
                    if ('dialekt' == strtolower($one_lang_str)) {
                        $one_has_k = true;
                    }
                    if ('F' == substr($one_lang_str,0,1)) {
                        $one_has_f = true;
                    }
                    if ((!$one_has_d) && (!$one_has_k)) {
                        foreach(explode('/', $one_lang_str) as $one_lang_part) {
                            if ('d' == $one_lang_part) {
                                $one_has_t = true;
                                break;
                            }
                        }
                    }
                }

                $one_language_class = null;
                if ($one_has_d) {
                    $one_language_class = 'german';
                }
                elseif ($one_has_k) {
                    $one_language_class = 'dialect';
                }
                elseif ($one_has_f) {
                    $one_language_class = 'french';
                }
                elseif ($one_has_t) {
                    $one_language_class = 'subtitles';
                }

                $one_date_time_info = array(
                    'date_time' => $one_date_info['date'] . ' ' . $one_date_info['time'],
                    'languages' => $one_date_info['lang'],
                    'canceled' => false,
                );
                if ($this->m_desktop_client) {
                    $one_date_time_info['language_class'] = $one_language_class;
                    if (!in_array($one_language_class, $current_language_classes)) {
                        $current_language_classes[] = $one_language_class;
                    }
                }

                $one_movie_screen_info['times'][] = $one_date_time_info;

            }

            $last_movie_data['screenings'][] = $one_movie_screen_info;

            if ($this->m_desktop_client && (!empty($current_language_classes))) {
                if (empty($last_movie_data['language_classes'])) {
                    $last_movie_data['language_classes'] = array();
                }

                foreach ($current_language_classes as $one_current_language_class) {
                    if (!in_array($one_current_language_class, $last_movie_data['language_classes'])) {
                        $last_movie_data['language_classes'][] = $one_current_language_class;
                    }
                }

            }

            $movie_list_data[] = $last_movie_data;

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

        $output_types = array();
        $output_type_rank = -1;
        foreach ($this->m_service->getMovieTypeList(array('country' => 'ch')) as $type_key => $type_info) {
            $output_type_rank += 1;
            $output_types[] = array(
                'genre_id' => $type_info['outer'], // $type_key,
                'genre_name' => $type_info['label'],
                'rank' => $output_type_rank,
            );
        }

        if (empty($movie_locations_data)) {
            $movie_locations_data = null;
        }
        if (empty($movie_list_data)) {
            $movie_list_data = null;
        }

        $use_date = $this->req_date;
        if (empty($use_date)) {
            $use_date = date('Y-m-d');
        }

        $last_modified_regions = '2012-06-19 18:00:00';
        $last_modified_genres = '2012-06-19 18:00:00';

        $output_data = null;

        if (!$this->m_movie_detail) {
            $output_data = array(
                'date' => $use_date,
                'regions_last_modified' => $last_modified_regions,
                'regions' => $output_regions,
                'genres_last_modified' => $last_modified_genres,
                'genres' => $output_types,
            );

            if ($with_screenings) {
                $output_data['locations'] = $movie_locations_data;
            }

            $output_data['films'] = $movie_list_data;
        }
        else {
            $film_data = null;
            if (!empty($movie_list_data)) {
                $film_data = $movie_list_data[0];
            }

            $movie_types = null;
            if ((!empty($output_types)) && (!empty($film_data)) && (!empty($film_data['genres']))) {

                $movie_types = array();
                $cur_movie_type_rank = -1;
                foreach ($output_types as $one_movie_type) {
                    if (in_array($one_movie_type['genre_id'], $film_data['genres'])) {
                        $cur_movie_type_rank += 1;
                        $one_movie_type['rank'] = $cur_movie_type_rank;
                        $movie_types[] = $one_movie_type;
                    }
                }
                if (empty($movie_types)) {
                    $movie_types = null;
                }

            }

            $output_data = array(
                //'genres_last_modified' => $last_modified_genres,
                'genres' => $movie_types,
            );

            if ($with_screenings) {
                $output_data['locations'] = $movie_locations_data;
            }

            $output_data['film'] = $film_data;
        }

        //$output_json = json_encode($output_data);
        $output_json = Zend_Json::encode($output_data);

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Length: ' . strlen($output_json));

        echo $output_json;

        exit(0);

    }

    /**
     * Serve list of sections.
     */
    private function _innerListProcess()
    {
        $empty_res = array();

        $param_date = $this->_request->getParam('date');
        $param_region = $this->_request->getParam('region'); // if empty: kanton-basel-stadt for lists, empty for detail
        $param_type = $this->_request->getParam('genre'); // not required, for lists only

        if (empty($param_date)) {
            $param_date = date('Y-m-d');
        }
        $this->req_date = $this->m_service->getRequestDate($param_date);
        if (!$this->req_date) {
            $this->req_date = date('Y-m-d');
        }

        if ((!$this->m_movie_detail) && (empty($param_region))) {
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
        if ((!$this->m_movie_detail) && (!$req_region)) {
            return $empty_res;
        }

        $params = array();

        $params['event_date'] = $this->req_date;

        if (!empty($req_region)) {
            $params['event_region'] = $req_region;
        }

        if ((!$this->m_movie_detail) && (!empty($param_type))) {
            $req_type = $this->m_service->getRequestMovieType($param_type);
            if ($req_type) {
                $params['event_type'] = $req_type;
            }
        }

        $params['publication'] = self::PUBLICATION;
        $params['language'] = self::LANGUAGE;
        $params['section'] = self::EV_SECTION;
        $params['article_type'] = self::EV_TYPE;

        $param_movie_key = $this->_request->getParam('movie_key');
        if ($this->m_movie_detail) {
            $params['movie_key'] = $param_movie_key;
            //$params['omit_multidate'] = true; // taking just movies screened that date
        }

        $params['order'] = array(array('field' => 'byname', 'dir' => 'asc'));
        $params['multidate'] = 'movie_screening';

        $movies = $this->m_service->getEventList($params);

        return $movies;

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
        // params: region, movie type, date, count, rating
        // for Z+, it is: zentralschweiz_region, null, current_date, 1
        $empty_res = array();

        $param_region = $this->_request->getParam('region');
        $param_type = $this->_request->getParam('genre');
        $param_date = $this->_request->getParam('date');
        $param_rating = $this->_request->getParam('rating');

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
            $req_type = $this->m_service->getRequestMovieType($param_type);
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

        if (empty($param_rating)) {
            $param_rating = null;
        }

        $params = array();

        $params['event_date'] = $this->req_date;
        $params['event_region'] = $req_region;
        if ($req_type) {
            $params['event_type'] = $req_type;
        }
        if ($param_rating) {
            $params['movie_rating'] = $param_rating;
        }
        $params['publication'] = self::PUBLICATION;
        $params['language'] = self::LANGUAGE;
        $params['section'] = self::EV_SECTION;
        $params['article_type'] = self::EV_TYPE;

        $params['multidate'] = 'movie_screening';

        //$params['order'] = array(array('field' => 'bycustom.num.movie_rating_wv.0', 'dir' => 'desc'), array('field' => 'byname', 'dir' => 'asc'));
        $params['order'] = array(array('field' => 'bycustom.ci.movie_key.no_key', 'dir' => 'desc'), array('field' => 'byname', 'dir' => 'asc'));

        $movies = $this->m_service->getEventList($params);

        return $movies;

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

