<?php
/*
Plugin Name: Migration Yoast Seo to WPML
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
        // $this->set_qt_default_language();
        // $this->set_qt_active_languages();

	}

	public function add_hooks() {
		add_action( 'init', array( $this, 'init' ), 101 );
		add_action( 'admin_menu', array( $this, 'yoast_seo_migration_plugin_setup_menu' ) );
		add_action( 'wp_ajax_ysw_migration_ajx', array( $this, '_ysw_migration' ) );
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
	                   class="button-primary" />
	            &nbsp;<span id="ysw_migration_working"
	                        style="display:none;"><?php _e( 'Working... <br> Remember this is early development version. If you notice any problem <a href="https://wpml.org/forums/" target="_blank">please report in WPML support forum</a>', 'ysw_migration' ) ?></span>
	        </p>


	        <div id="ysw_migration_status"
	             style="max-height:360px;overflow: auto;font-size:10px;background-color: #eee;padding:5px;border:1px solid #ddd;margin-bottom:8px;display:none;"></div>
	     </div>
	     <?php
	}

	public function _ysw_migration(){
		$this->response['messages'][] = __( 'Looking for previously migrated yoast seo metakeys.', 'ysw-migration' );
		$rows = $this->get_rows_to_miration( self::BATCH_SIZE );

		if ( $rows ) {
			$results = $this->process_rows_batch( $rows);
		} else {
			$this->response['messages'][] = __( 'No posts to import.', 'qt-import' );
			$this->response['keepgoing']  = 0;
		}

		$this->response['messages'][] = $results;

		echo json_encode( $this->response );
		exit;
	}
	public function get_rows_to_miration($limit){
		$total_rows = $this->wpdb->get_results( "
            SELECT count(*) FROM ".$this->wpdb->base_prefix."yoast_indexable
            WHERE 	title LIKE '[:%' OR 
					title LIKE '<!--:%' OR 
					description LIKE '[:%' OR 
					description LIKE '<!--:%'
			" );
		return $this->wpdb->get_results( "
            SELECT * FROM ".$this->wpdb->base_prefix."yoast_indexable
            WHERE 	title LIKE '[:%' OR 
					title LIKE '<!--:%' OR 
					description LIKE '[:%' OR 
					description LIKE '<!--:%'
			" );
	}
	// public function get_rows_to_miration($limit){
	// 	return $this->wpdb->get_col( "
 //            SELECT ID FROM {$this->wpdb->wp_yoast_indexable} p 
 //            WHERE 	title LIKE '[:%' OR 
	// 				title LIKE '<!--:%' OR 
	// 				description LIKE '[:%' OR 
	// 				description LIKE '<!--:%' 
 //            LIMIT " . $limit . "
 //        " );
	// }


	private function process_rows_batch( $rows) {
		foreach ( $rows as $key => $row ) {
			$rows[$key] = $this->process_row( $row );
		}
		return $rows;
	}

	private function process_row($row){
		$language_details = apply_filters( 'wpml_post_language_details', NULL, $row->object_id ) ;
		$language_code = $language_details["language_code"];
		$origin_yoast_seo_title = $this->regex_data($row->title);
		$origin_yoast_seo_description = $this->regex_data($row->description);
		$origin_yoast_seo_primary_focus_keyword = $this->regex_data($row->primary_focus_keyword);
		$index = array_search($language_code, $origin_yoast_seo_title[0]);
		$yoast_seo_title = $origin_yoast_seo_title[1][$index];
		$yoast_seo_description = $origin_yoast_seo_description[1][$index];
		$yoast_seo_primary_focus_keyword = $origin_yoast_seo_primary_focus_keyword[1][$index];
		$vowels = array("[:]", "<!–-:–->");
		$yoast_seo_title = "%%title%% %%page%% %%sep%% %%sitename%%";
		$yoast_seo_description = str_replace($vowels, "", $yoast_seo_description);
		$yoast_seo_primary_focus_keyword = str_replace($vowels, "", $yoast_seo_primary_focus_keyword);
		$id = $this->wpdb->update($this->wpdb->base_prefix."yoast_indexable", array( 'title' => $yoast_seo_title, 'description' => $yoast_seo_description, 'primary_focus_keyword' => $yoast_seo_primary_focus_keyword, ), array('ID'=> $row->ID));
		return $id;
	}

	function regex_data( $fragment ) {
		preg_match_all( '#\[:([a-z]{2})\]#ims', $fragment, $language_codes );
		$language_codes     = $language_codes[1];
		$text_contents      = preg_split( '#\[:[a-z]{2}\]#ims', $fragment );
		$_empty             = array_shift( $text_contents );

		return array( $language_codes, $text_contents );
	}
}

$YSW_migrationer = new YSW_migrationer;
