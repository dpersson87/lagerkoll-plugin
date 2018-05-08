<?php

if (!class_exists('fs_WP_List_Table')) {
    require_once(__DIR__ . '/fsWpListTable.class.php');
}

class lkProductsList extends fs_WP_List_Table {

    static $dm;

    /** Class constructor */
    public function __construct() {
        parent::__construct(
            [
                'singular' => __('Customer', 'sp'), //singular name of the listed records
                'plural'   => __('Customers', 'sp'), //plural name of the listed records
                'ajax'     => false //does this table support ajax?
            ]
        );

        //Bootstrap lagerkoll code
        require_once __DIR__ . '/../../lagerkoll_php/server/DataManager.php';
        self::$dm = new \Lagerkoll\DataManager();
    }

    /**
     * Retrieve customers data from the database
     *
     * @param int $per_page
     * @param int $page_number
     *
     * @return mixed
     */
    public static function get_customers($per_page = 100, $page_number = 1) {

        $orderByTranslator = array(
            "name"      => "Name",
            "articleid" => "article",
        );


        $q = isset($_GET['q']) ? $_GET['q'] : "";
        $q = isset($_POST['s']) ? $_POST['s'] : $q;

        $lagerkollArticles = self::$dm->getArticles(
            $q,
            0,
            $page_number - 1,
            100,
            "asc",
            'art'

        );

        $result = $lagerkollArticles->articles;
        $result = json_decode(json_encode($result), true);

        //$_REQUEST['orderby']
        //$_REQUEST['order'] )
        // $per_page";
        // $page_number

        return $result;
    }

    /**
     * Delete a customer record.
     *
     * @param int $id customer ID
     */
    public static function import_customer($id) {

    }

    /**
     * Returns the count of records in the database.
     *
     * @return null|string
     */
    public static function record_count() {


        $lagerkollStatus = self::$dm->getArticles(
            "",
            0,
            0,
            1
        );

        return $lagerkollStatus->articlesCount;

    }

    /** Text displayed when no customer data is available */
    public function no_items() {
        _e('No customers avaliable.', 'sp');
    }

    /**
     * Render a column when no column specific method exist.
     *
     * @param array  $item
     * @param string $column_name
     *
     * @return mixed
     */
    public function column_default($item, $column_name) {


        switch ($column_name) {

            case 'images' :

                //return print_r($item, true); //Show the whole array for troubleshooting purposes
                return count(lkPlugin::findLocalImages($item['articleId']));

                break;

            case 'webshopArticle' :
                return ($item['webshopArticle'] == 1 ? '<span class="dashicons dashicons-yes"></span>'
                    : '<span class="dashicons dashicons-no-alt"></span>');

                break;

            case 'fs' :

                $info = \ajlajk\ajlajkInterperter::getProductInformationFromSku($item['articleId']);

                return $info['colorReadable'] . " (" . $info['color'] . ") - " . $info['sizeReadable'] . " (" . $info['size'] . ")";

                break;

            default:
                return $item[ $column_name ];

            //return print_r($item, true); //Show the whole array for troubleshooting purposes
        }
    }

    /**
     * Render the bulk edit checkbox
     *
     * @param array $item
     *
     * @return string
     */
    function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="bulk-import[]" value="%s" />',
            $item['articleId']
        );
    }

    /**
     * Method for name column
     *
     * @param array $item an array of DB data
     *
     * @return string
     */
    function column_name($item) {

        $product = wc_get_product_id_by_sku($item['articleId']);
        $parent  = wp_get_post_parent_id($product);
        if ($parent) {
            $product = $parent;
        }


        $import_nonce = wp_create_nonce('sp_import');
        $title        = $item['name'];
        $actions      = array();

        if (!$product) {
/*            $actions['import'] = sprintf(
                '<a href="?page=%s&action=%s&product=%s&_wpnonce=%s">Importera</a>',
                esc_attr($_REQUEST['page']),
                'import',
                $item['articleId'],
                $import_nonce
            );*/

            $actions['showimages'] = sprintf(
                '<a href="?page=%s&action=%s&product=%s&_wpnonce=%s">Visa Bilder</a>',
                esc_attr($_REQUEST['page']),
                'showimages',
                $item['articleId'],
                $import_nonce
            );
        }
        else {
            $actions['visa'] = sprintf(
                '<a href="post.php?post=%s&action=edit">Visa i Woocomerce</a>',
                $product
            );

/*            $actions['showmeta'] = sprintf(
                '<a href="?page=%s&action=%s&product=%s&_wpnonce=%s">Visa Meta</a>',
                esc_attr($_REQUEST['page']),
                'showmeta',
                $item['articleId'],
                $import_nonce
            );*/

            $actions['showimages']   = sprintf(
                '<a href="?page=%s&action=%s&product=%s&_wpnonce=%s">Visa Bilder</a>',
                esc_attr($_REQUEST['page']),
                'showimages',
                $item['articleId'],
                $import_nonce
            );
/*            $actions['updateimages'] = sprintf(
                '<a href="?page=%s&action=%s&product=%s&_wpnonce=%s">uppdatera Bilder</a>',
                esc_attr($_REQUEST['page']),
                'updateimages',
                $item['articleId'],
                $import_nonce
            );*/

        }


        return $title . $this->row_actions($actions);
    }

    /**
     *  Associative array of columns
     *
     * @return array
     */
    function get_columns() {
        $columns = [
            'cb'             => '<input type="checkbox" />',
            'webshopArticle' => 'Webshop<br>(i lagerkoll)',
            'name'           => "Namn<br>(i lagerkoll)",
            'articleId'      => "Artikelnr<br>(i lagerkoll)",
            /*'price'          => "pris<br>(i lagerkoll)",*/
            'amount'         => "Antal i lager<br>(i lagerkoll)",
            'images'         => "antal bilder i mapp<br>(på server)",
            'fs'             => "Färg och storlek<br>(Beräknat utifrån artikelnummer)",
        ];

        return $columns;
    }

    /**
     * Columns to make sortable.
     *
     * @return array
     */
    public function get_sortable_columns() {
        $sortable_columns = array(
            'name' => array('name', true),
            //'articleId' => array('articleId', false),
        );

        return $sortable_columns;
    }

    /**
     * Returns an associative array containing the bulk action
     *
     * @return array
     */
    public function get_bulk_actions() {
        return array();

        $actions = [
            'bulk-import'    => 'Importera',
            'importAll'      => 'Importera ALLA',
            'updateallstock' => 'Uppdatera alla lagerstatusar',
        ];

        return $actions;
    }

    /**
     * Handles data query and filter, sorting, and pagination.
     */
    public function prepare_items() {
        //        $this->_column_headers = $this->get_column_info();

        $this->_column_headers = array($this->get_columns(), $this->get_sortable_columns(), array(), "name");

        /** Process bulk action */
        $this->process_bulk_action();
        $per_page     = $this->get_items_per_page('per_page', 200);
        $current_page = $this->get_pagenum();
        $total_items  = self::record_count();
        $this->set_pagination_args(
            [
                'total_items' => $total_items, //WE have to calculate the total number of items
                'per_page'    => $per_page //WE have to determine how many items to show on a page
            ]
        );
        $this->items = self::get_customers($per_page, $current_page);
    }

    public function process_bulk_action() {
        //Detect when a bulk action is being triggered...

        if ('bulk-import' === $this->current_action()) {
            $arrToImport = $_POST['bulk-import'];


            foreach ($arrToImport as $item) {
                lkPlugin::handleImport($item);
            }

        }

        if ('import' === $this->current_action()) {
            // In our file that handles the request, verify the nonce.
            $nonce = esc_attr($_REQUEST['_wpnonce']);
            if (!wp_verify_nonce($nonce, 'sp_import')) {
                die('Go get a life script kiddies');
            }
            else {


                lkPlugin::handleImport($_GET['product']);


                // esc_url_raw() is used to prevent converting ampersand in url to "#038;"
                // add_query_arg() return the current url

                $url = sprintf(
                    '?page=%s&imported=true',
                    esc_attr($_REQUEST['page'])
                );

                wp_redirect($url);

                exit;
            }
        }

        if ('showmeta' === $this->current_action()) {
            lkPlugin::showInfo($_GET['product']);
        }

        if ('showimages' === $this->current_action()) {
            lkPlugin::showImages($_GET['product']);
        }

        if ('updateimages' === $this->current_action()) {
            lkPlugin::updateImages($_GET['product']);
        }

        if ('fixSub' === $this->current_action()) {
            lkPlugin::fixSub($_GET['product']);
        }


        if ('importAll' === $this->current_action()) {
            lkPlugin::importAll();
        }


        if ('updateallimages' === $this->current_action()) {
            lkPlugin::updateAllImages();
        }

        if ('updateallstock' === $this->current_action()) {
            lkPlugin::updateAllStock();
        }

    }
}



