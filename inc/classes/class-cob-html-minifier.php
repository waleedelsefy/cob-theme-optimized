<?php
/**
 * HTML Minifier Class
 *
 * Handles the minification of the theme's HTML output on the fly
 * to reduce page size and improve loading performance.
 *
 * @package Capital_of_Business_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! class_exists( 'COB_HTML_Minifier' ) ) {

    /**
     * Manages the HTML minification process.
     */
    final class COB_HTML_Minifier {

        /**
         * The single instance of the class.
         * @var COB_HTML_Minifier
         */
        private static $instance = null;

        /**
         * Ensures only one instance of the class is loaded.
         * @return COB_HTML_Minifier - Main instance.
         */
        public static function instance() {
            if ( is_null( self::$instance ) ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Private constructor to set up the main hook.
         */
        private function __construct() {
            // Only run minification on the frontend, not in the admin area.
            if ( ! is_admin() ) {
                add_action( 'init', array( $this, 'initialize_minification' ), 1 );
            }
        }

        /**
         * Starts the output buffering if minification is active.
         */
        public function initialize_minification() {
            // You can make this a theme option later if you want.
            $is_minify_active = true;
            if ( $is_minify_active ) {
                ob_start( array( $this, 'minify_output' ) );
            }
        }

        /**
         * The main minification function that processes the HTML buffer.
         *
         * @param string $buffer The HTML content of the page.
         * @return string The minified HTML content.
         */
        public function minify_output( $buffer ) {
            // Do not minify XML feeds.
            if ( substr( ltrim( $buffer ), 0, 5) == '<?xml' ) {
                return $buffer;
            }

            // Configuration for minification.
            $minify_comments = true;

            // Replace special characters to protect certain blocks.
            $buffer = str_ireplace(
                ['<script', '/script>', '<pre', '/pre>', '<textarea', '/textarea>', '<style', '/style>'],
                ['M1N1FY-ST4RT<script', '/script>M1N1FY-3ND', 'M1N1FY-ST4RT<pre', '/pre>M1N1FY-3ND', 'M1N1FY-ST4RT<textarea', '/textarea>M1N1FY-3ND', 'M1N1FY-ST4RT<style', '/style>M1N1FY-3ND'],
                $buffer
            );

            // Split buffer by protected blocks.
            $split = explode( 'M1N1FY-3ND', $buffer );
            $buffer = '';

            for ( $i = 0; $i < count( $split ); $i++ ) {
                $start_pos = strpos( $split[$i], 'M1N1FY-ST4RT' );

                if ( $start_pos !== false ) {
                    $process = substr( $split[$i], 0, $start_pos );
                    $asis = substr( $split[$i], $start_pos + 12 );

                    // Minify JS inside <script> tags.
                    if ( substr( $asis, 0, 7) == '<script' ) {
                        $lines = explode( chr(10), $asis );
                        $asis = '';
                        foreach( $lines as $line ) {
                            if ( trim($line) ) {
                                $asis .= trim($line) . chr(10);
                            }
                        }
                        if ( $asis ) {
                            $asis = substr( $asis, 0, -1 );
                        }
                        if ( $minify_comments ) {
                            $asis = preg_replace( '!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $asis );
                        }
                        $asis = str_replace( [";\n", ">\n", "{\n", "}\n", ",\n"], [';', '>', '{', '}', ','], $asis );

                        // Minify CSS inside <style> tags.
                    } elseif ( substr( $asis, 0, 6) == '<style' ) {
                        $asis = preg_replace( ['/\>[^\S ]+/s', '/[^\S ]+\</s', '/(\s)+/s'], ['>', '<', '\\1'], $asis );
                        if ( $minify_comments ) {
                            $asis = preg_replace( '!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $asis );
                        }
                        $asis = str_replace( [chr(10), ' {', '{ ', ' }', '} ', '(', ')', ' :', ': ', ' ;', '; ', ' ,', ', ', ';}'], ['', '{', '{', '}', '}', '(', ')', ':', ':', ';', ';', ',', ',', '}'], $asis );
                    }
                } else {
                    $process = $split[$i];
                    $asis = '';
                }

                // Minify the general HTML part.
                $process = preg_replace( ['/\>[^\S ]+/s', '/[^\S ]+\</s', '/(\s)+/s'], ['>', '<', '\\1'], $process );
                if ( $minify_comments ) {
                    $process = preg_replace( '/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s', '', $process );
                }
                $buffer .= $process . $asis;
            }

            // Cleanup the markers.
            $buffer = str_replace( ["\n<script", "\n<style", "*/\n", 'M1N1FY-ST4RT'], ['<script', '<style', '*/', ''], $buffer );

            return $buffer;
        }
    }
}
