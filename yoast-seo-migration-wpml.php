<?php
/*
Plugin Name: Yoast Seo to WPML Migration 
Description: A plugin for migration yoast Seo metadatas when migration for wp multig
Plugin URI: https://elementor.com/?utm_source=wp-plugins&utm_campaign=plugin-uri&utm_medium=wp-dash
Author: Senior Dev
Version: 1.0.0
Author URI: https://elementor.com/?utm_source=wp-plugins&utm_campaign=author-uri&utm_medium=wp-dash

Version: 0.1
*/

class YSW_migrationer {

	private $ysw_default_language;
	private $ysw_active_languages;
	private $wpdb;
	private $response = [];
	private $ysw_migration_batch;
	const BATCH_SIZE = 10;

	function __construct() {
	    global $wpdb;
	    $this->wpdb = $wpdb;
		$this->add_hooks();

	}

	public function add_hooks() {
		add_action( 'init', array( $this, 'init' ), 101 );
		add_action( 'admin_menu', array( $this, 'yoast_seo_migration_plugin_setup_menu' ) );
		add_action( 'wp_ajax_ysw_migration_ajx', array( $this, '_ysw_migration' ) );
		add_action( 'wp_ajax_ysa_migration_ajx', array( $this, '_ysa_migration' ) );
    }

	public function init() {
		wp_enqueue_script( 'yswmigration', plugins_url( basename( dirname( __FILE__ ) ) ) . '/js/scripts.js' );
	}
 
	function yoast_seo_migration_plugin_setup_menu(){
	    add_options_page( 'Integration Yoast', 'Integration Yoast', 'manage_options', 'yoast-seo-migration', array(
			$this,
			'yoast_seo_migration_init'
		) );
	}
 
	function yoast_seo_migration_init(){
		?>
	    <div class="wrap">
	        <div id="icon-tools" class="icon32"><br/></div>
	        <h2>
		<?php echo __( 'Yoast Seo To wpml migration', 'ysw_migration' ) ?></h2>

	        <p>
	            <input type="button" id="ysw_migration_start"
	                   value="<?php esc_attr_e( 'Start', 'ysw_migration' ) ?>"
	                   class="button-primary" disabled />
	            &nbsp;<span id="ysw_migration_working"
	                        style="display:none;"><?php _e( 'Working... <br> Remember this is early development version. If you notice any problem <a href="https://wpml.org/forums/" target="_blank">please report in WPML support forum</a>', 'ysw_migration' ) ?></span>
	        </p>


	        <div id="ysw_migration_status"
	             style="max-height:360px;overflow: auto;font-size:10px;background-color: #eee;padding:5px;border:1px solid #ddd;margin-bottom:8px;display:none;"></div>
	     </div>

	    <div class="wrap">
	        <h2>
		<?php echo __( 'Migration to Yoast Seo from All in One Seo ', 'ysa_migration' ) ?></h2>

	        <p>
	            <input type="button" id="ysa_migration_start"
	                   value="<?php esc_attr_e( 'Start', 'ysa_migration' ) ?>"
	                   class="button-primary" />
	            &nbsp;<span id="ysa_migration_working"
	                        style="display:none;"><?php _e( 'Working... <br> Remember this is early development version. If you notice any problem <a href="https://wpml.org/forums/" target="_blank">please report in WPML support forum</a>', 'ysa_migration' ) ?></span>
	        </p>


	        <div id="ysa_migration_status"
	             style="max-height:360px;overflow: auto;font-size:10px;background-color: #eee;padding:5px;border:1px solid #ddd;margin-bottom:8px;display:none;"></div>
	     </div>
	     <?php
	}

	public function _ysa_migration(){
		$origin_seo_rows = $this->get_origin_seo_rows_to_migration();
		if ( $origin_seo_rows ) {
			$origin_seo_results = $this->process_origin_seo_rows_batch( $origin_seo_rows );
			$this->response['messages'][0] = "Migration Successed !";
		} else {
			$this->response['messages'][0] = __( 'No All in One Seo rows to migrate.', 'ysa-migrate' );
		}
		$aio_seo_rows = $this->get_postmeta_aio_rows_to_migration();
		if( $aio_seo_rows ) {
			$aio_seo_results = $this->process_aio_seo_rows_batch( $aio_seo_rows );
			$this->response['messages'][0] = "Migration Successed !";
		}

		echo json_encode( $this->response );
		exit;
	}

	public function get_origin_seo_rows_to_migration(){
		return $this->wpdb->get_results( "
            SELECT * FROM `".$this->wpdb->base_prefix."aioseo_posts`
            WHERE 	title IS NOT NULL
			" );
	}

	private function process_origin_seo_rows_batch( $rows ) {
		foreach ($rows as $key => $row) {
			$yoast_row = $this->wpdb->get_results("
				SELECT * FROM ".$this->wpdb->base_prefix."yoast_indexable
				WHERE object_id = '".$row->post_id."'");
			if(!empty($yoast_row)){
				$vowels = array("| #site_title");
				$row->title = str_replace($vowels, "%%sep%% %%sitename%%", $row->title);
				$id = $this->wpdb->update($this->wpdb->base_prefix."yoast_indexable", array('title' => $row->title, 'description' => $row->description), array('object_id'=> $row->post_id));
				return $id;
			}
		}
	}

	public function get_postmeta_aio_rows_to_migration(){
		return $this->wpdb->get_results( "
            SELECT * FROM ".$this->wpdb->base_prefix."postmeta
            WHERE 	meta_key = '_aioseop_title' OR 
					meta_key = '_aioseop_description'
			" );
	}

	private function process_aio_seo_rows_batch( $rows ) {
		foreach ( $rows as $key => $row ) {
			$rows[$key] = $this->process_aio_postmeta_row( $row );
		}
		return $rows;
	}

	private function process_aio_postmeta_row( $row ) {
		if($row->meta_key === "_aioseo_title"){
			$vowels = array("| #site_title");
			$row->meta_value = str_replace($vowels, "%%sep%% %%sitename%%", $row->meta_value);
			$result = $this->wpdb->update($this->wpdb->base_prefix."postmeta", array("meta_value" => $row->meta_value), array('post_id' => $row->post_id, "meta_key" => "_yoast_wpseo_title"));
			if ($result === FALSE || $result < 1) {
			    $this->wpdb->insert($this->wpdb->base_prefix."postmeta", array("post_id" => $row->post_id, "meta_key" => "_yoast_wpseo_title", "meta_value" => $row->meta_value));
			}
		}
		if($row->meta_key === "_aioseo_description"){
			$result = $this->wpdb->update($this->wpdb->base_prefix."postmeta", array("meta_value" => $row->meta_value), array('post_id' => $row->post_id, "meta_key" => "_yoast_wpseo_metadesc"));
			if ($result === FALSE || $result < 1) {
			    $this->wpdb->insert($this->wpdb->base_prefix."postmeta", array("post_id" => $row->post_id, "meta_key" => "_yoast_wpseo_metadesc", "meta_value" => $row->meta_value));
			}
		}
		return true;
	}

	public function _ysw_migration(){
		$yoast_indexable_rows = $this->get_yoast_indexable_rows_to_miration( self::BATCH_SIZE );
		if ( $yoast_indexable_rows ) {
			$yoast_indexable_results = $this->process_yoast_indexable_rows_batch( $yoast_indexable_rows );
		} else {
			$this->response['messages'][0] = __( 'No yoast_indexable rows to migrate.', 'ysw-migrate' );
		}
		$postmeta_rows = $this->get_postmeta_rows_to_miration( self::BATCH_SIZE );
		if ( $postmeta_rows ) {
			$postmeta_results = $this->process_postmeta_rows_batch( $postmeta_rows );
		} else {
			$this->response['messages'][0] = __( 'No postmeta rows to migrate.', 'postmeta-migrate' );
		}
		$post_rows = $this->get_post_rows_to_miration( self::BATCH_SIZE );
		if ( $post_rows ) {
			$post_results = $this->process_post_rows_batch( $post_rows );
		} else {
			$this->response['messages'][0] = __( 'No post rows to migrate.', 'post-migrate' );
		}
		if( count($yoast_indexable_rows) === count($yoast_indexable_results) && count($postmeta_rows) === count($postmeta_results) && count($post_rows) === count($post_results) )
			$this->response['messages'][0] = "Migration Successed !";

		echo json_encode( $this->response );
		exit;
	}

	public function get_yoast_indexable_rows_to_miration( $limit ){
		return $this->wpdb->get_results( "
            SELECT * FROM ".$this->wpdb->base_prefix."yoast_indexable
            WHERE 	title LIKE '[:%' OR 
					title LIKE '<!--:%' OR 
					description LIKE '[:%' OR 
					description LIKE '<!--:%' OR 
					primary_focus_keyword LIKE '[:%' OR 
					primary_focus_keyword LIKE '<!--:%' OR 
					breadcrumb_title LIKE '[:%' OR 
					breadcrumb_title LIKE '<!--:%'
			" );
	}

	private function process_yoast_indexable_rows_batch( $rows) {
		foreach ( $rows as $key => $row ) {
			$rows[$key] = $this->process_yoast_indexable_row( $row );
		}
		return $rows;
	}

	private function process_yoast_indexable_row( $row ){
		$new_row = array();
		$language_details = apply_filters( 'wpml_post_language_details', NULL, $row->object_id );
		$language_code = $language_details["language_code"];
		$new_row["title"] = $this->regex_data($row->title, $language_code);
		$new_row["description"] = $this->regex_data($row->description, $language_code);
		$new_row["primary_focus_keyword"] = $this->regex_data($row->primary_focus_keyword, $language_code);
		$new_row["breadcrumb_title"] = $this->regex_data($row->breadcrumb_title, $language_code);
		$new_row["title"] = strlen($new_row["title"]) > 0 ? "%%title%% %%page%% %%sep%% %%sitename%%" : $new_row["title"];
		$id = $this->wpdb->update($this->wpdb->base_prefix."yoast_indexable", $new_row, array('id'=> $row->id));
		return $id;
	}

	public function get_postmeta_rows_to_miration( $limit ){
		return $this->wpdb->get_results( "
            SELECT * FROM ".$this->wpdb->base_prefix."postmeta
            WHERE 	meta_value LIKE '[:%' OR 
					meta_value LIKE '<!--:%'
			" );
	}

	private function process_postmeta_rows_batch( $rows ) {
		foreach ( $rows as $key => $row ) {
			$rows[$key] = $this->process_postmeta_row( $row );
		}
		return $rows;
	}

	private function process_postmeta_row( $row ){
		$new_row = array();
		$language_details = apply_filters( 'wpml_post_language_details', NULL, $row->post_id );
		$language_code = $language_details["language_code"];
		$new_row["meta_value"] = $this->regex_data($row->meta_value, $language_code);
		if($row->meta_key === "_yoast_wpseo_title")
			$new_row["meta_value"] = strlen($new_row["meta_value"]) > 0 ? "%%title%% %%page%% %%sep%% %%sitename%%" : $new_row["meta_value"];
		$id = $this->wpdb->update($this->wpdb->base_prefix."postmeta", $new_row, array('meta_id'=> $row->meta_id));
		return $id;
	}

	public function get_post_rows_to_miration( $limit ){
		return $this->wpdb->get_results( "
            SELECT * FROM ".$this->wpdb->base_prefix."posts
            WHERE 	post_title LIKE '[:%' OR 
					post_title LIKE '<!--:%'
			" );
	}

	private function process_post_rows_batch( $rows ) {
		foreach ( $rows as $key => $row ) {
			$rows[$key] = $this->process_post_row( $row );
		}
		return $rows;
	}

	private function process_post_row( $row ){
		$new_row = array();
		$language_details = apply_filters( 'wpml_post_language_details', NULL, $row->ID );
		$language_code = $language_details["language_code"];
		$new_row["post_content"] = $this->regex_data($row->post_content, $language_code);
		$new_row["post_title"] = $this->regex_data($row->post_title, $language_code);
		$id = $this->wpdb->update($this->wpdb->base_prefix."posts", $new_row, array('ID'=> $row->ID));
		return $id;
	}

	function regex_data( $fragment, $language_code) {
		preg_match_all( '#\[:([a-z]{2})\]#ims', $fragment, $language_codes );
		$language_codes     = $language_codes[1];
		$text_contents      = preg_split( '#\[:[a-z]{2}\]#ims', $fragment );
		$_empty             = array_shift( $text_contents );

		$index = array_search($language_code, $language_codes);
		$text_contents = $text_contents[$index];
		$vowels = array("[:]", "<!–-:–->");
		$text_contents = str_replace($vowels, "", $text_contents);

		return $text_contents;
	}
}

$YSW_migrationer = new YSW_migrationer;
