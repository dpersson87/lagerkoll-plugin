<?php
/*
Plugin Name: Lagerkoll Plugin
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
Description: A brief description of the Plugin.
Version: 1.0
Author: Dennis
Author URI: http://URI_Of_The_Plugin_Author
License: A "Slug" license name e.g. GPL2
*/

//error_reporting(0);
add_theme_support('deactivate_layerslider');


/**
 * initiera ppluggin
 */
add_action(
    'plugins_loaded',
    function () {
        require_once "vendor/source/class/lkPlugin.class.php";

        new lkPlugin();
    }
);


if (isset($_GET['korcronsomfixarlagerkollimport'])) {
    add_action('init', 'runLagerkollCron');
}


function runLagerkollCron() {
    set_time_limit(55);


    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    $lkp = new lkPlugin();
    $lkp->updateProductsFromLagerkollCron();
}


/**
 * Funktion för att rensa bort färger ur titlar Skall ej behövas mer
 *
 * @param $title
 * @param $id
 *
 * @return mixed
 */
function filterProductTitle($title, $id) {
    global $product;

    $pt = get_post_type($id);

    if (!is_admin()) {
        if ($pt == "product") {

            if ($product == null) {
                $product = new WC_Product($id);
            }


            if (!$product->is_type('variation')) {
                $title = $title . " " . get_post_meta($id, '_sku', true);
            }
        }
    };

    return $title;
}

add_filter(
    'the_title',
    'filterProductTitle',
    1000,
    2
);


/**
 * Visa återförsäljar box om man har ?af i länk
 */
function output_AF_box() {
    if (isset($_GET["af"])) {
        global $product;
        echo "<style>.variations_form{ display:none !important; } .shop_attributes{margin-top:32px;} .woocommerce-page .button { padding: 25px 15px !important; }</style>";

        echo $product->list_attributes(
            ) . "<div class=\"af-box\" style='margin-top:32px;'><h3>Återförsäljar-info</h3>Klicka på den svarta återförsäljare-knappen, så öppnas din e-post upp där du skriver in din beställning. Ange artikelnummer, antal och färg.<br><br><a style='width:100%;' href=\"mailto:info@ajlajk.nu?subject=Beställning från återförsäljare&body=Jag%20%C3%B6nskar%20best%C3%A4lla%20f%C3%B6ljande%20produkt%2Fer%3A%0A%0AJag%20%C3%A4r%20inf%C3%B6rst%C3%A5dd%20att%20kl%C3%A4derna%20s%C3%A4ljs%20i%207-pack%20och%20smyckena%20i%206-pack.%20%0A%0AJag%20representerar%20f%C3%B6ljande%20f%C3%B6retag%3A%20%0A%0AVi%20p%C3%A5%20Ajlajk%20%C3%A5terkommer%20med%20en%20orderbekr%C3%A4ftelse%20n%C3%A4r%20vi%20hanterat%20din%20order.\" class=\"single_add_to_cart_button button alt\">Beställ som återförsäljare</a><br><center>eller</center><a href='" . get_permalink(
                 $product->the_id
             ) . "' style='color:#000;width:100%;background-color:#CCC;' class=\"af-btn single_add_to_cart_button button alt\">Beställ som privatperson</a></div>";

    }
}

add_action('woocommerce_product_meta_end', 'output_AF_box', 10, 1);


/**
 * Byt bild saknas bild
 */
function custom_fix_thumbnail() {
    add_filter('woocommerce_placeholder_img_src', 'custom_woocommerce_placeholder_img_src');

    function custom_woocommerce_placeholder_img_src($src) {
        $upload_dir = wp_upload_dir();
        $uploads    = untrailingslashit($upload_dir['baseurl']);
        $src        = $uploads . '/2016/08/produktbild-saknas.jpg';

        return $src;
    }
}

add_action('init', 'custom_fix_thumbnail');


# Override avia management of thumbnails.
# See : http://www.kriesi.at/support/topic/woocommerce-cayalog-page/
# See : http://www.kriesi.at/support/topic/woocommerce-archive-page-default-product-placeholder-not-showing/
function enfold_woocommerce_child_theme_override() {
    remove_action('woocommerce_before_shop_loop_item_title', 'avia_woocommerce_thumbnail', 10);
    add_action('woocommerce_before_shop_loop_item_title', 'avia_woocommerce_thumbnail_child_theme', 10);

    function avia_woocommerce_thumbnail_child_theme($asdf) {
        global $product, $avia_config;
        $rating = $product->get_rating_html(); //get rating

        $id   = get_the_ID();
        $size = 'shop_catalog';

        $gallery_thumbnail = avia_woocommerce_gallery_first_thumbnail($id, $size);
        $post_thumbnail    = get_the_post_thumbnail($id, $size);

        // Get the default WC thumbnail if empty.
        $post_thumbnail = empty($gallery_thumbnail) && empty($post_thumbnail) ?
            $post_thumbnail = wc_placeholder_img() : $post_thumbnail;

        echo "<div class='thumbnail_container'>";
        echo $gallery_thumbnail;
        echo $post_thumbnail;
        if (!empty($rating)) {
            echo "<span class='rating_container'>" . $rating . "</span>";
        }
        if ($product->product_type == 'simple') {
            echo "<span class='cart-loading'></span>";
        }
        echo "</div>";
    }
}

add_action('after_setup_theme', 'enfold_woocommerce_child_theme_override');


// Add "Clone" link to each row in the Woo Attributes admin page
function my_duplicate_post_link($actions, $post) {
    if ($post->post_type == 'product') {
        $prod = new WC_Product($post->ID);
        $sku  = $prod->get_sku();

        add_thickbox();

        $actions['updateimages'] = sprintf(
            '<a href="?page=%s&action=%s&product=%s&TB_iframe=true" class="thickbox">uppdatera Bilder</a>',
            'lpMain',
            'updateimages',
            $sku
        );

        $actions['fixSub'] = sprintf(
            '<a href="?page=%s&action=%s&product=%s&TB_iframe=true" class="thickbox">uppdatera Varianter</a>',
            'lpMain',
            'fixSub',
            $sku
        );
    }

    return $actions;
}

add_filter('post_row_actions', 'my_duplicate_post_link', 10, 2);


// döljer på produktsida
add_filter('term_links-product_cat', 'dennis_term_links_product_cat', 10, 1);

function dennis_term_links_product_cat($links) {

    $toRemove = array(
        'host-',
        'var-',
        'host',
        'var',
        'lagersale',
    );
    foreach ($links as $index => $link) {
        foreach ($toRemove as $strToRemove) {
            if (is_numeric(strpos($link, $strToRemove))) {
                unset($links[ $index ]);
            }
        }

    }


    return $links;
}


//döljer i widget
add_filter(
    'woocommerce_product_categories_widget_dropdown_args',
    'dennis_wc_product_dropdown_categories_get_terms_args'
);


// Lägg in idn på de kattegorier som skall döljas
function dennis_wc_product_dropdown_categories_get_terms_args($args) {

    // lägg till idn som skall döljas
    $args['exclude'] = array(
        104,
        90,
        89,
        91,
        92,
        158,
        118,
        143,
        142,
        170,
    );

    return $args;

}

/**
 * Trim zeros in price decimals
 **/
add_filter('woocommerce_price_trim_zeros', '__return_true');


add_filter(
    'woocommerce_sale_flash',
    function ($temp) {
        return "";
    }
);


add_filter(
    'avia_masonry_entries_query',
    function ($query, $params = array()) {


        if (isset($query['post_type']) && isset($query['post_type']['product_variation'])) {
            unset($query['post_type']['product_variation']);
        }

        return $query;

    }
);


add_filter('wcpv_posts_table_name_in_db', 'custom_wcpv_posts_table_name_in_db', 10, 1);

function custom_wcpv_posts_table_name_in_db($posts_table_name) {
    global $wpdb;
    $posts_table_name = $wpdb->prefix . "posts";

    return $posts_table_name;
}

/*
if (isset($_GET['dennis'])) {

    add_action(
        'wp',
        function () {
            $tempProd = wc_get_product("14327");

            $wcEmails = new WC_Emails();
            $result   = $wcEmails->no_stock($tempProd);

            var_dump($result);
            die;


        }
    );
}*/


//tvinga stor bild direkt då den är liten nog

    add_filter('wp_calculate_image_srcset', '__return_false');


    add_image_size( 'ajlajksingleshop', 450, 675, array( 'center', 'center' ) );
    add_filter( 'single_product_small_thumbnail_size', function(){return 'ajlajksingleshop';});

/*if (isset($_GET['dennis'])) {
    add_filter( 'single_product_small_thumbnail_size', function(){return 'ajlajksingleshop';});

} else {
    add_filter( 'single_product_small_thumbnail_size', function(){return 'large';});
}*/




add_filter('woocommerce_format_content', 'yanco_remove_inline_terms', 10, 2);
function yanco_remove_inline_terms( $apply_filters, $raw_string ) {
    if( is_checkout() ) {
        return '';
    }
    return $apply_filters;
}


    if( function_exists('acf_add_options_page') ) {

        acf_add_options_sub_page(array(
                                     'page_title' 	=> 'Tolka Artikelnummer',
                                     'menu_title'	=> 'Tolka Artikelnummer',
                                     'parent_slug'	=> 'lpMain',
                                 ));
    }
