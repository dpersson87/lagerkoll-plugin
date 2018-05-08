<?php

/**
 * Short description for file
 *
 * Long description for file (if any)...
 *
 * PHP version 5
 *
 * LICENSE: Contact Fristil for information about license
 *
 * @category   lagerkoll-plugin
 * @package    lkPlugin.class.phpthor     Dennis
 * @copyright  2016 Fristil AB
 * @license    Contact Fristil for information about license
 * @link       http://fristil.se
 */
class lkPlugin {

    public $tc;

    public function __construct() {


        if (!class_exists('lkProductsList')) {
            require_once(__DIR__ . '/lkProductsList.class.php');
        }


        require_once(__DIR__ . '/ajlajkInterperter.class.php');

        add_action('admin_menu', [$this, 'lkGenerateWpMenu']);
        add_action('init', ['lkPlugin', 'callback']);
        add_filter('set-screen-option', [__CLASS__, 'lkSetScreenOptions'], 10, 3);


        //add column
        add_filter('manage_edit-shop_order_columns', ['lkPlugin', 'custom_shop_order_column'], 11);
        // adding the data for each orders by column (example)
        add_action('manage_shop_order_posts_custom_column', ['lkPlugin', 'showLagerkollStatus'], 10, 2);
        add_action('admin_head', ['lkPlugin', 'my_column_width']);


    }


    public static function callback() {

        if (is_numeric(strpos($_SERVER['REQUEST_URI'], 'lagerkollcallback')) || is_numeric(
                strpos($_SERVER['REQUEST_URI'], 'kalaskulor')
            )
        ) {

            echo time();

            $body = json_decode(file_get_contents('php://input'));

            //logIt($body, $body->type . "-" . $body->action);
            logIt(time(), "allRequestsTimes", false);

            switch ($body->type) {
                case "article" :

                    logIt($body, $body->type . "-" . $body->action);
                    if ($body->action == "updated") {
                        self::updateProductFromLagerkoll($body->articleId);
                    }
                    if ($body->action == "created") {

                        self::updateProductFromLagerkoll($body->articleId, true);

                        //self::handleImport($body->articleId);
                    }
                    if ($body->action == "deleted") {
                        self::putInThrash($body->articleId);

                    }
                    break;
                /*case "store":
                    break;*/
                /*case "pricelist" :
                    break;*/
                case "price" :
                    logIt($body, $body->type . "-" . $body->action);
                    if ($body->action == "updated") {
                        self::updateProductFromLagerkoll($body->articleId);
                    }
                    if ($body->action == "created") {
                        self::handleImport($body->articleId);
                    }
                    break;
                /*case "currency" :
                    break;*/
                /*case "customer" :
                    break;*/
                /*case "customer_delivery_term" :
                    break;*/
                /*case "way_of_delivery" :
                    break;*/
                /*case "payment_term" :
                    break;*/
                case "customer_order" :
                    logIt($body, $body->type . "-" . $body->action);
                    if ($body->action == "updated") {
                        self::LagerkollOrderIDToWooStatus($body->customerOrderId);
                    }
                    if ($body->action == "created") {

                    }
                    break;
                /*case "supplier" :
                    break;*/
                /*case "supplier_delivery_term" :
                    break;*/
                default:
                    logIt($body, $body->type);
            }

            die;
        }

        if (isset($_GET['generatexml']) && !empty($_GET['generatexml']) && is_numeric($_GET['generatexml']) && is_admin()
        ) {
            $orderId = $_GET['generatexml'];

            $dontAutoPrint = false;
            if (isset($_GET['dontautoprint'])) {
                $dontAutoPrint = true;
            }


            $order = new WC_Order($orderId);
            if ($order) {

                $pacsoftXmlGenerated = get_post_meta($orderId, 'pacsoftXmlGenerated', true);


                if (!$pacsoftXmlGenerated) {
                    lkPlugin::generatePacsoftXml($orderId, false, $dontAutoPrint);
                }
            }


        }
    }

    public function lkGenerateWpMenu() {
        $hook = add_menu_page(
            'Lagerkoll',
            'Lagerkoll',
            'manage_options',
            'lpMain',
            [$this, 'lkShowOptionsPage']
        );
        add_action("load-$hook", [$this, 'lkScreenOptions']);
    }

    function lkScreenOptions() {
        $option = 'per_page';
        $args = [
            'label' => 'Produkter',
        ];
        add_screen_option($option, $args);
    }


    /*
     * TODO add max 500
     */
    public static function lkSetScreenOptions($status, $option, $value) {
        return $value;
    }


    // display the admin options page
    function lkShowOptionsPage() {

        if ($this->tc == false) {
            $this->tc = new lkProductsList();
        }


        if (isset($_GET['action']) && $_GET['action'] == 'updateimages' || isset($_GET['action']) && $_GET['action'] == 'fixSub') {
            ?>
            <script type="text/javascript">
                jQuery('#wpadminbar').remove();
                jQuery('#adminmenumain').remove();
                jQuery('#wpcontent').css({
                    'margin-left': "10px"
                })
            </script>
            <?php
            $this->tc->prepare_items();
        } else {
            include __DIR__ . "/../templates/main.php";
        }

    }


    public static function showInfo($originalItem) {


        $ajlajkProd = \ajlajk\ajlajkInterperter::getProductInformationFromSku($originalItem);


        // Hämta produkt med samma SKU
        $productWholeSkuId = wc_get_product_id_by_sku($ajlajkProd['sku']);
        $parentProductId = false;


        // Finns det produkt med samma SKU Hämta den
        if (!empty($productWholeSkuId)) {

            $productWholeSku = wc_get_product($productWholeSkuId);

            if ($productWholeSku->post->post_type == "product_variation") {
                $parentProductId = $productWholeSku->post->post_parent;
            }
        }

        self::displayInfo(array($parentProductId, $productWholeSkuId));
        die;


    }

    public static function showImages($originalItem) {

        $localImages = self::findLocalImages($originalItem);

        echo "<h3> Bilder som kan importeras</h3>";

        foreach ($localImages as $index => $localImage) {
            ?>

            <div style="width: 10%; min-width:50px; float: left;">
                <img src="<?= $localImage ?>" style="max-width: 100%;" alt="">
            </div>


            <?php
        }


        $productId = wc_get_product_id_by_sku($originalItem);

        echo "<h3 style='clear:both'>" . $productId . "</h3>";
        $product = wc_get_product($productId);


        if ($product->post_type == 'product_variation') {
            $image = $product->get_image();
            echo "<h3 style='clear: both;'>Befintlig Produktbild</h3>";
            echo $image;


            $parent = wc_get_product($product->parent_id);

            $attachment_ids = $parent->get_gallery_image_ids();
        } elseif ($product->post_type == 'product') {
            $attachment_ids = $product->get_gallery_image_ids();

        }


        if (!empty($attachment_ids)) {

            echo "<h3 style='clear: both;'>Befintlig Galleribilder</h3>";

            foreach ($attachment_ids as $attachment_id) {
                ?>

                <div style="width: 10%; min-width:50px; float: left;">
                    <img src="<?= wp_get_attachment_url($attachment_id) ?>" style="max-width: 100%;" title="<?=$attachment_id?>" alt="">
                </div>


                <?php
            }
        }


        die;

    }

    public static function fixSub($originalItem) {

        $productId = wc_get_product_id_by_sku($originalItem);
        self::updateParentsVariations($productId, true, true);

        echo "Klart";
        die;

    }

    public static function updateImages($originalItem, $kill = true) {


        $localImages = self::findLocalImages($originalItem);
        $productInfo = ajlajk\ajlajkInterperter::getProductInformationFromSku($sku);

        $productId = wc_get_product_id_by_sku($originalItem);
        //$product   = new WC_Product($productId);
        //$product = new WC_Product_Variable($productId);
        $product = wc_get_product($productId);


        if ($product->is_type('variable')) {
            $available_variations = $product->get_available_variations();

            foreach ($available_variations as $index => $available_variation) {

                self::updateImages($available_variation['sku'], false);
            }
        }


        $parent = $product->get_parent();

        if (isset($_GET['dennis'])) {

            //is kids?


            foreach ($localImages as $index => $localImage) {
                if ($productInfo['special']) {
                    // tabort om inte kids
                } else {
                    //tabort om kids

                    if (is_numeric(strpos(strtoupper($localImage), "KIDS"))) {
                        unset($localImages[$index]);
                    }
                }
            }

        }


        if (!$parent) {

            WC_Product_Variable::sync($productId);
            //WC_Product_Variable::variable_product_sync($productId);
            WC_Product_Variable::sync_stock_status($productId);

            //$p_v = new WC_Product_Variable($productId);
            $p_v = wc_get_product($productId);
            $p_v->variable_product_sync();

        }

        if (!empty($product) && !empty($localImages)) {
            self::handleImageUppload($localImages, $productId);
        }


        if (count($localImages) > 0) {
            echo $originalItem . " : hittade " . count($localImages) . " bilder " . "<br>";
        } else {
            echo "" . $originalItem . " hittade inga bilder<br>";
        }

        if ($kill) {
            die;
        }

    }

    public static function updateSimpleImages($originalItem, $kill = true) {


        $localImages = self::findLocalImages($originalItem);

        $productId = wc_get_product_id_by_sku($originalItem);
        $product = wc_get_product($productId);

        if (!empty($product) && !empty($localImages)) {
            self::handleImageUppload($localImages, $productId);
        }


        if (count($localImages) > 0) {
            echo $originalItem . " : hittade " . count($localImages) . " bilder " . "<br>";
        } else {
            echo "" . $originalItem . " hittade inga bilder<br>";
        }

        if ($kill) {
            die;
        }

    }

    public static function handleImport($originalItem) {


        require_once __DIR__ . '/../../lagerkoll_php/server/DataManager.php';
        $dm = new \Lagerkoll\DataManager();

        //        $originalItem = "A272-KIDS-SGR";

        $ajlajkProd = \ajlajk\ajlajkInterperter::getProductInformationFromSku($originalItem);


        // Hämta produkt med samma SKU
        $productWholeSkuId = wc_get_product_id_by_sku($ajlajkProd['sku']);
        $productWholeSku = false;
        $productWholeSkuLagerkoll = false;
        $parentProductId = false;
        $parentProductLagerkoll = false;

        try {
            $productWholeSkuLagerkoll = $dm->getArticle($ajlajkProd['sku'], true);
        } catch (Exception $e) {
            $productWholeSkuLagerkoll = false;
        }


        if (!$productWholeSkuLagerkoll->webshopArticle) {
            return false;
        }

        // Finns det produkt med samma SKU Hämta den
        if (!empty($productWholeSkuId)) {

            $productWholeSku = wc_get_product($productWholeSkuId);

            if ($productWholeSku->post->post_type == "product_variation") {
                $parentProductId = $productWholeSku->post->post_parent;
            }
        } //Finns inte produkt med samma sku och det inte är en grundprodukt kolla om vi har föräldern
        elseif (!$ajlajkProd['skuIsClean']) {
            $parentProductId = wc_get_product_id_by_sku($ajlajkProd['cleanSku']);

        }


        $post = get_post($parentProductId);


        //Har vi inte någon förälder? Skapa
        if (empty($parentProductId)) {

            try {
                $parentProductLagerkoll = $dm->getArticle($ajlajkProd['cleanSku'], true);
            } catch (Exception $e) {
                $parentProductLagerkoll = false;
            }


            if (!$parentProductLagerkoll->webshopArticle) {
                $parentProductLagerkoll = false;
            }


            if (empty($parentProductLagerkoll)) {
                try {
                    $parentProductLagerkoll = $dm->getArticle($ajlajkProd['sku'], true);
                    $productWholeSkuLagerkoll = $parentProductLagerkoll;
                } catch (Exception $e) {
                    $parentProductLagerkoll = false;
                }
            }

            $categories = explode(',', $parentProductLagerkoll->category);
            foreach ($categories as $index => $category) {
                $category = trim($category);
                $category = strtolower($category);
                $category = lcfirst($category);
                if (empty($category)) {
                    unset($categories[$index]);
                } else {
                    $categories[$index] = $category;
                }
            }


            try {
                $pricelist = \Lagerkoll\Settings::PRICELIST;
                $allProductPrices = $dm->getPricesInPriceListForArticle($pricelist, $parentProductLagerkoll->articleId);
                $productPrice = $allProductPrices->prices[0]->price;
            } catch (Exception $e) {
                $productPrice = 0;
            }

            if (empty($productPrice)) {
                $productPrice = $parentProductLagerkoll->price;
            }
            $productPrice = intval($productPrice);


            $images = self::findImages($parentProductLagerkoll->articleId);


            $parentProductId = self::createParentProduct(
                $ajlajkProd['skuIsClean'] ? $ajlajkProd['sku'] : $ajlajkProd['cleanSku'],
                $ajlajkProd['sku'],
                \ajlajk\ajlajkInterperter::removeColorFromString($parentProductLagerkoll->name),
                $ajlajkProd['skuIsClean'] ? $productPrice : 0,
                $parentProductLagerkoll->description,
                "", //$parentProductLagerkoll->description,
                0, //'thumbnail',
                $categories,
                $images,
                $ajlajkProd['skuIsClean']

            );


        }


        //har vi inte någon produkt
        if (empty($productWholeSkuId)) {

            $categories = explode(',', $productWholeSkuLagerkoll->category);
            foreach ($categories as $index => $category) {
                $category = trim($category);
                $category = strtolower($category);
                $category = lcfirst($category);
                if (empty($category)) {
                    unset($categories[$index]);
                } else {
                    $categories[$index] = $category;
                }
            }


            $productPrice = 0;
            try {
                $pricelist = \Lagerkoll\Settings::PRICELIST;
                $allProductPrices = $dm->getPricesInPriceListForArticle($pricelist, $ajlajkProd['sku']);
                $productPrice = $allProductPrices->prices[0]->price;
            } catch (Exception $e) {
            }


            if (empty($productPrice)) {
                $productPrice = $productWholeSkuLagerkoll->price;
            }
            $productPrice = intval($productPrice);

            $images = self::findImages($productWholeSkuLagerkoll->articleId);


            $productWholeSkuId = self::createVariationProduct(
                $ajlajkProd['sku'],
                $parentProductId,
                $productWholeSkuLagerkoll->name,
                $productPrice, //pris
                0, //'thumbnail',
                $productWholeSkuLagerkoll->amount, //nrOfStock = 0,
                $productWholeSkuLagerkoll->description,
                $images
            );


        }


        if ($parentProductId) {
            update_post_meta($parentProductId, 'fix_after_lagerkoll', 1);
            self::updateParentsVariations($parentProductId);
        }
        if ($productWholeSkuId) {
            update_post_meta($productWholeSkuId, 'fix_after_lagerkoll', 1);
            self::setAttributesForVariation($productWholeSkuId);
        }

        @self::updateImages($ajlajkProd['sku'], false);


        return true;
    }

    public function createProduct($data) {

        $post = array(
            'post_author' => 1,
            'post_content' => $data['post_content'],
            'post_status' => "publish",
            'post_title' => $data['title'],
            'post_parent' => $data['post_parent'],
            'post_type' => $data['post_type'],
            'post_excerpt' => $data['post_excerpt'],
        );


        $post_id = wp_insert_post($post, false);

        //Create post
        $metaValues = array(
            '_thumbnail_id' => $data['_thumbnail_id'],
            '_visibility' => 'visible',
            '_stock_status' => $data['_stock_status'],
            'total_sales' => '0',
            '_downloadable' => 'no',
            '_variation_description' => $data['_variation_description'],
            '_virtual' => 'no',
            '_regular_price' => $data['_price'],
            '_sale_price' => "",
            '_purchase_note' => "",
            '_featured' => "no",
            '_weight' => "",
            '_length' => "",
            '_width' => "",
            '_height' => "",
            '_sku' => $data['_sku'],
            '_product_attributes' => array(),
            '_sale_price_dates_from' => "",
            '_sale_price_dates_to' => "",
            '_price' => $data['_price'],
            '_sold_individually' => "",
            '_manage_stock' => $data['_manage_stock'],
            '_backorders' => "no",
            '_stock' => $data['_stock'],
            '_importedfromlagerkoll' => true,
            '_lagerkollartid' => $data['_lagerkollartid'],
        );


        foreach ($metaValues as $metaKey => $metaValue) {
            update_post_meta($post_id, $metaKey, $metaValue);
        }

        self::updateTerms($post_id, $data['terms']);

        return $post_id;


    }

    public static function updateTerms($post_id, $termsToChange, $append = true) {
        foreach ($termsToChange as $tax => $terms) {

            $termsIds = array();
            foreach ($terms as $term) {
                $tempTermObject = get_term_by('name', $term, $tax, ARRAY_A);

                if (empty($tempTermObject)) {
                    $tempTermObject = wp_insert_term($term, $tax, $args = array());
                }

                $termsIds[] = intval($tempTermObject['term_id']);
            }
            wp_set_object_terms($post_id, $termsIds, $tax, $append);

            /*           logIt(
                           array(
                               $post_id,
                               $termsIds,
                               'append' => $append,
                           ),
                           'uppadeteradeTerms'
                       );*/
        }
    }

    public function createParentProduct($sku, $orgSku, $title, $price = 0, $description = "", $shortDescription = "", $thumbnail = 0, $categories = array(), $images = array(), $isSimple = false) {
        $data = array(
            'title' => $title,              // Titel på produkten
            'post_type' => "product",       //"product" eller "product_variation"
            'post_parent' => false,
            'post_content' => $description,
            'post_excerpt' => $shortDescription,
            '_thumbnail_id' => $thumbnail,               //Id på thumbnail
            '_stock_status' => "instock",              //'instock', eller 'outofstock'
            //'_price'          => $price,              //normalt pris
            '_sku' => $sku,                       //artikelnr
            '_manage_stock' => "no",        //'no' 'yes'
            '_stock' => "",
            'terms' => array(
                'product_cat' => $categories,
                'product_type' => array('variable'),
            ),
            '_lagerkollartid' => $orgSku
            //'_variation_description' => '',
        );

        if ($isSimple) {
            unset($data['terms']['product_type']);
            $data['_price'] = $price;
        }


        $productId = self::createProduct($data);

        self::handleImageUppload($images, $productId);


        return $productId;

    }

    public function createVariationProduct($sku, $parent, $title, $price = 0, $thumbnail = 0, $nrOfStock = 0, $variantDescription, $images = array()) {
        $data = array(
            'title' => $title,              // Titel på produkten
            'post_type' => "product_variation",       //"product" eller "product_variation"
            'post_parent' => $parent,
            'post_content' => "",
            'post_excerpt' => "",
            '_thumbnail_id' => $thumbnail,               //Id på thumbnail
            '_stock_status' => $nrOfStock <= 0 ? "outofstock"
                : "instock",              //'instock', eller 'outofstock'
            '_price' => $price,              //normalt pris
            '_sku' => $sku,                       //artikelnr
            '_manage_stock' => "yes",        //'no' 'yes'
            '_stock' => $nrOfStock,
            'terms' => array(),
            '_variation_description' => $variantDescription,
            '_lagerkollartid' => $sku,
        );

        $productId = self::createProduct($data);

        self::handleImageUppload($images, $productId);

        self::updateParentsVariations($parent);

        return $productId;
    }

    public function updateVariationProduct($productId, $sku, $parent, $title, $price = 0, $thumbnail = 0, $nrOfStock = 0, $variantDescription, $images = array()) {
        $data = array(
            'title' => $title,              // Titel på produkten
            'post_type' => "product_variation",       //"product" eller "product_variation"
            'post_parent' => $parent,
            'post_content' => "",
            'post_excerpt' => "",
            '_thumbnail_id' => $thumbnail,               //Id på thumbnail
            '_stock_status' => $nrOfStock <= 0 ? "outofstock"
                : "instock",              //'instock', eller 'outofstock'
            '_price' => $price,              //normalt pris
            '_sku' => $sku,                       //artikelnr
            '_manage_stock' => "yes",        //'no' 'yes'
            '_stock' => $nrOfStock,
            'terms' => array(),
            '_variation_description' => $variantDescription,
            '_lagerkollartid' => $sku,
        );

        $productId = self::createProduct($data);

        self::handleImageUppload($images, $productId);

        self::updateParentsVariations($parent);

        return $productId;
    }

    public static function setAttributesForVariation($productId, $output = false) {

        $sku = get_post_meta($productId, '_sku', true);
        $ajlajkProd = \ajlajk\ajlajkInterperter::getProductInformationFromSku($sku);


        if ($ajlajkProd['color']) {

            $tempTermObject = get_term_by('name', $ajlajkProd['colorReadable'], 'pa_farg', ARRAY_A);
            update_post_meta($productId, "attribute_pa_farg", $tempTermObject['slug']);

        }

        if ($ajlajkProd['size']) {

            $tempTermObject = get_term_by('name', $ajlajkProd['sizeReadable'], 'pa_storlek', ARRAY_A);
            update_post_meta($productId, "attribute_pa_storlek", $tempTermObject['slug']);

        }


    }

    public function updateParentsVariations($parentID, $updateSub = false, $output = false) {
        wc_delete_product_transients($parentID);
        $children = get_children(
            array(
                'post_parent' => $parentID,
                'post_type' => 'product_variation',
                'numberposts' => -1,
                'post_status' => 'any',
            )
        );

        $terms = array(
            'pa_farg' => array(),
            'pa_storlek' => array(),
        );


        foreach ($children as $child) {
            wc_delete_product_transients($child->ID);
            $sku = get_post_meta($child->ID, '_sku', true);
            $ajlajkProd = \ajlajk\ajlajkInterperter::getProductInformationFromSku($sku);
            $outputText = $sku;


            if ($ajlajkProd['color']) {
                $terms['pa_farg'][] = $ajlajkProd['colorReadable'];
                $outputText .= " - " . $ajlajkProd['colorReadable'];
            }

            if ($ajlajkProd['size']) {
                $terms['pa_storlek'][] = $ajlajkProd['sizeReadable'];
                $outputText .= " - " . $ajlajkProd['sizeReadable'];
            }

            if ($output) {
                echo $outputText . "<br>";
            }

            if ($updateSub) {
                self::setAttributesForVariation($child->ID, $output);
            }


        }


        $attributes = array();

        if (count($terms['pa_storlek']) > 0) {
            $attributes['pa_storlek'] = Array(
                'name' => 'pa_storlek',
                'value' => '',
                'position' => 0,
                'is_visible' => '1',
                'is_variation' => '1',
                'is_taxonomy' => '1',
            );
        }
        if (count($terms['pa_farg']) > 0) {
            $attributes['pa_farg'] = Array(
                'name' => 'pa_farg',
                'value' => '',
                'position' => 0,
                'is_visible' => '1',
                'is_variation' => '1',
                'is_taxonomy' => '1',
            );
        }


        update_post_meta($parentID, '_product_attributes', $attributes);

        $defaultAttributes = array();


        if (count($terms['pa_storlek']) > 0) {

            $tempTermObject = get_term_by('name', $terms['pa_storlek'][0], 'pa_farg', ARRAY_A);
            $defaultAttributes['pa_storlek'] = $tempTermObject['slug'];
        }
        if (count($terms['pa_farg']) > 0) {
            $tempTermObject = get_term_by('name', $terms['pa_farg'][0], 'pa_farg', ARRAY_A);
            $defaultAttributes['pa_farg'] = $tempTermObject['slug'];

        }


        update_post_meta($parentID, '_default_attributes', $defaultAttributes);

        self::updateTerms($parentID, $terms);


        WC_Product_Variable::sync($parentID);

    }

    public static function handleImageUppload($images, $productId) {

        // add the function above to catch the attachments creation

        add_action(
            'add_attachment',
            function ($att_id) {
                lkPlugin::fixImages($att_id);
            }
        );


        foreach ($images as $imageName => $imageUrl) {

            $nameToSave = $filename = pathinfo($imageName, PATHINFO_FILENAME);
            $nameToSave = strtolower($nameToSave);


            $att_id = self::wp_get_attachment_by_file_name($nameToSave);
            if (!$att_id) {
                $image = \media_sideload_image($imageUrl, $productId, $nameToSave);
            } else {
                self::fixImages($att_id, $productId);
            }
        }


        // we have the Image now, and the function above will have fired too setting the thumbnail ID in the process, so lets remove the hook so we don't cause any more trouble
        remove_all_actions('add_attachment');
    }

    public static function fixImages($att_id, $productId = false) {
        // the post this was sideloaded into is the attachments parent!
        $attatchmentPost = get_post($att_id);
        if (!$productId) {
            $productId = $attatchmentPost->post_parent;
        }
        $product = get_post($productId);
        $parentId = $product->post_parent;
        if (empty($parentId)) {
            $parentId = $productId;
        }


        $thumbnail = intval(get_post_meta($productId, '_thumbnail_id', true));
        if (empty($thumbnail)) {
            update_post_meta($productId, '_thumbnail_id', $att_id);
        }


        $gallery = get_post_meta($parentId, '_product_image_gallery', true);

        $gallery = explode(',', $gallery);
        $gallery[] = $att_id;
        $gallery = array_unique($gallery);
        $gallery = implode(',', $gallery);

        update_post_meta($parentId, '_product_image_gallery', $gallery);


        $thumbnail = intval(get_post_meta($parentId, '_thumbnail_id', true));
        if (empty($thumbnail)) {
            update_post_meta($parentId, '_thumbnail_id', $att_id);
        }
    }

    public static function import__success() {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Produkten importerades!', 'sample-text-domain'); ?></p>
        </div>
        <?php
    }

    public static function findLocalImages($sku) {

        $productInfo = ajlajk\ajlajkInterperter::getProductInformationFromSku($sku);

        $replacer = array(
            "SPIN" => "S.PIN",
            "SKITT" => "S.KITT",
            "SNAV" => "S.NAV",
        );


        if ($productInfo['skuIsClean']) {
            $imageVariationsExactMatch = array(
                $sku,
            );
        } else {

            if (empty($productInfo['color'])) {
                return array();
            }

            if (array_key_exists($productInfo['color'], $replacer)) {
                $productInfo['color'] = $replacer[$productInfo['color']];
            }


            $imageVariationsExactMatch = array(
                $productInfo['cleanSku'] . "-" . $productInfo['color'],
                $productInfo['cleanSku'] . "_" . $productInfo['color'],
                $productInfo['cleanSku'] . "." . $productInfo['color'],
                $productInfo['cleanSku'] . " " . $productInfo['color'],
            );
        }


        $imageFolder = "/home/ajlajknu/public_html/imagesToImport9988776688559977/images";
        $imageBaseUrl = "http://www.ajlajk.nu.hemsida.eu/imagesToImport9988776688559977/images/";

        $scannedDirectory = self::scandir($imageFolder, null, -1);


        $scannedToReturn = array();

        foreach ($scannedDirectory as $imgRelativ => $imgAbsolute) {


            $imgTempName = explode("/", $imgRelativ);
            $ImageName = end($imgTempName);


            foreach ($imageVariationsExactMatch as $imageVariationExactMatch) {
                $startPos = strpos(strtoupper($ImageName), strtoupper($imageVariationExactMatch));
                if (is_numeric($startPos) && $startPos == 0) {


                    $scannedToReturn[$ImageName] = $imageBaseUrl . self::urlEncode($imgRelativ);
                }
            }
        }


        return $scannedToReturn;

    }

    public static function findImages($sku) {
        return self::findLocalImages($sku);


        $baseURl = "http://www.ajlajk.nu/media/com_eshop/products/";


        $ajlajkProd = \ajlajk\ajlajkInterperter::getProductInformationFromSku($sku);
        $skuNoSize = $ajlajkProd['cleanSku'] . '_' . $ajlajkProd['color'];
        $skuNoSize = strtoupper($skuNoSize);
        $skuSize = $skuNoSize . '_' . $ajlajkProd['size'];
        $skuSize = strtoupper($skuSize);

        $replacer = array(
            "SPIN" => "S.PIN",
            "SKITT" => "S.KITT",
            "SNAV" => "S.NAV",
        );

        foreach ($replacer as $find => $replace) {
            $skuNoSize = str_replace($find, $replace, $skuNoSize);
            $skuSize = str_replace($find, $replace, $skuSize);
        }

        $imagesToTry = array(
            ".jpg",
            "_1.jpg",
            "_2.jpg",
            "_3.jpg",
            "_4.jpg",
            "_5.jpg",
            "_6.jpg",
            "_7.jpg",
            "_8.jpg",
        );

        $imagesFound = array();

        foreach ($imagesToTry as $image) {

            $toCheck = array(
                $skuNoSize . $image => $baseURl . $skuNoSize . $image,
                $skuSize . $image => $baseURl . $skuSize . $image,

            );

            foreach ($toCheck as $imageName => $imageUrl) {

                $headers = get_headers($imageUrl, 1);
                if (is_numeric(strpos($headers[0], "200"))) {
                    $imagesFound[$imageName] = $imageUrl;
                }

            }


        }

        return $imagesFound;


    }

    public static function wp_get_attachment_by_file_name($post_name) {
        $args = array(
            'post_per_page' => 1,
            'post_type' => 'attachment',
            'title' => trim($post_name),
            'post_status' => 'any',
        );


        $get_posts = new Wp_Query($args);

        if (!isset($get_posts->posts[0])) {
            return false;
        }

        if ($get_posts->posts[0]) {
            return $get_posts->posts[0]->ID;
        } else {
            return false;
        }
    }

    public static function displayInfo($childs = array()) {


        $childsData = array();


        foreach ($childs as $child) {

            $childsData[$child] = array(
                post => get_post($child),
                meta => get_post_meta($child),
            );
        }

        echo "<table>";


        echo "<tr><td>ID</td>";
        foreach ($childs as $child) {
            ?>
            <td><?= $childsData[$child]['post']->ID ?></td><?php
        }
        echo "</tr>";

        echo "<tr><td>post_title<br>post_name</td>";
        foreach ($childs as $child) {
            ?>
            <td><?= $childsData[$child]['post']->post_title ?></td><?php
        }
        echo "</tr>";

        echo "<tr><td>excerpt</td>";
        foreach ($childs as $child) {
            ?>
            <td><?= get_the_excerpt($child) ?></td><?php
        }
        echo "</tr>";

        echo "<tr><td>post_parent</td>";
        foreach ($childs as $child) {
            ?>
            <td><?= $childsData[$child]['post']->post_parent ?></td><?php
        }
        echo "</tr>";

        echo "<tr><td style='border-bottom: 1px solid black;'>post_type</td>";
        foreach ($childs as $child) {
            ?>
            <td style='border-bottom: 1px solid black;'><?= $childsData[$child]['post']->post_type ?></td><?php
        }
        echo "</tr>";


        foreach ($childsData[$childs[0]]['meta'] as $index => $metaValue) {
            if ($index == "_product_attributes") {
                continue;
            }
            echo "<tr>";
            echo " <td>$index</td>";
            foreach ($childs as $child) {
                echo "<td>";
                echo $childsData[$child]['meta'][$index][0];
                echo "</td>";
            }
            echo "</tr>";
        }


        echo "<tr><td style='border-top: 1px solid black;'>_product_attributes</td>";
        foreach ($childs as $child) {
            ?>
            <td style='border-top: 1px solid black;'>
            <pre>
            <?= print_r(get_post_meta($child, '_product_attributes'), true) ?>
            </pre>

            </td><?php
        }
        echo "</tr>";
        echo "<tr><td style='border-top: 1px solid black;'>product_cat</td>";
        foreach ($childs as $child) {
            ?>
            <td style='border-top: 1px solid black;'>
            <pre>
            <?= print_r(wp_get_object_terms($child, 'product_cat'), true) ?>
            </pre>

            </td><?php
        }
        echo "</tr>";

        echo "<tr><td>product_type</td>";
        foreach ($childs as $child) {
            ?>
            <td>
            <pre>
            <?= print_r(wp_get_object_terms($child, 'product_type'), true) ?>
            </pre>

            </td><?php
        }
        echo "</tr>";

        echo "<tr><td>pa_storlek</td>";
        foreach ($childs as $child) {
            ?>
            <td>
            <pre>
            <?= print_r(wp_get_object_terms($child, 'pa_storlek'), true) ?>
            </pre>

            </td><?php
        }
        echo "</tr>";

        echo "<tr><td>pa_farg</td>";
        foreach ($childs as $child) {
            ?>
            <td>
            <pre>
            <?= print_r(wp_get_object_terms($child, 'pa_farg'), true) ?>
            </pre>

            </td><?php
        }
        echo "</tr>";

        echo "</table>";
    }

    public static function importAll() {
        $pageNr = isset($_GET['pagenr']) ? $_GET['pagenr'] : 0;
        $perPage = 10;


        require_once __DIR__ . '/../../lagerkoll_php/server/DataManager.php';
        $dm = new \Lagerkoll\DataManager();

        $lagerkollArticles = $dm->getArticles(
            "",
            0,
            $pageNr,
            $perPage,
            "asc"
        );

        $result = $lagerkollArticles->articles;
        $result = json_decode(json_encode($result), true);

        if (empty($result)) {

            echo "klart<pre>";
            die;
        }

        //echo "<pre>";


        echo "<h3>Importerar artiklar</h3>";
        echo "<p>" . ($pageNr * $perPage) . " till " . (($pageNr + 1) * $perPage) . "</p>";
        echo "<p> av " . $lagerkollArticles->articlesCount . " totalt</p><hr>";

        foreach ($result as $item) {
            $status = self::handleImport($item['articleId']);
            if ($status) {
                echo $item['articleId'] . " importerades<br> ";
            } else {
                echo "<b>" . $item['articleId'] . " importerades inte<br></b> ";
            }
        }


        $url = sprintf(
            '?page=%s&action=importAll&pagenr=%s',
            esc_attr($_REQUEST['page']),
            ++$pageNr
        );

        if ($pageNr < 500) {
            ?>
            <script type="text/javascript">
                setTimeout(function () {
                    location.href = "<?=$url?>"
                }, 1000)
            </script>
            <?php

        }

        die;


    }

    public static function updateAllImages() {
        $pageNr = isset($_GET['pagenr']) ? $_GET['pagenr'] : 1;
        $perPage = 10;


        $args = array(
            'post_type' => array('product', 'product_variation'),
            'posts_per_page' => $perPage,
            'paged' => $pageNr,
        );
        $query = new WP_Query($args);

        //        echo "<pre>";
        //       var_dump($query->posts);


        if (empty($query->posts)) {

            echo "klart<pre>";
            die;
        }


        echo "<h3>Uppdaterar bilder</h3>";
        echo "<p>" . ($pageNr * $perPage) . " till " . (($pageNr + 1) * $perPage) . "</p>";
        echo "<hr>";

        foreach ($query->posts as $item) {
            $sku = get_post_meta($item->ID, '_sku', true);
            self::updateImages($sku);
        }


        $url = sprintf(
            '?page=%s&action=updateallimages&pagenr=%s',
            esc_attr($_REQUEST['page']),
            ++$pageNr
        );

        if ($pageNr < 500) {
            ?>
            <script type="text/javascript">
                setTimeout(function () {
                    location.href = "<?=$url?>"
                }, 1000)
            </script>
            <?php

        }

        die;


    }

    public static function updateAllStock() {
        $pageNr = isset($_GET['pagenr']) ? $_GET['pagenr'] : 1;
        $perPage = 10;


        $args = array(
            'post_type' => array('product'),
            'posts_per_page' => $perPage,
            'paged' => $pageNr,
        );
        $query = new WP_Query($args);

        //        echo "<pre>";
        //       var_dump($query->posts);


        if (empty($query->posts)) {

            echo "klart<pre>";
            die;
        }


        echo "<h3>Uppdaterar lagerstatus texter</h3>";
        echo "<p>" . ($pageNr * $perPage) . " till " . (($pageNr + 1) * $perPage) . "</p>";
        echo "<hr>";

        foreach ($query->posts as $item) {
            WC_Product_Variable::sync_stock_status($item->ID);
            WC_Product_Variable::sync($item->ID);

        }


        $url = sprintf(
            '?page=%s&action=updateallstock&pagenr=%s',
            esc_attr($_REQUEST['page']),
            ++$pageNr
        );

        if ($pageNr < 500) {
            ?>
            <script type="text/javascript">
                setTimeout(function () {
                    location.href = "<?=$url?>"
                }, 1000)
            </script>
            <?php

        }

        die;


    }


    public static function checkIfExits($sku) {

        $ajlajkProd = \ajlajk\ajlajkInterperter::getProductInformationFromSku($sku);
        $productWholeSkuId = wc_get_product_id_by_sku($ajlajkProd['sku']);


        if (empty($productWholeSkuId)) {

            return false;
        } else {
            return true;
        }
    }

    public static function updateProductFromLagerkoll($sku, $create = false) {


        require_once __DIR__ . '/../../lagerkoll_php/server/DataManager.php';
        $dm = new \Lagerkoll\DataManager();

        //        $originalItem = "A272-KIDS-SGR";

        $ajlajkProd = \ajlajk\ajlajkInterperter::getProductInformationFromSku($sku);
        $productWholeSkuId = wc_get_product_id_by_sku($ajlajkProd['sku']);


        if (empty($productWholeSkuId)) {
            /* logIt(
                 array(
                     $ajlajkProd,
                     $productWholeSkuId,
                     'create' => $create,
                 ),
                 'saknades-' . $sku
             );*/

            self::handleImport($sku);

            return;
        }


        $productWholeSku = wc_get_product($productWholeSkuId);


        try {
            $productWholeSkuLagerkoll = $dm->getArticle($ajlajkProd['sku'], true);
        } catch (Exception $e) {
            $productWholeSkuLagerkoll = false;
        }


        // radera om ej i ws längre

        if (!$productWholeSkuLagerkoll->webshopArticle && !$productWholeSku->is_type('variable')) {


            $parent = $productWholeSku->get_parent();

            self::putInThrash($sku);
            wc_delete_product_transients($productWholeSkuId);

            if ($parent) {
                WC_Product_Variable::sync($parent);
                WC_Product_Variable::sync_stock_status($parent);
                //WC_Product_Variable::sync_attributes($parent);
                $tempProd = new WC_Product_Variable($parent);
                $tempProd->variable_product_sync();
                wc_delete_product_transients($parent);
                self::updateParentsVariations($parent);


                $variations = $tempProd->get_available_variations();
                if (empty($variations)) {
                    wp_delete_post($parent, true);
                }

            }

            return;
        }


        $categories = explode(',', $productWholeSkuLagerkoll->category);
        foreach ($categories as $index => $category) {
            $category = trim($category);
            $category = strtolower($category);
            $category = lcfirst($category);
            if (empty($category)) {
                unset($categories[$index]);
            } else {
                $categories[$index] = $category;
            }
        }
        $categories = array(
            'product_cat' => $categories,
        );

        $productPrice = 0;
        try {

            $pricelist = \Lagerkoll\Settings::PRICELIST;
            $allProductPrices = $dm->getPricesInPriceListForArticle($pricelist, $ajlajkProd['sku']);
            $productPrice = $allProductPrices->prices[0]->price;
        } catch (Exception $e) {
        }


        if (empty($productPrice)) {
            $productPrice = $productWholeSkuLagerkoll->price;
        }
        $productPrice = intval($productPrice);


        $post = array(
            'ID' => $productWholeSkuId,
            'post_content' => $productWholeSkuLagerkoll->description,
            'post_status' => "publish",
            'post_excerpt' => $productWholeSkuLagerkoll->description,
        );


        wp_update_post($post);

        $salePrice = get_post_meta($productWholeSkuId, '_sale_price', true);
        if (!$salePrice) {
            $salePrice = $productPrice;
        }


        /**
         * Funktion för att skicka out of stock meddelande om vi
         * updaterar från lagerkoll och antalet är mindre eller
         * lika med noll och har varit större
         */
        $oldStock = intval(get_post_meta($productWholeSkuId, '_stock', true));
        $newStock = intval($productWholeSkuLagerkoll->amount);
        if ($newStock != $oldStock && !($oldStock <= 0) && $newStock <= 0) {
            $wcEmails = new WC_Emails();
            $wcEmails->no_stock($productWholeSku);
        }

        //Create post
        $metaValues = array(
            '_variation_description' => $productWholeSkuLagerkoll->description,
            '_price' => $salePrice,
            '_regular_price' => $productPrice,
            '_stock' => $productWholeSkuLagerkoll->amount,
            //'fix_after_lagerkoll'    => 1,
        );


        foreach ($metaValues as $metaKey => $metaValue) {
            update_post_meta($productWholeSkuId, $metaKey, $metaValue);
        }

        self::updateTerms($productWholeSkuId, $categories);


        $productWholeSku->set_stock($productWholeSkuLagerkoll->amount);

        wc_delete_product_transients($productWholeSkuId);

        $parent = $productWholeSku->get_parent();
        if ($parent) {

            self::updateTerms($parent, $categories);

            WC_Product_Variable::sync($parent);
            WC_Product_Variable::sync_stock_status($parent);
            //WC_Product_Variable::sync_attributes($parent);
            $tempProd = new WC_Product_Variable($parent);
            $tempProd->variable_product_sync();
            wc_delete_product_transients($parent);

            //Create post
            $metaValues = array(//'fix_after_lagerkoll' => 1,
            );


            foreach ($metaValues as $metaKey => $metaValue) {
                update_post_meta($parent, $metaKey, $metaValue);
            }


        }


        do_action('lagerkoll_update_post', $productWholeSkuId);


    }

    public static function putInThrash($sku) {
        $ajlajkProd = \ajlajk\ajlajkInterperter::getProductInformationFromSku($sku);
        $productWholeSkuId = wc_get_product_id_by_sku($ajlajkProd['sku']);

        if ($productWholeSkuId) {
            //$productToTrash = new WC_Product($productWholeSkuId);
            //$parent = $productToTrash->get_parent();

            wp_delete_post($productWholeSkuId, true);

            /*if(!empty($parent)) {
                $parent = new WC_Product_Variable($parent);
                $variations = $parent->get_available_variations();
                if(empty($variations)) {
                    self::putInThrash($parent);
                }
            }*/
        }
    }

    /**
     * Scans a directory for files of a certain extension.
     *
     * @since  3.4.0
     *
     * @static
     * @access private
     *
     * @param string $path Absolute path to search.
     * @param array|string|null $extensions Optional. Array of extensions to find, string of a single extension,
     *                                         or null for all extensions. Default null.
     * @param int $depth Optional. How many levels deep to search for files. Accepts 0, 1+, or
     *                                         -1 (infinite depth). Default 0.
     * @param string $relative_path Optional. The basename of the absolute path. Used to control the
     *                                         returned path for the found files, particularly when this function
     *                                         recurses to lower depths. Default empty.
     *
     * @return array|false Array of files, keyed by the path to the file relative to the `$path` directory prepended
     *                     with `$relative_path`, with the values being absolute paths. False otherwise.
     */
    private static function scandir($path, $extensions = null, $depth = 0, $relative_path = '') {
        if (!is_dir($path)) {
            return false;
        }

        if ($extensions) {
            $extensions = (array)$extensions;
            $_extensions = implode('|', $extensions);
        }

        $relative_path = trailingslashit($relative_path);
        if ('/' == $relative_path) {
            $relative_path = '';
        }

        $results = scandir($path);

        $files = array();

        foreach ($results as $result) {
            if ('.' == $result[0]) {
                continue;
            }

            if (is_dir($path . '/' . $result)) {

                if (!$depth || 'CVS' == $result) {
                    continue;
                }
                $found = self::scandir($path . '/' . $result, $extensions, $depth - 1, $relative_path . $result);
                $files = array_merge_recursive($files, $found);
            } elseif (!$extensions || preg_match('~\.(' . $_extensions . ')$~', $result)) {
                $files[$relative_path . $result] = $path . '/' . $result;
            }
        }

        return $files;
    }


    public static function urlEncode($string) {

        if (is_numeric(strpos($string, "/"))) {
            $strArray = explode("/", $string);
        } else {
            $strArray = array($string);
        }

        $entities = array('+');
        $replacements = array('%20');

        foreach ($strArray as $index => $item) {
            $strArray[$index] = str_replace($entities, $replacements, urlencode($item));
        }

        return implode("/", $strArray);
    }

    public static function orderChangedWooHook($orderId, $oldStatus, $newStatus) {
        /*$orderToSendTemplate = array(
            //"orderId"             => 12,
            "externalOrderId"     => null, //Webshop ORDER ID
            //"storeGroupId"        => 192005,
            "customerId"          => 1,
            //"customerOrderNumber" => null,
            "state"               => 0,
            //"administrationFee"   => 0.0,
            "currency"            => "SEK",
            "currencyRate"        => 1.0,
            //"comments"            => null,
            "deliveryAddress1"    => "Kustvägen 12",
            "deliveryAddress2"    => null,
            "deliveryZipCode"     => "21119",
            "deliveryCity"        => "Malmö",
            "deliveryCountry"     => "SE",
            "deliveryDate"        => 1469172400193,
            "deliveryName"        => null,
            "freight"             => 0.0,
            //"orderDate"           => 1468567600193,
            "ourReference"        => "Webshop ORDER ID",
            //"phone1"              => null,
            //"phone2"              => null,
            //"remarks"             => null,
            //"deliveryTerm"        => null,
            //"paymentTerm"         => null,
            //"vatIncluded"         => true,
            //"wayOfDelivery"       => null,
            //"yourReference"       => null,
            "products"            => array(),
            "pricelistId"         => 1,
        );

        $orderToSendTemplate['products'] = array(
            //"storeId"         => 186008,
            //"storeGroupId"    => 192005,
            "articleId"       => "192005-37",
            "orderedAmount"   => 3.0,
            "deliveredAmount" => 0.0,
            //"piecePrice"      => 27.5,
            //"totalPrice"      => 82.5,
            //"discountType"    => "PERCENT",
            //"discount"        => 0.0,
            //"description"     => null,
        );*/

        $status = array(
            'pending' => 0,              // Order received (unpaid)
            'processing' => 10,           // Payment received and stock has been reduced- the order is awaiting fulfilment
            'on-hold' => 0,              //Awaiting payment – stock is reduced, but you need to confirm payment
            'completed' => false,            //Order fulfilled and complete – requires no further action
            'cancelled' => false,            //Cancelled by an admin or the customer – no further action required
            'refunded' => false,             //Refunded by an admin – no further action required
            'failed' => false                //Payment failed or was declined (unpaid)
        );

        $state = $status[$newStatus];

        //echo "state: $state <br>";

        $sentToLagerkoll = get_post_meta($orderId, '_sentTolagerkoll', true);

        $order = new WC_Order($orderId);


        if (!$sentToLagerkoll && is_numeric($state)) {
            //echo "skall skicka<br>";


            $products_raw = $order->get_items();
            $customer_raw = $order->get_address('shipping');
            //$shipping_raw = $order->get_total_shipping();
            $shipping_raw = floatval($order->get_total_shipping()) + floatval($order->get_shipping_tax());


            $orderToSend = array(
                "externalOrderId" => $order->id,
                "yourReference" => $order->id,
                "customerId" => 77030,  //Hårdkodat för att fungera på alla ställen
                "state" => $state,
                "currency" => "SEK",
                "currencyRate" => 1.0,
                //"comments"            => null,
                "deliveryAddress1" => $customer_raw['address_1'],
                "deliveryAddress2" => $customer_raw['address_2'],
                "deliveryZipCode" => $customer_raw['postcode'],
                "deliveryCity" => $customer_raw['city'],
                "deliveryCountry" => $customer_raw['country'],
                "deliveryName" => $customer_raw['first_name'] . " " . $customer_raw['last_name'],
                "freight" => $shipping_raw,
                "deliveryDate" => strtotime(date('Y-m-d')) . "000",
                "ourReference" => "Webshop: " . $order->id,
                "products" => array(),
                //"pricelistId"      => 345,
            );

            foreach ($products_raw as $index => $product) {


                $sku = $product['variation_id'];
                $prodid = self::getSkuFromID($product['variation_id']);
                if (empty($prodid)) {

                    $sku = $product['product_id'];
                    $prodid = self::getSkuFromID($product['product_id']);
                }


                $origProd = wc_get_product($sku);
                $regularPrice = intval($origProd->get_regular_price()) * $product['qty'];
                //$takenPrice   = $product['item_meta']['_line_subtotal'][0] + $product['item_meta']['_line_tax'][0];
                $takenPrice = $product['subtotal'] + $product['subtotal_tax'];

                $newRow = array(
                    //"storeId"         => 186008,
                    //"storeGroupId"    => 192005,
                    "articleId" => $prodid,
                    "orderedAmount" => $product['qty'],
                    "deliveredAmount" => 0.0,
                    "piecePrice" => $regularPrice,
                    "totalPrice" => $takenPrice * $product['qty'],
                    //"discountType"    => "AMOUNT",
                    //"discount"        => 0.0,
                    /* "description"     => array(
                         $product,
                         $sku,
                         $origProd,
                         $regularPrice,
                         $takenPrice
                     ),*/
                );


                if ($regularPrice != $takenPrice && $regularPrice > $takenPrice) {
                    $discounted = $regularPrice - $takenPrice;

                    $newRow['discountType'] = "AMOUNT";
                    $newRow['discount'] = $discounted;


                }


                $orderToSend['products'][] = $newRow;

            }

            //            echo json_encode($orderToSend);die;


            require_once __DIR__ . '/../../lagerkoll_php/server/DataManager.php';
            $dm = new \Lagerkoll\DataManager();


            try {
                $response = $dm->addCustomerOrder($orderToSend);
                $order->add_order_note('Order Skapad i lagekoll');
                //var_dump($response);
            } catch (Exception $e) {

                $order->add_order_note('Order kunde ej skapas i lagerkoll');
                throw $e;
                //die;
                //$response = false;
            }

            if ($response) {
                update_post_meta($orderId, '_sentTolagerkoll', true);
                update_post_meta($orderId, '_sentTolagerkollState', $state);
                update_post_meta($orderId, '_lagekollOrderId', $response->orderId);
            }


        } elseif ($sentToLagerkoll) {

            //echo "redan skickad<br>";


            $oldState = get_post_meta($orderId, '_sentTolagerkollState', true);


            //            $order->add_order_note('redan skickad (' . $oldState . " " . $state . ")");

            if ($oldState != $state) {
                $lagerKollOrderId = get_post_meta($orderId, '_lagekollOrderId', true);

                require_once __DIR__ . '/../../lagerkoll_php/server/DataManager.php';
                $dm = new \Lagerkoll\DataManager();

                if ($oldState == 0 && $state == 10) {

                    //echo "Märk för packning<br>";

                    $checkState = self::getLagerKollOrderState($lagerKollOrderId);

                    if ($checkState == 0) {
                        $order->add_order_note('Lagerkoll order uppdaterad Ok att packa');
                        $dm->CustomerOrderReadyForPacking($lagerKollOrderId);
                    }

                }

                $statusToAbort = array(
                    'cancelled',
                    'refunded',
                    'failed',
                );
                if (in_array($newStatus, $statusToAbort)) {

                    //echo "avbryt<br>";


                    $checkState = self::getLagerKollOrderState($lagerKollOrderId);

                    if ($checkState < 50) {
                        $order->add_order_note('Lagerkoll order Makulerad');
                        $dm->CustomerOrderCancel($lagerKollOrderId);
                    }

                }

                if ($newStatus == "completed") {

                    //echo "fakturera<br>";

                    //TODO lägg till koll på faktura eller inte


                    $checkState = self::getLagerKollOrderState($lagerKollOrderId);

                    if ($checkState != 50) {
                        //$order->add_order_note('Lagerkoll order fakturerad');

                        //$dm->CustomerOrderInvoice($lagerKollOrderId);
                    }
                }

                update_post_meta($orderId, '_sentTolagerkollState', $state);
            }


        } else {

        }

        //        var_dump($orderId);

        logIt(array($orderId, $oldStatus, $newStatus, $order), "order");


    }

    public static function getSkuFromID($ID) {
        return get_post_meta($ID, '_sku', true);
    }

    public static function getLagerKollOrderState($lagerKollOrderId) {
        require_once __DIR__ . '/../../lagerkoll_php/server/DataManager.php';
        $dm = new \Lagerkoll\DataManager();

        $order = $dm->getCustomerOrder($lagerKollOrderId);

        return $order->state;
    }


    public static function LagerkollOrderIDToWooStatus($lkOrderId) {
        require_once __DIR__ . '/../../lagerkoll_php/server/DataManager.php';
        $dm = new \Lagerkoll\DataManager();

        $lkOrder = $dm->getCustomerOrder($lkOrderId);
        $wcOrderId = $lkOrder->yourReference;
        $wcOrder = new WC_Order($wcOrderId);

        logIt(
            array(
                '$lkOrderId' => $lkOrderId,
                '$lkOrder' => $lkOrder,
                '$wcOrderId' => $wcOrderId,
                '$wcOrder' => $wcOrder,

            ),
            "updateOrderCallback"
        );

        if ($wcOrder) {
            $wcState = $wcOrder->get_status();


            update_post_meta($wcOrderId, '_sentTolagerkollState', $lkOrder->state);

            switch ($lkOrder->state) {
                /*case 0:     // new
                    break;*/
                /*            case 5:
                                break;*/
                /*            case 10:    // PACKING
                                break;*/
                /*case 15:    // DROP-shipment
                    break;*/
                /*case 20:    // PACKED
                    break;*/
                /*            case 30:    // PARTLY SENT
                                break;*/
                case 40:    // SENT
                    if ($wcState != "completed") {
                        $wcOrder->add_order_note('Order uppdaterad ifrån Lagerkoll');
                        $wcOrder->update_status('completed');
                    }

                    break;
                case 50:    // CANCELLED
                    if ($wcState != "cancelled") {
                        $wcOrder->add_order_note('Order uppdaterad ifrån Lagerkoll');
                        $wcOrder->update_status('cancelled');
                    }
                    break;
                /*case 60:    // INVOICED
                    break;*/

            }


        }

    }

    function updateProductsFromLagerkollCron() {

        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);

        $query = new WP_Query(
            array(
                //'post_type'      => array('product', 'product_variation'),
                'post_type' => array('product'),
                'posts_per_page' => 10,
                'meta_query' => array(
                    array(
                        'key' => 'fix_after_lagerkoll',
                        'value' => array(1),
                        'compare' => 'IN',
                    ),
                ),
            )
        );

        //        var_dump($query);


        foreach ($query->posts as $item) {


            $sku = get_post_meta($item->ID, '_sku', true);

            $prodItem = wc_get_product($item->ID);
            $prodType = $prodItem->product_type;


            echo $item->ID . " - $sku ($prodType)<br>";

            if ($prodType == "variable") {
                self::updateParentsVariations($item->ID, true, true);
                self::updateImages($sku, false);
            } elseif ($prodType == "simple") {
                self::updateSimpleImages($sku, false);
            }


            echo "<hr>";

            update_post_meta($item->ID, 'fix_after_lagerkoll', 0);

        }

        die;
    }

    //add column to orders
    public static function custom_shop_order_column($columns) {
        //add columns
        /*$columns['lagerkoll'] = "Lagerkoll";*/
        $columns['pacsoftny'] = "Frakthandling";

        return $columns;
    }

    public static function showLagerkollStatus($column) {
        global $the_order;
        $orderId = $the_order->id;

        switch ($column) {
            case 'lagerkoll' :

                $sent = get_post_meta($orderId, '_sentTolagerkoll', true);
                $state = get_post_meta($orderId, '_sentTolagerkollState', true);
                $lkOrderId = get_post_meta($orderId, '_lagekollOrderId', true);

                $states = array(
                    0 => "NY",
                    5 => "Klar att packas",
                    10 => "Packning påbörjad",
                    15 => "Sickas direkt från lev",
                    20 => "Packad",
                    30 => "Dellevererad",
                    40 => "Skickad",
                    50 => "Avbruten",
                    60 => "Bokförd",
                );


                if ($sent) {
                    echo '<a target="_blank" title="Status ' . $states[$state] . '" href="https://www.lagerkoll.com/admin/customer_order_status.jsp?order_id=' . $lkOrderId . '">' .
                        '<span class="dashicons-before dashicons-yes" style="color: green;">' . $states[$state] .
                        '</a>';

                } else {
                    $currentStatus = $the_order->get_status();

                    $okStatus = array(
                        'pending',              // Order received (unpaid)
                        'processing',           // Payment received and stock has been reduced- the order is awaiting fulfilment
                        'on-hold',
                    );

                    if (in_array($currentStatus, $okStatus)) {
                        echo '<a href="' . esc_url(
                                add_query_arg(
                                    array('post' => $orderId, 'action' => 'edit', 'sendtolagerkoll' => true,),
                                    admin_url('post.php')
                                )
                            ) . '" title="Skicka till lagerkoll nu"><span class="dashicons-before dashicons-no-alt"></a>';
                    } else {
                        //echo '<span title="Kan inte Skicka denna order." class="dashicons-before dashicons-no-alt">';
                    }

                }
                break;

            case 'klarnaid' :
                $klarnaOrderId = get_post_meta($orderId, '_klarna_order_id', true);

                echo $klarnaOrderId;

                break;

            case "pacsoftny":


                $pacsoftXmlGenerated = get_post_meta($orderId, 'pacsoftXmlGenerated', true);


                if ($pacsoftXmlGenerated) {
                    $pacsoftSendId = get_post_meta($orderId, 'pacsoftSendId', true);
                    $pacsoftReturnId = get_post_meta($orderId, 'pacsoftReturnId', true);

                    $deliveryMethod = get_post_meta($orderId, 'deliveryMethod', true);

                    if (empty($deliveryMethod)) {
                        $deliveryMethod = "PN";
                    }

                    if ($deliveryMethod == "PN") {
                        $trackingUrl = "https://tracking.postnord.com/?id=";
                    } else if ($deliveryMethod == "DHL") {
                        $trackingUrl = "http://www.dhl.se/sv/express/godssoekning.html?brand=DHL&AWB=";
                    }

                    if (empty($pacsoftReturnId) && empty($pacsoftSendId)) {
                        echo "Fraktsedlar begärda";
                    } else {
                        if ($pacsoftSendId) {
                            echo $deliveryMethod . ' Tur : <a target="blank" href="' . $trackingUrl . $pacsoftSendId . '">' . $pacsoftSendId . ' <span style="font-size:14px; line-height: 18px;" class="dashicons dashicons-external"></span></a><br>';
                        }

                        if ($pacsoftReturnId) {
                            echo $deliveryMethod . ' Retur : <a target="blank" href="' . $trackingUrl . $pacsoftReturnId . '">' . $pacsoftReturnId . ' <span style="font-size:14px; line-height: 18px;" class="dashicons dashicons-external"></span></a>';
                        }

                    }

                } else {

                    $templateExists = lkPlugin::pacsoftTemplate($orderId);
                    $orderStatus = $the_order->status;

                    if ($templateExists && $orderStatus !== "cancelled" && intval($orderId) > 23793) {
                        $generateXmlUrlForId = add_query_arg(
                            array(
                                'generatexml' => $orderId,
                            )
                        );
                        $generateXmlUrlForId2 = add_query_arg(
                            array(
                                'generatexml' => $orderId,
                                "dontautoprint" => true
                            )
                        );
                        echo '<a href="' . $generateXmlUrlForId . '" class="button" title="Generera och Skriv ut fraktsedelar" style="margin:5px">Skriv ut fraktsedel</a><br>';
                        echo '<a href="' . $generateXmlUrlForId2 . '" class="button" title="Generera fraktsedlar" style="margin:5px">Spara fraktsedel</a>';
                    }

                    if (!$templateExists) {
                        echo "Kan inte generera fraktsedlar för denna order";
                        echo "Kan inte generera fraktsedlar för denna order";
                    }


                }

                break;
        }
    }

    public static function my_column_width() {
        echo '<style type="text/css">';
        echo '.column-lagerkoll { text-align: center; width:60px !important; overflow:hidden }';
        echo '.klarnaid.column-klarnaid {font-size: 9px !important;}';

        echo '</style>';
    }

    public static function generatePacsoftXml($orderId, $returnContent = false, $dontAutoPrint = false) {
        $order = new WC_Order($orderId);
        $templateFile = lkPlugin::pacsoftTemplate($order);
        $country = lkPlugin::getOrderShippingCountry($order);

        if ($templateFile) {

            $fileString = file_get_contents($templateFile);
            $billingAddress = $order->get_address('billing');
            $shippingAddress = $order->get_address('shipping');

            $shippingAddress['postcode'] = preg_replace('/[^0-9]/', '', $shippingAddress['postcode']);

            $customsValue = $order->total - $order->shipping_total - $order->shipping_tax; // ||customsvalue||
            $customsCurrency = $order->currency; // ||customscurrency||


            if ($dontAutoPrint) {
                $fileString = str_replace('<val n="autoprint">YES</val>', '<val n="autoprint">NO</val>', $fileString);
            }


            $fileString = str_replace("||orderno||", $order->id, $fileString);
            $fileString = str_replace("||reference||", $order->id, $fileString);
            $fileString = str_replace("||referencebarcode||", $order->id, $fileString);

            $fileString = str_replace(
                "||name||",
                $shippingAddress['first_name'] . " " . $shippingAddress['last_name'],
                $fileString
            );
            $fileString = str_replace("||address1||", $shippingAddress['address_1'], $fileString);
            $fileString = str_replace("||address2||", $shippingAddress['address_2'], $fileString);
            $fileString = str_replace("||zipcode||", $shippingAddress['postcode'], $fileString);
            $fileString = str_replace("||city||", $shippingAddress['city'], $fileString);
            $fileString = str_replace("||country||", $shippingAddress['country'], $fileString);


            $fileString = str_replace("||customsvalue||", $customsValue, $fileString);
            $fileString = str_replace("||customscurrency||", $customsCurrency, $fileString);


            $billingAddress['email'] = str_replace("guest_checkout@klarna.com", "", $billingAddress['email']);

            if (!empty($billingAddress['email'])) {
                $fileString = str_replace("||email||", $billingAddress['email'], $fileString);
                $fileString = str_replace("||notemail||", $billingAddress['email'], $fileString);
            } else {
                $fileString = str_replace('<val n="email">||email||</val>', "", $fileString);
                $fileString = str_replace(
                    '<addon adnid="PRENOT"><val n="text4">||notemail||</val></addon>',
                    "",
                    $fileString
                );
            }

            /*            if (!empty($billingAddress['phone'])) {
                            $billingAddress['phone'] = str_replace("+46", "0", $billingAddress['phone']);
                            $billingAddress['phone'] = str_replace("0046", "0", $billingAddress['phone']);

                            $billingAddress['phone'] = preg_replace("/[^0-9]/", "", $billingAddress['phone']);

                            if (strpos($billingAddress['phone'], "07") !== 0) {
                                $billingAddress['phone'] = "";
                            }

                            if (strlen($billingAddress['phone']) !== 10) {
                                $billingAddress['phone'] = "";
                            }
                        }*/


            if (!empty($billingAddress['phone'])) {
                $fileString = str_replace("||sms||", $billingAddress['phone'], $fileString);
                $fileString = str_replace("||phone||", $billingAddress['phone'], $fileString);
            } else {
                $fileString = str_replace('<val n="phone">||phone||</val>', "", $fileString);
                $fileString = str_replace('<val n="sms">||sms||</val>', "", $fileString);
                $fileString = str_replace('<addon adnid="notsms"><val n="misc">||sms||</val></addon>', "", $fileString);
            }

            $fileString = str_replace("\xEF\xBB\xBF", '', $fileString);
            $fileString = mb_convert_encoding($fileString, 'ISO-8859-1', 'UTF-8');

            $production = true;
            if ($production) {
                file_put_contents("/home/ajlajknu/pacsoft/ut/" . $country . "-" . $order->id . ".xml", $fileString);
                update_post_meta($order->id, 'pacsoftXmlGenerated', true);

            } else {
                file_put_contents("/home/ajlajknu/pacsoft/ut-test/" . $country . "-" . $order->id . ".xml", $fileString);
            }


            if ($returnContent) {
                return $fileString;
            }

        }


    }

    public static function pacsoftTemplate($orderId) {
        $order = new WC_Order($orderId);

        $shippingAddress = $order->get_address('shipping');
        $country = $shippingAddress['country'];

        $templateFile = "/home/ajlajknu/pacsoft/mallar/" . $country . ".xml";

        if (file_exists($templateFile)) {
            return $templateFile;
        } else {
            return false;
        }
    }

    public static function getOrderShippingCountry($orderId) {
        $order = new WC_Order($orderId);

        $shippingAddress = $order->get_address('shipping');
        $country = $shippingAddress['country'];
        return $country;
    }

    public static function readPacsoftStatuses() {
        $inputDir = "/home/ajlajknu/pacsoft/in/";
        $allFiles = scandir($inputDir);

        if (count($allFiles) > 2) {

            $lines = file($inputDir . $allFiles[2]);//file in to an array

            foreach ($lines as $lineNr => $line) {
                if (!empty($line)) {

                    $info = explode(";", $line);

                    if (isset($line[0]) && isset($line[1])) {
                        if (strlen($info[1]) == 13) {
                            $deliveryMethod = "PN";
                        } else {
                            $deliveryMethod = "DHL";
                        }

                        if ($lineNr == 0) {
                            update_post_meta($info[0], 'pacsoftSendId', $info[1]);
                        } else {
                            update_post_meta($info[0], 'pacsoftReturnId', $info[1]);
                        }

                        update_post_meta($info[0], 'pacsoftXmlGenerated', true);
                        update_post_meta($info[0], 'deliveryMethod', $deliveryMethod);

                    }
                }
            }
// TODO RADERA FIL
            unlink($inputDir . $allFiles[2]);
        }
    }

}

add_action('woocommerce_order_status_changed', ['lkPlugin', 'orderChangedWooHook'], 1, 3);
//add_action('woocommerce_order_status_completed', ['lkPlugin', 'orderChangedWooHook']);


function logIt($data, $file = false, $includeDate = true) {

    if (!$file) {
        $file = '/dennis.log';
    } else {
        $file = "/logs/" . $file . ".log";
    }

    if ($includeDate) {
        error_log(
            date("Y-m-d H:i:s") . "\r\n" .
            print_r($data, true) . "\r\n",
            3,
            dirname(__FILE__) . $file
        );
    } else {
        error_log(
            print_r($data, true) . "\r\n",
            3,
            dirname(__FILE__) . $file
        );
    }

}


add_action(
    'woocommerce_admin_order_data_after_shipping_address',
    function ($order) {
        //$order = new WC_Order();
        $orderId = $order->id;

        $sent = get_post_meta($orderId, '_sentTolagerkoll', true);
        $state = get_post_meta($orderId, '_sentTolagerkollState', true);
        $lkOrderId = get_post_meta($orderId, '_lagekollOrderId', true);

        if (!$state) {
            $state = 999;
            //$state = lkPlugin::getLagerKollOrderState($orderId);
            //update_post_meta($orderId, '_sentTolagerkollState', $state);
        }

        $states = array(
            0 => "NY",
            5 => "Klar att packas",
            10 => "Packning påbörjad",
            15 => "Sickas direkt från lev",
            20 => "Packad",
            30 => "Dellevererad",
            40 => "Skickad",
            50 => "Avbruten",
            60 => "Bokförd",
            999 => "Okänd",
        );


        echo "<h3>Lagerkoll Status </h3>";
        echo '<div class="lagerKoll">';

        if ($sent) {
            echo '<p>Order skickad till lagerkoll<br>';
            echo 'Status ' . $states[$state] . ' <br>';
            echo '<a target="_blank" href="https://www.lagerkoll.com/admin/customer_order_status.jsp?order_id=' . $lkOrderId . '">Se order i lagerkoll</a></p>';
        } else {
            echo '<p><strong>Order EJ skickad till lagerkoll</strong></p>';
        }


        echo '</div>';

        echo "<h3>Unifaun Status </h3>";
        echo '<div class="lagerKoll">';

        $pacsoftXmlGenerated = get_post_meta($orderId, 'pacsoftXmlGenerated', true);


        if ($pacsoftXmlGenerated) {
            $pacsoftSendId = get_post_meta($orderId, 'pacsoftSendId', true);
            $pacsoftReturnId = get_post_meta($orderId, 'pacsoftReturnId', true);
            $deliveryMethod = get_post_meta($orderId, 'deliveryMethod', true);

            if (empty($deliveryMethod)) {
                $deliveryMethod = "PN";
            }

            if ($deliveryMethod == "PN") {
                $trackingUrl = "https://tracking.postnord.com/?id=";
            } else if ($deliveryMethod == "DHL") {
                $trackingUrl = "http://www.dhl.se/sv/express/godssoekning.html?brand=DHL&AWB=";
            }


            if (empty($pacsoftReturnId) && empty($pacsoftSendId)) {
                echo "<p>Fraktsedlar begärda.</p>";
            } else {


                echo "<p>Fraktsedlar skapade, <br>";
                if ($pacsoftSendId) {
                    echo $deliveryMethod . ' Tur : <a target="blank"  href="' . $trackingUrl . $pacsoftSendId . '">' . $pacsoftSendId . ' <span style="font-size:14px; line-height: 18px;" class="dashicons dashicons-external"></span></a><br>';
                }

                if ($pacsoftReturnId) {
                    echo $deliveryMethod . ' Retur : <a target="blank" href="' . $trackingUrl . $pacsoftReturnId . '">' . $pacsoftReturnId . ' <span style="font-size:14px; line-height: 18px;" class="dashicons dashicons-external"></span></a>';
                }
                echo "</p>";

            }

        } else {

            $templateExists = lkPlugin::pacsoftTemplate($orderId);
            $orderStatus = $order->status;

            if ($templateExists && $orderStatus !== "cancelled" && intval($orderId) > 23793) {
                $generateXmlUrlForId = add_query_arg(
                    array(
                        'generatexml' => $orderId,
                    )
                );
                $generateXmlUrlForId2 = add_query_arg(
                    array(
                        'generatexml' => $orderId,
                        "dontautoprint" => true
                    )
                );
                echo '<p>Fraktsedlar inte skapade,<br>';
                echo '<a href="' . $generateXmlUrlForId . '" class="" title="Generera och Skriv ut fraktsedlar" style="margin:5px">Skriv ut fraktsedel</a><br>';
                echo '<a href="' . $generateXmlUrlForId2 . '" class="" title="Generera fraktsedlar" style="margin:5px">Spara fraktsedel</a>';
                echo '</p>';
            }

            if (!$templateExists) {
                echo "<p>Kan inte generera fraktsedlar för denna order</p>";
            }


        }


        echo '</div>';

    }
);


add_action(
    'woocommerce_admin_order_data_after_billing_address',
    function ($order) {
        if (isset($_GET['showxml'])) {

            echo "<h3>XML Fil</h3>";
            echo '<div class="xmlFil"><pre>';

            if (!wp_next_scheduled('pacsoftReadStatuses')) {
                wp_schedule_event(time(), '1min', 'pacsoftReadStatuses');
            } else {
                wp_clear_scheduled_hook('pacsoftReadStatuses');
            }


            echo '</pre></div>';
        }


    }
);


add_action('pacsoftReadStatuses', 'pacsoftReadStatusesFunction');

function pacsoftReadStatusesFunction() {
    lkPlugin::readPacsoftStatuses();
}

function my_cron_schedules($schedules) {
    if (!isset($schedules["1min"])) {
        $schedules["1min"] = array(
            'interval' => 1 * 60,
            'display' => __('Once every minute'),
        );
    }

    return $schedules;
}

add_filter('cron_schedules', 'my_cron_schedules');


add_action('init', function () {
    if (!wp_next_scheduled('pacsoftReadStatuses')) {
        wp_schedule_event(time(), '1min', 'pacsoftReadStatuses');
    }
});

