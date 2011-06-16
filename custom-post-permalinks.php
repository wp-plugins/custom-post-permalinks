<?php
/*
Plugin Name: Custom Post Permalinks
Description: Adds more flexible permalinks for custom post types.
Author: John P. Bloch
Version: 1.1.4
Text Domain: custom-post-permalinks
*/

if( !defined( 'CPP_TAXONOMY_DEFAULT_VALUE' ) )
	define( 'CPP_TAXONOMY_DEFAULT_VALUE', _x( 'none', 'No taxonomy terms for post', 'custom-post-permalinks' ) );

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
	
	var $version = '1.1.1';
	
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
		add_action( 'wp_loaded', array( &$this, 'option_set' ), 99 );
		add_action( 'wp_loaded', array( &$this, 'init' ), 100 );
		add_action( 'admin_init', array( &$this, 'admin_init' ) );
		add_action( 'template_redirect', array( &$this, 'template_redirect' ) );
		add_action( 'parse_request', array( &$this, 'request_filter' ), 10, 1 );
		add_action( 'permalink_structure_changed', array( &$this, 'update' ), 10, 1 );
		add_filter( 'post_type_link', array( &$this, 'extra_permalinks' ), 10, 4 );
		add_filter( 'rewrite_rules_array', array( &$this, 'rise_to_the_top' ), 100 );
		$this->options = get_option( $this->settings_name );
	}
	
	/**
	 * Updates (since this is the first version, only sets) plugin options. Also sets post types and array keys.
	 * Runs at priority 99 so we don't miss any post_types.
	 * 
	 * @since 1.0
	 */
	
	function option_set(){
		load_plugin_textdomain( $this->slug, null, basename( dirname( __FILE__ ) ) . '/lang' );
		$this->post_types = get_post_types( array( '_builtin' => false, 'publicly_queryable' => true ), 'object' );
		$this->post_type_keys = array_keys( $this->post_types );
		global $wp_rewrite, $wp_taxonomies;
		foreach( $wp_taxonomies as $tax => $t ){
			if( !$t->_builtin ){
				foreach( $t->object_type as $pt ){
					if(isset($this->post_types[$pt])){
						$key = array_search( "%$tax%", $wp_rewrite->rewritecode );
						if(false !== $key){
							$wp_rewrite->rewritereplace[$key] = '(.+?)';
							$this->post_types[$pt]->taxonomies[] = $tax;
						}
					}
				}
			}
		}
		$version = get_option( $this->version_option );
		if( empty( $version ) || version_compare( $this->version, $version, '!=' ) || ( count( $this->options['pstructs'] ) !== count($this->post_types) ) ){
			$opt = array( 'pstructs' => array() );
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
			if( version_compare( $this->version, $version, '!=' ) ){
				add_action( 'admin_init', 'flush_rewrite_rules', 1 );
				update_option( $this->version_option, $this->version );
			}
			update_option( $this->settings_name, $this->options );
		}
	}
	
	/**
	 * Registers core functionality of the plugin.
	 * 
	 * This function runs at wp_loaded, but on priority 100 so as not to miss any custom post types.
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
				$wp_rewrite->add_rewrite_tag( '%post_type_' . $k . '%', '('.$rw_slug.')', 'post_type=' );
				$struct = str_replace( '%post_type%', '%post_type_' . $k . '%', $struct );
				$with_front = ! empty( $this->post_types[$k]->rewrite['with_front'] );
				add_permastruct( $k, $struct, $with_front, $this->post_types[$k]->permalink_epmask );
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
			add_settings_section( $this->slug . '_section', __('Extra Permalink Settings for Non-Hierarchical Custom Post Types',$this->slug), array( &$this, 'permalinks_settings' ), 'permalink' );
			foreach( $this->post_types as $n => $t )
				add_settings_field( $this->slug . '_pt_' . $n, sprintf( __('Custom Permalink for %s',$this->slug), $t->labels->name ), array( &$this, 'permalinks_fields' ), 'permalink', $this->slug . '_section' );
		}
		if( isset( $_POST['permalink_structure'] ) ){
			global $wp_rewrite;
			$prefix = ( ! got_mod_rewrite() && ! $iis7_permalinks ) ? '/index.php' : '';
			if( !empty( $_POST['permalink_structure'] ) ){
				$permalink = preg_replace( '@/+@', '/', '/' . str_replace( '#', '', $_POST['permalink_structure'] ) );
				if($prefix)
					$permalink = $prefix . preg_replace( '#^/?index\.php#', '', $permalink );
				if( $wp_rewrite->permalink_structure == $permalink ){
					$this->update( $wp_rewrite->permalink_structure );
				}
			}
		}
	}
	
	/**
	 * Updates the custom permastructs
	 * 
	 * @since 1.1
	 * @param $structure string The new permalink structure
	 */
	
	function update( $structure ){
		if( empty( $structure ) )
			return;
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
				$struct = str_replace( '%post_type%', '%post_type_' . $k . '%', $struct );
				$with_front = ! empty( $this->post_types[$k]->rewrite['with_front'] );
				add_permastruct( $k, $struct, $with_front, $this->post_types[$k]->permalink_epmask );
			}
		}
	}
	
	/**
	 * Returns an array of the allowed rewrite tags for the given post type
	 * 
	 * @since 1.1
	 * @param $post_type string The post type to be checked for
	 * @return array The allowed rewrite tags
	 */
	
	function get_allowed_tags( $post_type, $leavename = false ){
		$tags = array(
			'%year%',
			'%monthnum%',
			'%day%',
			'%hour%',
			'%minute%',
			'%second%',
			'%post_id%',
			'%author%',
		);
		if( !empty( $this->post_types[$post_type]->taxonomies ) ){
			// Add the post type's taxonomy tags
			foreach( $this->post_types[$post_type]->taxonomies as $tax )
				$tags[] = $tax == 'post_tag' ? '%tag%' : '%' . $tax . '%';
		}
		$tags[] = '%post_type_' . $post_type . '%';
		$tags[] = $leavename ? '' : '%' . $post_type . '%';
		return $tags;
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
			$allowed_tags = $this->get_allowed_tags( $type );
			foreach($allowed_tags as $at)
				$a_t[] = trim($at,'%');
			$struct = preg_replace( '@%('.implode('|',$a_t).'|post_type)%@', 'TPPALL$1TPPALL', $struct );
			$struct = preg_replace( '@%[^%/]*%@', '', $struct );
			$struct = str_replace( 'TPPALL', '%', $struct );
			$struct = preg_replace( '#/+#', '/', str_replace( array('#',' '), '', ltrim( $struct, '/' ) ) );
			if( ( str_replace($type, '', $struct) == str_replace(array('postname','pagename'),'',$wp_rewrite->permalink_structure) ) && false === stripos( $struct, '%post_type%' ) )
				$struct = '/%post_type%' . $struct;
			if( ( $wp_rewrite->front != '/' ) && !empty($wp_rewrite->front) && !empty( $this->post_types[$type]->rewrite['with_front'] ) )
				$struct = ltrim( $struct, '/' );
			$struct = rtrim( $struct, '/' );
			if( $wp_rewrite->use_trailing_slashes )
				$struct .= '/';
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
		printf( __('Permalinks settings for custom post types. You may use the same tags as above in the permastructs. Additionally, you may add a tag for any taxonomy registered for a post type. For example, if you have a taxonomy called %1$s, the tag would be %2$s. If the post type is not registered for a taxonomy, the tag will be removed from the structure. In links that use taxonomies other than categories, if a post is not assigned any terms, it will be replaced by the word "null". To override this, define the constant %3$s in %4$s with the string you wish to use. In addition to the normal tags above, you may use the %5$s tag to match the rewrite slug for the post type. Additionally, each post type has its own rewrite tag to match the name of the post in the URL. Post type rewrite tags are beside their respective post types.', $this->slug), '<strong>foo_bar</strong>', '<strong>%foo_bar%</strong>', '<strong>CPP_TAXONOMY_DEFAULT_VALUE</strong>', '<strong>wp-config.php</strong>', '<strong>%post_type%</strong>' );
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
		global $wp_rewrite;
		$struct = isset( $this->options['pstructs'][$pt] ) && !empty( $this->options['pstructs'][$pt] ) ? $this->options['pstructs'][$pt] : $wp_rewrite->extra_permastructs[$pt][0];
		if( '/' != $struct{0} )
			$struct = '/' . $struct;
		?>
		<input type="text" name="<?php echo $this->settings_name; ?>[pstructs][<?php echo $pt; ?>]" class="regular-text code" value="<?php echo $struct; ?>" /> <span class="description"><?php
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
		$author = '';
		if( false !== stripos( $link, '%author%' ) ){
			$authordata = get_userdata($post->post_author);
			$author = $authordata->user_nicename;
		}
		$rewritecode = $this->get_allowed_tags( $post->post_type, $leavename );
		$unixtime = strtotime($post->post_date);
		$date = explode(' ', date('Y m d H i s', $unixtime));
		$replace_array = array(
			$date[0],
			$date[1],
			$date[2],
			$date[3],
			$date[4],
			$date[5],
			$post->ID,
			$author,
		);
		if( !empty( $this->post_types[$post->post_type]->taxonomies ) ){
			foreach( $this->post_types[$post->post_type]->taxonomies as $tax ){
				if( false === stripos( $link, '%'.$tax.'%' ) ){
					$addition = 'null';
				} else {
					$addition = $this->get_tax_string( $tax, $post );
				}
				$replace_array[] = $addition;
			}
		}
		$replace_array[] = (isset($this->post_types[$post->post_type]->rewrite['slug']) && !empty($this->post_types[$post->post_type]->rewrite['slug'])) ? $this->post_types[$post->post_type]->rewrite['slug'] : $post->post_type;
		$replace_array[] = $post->post_name;
		$path = str_replace($rewritecode, $replace_array, $link);
		return $path;
	}
	
	/**
	 * Returns the taxonomy string to be inserted into the permalink
	 * 
	 * @since 1.1
	 * @param $tax string The taxonomy being evaluated
	 * @return string The taxonomy string for the permalink
	 */
	
	function get_tax_string( $tax, $post ){
		$addition = '';
		switch($tax){
			case 'category':
				$cats = get_the_category($post->ID);
				if($cats){
					usort( $cats, '_usort_terms_by_id' );
					$addition = $cats[0]->slug;
					if( $parent = $cats[0]->parent )
						$addition = get_category_parents($parent, false, '/', true) . $addition;
				}
				if( empty($addition) ){
					$default_category = get_category( get_option( 'default_category' ) );
					$addition = is_wp_error( $default_category ) ? '' : $default_category->slug;
				}
				break;
			case 'post_tag':
				$tags = get_the_tags( $post->ID );
				if( !is_wp_error( $tags ) && !empty( $tags ) ){
					usort( $tags, '_usort_terms_by_id' );
					$addition = $tags[0]->slug;
				}
				if( empty($addition))
					$addition = '';
				break;
			default:
				$terms = get_the_terms( $post->ID, $tax );
				if( !is_wp_error( $terms ) && !empty( $terms ) ){
					usort( $terms, '_usort_terms_by_id' );
					$addition = $terms[0]->slug;
					if( is_taxonomy_hierarchical( $tax ) && !empty( $terms[0]->parent ) ){
						$parent_item = $terms[0]->parent;
						while( !empty( $parent_item ) ){
							$parent = get_term( $parent_item, $tax );
							if( !is_wp_error( $parent ) && !empty( $parent ) )
								$addition = $parent->slug . '/' . $addition;
							$parent_item = empty($parent->parent) ? false : $parent->parent;
						}
					}
				}
				break;
		}
		return empty($addition) ? CPP_TAXONOMY_DEFAULT_VALUE : $addition;
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
	
	/**
	 * Bumps important page/feed rewrite rules to the top of the array.
	 * 
	 * Hooks into rewrite_rules_array (fires only on updating the permalinks)
	 * 
	 * @since 1.1
	 * @param $rules array The finished rewrite rules
	 * @return array The sorted array of rewrite rules
	 */
	
	function rise_to_the_top( $rules ){
		if( empty( $this->post_types ) )
			return $rules;
		$top = array();
		foreach( $rules as $regex => $args ){
			foreach( $this->post_types as $k => $t ){
				$slug = (isset($t->rewrite['slug']) && !empty($t->rewrite['slug']))?$t->rewrite['slug'] : $k;
				if( ( false !== strpos( $regex, "($slug)" ) ) && (
						( false !== strpos( $regex, '/page/' ) ) ||
						( false !== strpos( $regex, '/feed/' ) ) ||
						( false !== strpos( $regex, '/(feed|' ) ) ) ){
					$top = array_merge( array( $regex => $args ), $top );
				}
			}
		}
		if(!empty($top))
			$rules = array_merge( $top, $rules );
		return $rules;
	}

}

/**
 * JPB_Custom_Post_Permalinks object
 * @global object @JPB_Custom_Post_Permalinks
 * @since 1.0
 */

$JPBCPP = new JPB_Custom_Post_Permalinks();
