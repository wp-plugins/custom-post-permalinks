<?php
/*
Plugin Name: Custom Post Permalinks
Plugin URI: http://www.johnpbloch.com
Description: Adds more flexible permalinks for custom post types.
Author: John P. Bloch
Version: 1.0.3
Author URI: http://www.johnpbloch.com/
Text Domain: custom-post-permalinks
*/

/**
 * JPB_Custom_Post_Permalinks class
 * 
 * Controls all functionality for the plugin
 * 
 * @since 1.0
 */

class JPB_Custom_Post_Permalinks{

	/**
	 * Stores the plugin options
	 * 
	 * Stores options, most notably: the custom permalink structures for custom post types
	 * 
	 * @since 1.0
	 * @var array
	 */
	
	var $options = array();
	
	/**
	 * Stores the plugin settings name (option name)
	 * 
	 * @since 1.0
	 * @var string
	 */
	
	var $settings_name = 'jpb_custom_post_permalinks_settings';
	
	/**
	 * Stores the option name for the plugin version
	 * 
	 * @since 1.0
	 * @var string
	 */
	
	var $version_option = 'jpb_custom_post_permalinks_version';
	
	/**
	 * Stores the current version of the plugin
	 * 
	 * @since 1.0
	 * @var string
	 */
	
	var $version = '1.0.1';
	
	/**
	 * Stores the plugin slug
	 * 
	 * @since 1.0
	 * @var string
	 */
	
	var $slug = 'custom-post-permalinks';
	
	/**
	 * Stores all non-builtin non-hierarchical post types which are publicly queryable
	 * 
	 * @since 1.0
	 * @var array
	 */
	
	var $post_types = array();
	
	/**
	 * Stores the post type names of all post types stored in JPB_Custom_Post_Permalinks::post_types
	 */
	
	var $post_type_keys = array();
	
	/**
	 * PHP4 Constructor
	 * 
	 * Calls the PHP5 constructor; takes no arguments
	 * 
	 * @since 1.0
	 */
	
	function JPB_Custom_Post_Permalinks(){
		$this->__construct();
	}
	
	/**
	 * PHP5 Constructor
	 * 
	 * Sets up all the necessary filters and actions, as well as setting the plugin's options
	 * 
	 * @since 1.0
	 */
	
	function __construct(){
		add_action('init', array($this, 'option_set'), 99);
		add_action( 'init', array( $this, 'init' ), 100 );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'template_redirect', array( $this, 'template_redirect' ) );
		add_action('parse_request', array($this,'request_filter'),10,1);
		add_filter('post_type_link',array($this,'extra_permalinks'),10,4);
		$this->options = get_option($this->settings_name);
	}
	
	/**
	 * Updates (since this is the first version, only sets) plugin options. Also sets post types and array keys.
	 * Runs at priority 99 so we don't miss any post_types.
	 * 
	 * @since 1.0
	 */
	
	function option_set(){
		load_plugin_textdomain( $this->slug, null, basename( dirname( __FILE__ ) ) . '/lang' );
		$this->post_types = get_post_types( array( '_builtin' => false, 'publicly_queryable' => true, 'hierarchical' => false ), 'object' );
		$this->post_type_keys = array_keys( $this->post_types );
		$version = get_option( $this->version_option );
		if( empty( $version ) || version_compare( $this->version, $version, '!=' ) || ( count( $this->options['pstructs'] ) !== count($this->post_types) ) ){
			$opt = array( 'pstructs' => array() );
			global $wp_rewrite;
			foreach( $this->post_types as $n => $p ){
				$struct = isset($wp_rewrite->extra_permastructs[$n][0]) ? $wp_rewrite->extra_permastructs[$n][0] : '';
				$opt['pstructs'][$n] = $struct;
			}
			foreach( array_keys((array)$this->options['pstructs']) as $key ){
				if( !isset( $this->post_types[$key] ) )
					unset($this->options['pstructs'][$key]);
			}
			if( is_array($this->options) ){
				$opt['pstructs'] = array_merge( $opt['pstructs'], (array)$this->options['pstructs'] );
				unset($this->options['pstructs']);
				$this->options = array_merge( $opt, $this->options );
			} else {
				$this->options = $opt;
			}
			update_option( $this->settings_name, $this->options );
			update_option( $this->version_option, $this->version );
		}
	}
	
	/**
	 * Registers core functionality of the plugin.
	 * 
	 * This function runs at init, but on priority 100 so as not to miss any custom post types.
	 * If there are no custom post types that need the permalink structures, do nothing. Otherwise,
	 * set up the post_type rewrite tag and add the permastructs.
	 * 
	 * @since 1.0
	 */
	
	function init(){
		if(!empty($this->post_types)){
			global $wp_rewrite;
			foreach($this->options['pstructs'] as $k => $struct){
				$rw_slug = empty( $this->post_types[$k]->rewrite['slug'] ) ? $k : $this->post_types[$k]->rewrite['slug'];
				$wp_rewrite->add_rewrite_tag( '%post_type_' . $rw_slug . '%', '('.$rw_slug.')', 'post_type=' );
				$struct = str_replace( '%post_type%', '%post_type_' . $rw_slug . '%', $struct );
				$with_front = ! empty( $this->post_types[$k]->rewrite['with_front'] );
				add_permastruct( $k, $struct, $with_front, EP_PERMALINK );
			}
		}
	}
	
	/**
	 * Runs at admin_init. Adds fields ot the permalinks pages if there are applicable custom post types.
	 * 
	 * @since 1.0
	 */
	
	function admin_init(){
		if( !empty( $this->post_types ) ){
			add_settings_section( $this->slug . '_section', __('Extra Permalink Settings for Non-Hierarchical Custom Post Types',$this->slug), array( $this, 'permalinks_settings' ), 'permalink' );
			foreach( $this->post_types as $n => $t )
				add_settings_field( $this->slug . '_pt_' . $n, sprintf( __('Custom Permalink for %s',$this->slug), $t->labels->name ), array($this,'permalinks_fields'), 'permalink', $this->slug . '_section' );
		}
		if( isset( $_POST[$this->settings_name] ) && is_array( $_POST[$this->settings_name] ) && current_user_can('manage_options') ){
			check_admin_referer( $this->slug . '_update_permalinks', '_wpnonce_custom' );
			foreach($_POST[$this->settings_name] as $k => $v){
				if(!isset($this->options[$k]))
					unset($_POST[$this->settings_name][$k]);
			}
			if( isset($_POST[$this->settings_name]['pstructs']) && is_array( $_POST[$this->settings_name]['pstructs'] ) )
				$_POST[$this->settings_name]['pstructs'] = $this->perm_san($_POST[$this->settings_name]['pstructs']);
			if(!empty($_POST[$this->settings_name]))
				$this->options = array_merge( $this->options, $_POST[$this->settings_name] );
			update_option( $this->settings_name, $this->options );
			foreach($this->options['pstructs'] as $k => $struct){
				$rw_slug = empty( $this->post_types[$k]->rewrite['slug'] ) ? $k : $this->post_types[$k]->rewrite['slug'];
				$struct = str_replace( '%post_type%', '%post_type_' . $rw_slug . '%', $struct );
				$with_front = ! empty( $this->post_types[$k]->rewrite['with_front'] );
				add_permastruct( $k, $struct, $with_front, EP_PERMALINK );
			}
		}
	}
	
	/**
	 * Filters custom permastructs to make sure they're valid
	 * 
	 * @since 1.0
	 * @param $structs array Custom permastructs to be filtered
	 * @return array Filtered permastructs
	 */
	
	function perm_san( $structs = array() ){
		global $wp_rewrite, $wp_taxonomies;
		if( !is_array( $structs ) || empty( $structs ) || !$wp_rewrite->using_permalinks() )
			return array();
		$prefix = ( !iis7_supports_permalinks() && !got_mod_rewrite() ) ? '/index.php' : '';
		foreach( $structs as $type => $struct){
			if( false !== stripos($struct,'%category%') && !is_object_in_taxonomy($type,'category') )
				$struct = str_replace('%category%','',$struct);
			$struct = preg_replace( '#/+#', '/', '/' . str_replace( array('#',' '), '', $struct ) );
			if( ( str_replace($type, '', $struct) == str_replace(array('postname','pagename'),'',$wp_rewrite->permalink_structure) ) && false === stripos( $struct, '%post_type%' ) )
				$struct = '/%post_type%' . $struct;
			$structs[$type] = $prefix . $struct;
		}
		return $structs;
	}
	
	/**
	 * Runs at template_redirect. Makes sure WP doesn't think it's home if it's viewing custom post types
	 * 
	 * @since 1.0
	 */
	
	function template_redirect(){
		if ( in_array( get_query_var( 'post_type' ), $this->post_type_keys ) ) {
			global $wp_query;
			$wp_query->is_home = false;
		}
	}
	
	/**
	 * Description for permalinks settings section
	 * 
	 * @since 1.0
	 */
	
	function permalinks_settings(){
		?>
		<p><?php
		printf( __('Permalinks settings for custom post types. You may use the same tags as above in the permastructs. If you use %1$s, make sure the post type actually uses categories and not another taxonomy. In addition to the normal tags above, you may use the %2$s tag to match the rewrite slug for the post type. Additionally, each post type has its own rewrite tag to match the name of the post in the URL. Post type rewrite tags are beside their respective post types.', $this->slug), '<strong>%category%</strong>', '<strong>%post_type%</strong>' );
		?></p>
		<p>
		<?php
		printf( __('For example, with a post type named %1$s, a permalink like %2$s would look like %3$s.',$this->slug), '<code>news</code>', '<code>/%post_type%/%year%/%monthnum%/%news%/</code>', '<code>http://example.com/news/2010/08/sample-news-post</code>' );
		?>
		</p>
		<?php
		wp_nonce_field( $this->slug . '_update_permalinks', '_wpnonce_custom' );
	}
	
	/**
	 * Prints out permalink fields for applicable custom post types.
	 * 
	 * @since 1.0
	 */
	
	function permalinks_fields(){
		$pt = array_shift( $this->post_type_keys );
		$post_type = $this->post_types[$pt];
		?>
		<input type="text" name="<?php echo $this->settings_name; ?>[pstructs][<?php echo $pt; ?>]" class="regular-text code" value="<?php echo $this->options['pstructs'][$pt]; ?>" /> <span class="description"><?php
		printf( __('The permalink tag for the post name for %1$s is %2$s.', $this->slug), $post_type->labels->name, '</span>%'.$pt.'%' );
		?>
		<?php
	}
	
	/**
	 * Filters the permalinks requests for applicable custom post types
	 * 
	 * Uses code from the core permalinks function
	 * 
	 * @since 1.0
	 * @param $link string The unfiltered permalink
	 * @param $post object The current post object
	 * @param $leavename bool Whether to keep the postname
	 * @param $sample bool Whether it's a sample permalink
	 * @return string The correct permalink for the post
	 */
	
	function extra_permalinks( $link, $post, $leavename, $sample ){
		if( !isset($this->post_types[$post->post_type]) )
			return $link;
		global $wp_taxonomies;
		$category = $author = false;
		if( ( false !== stripos($link, '%category%') ) && is_object_in_taxonomy( $post->post_type, 'category' ) ){
			$cats = get_the_category($post->ID);
			if($cats){
				usort( $cats, '_usort_terms_by_id' );
				$category = $cats[0]->slug;
				if( $parent = $cats[0]->parent )
					$category = get_category_parents($parent, false, '/', true) . $category;
			}
			if( empty($category) ){
				$default_category = get_category( get_option( 'default_category' ) );
				$category = is_wp_error( $default_category ) ? '' : $default_category->slug;
			}
		}
		if( false !== stripos( $link, '%author%' ) ){
			$authordata = get_userdata($post->post_author);
			$author = $authordata->user_nicename;
		}
		$rewritecode = array(
			'%year%',
			'%monthnum%',
			'%day%',
			'%hour%',
			'%minute%',
			'%second%',
			$leavename? '' : '%postname%',
			'%post_id%',
			$leavename? '' : '%pagename%',
			'%post_type_' . ( empty($this->post_types[$post->post_type]->rewrite['slug']) ? $post->post_type : $this->post_types[$post->post_type]->rewrite['slug'] ) .'%',
			'%category%',
			$author? '%author%' : '',
			$leavename? '' : '%'.$post->post_type.'%',
		);
		$unixtime = strtotime($post->post_date);
		$date = explode(' ', date('Y m d H i s', $unixtime));
		$replace_array = array(
			$date[0],
			$date[1],
			$date[2],
			$date[3],
			$date[4],
			$date[5],
			$post->post_name,
			$post->ID,
			$post->post_name,
			$this->post_types[$post->post_type]->rewrite['slug'],
			$category? $category : '',
			$author,
			$post->post_name,
		);
		$path = str_replace($rewritecode, $replace_array, $link);
		return $path;
	}
	
	/**
	 * Filters the WP::parse_request results if necesary.
	 * 
	 * Because the query_var value of post_type is deleted when the rewrite slug differs from the post_type name,
	 * we need to see if there's a match in the query_string for the post_type set to the rewrite string. If there
	 * is, we'll set the query_var manually to the post_type key so that wp_query will pull the right posts.
	 * 
	 * @since 1.0
	 * @param $wp object The global WP object
	 */
	
	function request_filter( $wp ){
		foreach($this->post_types as $k => $type){
			if( preg_match( '#^(.*&)?post_type='.$type->rewrite['slug'].'(&.*)?$#', $wp->matched_query ) && ($k != $type->rewrite['slug'] && !isset($wp->query_vars['post_type'])) ){
				$wp->matched_query = str_replace($type->rewrite['slug'], $k, $wp->matched_query);
				$wp->query_vars['post_type'] = $k;
				break;
			}
		}
	}

}

/**
 * JPB_Custom_Post_Permalinks object
 * @global object @JPB_Custom_Post_Permalinks
 * @since 1.0
 */

$JPBCPP = new JPB_Custom_Post_Permalinks();

?>