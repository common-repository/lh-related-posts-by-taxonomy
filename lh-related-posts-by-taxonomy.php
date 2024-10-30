<?php
/**
 * Plugin Name: LH Related Posts by taxonomy
 * Plugin URI: https://lhero.org/portfolio/lh-related-posts-by-taxonomy/
 * Version: 1.00
 * Description: Handles the display of related posts using the related posts by taxonomy plugin
 * Version: 1.01
 * Author: Peter Shaw
 * Text Domain: lh_related_posts_by_taxonomy
 * Domain Path: /languages
 * Author URI: https://shawfactor.com
*/

if ( ! defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

/**
* LH Related Posts by taxonomy plugin class
*/


if (!class_exists('LH_Related_posts_by_taxonomy_plugin')) {

class LH_Related_posts_by_taxonomy_plugin {
    
    private static $instance;
    
    static function return_plugin_namespace(){
    
        return 'lh_rpbt';
    
    }

    static function check_if_tag_is_in_string($html, $tag){
        
        if (empty($html)){
            
          return false;
          
        }
        
        libxml_use_internal_errors(true);        
        $dom = new DOMDocument;
    
        $dom->loadHTML( mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
    
        $tags = $dom->getElementsByTagName($tag);
     
        libxml_clear_errors();  
     
        if ($tags->length == 0){
         
            return false;
         
        } else {
         
            return true;
        
        }
     
    }



    static function get_applicable_post_types() {
        
        $posttypes = get_post_types_by_support(array('editor'));
    
        foreach ($posttypes as $posttype) {
            
            if (!is_post_type_viewable($posttype)) {
                
                $posttypes = array_diff( $posttypes, array($posttype) );
                
            }
            
        }
    
        $posttypes = array_diff($posttypes, array('attachment','product'));
    
        return apply_filters( self::return_plugin_namespace().'_get_applicable_post_types', $posttypes );
        
        
    }

    static function is_applicable_post_type($post_type) {
        
        $post_types = self::get_applicable_post_types();
    
        return in_array( $post_type , $post_types );
        
    }

    
    static function get_excluded_ids($post){
        
        $excluded = false;
        
        return apply_filters( self::return_plugin_namespace().'_get_excluded_ids_filter', $excluded, $post);
    
    }

    static function get_included_ids($post){
        
        $included = false;
        
        return apply_filters( self::return_plugin_namespace().'_get_included_ids_filter', $included, $post );
        
    }



    static function is_content_context($post){
        
         //check if it's a singular page and not a feed etc.
        if (in_the_loop() && is_singular() && (get_queried_object_id() == $post->ID) && isset($post->ID) && is_main_query() && !is_feed() && (self::is_applicable_post_type($post->post_type)) && !has_shortcode( $post->post_content, 'lh_rpbt_display' ) ) {
       
            return true;         
            
        } else {
            
            return false;    
            
        }
        
    }

    static function is_shortcode_context($post){
        
         //check if it's a singular page and not a feed etc.
        if (in_the_loop() && is_singular() && isset($post->ID) && is_main_query() && !is_feed() && (self::is_applicable_post_type($post->post_type))  ) {
       
            return true;         
            
        } else {
            
            return false;    
            
        }
        
    }



    /**
     * Validates ids.
     * Checks if ids is a comma separated string or an array with ids.
     *
     * @since 0.2
     * @param string|array $ids Comma separated list or array with ids.
     * @return array Array with postive integers
     */
        
    static function validate_ids( $ids ) {
    	if ( ! is_array( $ids ) ) {
    		/* allow positive integers, 0 and commas only */
    		$ids = preg_replace( '/[^0-9,]/', '', (string) $ids );
    		/* convert string to array */
    		$ids = explode( ',', $ids );
    	}
    	/* convert to integers and remove 0 values */
    	$ids = array_filter( array_map( 'intval', (array) $ids ) );
    	return array_values( array_unique( $ids ) );
    }



    static function get_related_posts_by_taxonomy_via_sql($post, $args = array()){
    
        global $wpdb;
    
        $taxonomy_objects = get_object_taxonomies( $post->post_type, 'names' );

        $terms = wp_get_object_terms( $post->ID, $taxonomy_objects, array( 'fields' => 'ids' ) );

        $terms = apply_filters(self::return_plugin_namespace().'_return_term_ids', $terms, $post, $taxonomy_objects);

        if (!empty($terms)){

            $term_ids = implode( ', ', $terms );
    
            $sql = "SELECT ".$wpdb->posts.".ID , count(distinct tt.term_taxonomy_id) as termcount FROM ".$wpdb->posts." INNER JOIN ".$wpdb->term_relationships." tr ON (".$wpdb->posts.".ID = tr.object_id) INNER JOIN ".$wpdb->term_taxonomy." tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id) WHERE ( ( post_type = 'post' AND ( post_status = 'publish' ) ) ) AND ".$wpdb->posts.".ID != ".$post->ID." AND ( tt.term_id IN (".$term_ids.") )";

            if ($excluded_ids = self::get_excluded_ids($post)){
    
                $sql .= " AND ".$wpdb->posts.".ID NOT IN (".implode(",", $excluded_ids).")";    
    
            }

            if ($included_ids = self::get_included_ids($post)){
    
                $sql .= " AND ".$wpdb->posts.".ID IN (".implode(",", $included_ids).")";    

            }

            $sql .= " GROUP BY ".$wpdb->posts.".ID ORDER BY termcount DESC, ".$wpdb->posts.".post_date DESC LIMIT 3";
 
            $results = $wpdb->get_results( $sql );

            $results = wp_list_pluck( $results, 'ID' );


            return $results;

        } else {
    
            return false;
    
        }
    
    }


    
    






    public function output_related_posts($post, $args = array()){
    
        ob_start();

        if (!empty($args['post_ids'])){
    
            if (is_string($args['post_ids'])){
    
                $post_ids = explode( ',', $args['post_ids']); 

            } else {
    
                $post_ids = $args['post_ids'];    
    
            }

        } else {    
    
            $post_ids = self::get_related_posts_by_taxonomy_via_sql($post, $args);

        }

        $this->has_run = true;

        if (!empty($post_ids)){

            $args = array(
                'post__in'=> $post_ids, 
                'post_type'      => self::get_applicable_post_types(),
                'orderby'=>'post__in',
            );
    
            // The Query
            $the_query = new WP_Query( $args ); 
    
            $dir = get_stylesheet_directory().'/'.self::return_plugin_namespace().'-templates/default-template.php';
    
            wp_enqueue_style( self::return_plugin_namespace().'-style' );
    
            if ($the_query->have_posts()) {
    
                if (file_exists($dir)){
        
                    include($dir);
        
                } else {
        
                    include( plugin_dir_path( __FILE__ ).'/'.self::return_plugin_namespace().'-templates/default-template.php');    
        
                }
    
            }

            wp_reset_postdata();

            $return_string = ob_get_contents();

            ob_end_clean();

        } else {
    
            return '';    
    
        }

        return $return_string;
    
    }
    


    
    
    public function maybe_add_related( $content ) {
        
        global $post;
    
    
        //check if it's a singular page and not a feed etc.
        if ((self::is_content_context($post)) && !empty($content)  && !self::check_if_tag_is_in_string($content, 'form')) {
            
            
            $the_args = array();
            
            $the_args = apply_filters(self::return_plugin_namespace().'_return_related_post_args', $the_args, $content, $post);
                
            $related_posts = $this->output_related_posts($post, $the_args);
         
            // add related post content
            $content = $content . $related_posts;
            
            global $wp_filter;
            $current_filter_data = $wp_filter[ current_filter() ];
                    
             $removed = remove_filter('the_content', array($this,'maybe_add_related'),$current_filter_data->current_priority(), 1);        
        
    
        }
        
    
     
        return $content;
    }



    public function maybe_add_related_tribe( $content ) {
        
        global $post;
        
        echo $this->output_related_posts($post);
        
    }


    public function after_body_open(){
    
        $priority = PHP_INT_MAX - 5;
    
        if (is_singular() && (!function_exists( 'is_checkout') or !is_checkout()) && (!function_exists( 'is_cart') or !is_cart()) && !is_singular('tribe_events') && !is_front_page() && !is_home()){
    
            //filter the content to add related posts
            add_filter( 'the_content', array($this,'maybe_add_related'), $priority, 1);

        } elseif (is_singular('tribe_events') ){
    
            do_action( 'tribe_events_after_the_content' , array($this,'maybe_add_related_tribe'), $priority); 
    
        }

    }

    public function display_shortcode_output( $atts, $content = null ) {
        
        
        // define attributes and their defaults
        extract(
            shortcode_atts(
                array (
                    'post_ids' => false,
                    'format' => 'svg',
                ),
                $atts
            )
        );
        
        
        
        
        global $post;
            
        $related_posts = '';
    
        if ($post_ids == ""){
            
            return false;
            
        }
            
    
    
        $args = array();
    
        if (isset($post_ids)){
        
            $args['post_ids'] = $post_ids;
        
        }
            
        $related_posts .= $this->output_related_posts($post, $args);
    
        $this->has_run = true;   
    
        return $related_posts;
        
    }

    public function register_shortcodes(){
        
        add_shortcode(self::return_plugin_namespace().'_display', array($this,'display_shortcode_output')); 
    
    }




    public function new_image_size() {
        
        add_image_size( 'lh_rpbt_featured', 200, 150, true);
    
    }
    
    public function register_core_scripts_and_styles(){
    
        if (!is_admin()){    
    
            if (!class_exists('LH_Register_file_class')) {
     
                include_once('includes/lh-register-file-class.php');
    
            }

            $add_array = array();
            
            $lh_related_posts_by_taxonomy_styles = new LH_Register_file_class( self::return_plugin_namespace().'-style', plugin_dir_path( __FILE__ ).'styles/lh-related-posts-by-taxonomy.css', plugins_url( '/styles/lh-related-posts-by-taxonomy.css', __FILE__ ), false, array(), false, $add_array);
            
            unset($add_array);

        }

    }



    public function plugin_init(){
        
        //load translations
        load_plugin_textdomain( self::return_plugin_namespace(), false, basename( dirname( __FILE__ ) ) . '/languages' );
        
        //add some hooks on body open so they only run when needed
        add_action( 'wp_body_open', array($this,'after_body_open'));
        
        //register a shortcode for customised front end display
        add_action('init', array($this,'register_shortcodes'));
        
        //register an additional image size
        add_action( 'after_setup_theme', array($this,'new_image_size') );
        
        //register the core script
        add_action( 'wp_loaded', array($this, 'register_core_scripts_and_styles'), 10 );
    
    }


    
     /**
     * Gets an instance of our plugin.
     *
     * using the singleton pattern
     */
    public static function get_instance(){
        
        if (null === self::$instance) {
            
            self::$instance = new self();
        
            
        }
 
        return self::$instance;
        
    }
    





    public function __construct() {
        
        //run our hooks on plugins loaded to as we may need checks  
        add_action( 'plugins_loaded', array($this,'plugin_init'));
    
    }
    



}

$lh_related_posts_by_taxonomy_instance = LH_Related_posts_by_taxonomy_plugin::get_instance();


}

?>