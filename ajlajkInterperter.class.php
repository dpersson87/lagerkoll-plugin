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
 * @package    ajlajkInterperter.class.php
 * @author     Dennis
 * @copyright  2016 Fristil AB
 * @license    Contact Fristil for information about license
 * @link       http://fristil.se
 */

namespace ajlajk;


class ajlajkInterperter {

    private static $colors = array();
    /*private static $colors = array(

        'ARMY' => 'Lt Army',
        'APRI' => 'Lt Apricot',

        'GREY'  => 'Lt Grey',
        'GRM'   => 'Lt Grey Melange',
        'GREEN' => 'Lt Green',

        'LILAC' => 'Lt Lilac',

        'PINK' => 'Lt Pink',
        'POT'  => 'Lt Potato',


        //A
        'ALEA' => 'Alea',
        'AP'   => 'Apricot',
        'AP_'  => 'Apricos',
        'APR'  => 'Soft apricot',
        'ARM'  => 'Army',

        'AU'        => 'Aubergine',
        'AUB'       => 'Aubergine',

        //B
        'BELLA'     => 'Bella',
        'BL'        => 'Black',
        'BLU'       => 'Soft blue',
        'BLSI'      => 'Black Silver',
        'BLGO'      => 'Black Gold',

        //C
        'CER'       => 'Cerise',
        'CH'        => 'Charcoal',
        'CL'        => 'Charcoal',
        'CLEO'      => 'Cleo',
        'CLB'       => 'Cloudblue',
        'CAMOBR'    => 'Camo Brown',
        'CAMO'      => 'Camo',


        //D
        'DCL'       => 'D. Charcoal',
        'DGRM'      => 'Dark Greymelange',
        'DGREY'     => 'D. Grey',
        'DIP'       => 'Dirty pink',
        'DIP_1'     => 'D.pink',
        'DUP'       => 'Dusty pink',
        'DS'        => 'Dark stone',
        'DBR'       => 'Dark Brown',
        'DBL'       => 'Dark Blue',
        'DLILAC'    => 'D. Lilac',

        //E
        'EC'        => 'Ecru',

        //F
        'FLOW'      => 'Flower',

        //G
        'GR'        => 'Grey',
        //'GRM'       => 'Grey Melange',
        'GO'        => 'Gold',

        //H
        //I
        //J

        //K
        'KIT'       => 'Soft kitt',
        'KITT'      => 'Kitt',

        //L
        'LAV'       => 'Soft Lavender',
        'Soft20Lav' => 'Soft Lavender',
        'LEO'       => 'Leo',
        'LIM'       => 'Lime',
        'LBR'       => 'Light Brown',
        'LBL'       => 'Light Blue',


        //M

        //N
        'NAV'       => 'Navy',
        'NAVY'      => 'Navy',
        'NAT'       => 'Nature',

        //O
        'OL'        => 'Olive',
        'OR'        => 'Orange',
        'OW'        => 'Offwhite',

        //P
        'PI'        => 'Pink',
        'PIN'       => 'Pink',
        'PO'        => 'Potato',

        'PU'        => 'Purple',

        //Q

        //R
        'RU'        => 'Rust',
        'ROSE'      => 'Rose',

        //S
        'SA'        => 'Sand',
        'SI'        => 'Silver',
        //'SKITT'     => 'S.Kitt',
        'SKITT'     => 'Kitt',
        'SPIN'      => 'S.pink',
        'S.PIN'     => 'S.pink',
        'SGR'       => 'S.Grey',
        'SRG'       => 'S.Grey',
        'SGRM'      => 'S.Grey Melange',
        'SNAV'      => 'S.Navy',
        'SBLU'      => 'S.Blue',
        'S.NAV'     => 'S.Navy',
        'STU'       => 'S.Turkose',


        //T
        'TU'        => 'Turkose',
        'TP'        => 'Taupe',

        //U
        //V
        //W
        'WH'        => 'White',
        'WHGO'      => 'White Gold',
        'WHSI'      => 'White Silver',
        'Vit20glas' => 'White',

        //X
        //Y
        //Z


    );*/

    private static $exceptions = array(
        'FG-B-02',
        'FG-B-03',
        'FG-K-02',
        'FG-K-05',
    );
    /*private static $sizes      = array(
        'S'  => 'Small',
        'M'  => 'Medium',
        'L'  => 'Large',
        'SM' => 'Small/Medium',
        'ML' => 'Medium/large',

    );*/
    private static $sizes    = array();
    private static $specials = array(
        "KIDS" => "Barnkollektion",
    );

    public static function findColorInString($string, $delimeter = array("-", "_")) {


        if (empty(self::$sizes)) {
            self::getAcfSizes();
        }
        if (empty(self::$colors)) {
            self::getAcfColors();
        }

        $string = strtoupper($string);

        foreach (self::$colors as $short => $color) {


            //del before and imagetype after
            if (is_numeric(stripos($string, $color))) {
                return $color;
            }
            $color20 = str_replace(" ", "20", $color);
            if (is_numeric(stripos($string, $color20))) {
                return $color;
            }

            $short = strtoupper($short);

            foreach ($delimeter as $del) {
                //del beefore and after
                if (is_numeric(strpos($string, $del . $short . $del))) {
                    return $color;
                }

                //del before and imagetype after
                if (is_numeric(strpos($string, $del . $short . '.JPG'))) {
                    return $color;
                }

                //del before and imagetype after
                if (is_numeric(strpos($string, $del . $short . '_'))) {
                    return $color;
                }
            }


        }

        return false;
    }

    public static function removeColorFromString($string) {

        if (empty(self::$sizes)) {
            self::getAcfSizes();
        }
        if (empty(self::$colors)) {
            self::getAcfColors();
        }


        $colorsSpace = array();
        $colors      = array();

        foreach (self::$colors as $color) {
            if (is_numeric(strpos($color, " "))) {
                $colorsSpace[] = $color;
            }
            else {
                $colors[] = $color;
            }
        }

        $colors = array_merge($colorsSpace, $colors);

        foreach ($colors as $index => $color) {

            $wordsToFind = array(
                "" . $color . " ",  //först
                " " . $color . "",  // i mening eller sist
            );


            foreach ($wordsToFind as $word) {
                $string = str_ireplace($word, "", $string);
            }


        }

        return $string;
    }

    public static function getProductInformationFromSku($sku) {
        $skuPartsOutput = array(
            'sku'             => $sku,
            'skuIsClean'      => true,
            'cleanSku'        => false,
            'special'         => false,
            'specialReadable' => false,
            'color'           => false,
            'colorReadable'   => false,
            'size'            => false,
            'sizeReadable'    => false,
        );


        if (in_array($sku, self::$exceptions)) {
            return $skuPartsOutput;
        }

        if (empty(self::$sizes)) {
            self::getAcfSizes();
        }
        if (empty(self::$colors)) {
            self::getAcfColors();
        }

        $skuPartsInput = explode("-", $sku);

        if (strlen($skuPartsInput[0]) == 1 && isset($skuPartsInput[1])) {
            $skuPartsInput[0] = $skuPartsInput[0] . "-" . $skuPartsInput[1];
            unset($skuPartsInput[1]);

        }


        if (count($skuPartsInput) > 4) {
            throw new \Error('Fel antal delar i produktnr');
        }


        if ($sku != $skuPartsInput[0]) {
            $skuPartsOutput['cleanSku']   = $skuPartsInput[0];
            $skuPartsOutput['skuIsClean'] = false;
        }

        foreach ($skuPartsInput as $skuPart) {
            if (array_key_exists($skuPart, self::$specials)) {
                $skuPartsOutput['special']         = $skuPart;
                $skuPartsOutput['specialReadable'] = self::$specials[ $skuPart ];
                $skuPartsOutput['cleanSku']        = $skuPartsOutput['cleanSku'] . '-' . $skuPart;
            }

            if (array_key_exists($skuPart, self::$colors)) {
                $skuPartsOutput['color']         = $skuPart;
                $skuPartsOutput['colorReadable'] = trim(self::$colors[ $skuPart ]);
                $skuPartsOutput['colorReadable'] = str_replace("    ", " ", $skuPartsOutput['colorReadable']);
            }

            if (array_key_exists($skuPart, self::$sizes)) {
                $skuPartsOutput['size']         = $skuPart;
                $skuPartsOutput['sizeReadable'] = self::$sizes[ $skuPart ];
            }
        }


        return $skuPartsOutput;
    }


    public static function getAcfSizes() {
        self::$sizes = get_field('size_in_art', 'option');
        self::$sizes = \ajlajk\ajlajkInterperter::fixACF(self::$sizes);
    }

    public static function getAcfColors() {
        self::$colors = get_field('color_in_art', 'option');
        self::$colors = \ajlajk\ajlajkInterperter::fixACF(self::$colors);
    }

    public static function fixACF($array) {
        $newArray = array();

        foreach ($array as $item) {
            $newArray[ $item['in_art'] ] = $item['humanreadable'];
        }


        return $newArray;
    }
}