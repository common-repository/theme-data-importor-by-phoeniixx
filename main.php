<?php
/*
Plugin Name: One Click Demo Importer By Phoeniixx
Plugin URI: https://www.phoeniixx.com/product/one-click-demo-importer-by-phoeniixx/
Description: This plugin is helpful to import demo content for your theme. It's a one-click demo content importer. You can import content, images, widgets and theme settings of the customizer. It supports themes created by Phoeniixx.
Author: phoeniixx
Author URI:  http://www.phoeniixx.com/
Version: 1.1.3
Text Domain: phoen-theme-data-importer
License: http://www.gnu.org/licenses/gpl-2.0.html
*/

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'PHOEN_PLUGIN_DATA_URL', trailingslashit ( plugins_url('', __FILE__) ) );
define( 'PHOEN_PLUGIN_DATA_PATH', trailingslashit( plugin_dir_path( __FILE__) ) );

class Phoen_Import_Data_Main {
	private $url;
 	private $errors = array();
    private $cache_time = 0;
    private $page_slug = 'phoen-theme-data-importer';
    private $items = array();
	private $allowed_authors = array();   
    private  $is_theme_page = false;
	private $config_data= array();
    private $tgmpa;
	
    function phoen_enqueue_scripts( $hook = '' ){
        $load_script = false;
        if ( strpos( $hook, 'appearance_' ) === 0 ) {
            $load_script = true;
        }

        if ( ! $load_script ) {
            if ( strpos( $hook, 'phoen-theme-data-importer' ) !== false ) {
                $load_script = true;
            }
        }

        $load_script = apply_filters( 'demo_contents_load_scripts', $load_script, $hook );
        if ( ! $load_script ) {
            return false;
        }

        wp_enqueue_style( 'phoen-theme-data-importer', PHOEN_PLUGIN_DATA_URL . 'assets/scss/style.css', false );
      
        wp_enqueue_script( 'underscore');
        wp_enqueue_script( 'phoen-theme-data-importer', PHOEN_PLUGIN_DATA_URL.'assets/js/phoen_main_custom_js.js', array( 'jquery', 'underscore' ) );
        wp_enqueue_script( 'phoen-theme-data-jquery', PHOEN_PLUGIN_DATA_URL.'assets/js/jquery_cookie.js', array( 'jquery') );
        wp_enqueue_media();
        $run = isset( $_REQUEST['import_now'] ) && $_REQUEST['import_now'] == 1 ? 'run' : 'no';
        $themes = $this->phoen_install_themes( $this->is_theme_page ?  false : true );
        $tgm_url = '';
        // Localize the javascript.
        $plugins = array();
		if ( empty( $this->tgmpa ) ) {
            if ( class_exists( 'TGM_Plugin_Activation' ) ) {
                $this->tgmpa = isset( $GLOBALS['tgmpa'] ) ? $GLOBALS['tgmpa'] : TGM_Plugin_Activation::get_instance();
            }
        }
        if ( ! empty( $this->tgmpa ) ) {
            $tgm_url = $this->tgmpa->get_tgmpa_url();
            $plugins = $this->all_required_plugins_data();
        }

        $template_slug  = get_option( 'template' );
        $theme_slug     = get_option( 'stylesheet' );

        wp_localize_script( 'phoen-theme-data-importer', 'phoen_data_variable_arguments', array(
            'tgm_plugin_nonce' 	=> array(
                'update'  	=> wp_create_nonce( 'tgmpa-update' ),
                'install' 	=> wp_create_nonce( 'tgmpa-install' ),
            ),
            'messages' 		        => array(
                'plugin_installed'    => __( '%s installed', 'phoen-theme-data-importer' ),
                'plugin_not_installed'    => __( '%s not installed', 'phoen-theme-data-importer' ),
                'plugin_not_activated'    => __( '%s not activated', 'phoen-theme-data-importer' ),
                'plugin_installing' => __( 'Installing %s...', 'phoen-theme-data-importer' ),
                'plugin_activating' => __( 'Activating %s...', 'phoen-theme-data-importer' ),
                'plugin_activated'  => __( '%s activated', 'phoen-theme-data-importer' ),
            ),
            'tgm_bulk_url' 		    => $tgm_url,
            'ajaxurl'      		    => admin_url( 'admin-ajax.php' ),
            'theme_url'      		=> admin_url( 'themes.php' ),
            'wpnonce'      		    => wp_create_nonce( 'merlin_nonce' ),
            'action_install_plugin' => 'tgmpa-bulk-activate',
            'action_active_plugin'  => 'tgmpa-bulk-activate',
            'action_update_plugin'  => 'tgmpa-bulk-update',
            'plugins'               => $plugins,
            'home'                  => home_url('/'),
            'btn_done_label'        => __( 'All Done! View Site', 'phoen-theme-data-importer' ),
            'failed_msg'            => __( 'Import Failed!', 'phoen-theme-data-importer' ),
            'import_now'            => __( 'Import Now', 'phoen-theme-data-importer' ),
            'importing'             => __( 'Importing...', 'phoen-theme-data-importer' ),
            'activate_theme'        => __( 'Activate Now', 'phoen-theme-data-importer' ),
            'checking_theme'        => __( 'Checking theme', 'phoen-theme-data-importer' ),
            'checking_resource'        => __( 'Checking resource', 'phoen-theme-data-importer' ),
            'confirm_leave'         => __( 'Importing demo content..., are you sure want to cancel ?', 'phoen-theme-data-importer' ),
            'installed_themes'      => $themes,
            'current_theme'         => $template_slug,
            'current_child_theme'   => $theme_slug,
        ) );

    }
	
	public function all_required_plugins_data() {
       if ( empty( $this->tgmpa ) ) {
            if ( class_exists( 'TGM_Plugin_Activation' ) ) {
                $this->tgmpa = isset( $GLOBALS['tgmpa'] ) ? $GLOBALS['tgmpa'] : TGM_Plugin_Activation::get_instance();
            }
        }
        if ( empty( $this->tgmpa ) ) {
            return array();
        }
        $plugins  = array(
            'all'      => array(), // Meaning: all plugins which still have open actions.
            'install'  => array(),
            'update'   => array(),
            'activate' => array(),
        );

        $tgmpa_url = $this->tgmpa->get_tgmpa_url();

        foreach ( $this->tgmpa->plugins as $slug => $plugin ) {
			
            if ( $this->tgmpa->is_plugin_active( $slug ) && false === $this->tgmpa->does_plugin_have_update( $slug ) ) {
                continue;
            } else {
                $plugins['all'][ $slug ] = $plugin;

                $args =   array(
                    'plugin' => $slug,
                    'tgmpa-page' => $this->tgmpa->menu,
                    'plugin_status' => 'all',
                    '_wpnonce' => wp_create_nonce('bulk-plugins'),
                    'action' => '',
                    'action2' => -1,
                    //'message' => esc_html__('Installing', '@@textdomain'),
                );

                $plugin['page_url'] = $tgmpa_url;

                if ( ! $this->tgmpa->is_plugin_installed( $slug ) ) {
                    $plugins['install'][ $slug ] = $plugin;
                    $action = 'tgmpa-bulk-install';
                    $args['action'] = $action;
                    $plugins['install'][ $slug ][ 'args' ] = $args;
                } else {
                    if ( false !== $this->tgmpa->does_plugin_have_update( $slug ) ) {
                        $plugins['update'][ $slug ] = $plugin;
                        $action = 'tgmpa-bulk-update';
                        $args['action'] = $action;
                        $plugins['update'][ $slug ][ 'args' ] = $args;
                    }
                    if ( $this->tgmpa->can_plugin_activate( $slug ) ) {
                        $plugins['activate'][ $slug ] = $plugin;
                        $action = 'tgmpa-bulk-activate';
                        $args['action'] = $action;
                        $plugins['activate'][ $slug ][ 'args' ] = $args;
                    }
                }

            }
        }

        return $plugins;
    }

    function phoen_own_theme( $owner ){
        $owner_passed = false;
        if ( $owner ) {
            $owner = strtolower( sanitize_text_field( $owner ) );
			
			if ( empty( $this->allowed_authors ) ) {
				$theme_owners  = apply_filters( 'demo_contents_allowed_authors', array(
						'phoeniixx'=>'Phoeniixx'
				) );
			}
            
            $owner_passed = isset( $theme_owners[ $owner ] ) ? true : false;
        }

        return apply_filters( 'demo_content_is_allowed_author', $owner_passed, $owner );
    }

	function  phoen_front_end_customiser_panel(){
        ?>
        <script id="tmpl-phoen-theme-data-importer--preview" type="text/html">
            <div id="phoen-theme-data-importer--preview">

                  <span type="button" class="phoen-theme-data-importer-collapse-sidebar button" aria-expanded="true">
                        <span class="collapse-sidebar-arrow"></span>
                        <span class="collapse-sidebar-label"><?php _e( 'Collapse', 'phoen-theme-data-importer' ); ?></span>
                    </span>

                <div id="phoen-theme-data-importer-sidebar">
                    <span class="phoen-theme-data-importer-close"><span class="screen-reader-text"><?php _e( 'Close', 'fdi' ); ?></span></span>

                    <div id="phoen-theme-data-importer-sidebar-topbar">
                        <span class="ft-theme-name">{{ data.name }}</span>
                    </div>

                    <div id="phoen-theme-data-importer-sidebar-content">
                        <# if ( data.demo_version ) { #>
                        <div id="phoen-theme-data-importer-sidebar-heading">
                            <span><?php _e( "Your're viewing demo", 'phoen-theme-data-importer' ); ?></span>
                            <strong class="panel-title site-title">{{ data.demo_name }}</strong>
                        </div>
                        <# } #>
                        <# if ( data.img ) { #>
                            <div class="phoen-theme-data-importer--theme-thumbnail"><img src="{{ data.img }}" alt=""/></div>
                        <# } #>

                        <div class="phoen-theme-data-importer--activate-notice">
                            <?php _e( 'This theme is inactivated. Your must activate this theme before import demo content', 'phoen-theme-data-importer' ); ?>
                        </div>

                        <div class="phoen-theme-data-importer--activate-notice resources-not-found phoen-theme-data-importer-hide">
                            <p class="phoen-theme-data-importer--msg"></p>
                            <div class="phoen-theme-data-importer---upload">
                                <p><button type="button" class="phoen-theme-data-importer--upload-xml button-secondary"><?php _e( 'Upload XML file .xml', 'phoen-theme-data-importer' ); ?></button></p>
                                <p><button type="button" class="phoen-theme-data-importer--upload-json button-secondary"><?php _e( 'Upload config file .json or .txt', 'phoen-theme-data-importer' ); ?></button></p>
                            </div>
                        </div>

                        <div class="phoen-theme-data-importer-import-progress">

                            <div class="phoen-theme-data-importer--step phoen-theme-data-importer-install-plugins phoen-theme-data-importer--waiting">
                                <div class="phoen-theme-data-importer--step-heading"><?php _e( 'Install Recommended Plugins', 'phoen-theme-data-importer' ); ?></div>
                                <div class="phoen-theme-data-importer--status phoen-theme-data-importer--phoen_load_min"></div>
                                <div class="phoen-theme-data-importer--child-steps"></div>
                            </div>

                            <div class="phoen-theme-data-importer--step phoen-theme-data-importer-import-users phoen-theme-data-importer--waiting">
                                <div class="phoen-theme-data-importer--step-heading"><?php _e( 'Import Users', 'phoen-theme-data-importer' ); ?></div>
                                <div class="phoen-theme-data-importer--status phoen-theme-data-importer--waiting"></div>
                                <div class="phoen-theme-data-importer--child-steps"></div>
                            </div>

                            <div class="phoen-theme-data-importer--step phoen-theme-data-importer-import-categories phoen-theme-data-importer--waiting">
                                <div class="phoen-theme-data-importer--step-heading"><?php _e( 'Import Categories', 'phoen-theme-data-importer' ); ?></div>
                                <div class="phoen-theme-data-importer--status phoen-theme-data-importer--completed"></div>
                                <div class="phoen-theme-data-importer--child-steps"></div>
                            </div>

                            <div class="phoen-theme-data-importer--step phoen-theme-data-importer-import-tags phoen-theme-data-importer--waiting">
                                <div class="phoen-theme-data-importer--step-heading"><?php _e( 'Import Tags', 'phoen-theme-data-importer' ); ?></div>
                                <div class="phoen-theme-data-importer--status phoen-theme-data-importer--completed"></div>
                                <div class="phoen-theme-data-importer--child-steps"></div>
                            </div>

                            <div class="phoen-theme-data-importer--step phoen-theme-data-importer-import-taxs phoen-theme-data-importer--waiting">
                                <div class="phoen-theme-data-importer--step-heading"><?php _e( 'Import Taxonomies', 'phoen-theme-data-importer' ); ?></div>
                                <div class="phoen-theme-data-importer--status phoen-theme-data-importer--waiting"></div>
                                <div class="phoen-theme-data-importer--child-steps"></div>
                            </div>

                            <div class="phoen-theme-data-importer--step  phoen-theme-data-importer-import-posts phoen-theme-data-importer--waiting">
                                <div class="phoen-theme-data-importer--step-heading"><?php _e( 'Import Posts & Media', 'phoen-theme-data-importer' ); ?></div>
                                <div class="phoen-theme-data-importer--status phoen-theme-data-importer--waiting"></div>
                                <div class="phoen-theme-data-importer--child-steps"></div>
                            </div>

                            <div class="phoen-theme-data-importer--step phoen-theme-data-importer-import-theme-options phoen-theme-data-importer--waiting">
                                <div class="phoen-theme-data-importer--step-heading"><?php _e( 'Import Options', 'phoen-theme-data-importer' ); ?></div>
                                <div class="phoen-theme-data-importer--status phoen-theme-data-importer--waiting"></div>
                                <div class="phoen-theme-data-importer--child-steps"></div>
                            </div>

                            <div class="phoen-theme-data-importer--step phoen-theme-data-importer-import-widgets phoen-theme-data-importer--waiting">
                                <div class="phoen-theme-data-importer--step-heading"><?php _e( 'Import Widgets', 'phoen-theme-data-importer' ); ?></div>
                                <div class="phoen-theme-data-importer--status phoen-theme-data-importer--waiting"></div>
                                <div class="phoen-theme-data-importer--child-steps"></div>
                            </div>

                            <div class="phoen-theme-data-importer--step  phoen-theme-data-importer-import-customize phoen-theme-data-importer--waiting">
                                <div class="phoen-theme-data-importer--step-heading"><?php _e( 'Import Customize Settings', 'phoen-theme-data-importer' ) ?></div>
                                <div class="phoen-theme-data-importer--status phoen-theme-data-importer--waiting"></div>
                                <div class="phoen-theme-data-importer--child-steps"></div>
                            </div>
                        </div>

                    </div><!-- /.phoen-theme-data-importer-sidebar-content -->

                    <div id="phoen-theme-data-importer-sidebar-footer">
                        <a href="#" " class="phoen-theme-data-importer--import-now button button-primary"><?php _e( 'Import Now', 'phoen-theme-data-importer' ); ?></a>
                    </div>

                </div>
                <div id="phoen-theme-data-importer-viewing">
                    <iframe src="{{ data.demoURL }}"></iframe>
                </div>
            </div>
        </script>
        <?php
    }
	
	function get_details_link( $theme_slug, $theme_name ) {
		
		$phoen_main=str_replace("-","",$theme_slug);
		
		if (strpos($theme_slug, 'pro') !== false) {
			
			$phoen_main=str_replace("-","",$theme_slug);
			
			$link='http://'.$phoen_main.'.phoeniixxdemo.com/';
			
		}else{
			
			$phoen_main=str_replace("-","",$theme_slug);
			
			$link='http://'.$phoen_main.'free.phoeniixxdemo.com/';
		}
	
        return apply_filters( 'demo_contents_get_details_link', $link, $theme_slug, $theme_name );
    }
	
	function phoen_install_themes( $all_demos = true ){

        //$key = 'demo_contents_get_themes_'.( (int ) $all_demos );
		$key = 'demo_contents_get_themes_'.( (int ) $all_demos );

        $cache = wp_cache_get( $key );
        if ( false !== $cache ) {
            $this->items = $cache;
        }
        // If already setup
        if ( ! empty( $this->items) ) {
            // return $this->items;
        }

        // if have filter for list themes
        $this->items = apply_filters( 'demo_contents_get_themes', array(), $all_demos, $this );
        if ( ! empty( $this->items) ) {
            return $this->items;
        }
		
        $active_theme_slug = get_option( 'template' );
        $child_theme_slug    = get_option( 'stylesheet' );
			
		$phoen_main=str_replace("-","",$active_theme_slug);
		
		if (strpos($active_theme_slug, 'pro') !== false) {
			
			$phoen_main=str_replace("-","",$active_theme_slug);
			
			$phoen_demo_url='http://'.$phoen_main.'.phoeniixxdemo.com/';
			
		}else{
			
			$phoen_main=str_replace("-","",$active_theme_slug);
			
			$phoen_demo_url='http://'.$phoen_main.'free.phoeniixxdemo.com/';
		}
	
        $current_active_slugs = array( $active_theme_slug );

        if ( $all_demos ) {
            $installed_themes = wp_get_themes();
        } else {
            $_activate_theme = wp_get_theme( $active_theme_slug );
            $installed_themes = array(  );
            $installed_themes[ $active_theme_slug ] = $_activate_theme;
        }

        $list_themes = array();

        // Listing installed themes
        foreach ( ( array )$installed_themes as $theme_slug => $theme ) {
            if ( ! $this->phoen_own_theme($theme->get('Author'))) {
                continue;
            }
            $is_activate = false;
            if(  $theme_slug == $active_theme_slug || $theme_slug == $child_theme_slug ) {
                $is_activate = true;
            }
            $list_themes[ $theme_slug ] = array(
                'slug'              => $theme_slug,
                'name'              => $theme->get('Name'),
                'screenshot'        => $theme->get_screenshot(),
                'author'            => $theme->get('Author'),
                'activate'          => $is_activate,
                'demo_version'      => '',
                'demo_url'          => $phoen_demo_url,
                'demo_version_name' => '',
                'is_plugin'         => false
            );
        }
        $current_theme = false;
     
        if (  isset( $list_themes[ $active_theme_slug ]  ) ) {
            $current_theme = $list_themes[ $active_theme_slug ];
            unset( $list_themes[ $active_theme_slug ] );
        }
        // Move current theme to top
        if ( $current_theme ) {
            $current_theme['activate'] = true;
            $list_themes = array( $active_theme_slug => $current_theme ) + $list_themes;
        }

        $this->items = $list_themes;
        wp_cache_set( $key, $this->items );

        return $this->items;
    }
	
	function phoen_loop_theme( $theme ){
        ?>
        <div class="theme <?php echo  (  $theme['activate'] ) ? 'phoen-theme-data-importer--current-theme' : ''; ?>" tabindex="0">
          
			<div class="theme-screenshot">
                <img src="<?php echo esc_url($theme['screenshot']); ?>" alt="">
            </div>
            <?php if ( $theme['activate'] ) { ?>
                <span class="more-details"><?php _e('Current Theme', 'phoen-theme-data-importer'); ?></span>
            <?php } else { ?>
                <span class="more-details"><?php _e('View Details', 'phoen-theme-data-importer'); ?></span>
            <?php } ?>

            <div class="theme-author"><?php sprintf(__('by %s', 'phoen-theme-data-importer'),$theme['author'] ); ?></div>
            <div class="theme-name"><span><?php echo  ( $theme['demo_version_name'] ) ? esc_html( $theme['demo_version_name']  ) : esc_html($theme['name']); ?></span>
				<div class="theme-actions">
					<a
						data-theme-slug="<?php echo esc_attr( $theme['slug'] ); ?>"
						data-demo-version="<?php echo esc_attr( $theme['demo_version'] ); ?>"
						data-demo-version-name="<?php echo esc_attr( $theme['demo_version_name'] ); ?>"
						data-name="<?php echo esc_html($theme['name']); ?>"
						data-demo-url="<?php echo esc_attr( $theme['demo_url'] ); ?>"
						class="phoen-theme-data-importer--preview-theme-btn button button-primary customize"
						href="#"
					><?php _e('Start Import', 'phoen-theme-data-importer'); ?></a>
				</div>
			</div>
            
        </div>
		
		<style>
			.theme-browser .theme-name .theme-actions {
				margin: 0 10px 0 0;
				padding: 0;
			}
			
			.demo-import-tab-content .theme-browser .theme .theme-name {
				position: relative;
			}
			
			
			
		</style>
		
        <?php
    }
	
	function phoen_theme_list( $all_demos = false ){

        $this->phoen_install_themes( $all_demos, true );
		
        $number_theme = count( $this->items );
        ?>
        <div class="theme-browser rendered phoen-theme-data-importer-themes-listing">
            <div class="themes wp-clearfix">
                <?php
                if ( $number_theme > 0 ) {
                    if ( has_action( 'demo_contents_before_themes_listing' ) ) {
                        do_action( 'demo_contents_before_themes_listing', $this );
                    } else {

                        // Listing installed themes
                        foreach (( array ) $this->items as $theme_slug => $theme ) {
                            $this->phoen_loop_theme( $theme );
                        }

                        do_action('demo_content_themes_listing');
                    } // end check if has actions
                } else {
                    ?>
                    <div class="phoen-theme-data-importer-no-themes">
                        <?php _e( 'No Themes Found', 'phoen-theme-data-importer' ); ?>
                    </div>
                    <?php
                }
                ?>
            </div><!-- /.Themes -->
        </div><!-- /.theme-browser -->
        <?php
    }
	
	//...**********panel************//
	 function get_plugin_information( $hook ) {

        if( $hook != 'themes.php' ) {
            return;
        }
        if ( ! isset( $_REQUEST['__get_plugin_information'] ) ) {
            return;
        }

        $plugins = $this->all_required_plugins_data();
        ob_clean();
        ob_flush();

        ob_start();
        wp_send_json_success( $plugins );
        die();
    }
	
	 function phoen_importer_ajax_main_func(){	
	
        if ( ! class_exists( 'Merlin_WXR_Parser' ) ) {
            require PHOEN_PLUGIN_DATA_PATH. 'inc/class-merlin-xml-parser.php' ;
        }

        if ( ! class_exists( 'Merlin_Importer' ) ) {
            require PHOEN_PLUGIN_DATA_PATH .'inc/class-merlin-importer.php';
        }

        if ( ! current_user_can( 'import' ) ) {
            wp_send_json_error( __( "You have not permissions to import.", 'phoen-theme-data-importer' ) );
        }

        $doing = isset( $_REQUEST['doing'] ) ? sanitize_text_field( $_REQUEST['doing'] ) : '';
        if ( ! $doing ) {
            wp_send_json_error( __( "No actions to do", 'phoen-theme-data-importer' ) );
        }

        // Current theme for import
        $current_theme = isset( $_REQUEST['current_theme'] ) ? $_REQUEST['current_theme'] : false;

        $current_theme = wp_parse_args( $current_theme, array(
            'name' => '',
            'slug' => '',
            'demo_version' => '',
            'demo_name' => '',
            'activate' => '',
            'xml_id' => '',
            'json_id' => ''
        ) );
        $active_theme_slug = false;
        $current_theme_demo_version = false;
        if ( ! $current_theme || ! is_array( $current_theme ) || ! isset( $current_theme['slug'] ) || ! $current_theme['slug'] ) {
            wp_send_json_error( __( 'Not theme selected', 'phoen-theme-data-importer' ) );
        }

        $active_theme_slug = sanitize_text_field( $current_theme['slug'] );
        if ( $current_theme['demo_version'] ) {
            $current_theme_demo_version = sanitize_text_field( $current_theme['demo_version'] );
        }

        $themes = $this->phoen_install_themes();
        // if is_activate theme
        if ( $doing == 'activate_theme' ) {
            switch_theme( $active_theme_slug );
            wp_send_json_success( array( 'msg' => sprintf( __( '%s theme activated', 'phoen-theme-data-importer' ), $themes[ $active_theme_slug ]['name'] ) ) );
        }

        if ( $doing == 'phoen_all_cloud_files' ){
			
           $file_data = $this->phoen_data_files( $current_theme );
		   
		   
	/* print_r($file_data);die(); */
			 
            if ( ! $file_data || empty( $file_data ) ) {
                wp_send_json_error( sprintf( __( 'Demo data not found for <strong>%s</strong>. However you can import demo content by upload your demo files below.', 'phoen-theme-data-importer' ) , $themes[ $active_theme_slug ]['name'] ) );
            } else {
                wp_send_json_success( __( 'Demo data ready for import.', 'phoen-theme-data-importer' ) );
            }
        }
		
        /// Check theme activate
        if ( ! isset( $themes[ $active_theme_slug ] ) ) {
            wp_send_json_error( __( 'This theme have not installed.', 'phoen-theme-data-importer' ) );
        }


        //wp_send_json_success(); // just for test
       $file_data = $this->phoen_data_files( $current_theme );
       
	   
	   
	   if ( ! $file_data || empty( $file_data ) ) {
           wp_send_json_error( array( 'type' => 'no-files', 'msg' => __( 'Dummy data files not found', 'phoen-theme-data-importer' ), 'files' => $file_data  ) );
       }

       $transient_key = '_demo_content_'.$active_theme_slug;
	   
       if ( $current_theme_demo_version ) {
           $transient_key .= '-demos-'.$current_theme_demo_version;
       }


        $merlin_obj = new Merlin_Importer();
		
		$content = get_transient( $transient_key );
  
	  if ( ! $content ) {
            $parser = new Merlin_WXR_Parser();
            $content = $parser->parse( $file_data['xml'] );
           set_transient( $transient_key, $content, DAY_IN_SECONDS );
		   	
        }

        if ( is_wp_error( $content ) ) {
            wp_send_json_error( __( 'Dummy content empty', 'phoen-theme-data-importer' ) );
        }

        // Setup config
        $option_config = get_transient( $transient_key.'-json' );
	
        if ( false === $option_config || $option_config == '' ) {
			
            if ( file_exists( $file_data['xml']  ) ) {
				
                global $wp_filesystem;
                WP_Filesystem();
                $file_contents = $wp_filesystem->get_contents( $file_data['json'] );
			
				$option_config = json_decode( $file_contents, true );
				 /* print_r($option_config);
				echo "import_widget"; */
				set_transient( $transient_key.'-json',  $option_config, DAY_IN_SECONDS ) ;
            }
			
        }

        switch ( $doing ) {
            case 'phoen_users_import':
                if ( ! empty( $content['users'] ) ) {
                    $merlin_obj->phoen_users_import( $content['users'] );
				
					
                }
                break;

            case 'phoen_categories_import':
                if ( ! empty( $content['categories'] ) ) {
                    $merlin_obj->importTerms( $content['categories'] );
                }
                break;
            case 'phoen_tags_import':
                if ( ! empty( $content['tags'] ) ) {
                    $merlin_obj->importTerms( $content['tags'] );
                }
                break;
            case 'phoen_taxs_import':
                if ( ! empty( $content['terms'] ) ) {
                    $merlin_obj->importTerms( $content['terms'] );
                }
                break;
            case 'phoen_posts_import':
                if ( ! empty( $content['posts'] ) ) {
                    $merlin_obj->importPosts( $content['posts'] );
                }
                $merlin_obj->remapImportedData();
                do_action( 'phoen_importer_action_posts_completed', $this, $merlin_obj );

                break;

            case 'phoen_theme_option':
                if ( isset( $option_config['options'] ) ){
                    $this->all_setting_data( $option_config['options'] );
                }

                // Setup Pages
                $processed_posts = get_transient('_wxr_imported_posts') ? get_transient('_wxr_imported_posts') : array();
                if ( isset( $option_config['pages'] ) ){
                    foreach ( $option_config['pages']  as $key => $id ) {
                        $val = isset( $processed_posts[ $id ] )  ? $processed_posts[ $id ] : null ;
                        update_option( $key, $val );
                    }
                }

                do_action( 'phoen_importer_action_theme_options_completed', $this, $merlin_obj );

                break;
				
            case 'phoen_widgets_import':
                $this->config_data = $option_config;
		
                if ( isset( $option_config['widgets'] ) ){
                  
                    if ( ! isset( $this->config_data['widgets_config'] ) ) {
                        $this->config_data['widgets_config'] = array();
                    }
                    $merlin_obj->importWidgets( $option_config['widgets'], $this->config_data['widgets_config'] );
                    do_action( 'phoen_importer_action_widgets_completed', $this, $merlin_obj );
                }
                break;

            case 'phoen_customize_import':
			
                if ( isset( $option_config['theme_mods'] ) ){
                  
					$all_data = $merlin_obj->phoen_import_theme_data( $option_config['theme_mods'] );
					
                    if ( isset( $option_config['customizer_keys'] ) ) {
                        foreach ( ( array ) $option_config['customizer_keys'] as $k => $list_key ) {
                            $this->phoen_reset_ides( $k, $list_key );
                        }
                    }
                }
				
                do_action( 'phoen_importer_action_customize_completed', $this, $merlin_obj );

                // Remove transient data if is live mod
                if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
                    $merlin_obj->importEnd();
                    // Delete file
                    $file_key = '_demo_contents_file_'.$active_theme_slug;
                    if ( $current_theme_demo_version ) {
                        $file_key .= '-demos-'.$current_theme_demo_version;
                    }
                    $files = get_transient( $file_key );
                    if ( is_array( $files ) ) {
                        foreach ( $files as $file_id ) {
                            wp_delete_attachment( $file_id );
                        }
                    }
                    delete_transient( $transient_key );
                    delete_transient( $transient_key.'-json' );
                }

                break;

        } // end switch action

        wp_send_json_success( );

    }
	
	function all_setting_data( $options ){
        if ( empty( $options ) ) {
            return ;
        }
        foreach ( $options as $option_name => $ops ) {
            update_option( $option_name, $ops );
        }
    }
	 function phoen_reset_ides( $theme_mod_name = null, $list_keys, $url ='', $option_type = 'theme_mod' ){

        $processed_posts = get_transient('_wxr_imported_posts') ? get_transient('_wxr_imported_posts') : array();
        if ( ! is_array( $processed_posts ) ) {
            $processed_posts = array();
        }

        // Setup service
        $data = get_theme_mod( $theme_mod_name );
        if (  is_string( $list_keys ) ) {
            switch( $list_keys ) {
                case 'media':
                    $new_data = $processed_posts[ $data ];
                    if ( $option_type == 'option' ) {
                        update_option( $theme_mod_name , $new_data );
                    } else {
                        set_theme_mod( $theme_mod_name , $new_data );
                    }
                    break;
            }
            return;
        }

        if ( is_string( $data ) ) {
            $data = json_decode( $data, true );
        }
        if ( ! is_array( $data ) ) {
            return false;
        }
        if ( ! is_array( $processed_posts ) ) {
            return false;
        }

        if ( $url ) {
            $url = trailingslashit( $this->config_data['home_url'] );
        }

        $home = home_url('/');


        foreach ($list_keys as $key_info) {
            if ($key_info['type'] == 'post' || $key_info['type'] == 'page') {
                foreach ($data as $k => $item) {
                    if (isset($item[$key_info['key']]) && isset ($processed_posts[$item[$key_info['key']]])) {
                        $data[$k][$key_info['key']] = $processed_posts[ $item[$key_info['key']] ];
                    }
                }
            } elseif ($key_info['type'] == 'media') {

                $main_key = $key_info['key'];
                $sub_key_id = 'id';
                $sub_key_url = 'url';
                if ($main_key) {

                    foreach ($data as $k => $item) {
                        if ( isset ($item[$main_key]) && is_array($item[$main_key])) {
                            if (isset ($item[$main_key][$sub_key_id]) ) {
                                $data[$k][$main_key][$sub_key_id] = $processed_posts[ $item[$main_key] [$sub_key_id] ];
                            }
                            if (isset ($item[$main_key][$sub_key_url])) {
                                $data[$k][$main_key][$sub_key_url] = str_replace($url, $home, $item[$main_key][$sub_key_url]);
                            }
                        }
                    }

                }
            }
        }

        if ( $option_type == 'option' ) {
            update_option( $theme_mod_name , $data );
        } else {
            set_theme_mod( $theme_mod_name , $data );
        }

    }
	
	 function phoen_data_files( $args = array() ) {
		 
        $args = wp_parse_args( $args, array(
            'slug' => '',
            'demo_version' => '',
            'xml_id' => '',
            'json_id' => ''
        ) );

        $theme_name = $args['slug'];
		
        $demo_version = $args['demo_version'];
		
        if ( $args['xml_id'] ) {
            return array( 'xml' => get_attached_file( $args['xml_id'] ), 'json' => get_attached_file( $args['json_id'] ) );
        }

        if ( ! $theme_name ) {
            return false;
        }

         $sub_path = $theme_name;
	
        if ( $demo_version ) {
            $sub_path .= '/demos/'.$demo_version;
        }
        $prefix_name = str_replace( '/', '-', $sub_path );

        $xml_file_name =  $prefix_name.'-demo.xml' ;
        $config_file_name = $prefix_name.'-config.json';
	
        $xml_file = false;
        $config_file = false;

        $files_data = get_transient( '_demo_contents_file_'.$prefix_name );

        // If have cache
        if ( ! empty( $files_data ) ) {
            $files_data = wp_parse_args( $files_data, array( 'xml' => '', 'json' => '' ) );
            $xml_file = get_attached_file( $files_data['xml'] );
            $config_file = get_attached_file( $files_data['json']  );
            if ( ! empty( $xml_file ) ) {
                return  array( 'xml' => $xml_file, 'json' => $config_file );
            }
        }
		
        $xml_id = $this->cloud_data_file( "http://democontentphoeniixx.com/wp-content/uploads/2018/10/$prefix_name-demo.xml",  $xml_file_name );
		
        if ( $xml_id ) {
            $xml_file = get_attached_file( $xml_id );
        }

	   $config_id = $this->cloud_data_file( "http://democontentphoeniixx.com/wp-content/uploads/2018/10/$prefix_name-config.json",  $config_file_name );
		
        if ( $config_id ) {
            $config_file = get_attached_file( $config_id );
        }
		
		
        if ( ! empty( $xml_file ) ) {
            set_transient( '_demo_contents_file_'.$prefix_name, array( 'xml' => $xml_id, 'json' => $config_id ) );
            return  array( 'xml' => $xml_file, 'json' => $config_file );
        }

        return false;

    }
	//---------process--
	
    function __construct(){
		
        add_action('admin_footer', array($this, 'phoen_front_end_customiser_panel'));
      
        add_action('admin_enqueue_scripts', array($this, 'phoen_enqueue_scripts'));

        $active_theme_slug = get_option( 'template' );
		
        add_action( $active_theme_slug.'_phoen_importer_tab_main', array( $this, 'phoen_theme_list' ), 35 );

		add_action('wp_ajax_phoen_data_ajax_func', array($this, 'phoen_importer_ajax_main_func'));
		add_action('admin_enqueue_scripts', array($this, 'get_plugin_information'), 900, 1);
		     
    } 

    function media_handle_sideload( $file_array, $post_id, $desc = null, $post_data = array(), $save_attachment = true ) {
        $overrides = array(
            'test_form'=>false,
            'test_type'=>false
        );

        $time = current_time( 'mysql' );
        if ( $post = get_post( $post_id ) ) {
            if ( substr( $post->post_date, 0, 4 ) > 0 )
                $time = $post->post_date;
        }

        $file = wp_handle_sideload( $file_array, $overrides, $time );
        if ( isset($file['error']) )
            return new WP_Error( 'upload_error', $file['error'] );

        $url = $file['url'];
        $type = $file['type'];
        $file = $file['file'];
        $title = preg_replace('/\.[^.]+$/', '', basename($file));
        $content = '';

        if ( $save_attachment ) {
            if (isset($desc)) {
                $title = $desc;
            }

            // Construct the attachment array.
            $attachment = array_merge(array(
                'post_mime_type' => $type,
                'guid' => $url,
                'post_parent' => $post_id,
                'post_title' => $title,
                'post_content' => $content,
            ), $post_data);

            // This should never be set as it would then overwrite an existing attachment.
            unset($attachment['ID']);

            // Save the attachment metadata
            $id = wp_insert_attachment($attachment, $file, $post_id);

            return $id;
        } else {
            return $file;
        }
    } 

    function cloud_data_file( $url, $name = '', $save_attachment = true ){
        if ( ! $url || empty ( $url ) ) {
            return false;
        }
        // These files need to be included as dependencies when on the front end.
        require_once (ABSPATH . 'wp-admin/includes/image.php');
        require_once (ABSPATH . 'wp-admin/includes/file.php');
        require_once (ABSPATH . 'wp-admin/includes/media.php');
        $file_array = array();
        // Download file to temp location.
        $file_array['tmp_name'] = download_url( $url );

        // If error storing temporarily, return the error.
        if ( empty( $file_array['tmp_name'] ) || is_wp_error( $file_array['tmp_name'] ) ) {
            return false;
        }

        if ( $name ) {
            $file_array['name'] = $name;
        } else {
            $file_array['name'] = basename( $url );
        }
        // Do the validation and storage stuff.
        $file_path_or_id = $this->media_handle_sideload( $file_array, 0, null, array(), $save_attachment );

        // If error storing permanently, unlink.
        if ( is_wp_error( $file_path_or_id ) ) {
            @unlink( $file_array['tmp_name'] );
            return false;
        }
        return $file_path_or_id;
    }

}

add_action( 'activated_plugin', 'phoen_demo_importer_redirect', 90, 2 );

function phoen_demo_importer_redirect( $plugin, $network_wide = false){
	
	if ( ! $network_wide &&  $plugin == plugin_basename( __FILE__ ) ) {
	
 	$template_slug = get_option('template');
	
	$temp_array=array('craze','eezy-store','foody','jstore','news-prime','seofication','business-booster');
	
	$url = add_query_arg(
            array(
                'page' => "$template_slug-pro",
                'importer' => 'phoen-data-importer',
                'active_class' => '3',
            ),
            admin_url('themes.php')
        );
		if(isset($temp_array) && in_array($template_slug,$temp_array)){
			wp_redirect($url);
			die();
		}
	}
			
}

if ( is_admin() ) {
    function phoen_main_init(){
        new Phoen_Import_Data_Main();
    }
    add_action( 'plugins_loaded', 'phoen_main_init' );
}

// Support Upload XML file
add_filter('upload_mimes', 'phoen_allow_files');
function phoen_allow_files($mimes)
{
    if ( current_user_can( 'upload_files' ) ) {
		$mimes = array_merge($mimes, array(
			'xml' => 'application/xml',
			'json' => 'application/json'
		));
    }
    return $mimes;
}
