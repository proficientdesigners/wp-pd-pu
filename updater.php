<?php
/**
 * 
 */
class PD_WP_Plugin_Updater
{
	
	public function __construct( $file )
	{
		$this->file = $file;
		add_filter( 'plugins_api', [$this, 'misha_plugin_info'], 20, 3);
		add_filter( 'site_transient_update_plugins', [$this, 'misha_push_update'] );
		add_action( 'upgrader_process_complete', [$this, 'misha_after_update'], 10, 2 );
	}

	/*
	 * $res empty at this step 
	 * $action 'plugin_information'
	 * $args stdClass Object ( [slug] => woocommerce [is_ssl] => [fields] => Array ( [banners] => 1 [reviews] => 1 [downloaded] => [active_installs] => 1 ) [per_page] => 24 [locale] => en_US )
	 */	
	public function misha_plugin_info( $res, $action, $args ){

		/*do nothing if this is not about getting plugin information*/
		if( $action !== 'plugin_information' )
			return false;

		/*do nothing if it is not our plugin	*/
		if( $this->file !== $args->slug )
			return false;

		/*trying to get from cache first*/
		if( false == $remote = get_transient( "misha_upgrade_$this->file" ) ) {

			/*info.json is the file with the actual plugin information on your server*/
			$remote = wp_remote_get( 'http://localhost/wp-plugin-update-test/info.json',
				array(
					'timeout' => 10,
					'headers' => array(
						'Accept' => 'application/json'
					)
				)
			);

			if ( !is_wp_error( $remote ) && isset( $remote['response']['code'] ) && $remote['response']['code'] == 200 && !empty( $remote['body'] ) ) {
				set_transient( "misha_upgrade_$this->file", $remote, 0 ); /*12 hours cache*/
			}

		}

		if( $remote ) {

			$remote = json_decode( $remote['body'] );
			$res = new stdClass();
			$res->name = $remote->name;
			$res->slug = $this->file;
			$res->version = $remote->version;
			$res->tested = $remote->tested;
			$res->requires = $remote->requires;
			$res->author = '<a href="https://proficientdesigners.com">Proficient Designers</a>';
			$res->author_profile = 'https://proficientdesigners.com';
			$res->download_link = $remote->download_url;
			$res->trunk = $remote->download_url;
			$res->last_updated = $remote->last_updated;
			$res->sections = array(
				'description' => $remote->sections->description,
				'installation' => $remote->sections->installation,
				'changelog' => $remote->sections->changelog
				//you can add your custom sections (tabs) here
			);
			if( !empty( $remote->sections->screenshots ) ) {
				$res->sections['screenshots'] = $remote->sections->screenshots;
			}

			/*$res->banners = array(
				'low' => 'https://YOUR_WEBSITE/banner-772x250.jpg',
				'high' => 'https://YOUR_WEBSITE/banner-1544x500.jpg'
			);*/
			return $res;

		}

		return false;

	}


	public function misha_push_update( $transient ){

		if ( empty($transient->checked ) ) {
			return $transient;
		}

		/*trying to get from cache first*/
		if( false == $remote = get_transient( "misha_upgrade_$this->file" ) ) {

			/*info.json is the file with the actual plugin information on your server*/
			$remote = wp_remote_get( 'http://localhost/wp-plugin-update-test/info.json',
				array(
					'timeout' => 10,
					'headers' => array(
						'Accept' => 'application/json'
					)
				)
			);

			if ( !is_wp_error( $remote ) && isset( $remote['response']['code'] ) && $remote['response']['code'] == 200 && !empty( $remote['body'] ) ) {
				set_transient( "misha_upgrade_$this->file", $remote, 0 ); /*12 hours cache*/
			}

		}

		if( $remote ) {

			$remote = json_decode( $remote['body'] );
			if( $remote && version_compare( '1.0', $remote->version, '<' ) && version_compare($remote->requires, get_bloginfo('version'), '<' ) ) {
				$res = new stdClass();
				$res->slug = $this->file;
				$res->plugin = $this->file;
				$res->new_version = $remote->version;
				$res->tested = $remote->tested;
				$res->package = $remote->download_url;
				$res->url = $remote->homepage;
				$res->compatibility = new stdClass();
				$transient->response[$res->plugin] = $res;
				/*$transient->checked[$res->plugin] = $remote->version;*/
			}

		}
		return $transient;
	}

	public function misha_after_update( $upgrader_object, $options ) {
		if ( $options['action'] == 'update' && $options['type'] === 'plugin' )  {
			delete_transient( "misha_upgrade_$this->file" );
		}
	}

}