<?php
/*
Plugin Name: Image handling
Plugin URI: https://github.com/mhmli/
Description: Redirect all image requests to a PHP script so that e.g. watermarking and re-sizing can take place on-the-fly. No imagess will be generated or cached on the server.
Author: Mark Howells-Mead
Version: 1.0
Author URI: https://github.com/mhmli/
*/

class MHM_Imagehandling {
	
	var $key = '',
		$resourcesPath = '',
		$handlerScript = '';

	private $remove_rule_flag = false;

	//////////////////////////////////////////////////
	
	private function dump($var,$die=false){
		echo '<pre>' .print_r($var,1). '</pre>';
		if($die){die();}
	}//dump

	//////////////////////////////////////////////////

	public function __construct(){

		$this->key = basename(__DIR__);
		$this->resourcesPath = __DIR__.'/Resources/';
		$this->resourcesURI	 = plugin_dir_url( __FILE__ ).'Resources';
		$this->handlerScript = preg_replace('~^\/~', '', str_replace($_SERVER['DOCUMENT_ROOT'], '', $this->resourcesPath) . 'Private/RequestHandler.php');

        register_deactivation_hook( __FILE__, array( &$this, 'remove_rules' ) );

		$this->initBackend();
		

	}

	//////////////////////////////////////////////////
	
	function flush_rules(){
		global $wp_rewrite;
		$wp_rewrite->flush_rules(true);
	}

	//////////////////////////////////////////////////

	public function add_rules() {
		//only add rules if the plugin is not being deactivated
		
		if(!$this->remove_rule_flag){
			global $wp_rewrite;

            //consider moving this to a plugin option and regenerate rewrite rules
            $uploads_path = 'wp-content/uploads/';

            //write the new .htaccess rules to redirect image serving to the image serving proxy
            $non_wp_rules = array($uploads_path.'(.*)\.(jpe?g|gif|png)$' => $this->handlerScript);

			/*foreach($non_wp_rules as $array_value){
				if($array_value != NULL){
					array_unshift($wp_rewrite->non_wp_rules, $array_value);
				}
			}*/

			$wp_rewrite->non_wp_rules = $non_wp_rules + $wp_rewrite->non_wp_rules;

			$this->flush_rules();
		}
		
	}

	//////////////////////////////////////////////////

	public function remove_rules(){
		$this->remove_rule_flag = true;
		remove_action( 'generate_rewrite_rules', array( &$this, 'add_rules') );
		$this->flush_rules();
	}

	//////////////////////////////////////////////////

	private function initBackend(){
		add_action( 'admin_init', array(&$this, 'add_rules') ); // htaccess
		add_action( 'admin_menu', array(&$this, 'add_admin_menu') );
		add_action( 'admin_init', array(&$this, 'admin_settings_init') );
	}

	//////////////////////////////////////////////////

	function add_admin_menu() { 
		add_options_page( 'Image handling', 'Image handling', 'manage_options', 'image_handling', array(&$this, 'options_page') );
	}

	//////////////////////////////////////////////////

	function admin_settings_init() { 
	
		wp_enqueue_script('jquery');
		wp_enqueue_script('admin_imageupload', plugin_dir_url( __FILE__ ).'Resources/Public/JavaScript/admin_uploadimage.js', 'jquery', '1.0', true);
	
		// Enqueue the Media Uploader script
		wp_enqueue_media();
	
		register_setting( 'pluginPage', 'mhm_imagehandling_settings' );
	
		add_settings_section(
			'section1', 
			'',//__( 'Your section description', 'mhm-imagehandling' ), 
			array(&$this, 'section_callback'), 
			'pluginPage'
		);

		add_settings_field( 
			'image_url', 
			__( 'Watermark image', 'mhm-imagehandling' ), 
			array(&$this, 'field_image_render'), 
			'pluginPage', 
			'section1' 
		);

		add_settings_field( 
			'resizepercent', 
			__( 'Resize watermark image (percent)', 'mhm-imagehandling' ), 
			array(&$this, 'field_resizepercent'), 
			'pluginPage', 
			'section1' 
		);
	
		add_settings_field( 
			'minimumsize', 
			__( 'Minimum width of image to be watermarked (px)', 'mhm-imagehandling' ), 
			array(&$this, 'field_minimum_size_to_watermark'), 
			'pluginPage', 
			'section1' 
		);
	
		add_settings_field( 
			'activate', 
			__( 'Add watermark to images', 'mhm-imagehandling' ), 
			array(&$this, 'field_activate_render'), 
			'pluginPage', 
			'section1' 
		);
	}

	//////////////////////////////////////////////////

	function field_image_render(  ) { 
		$options = get_option( 'mhm_imagehandling_settings' );
		?>
	    <input type="text" name="mhm_imagehandling_settings[image_url]" id="mhm_imagehandling_settings_image_url" class="regular-text" value="<?php echo $options['image_url']; ?>" />
	    <input type="button" name="upload-btn" id="upload-btn" class="button-secondary" value="Select/upload" />
		<?php
	}

	function field_resizepercent(  ) { 
		$options = get_option( 'mhm_imagehandling_settings' );
		if( !$options['resizepercent'] ){
			$options['resizepercent'] = 10;
		}
		?>
	    <input type="text" name="mhm_imagehandling_settings[resizepercent]" id="mhm_imagehandling_settings_resizepercent" class="regular-text" value="<?php echo $options['resizepercent']; ?>" />
		<?php
	}

	function field_minimum_size_to_watermark(  ) { 
		$options = get_option( 'mhm_imagehandling_settings' );
		?>
	    <input type="text" name="mhm_imagehandling_settings[minimumsize]" id="mhm_imagehandling_settings_minimumsize" class="regular-text" value="<?php echo $options['minimumsize']; ?>" />
		<?php
	}

	function field_activate_render(  ) { 
		$options = get_option( 'mhm_imagehandling_settings' );
		?>
		<input type="checkbox" name="mhm_imagehandling_settings[activate]" <?php checked( $options['activate'], 1 ); ?> value="1" />
		<?php
	}

	//////////////////////////////////////////////////

	function section_callback(  ) { 
	
		//echo __( 'This section description', 'mhm-imagehandling' );
	
	}

	//////////////////////////////////////////////////

	function options_page() { 
		?>
		<form action='options.php' method='post'>
			
			<h2>Image handling</h2>

			<?php
			settings_fields( 'pluginPage' );
			do_settings_sections( 'pluginPage' );
			submit_button();
			?>
			
		</form>
		<?php
	}

}

new MHM_Imagehandling();