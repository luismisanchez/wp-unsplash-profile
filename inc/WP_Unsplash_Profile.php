<?php
	
	use GuzzleHttp\Client;
	use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Uri;
use Twig\Environment;
	use Twig\Error\LoaderError;
	use Twig\Error\RuntimeError;
	use Twig\Error\SyntaxError;
	use Twig\Extension\DebugExtension;
	use Twig\Loader\FilesystemLoader;
	
	class WP_Unsplash_Profile {
		
		/**
		 * @var void
		 */
		private $settings;
		
		/**
		 * @var Environment
		 */
		private $twig;
		/**
		 * @var string
		 */
		private $host;
		/**
		 * @var Client
		 */
		private $client;
		/**
		 * @var string
		 */
		private $method;
		/**
		 * @var string
		 */
		private $uri;
		
		private $wp_unsplash_get_user_photos_response;
		
		public function __construct() {
			
			add_action( 'admin_menu', array( $this, 'wp_unsplash_add_admin_menu' ) );
			add_action( 'admin_init', array( $this, 'wp_unsplash_init_settings'  ) );
			$this->host = $_SERVER['HTTP_HOST'];
			$this->settings = get_option( 'wp_unsplash_settings' );
			$number = $this->settings['number_photos'];
			if (!$this->settings['number_photos']) {
				$number = 10;
			}
			
			if ($this->settings['access_key']) {
				$base_uri = new Uri('https://api.unsplash.com');
				$this->method = 'GET';
				$this->uri = $base_uri->withPath('users/' . $this->settings['user_name'] . '/photos/?client_id=' . $this->settings['access_key'] . '&per_page=' . $number);
			} else {
				$base_uri = new Uri('https://wp-unsplash-profile.wp-api.dev');
				$this->method = 'POST';
				$this->uri = $base_uri->withPath('/wp-json/wp-unsplash-proxy/v1/' . $this->settings['user_name'] . '/' . $number . '/' . $this->host);
			}
			
			try {
				$this->client = new Client([
					'base_uri' => $base_uri,
				]);

			} catch ( ClientException $e) {
				wp_die($e->getCode());
			}
			
			$loader = new FilesystemLoader(plugin_dir_path( __FILE__ ) . '../templates');
			$this->twig = new Environment($loader, [
				'debug' => false,
				'cache' => plugin_dir_path( __FILE__ ) . '../templates/compilation_cache',
			]);
			$this->twig->addExtension(new DebugExtension());
			
			add_action('wp_enqueue_scripts', array($this, 'wp_unsplash_enqueue_assets'));
			add_shortcode('wp_unsplash_profile', array($this, 'wp_unsplash_shortcode'));
			
			if ($this->settings['user_name']) {
				$this->wp_unsplash_get_user_photos();
			}

		}
		
		public function wp_unsplash_enqueue_assets() {
			
			//styles
			wp_enqueue_style( 'wp-unsplash-profile-lightgallery',  plugins_url() . '/wp-unsplash-profile/assets/css/lightgallery.min.css' );
			wp_enqueue_style( 'wp-unsplash-profile',  plugins_url() . '/wp-unsplash-profile/assets/css/styles.css' );
			//scripts
			wp_enqueue_script( 'wp-unsplash-profile-isotope',  plugins_url() . '/wp-unsplash-profile/assets/js/isotope.pkgd.min.js', array('jquery'), false, true );
			wp_enqueue_script( 'wp-unsplash-profile-imagesLoaded',  plugins_url() . '/wp-unsplash-profile/assets/js/imagesloaded.pkgd.min.js', array('wp-unsplash-profile-isotope'), false, true );
			wp_enqueue_script( 'wp-unsplash-profile-lightgallery',  plugins_url() . '/wp-unsplash-profile/assets/js/lightgallery.min.js', array('jquery'), false, true );
			wp_enqueue_script( 'wp-unsplash-profile-scripts',  plugins_url() . '/wp-unsplash-profile/assets/js/scripts.js', array('wp-unsplash-profile-imagesLoaded','wp-unsplash-profile-lightgallery'), false, true );
			
		}

		public function wp_unsplash_get_user_photos() {

			try {
				$this->wp_unsplash_get_user_photos_response = json_decode($this->client->request( $this->method, $this->uri )->getBody()->getContents());
			} catch ( ClientException $e) {
				return $e->getCode();
			}

		}

		public function wp_unsplash_shortcode() {
			
			if (empty($this->wp_unsplash_get_user_photos_response) || $this->wp_unsplash_get_user_photos_response === 404) {
				return '<p>' . __( 'Ooops! No photos returned. Please review Username and API Key provided in plugin settings.', 'wp-unsplash-profile' ) . $this->wp_unsplash_get_user_photos_response . '</p>';
			}
			
			if ($this->wp_unsplash_get_user_photos_response === 401) {
				return '<p>' . __( 'Ooops! Something went wrong. Please review Access Key provided in plugin settings.', 'wp-unsplash-profile' ) . '</p>';
			}
		
			if ($this->settings['user_name']) {
				
				try {
					$template = $this->twig->load( 'wp_unsplash_profile.twig' );
					return $template->render( [
						'photos'   => $this->wp_unsplash_get_user_photos_response,
						'settings' => $this->settings
					] );
				} catch ( LoaderError $e ) {
					return 'LoaderError: ' . $e->getMessage();
				} catch ( RuntimeError $e ) {
					return 'RuntimeError: ' . $e->getMessage();
				} catch ( SyntaxError $e ) {
					return 'SyntaxError: ' . $e->getMessage();
				}
				
			} else {
				return '<p>' . __( 'Something went wrong. Please review plugin settings and provide a valid Username at least.', 'wp-unsplash-profile' ) . '</p>';
			}
			
		}
		
		public function wp_unsplash_add_admin_menu() {
			
			add_menu_page(
				esc_html__( 'WordPress Unsplash Profile Plugin', 'wp-unsplash-profile' ),
				esc_html__( 'Unsplash Profile', 'wp-unsplash-profile' ),
				'manage_options',
				'wp-unsplash-profile',
				array( $this, 'wp_unsplash_page_layout' ),
				'dashicons-camera',
				10
			);
			
		}
		
		public function wp_unsplash_init_settings() {
			
			register_setting(
				'wp_unsplash_settings_group',
				'wp_unsplash_settings'
			);
			
			add_settings_section(
				'wp_unsplash_settings_section',
				__('Settings', 'wp-unsplash-profile'),
				'',
				'wp_unsplash_settings'
			);
			
			add_settings_field(
				'user_name',
				__( 'Unsplash Username', 'wp-unsplash-profile' ),
				array( $this, 'wp_unsplash_render_user_name_field' ),
				'wp_unsplash_settings',
				'wp_unsplash_settings_section'
			);
			
			add_settings_field(
				'access_key',
				__( 'Access Key', 'wp-unsplash-profile' ),
				array( $this, 'wp_unsplash_render_access_key_field' ),
				'wp_unsplash_settings',
				'wp_unsplash_settings_section'
			);
			
			add_settings_field(
				'number_photos',
				__( 'Number of photos', 'wp-unsplash-profile' ),
				array( $this, 'wp_unsplash_render_number_photos_field' ),
				'wp_unsplash_settings',
				'wp_unsplash_settings_section'
			);
			
		}
		
		public function wp_unsplash_page_layout() {
			
			// Check required user capability
			if ( !current_user_can( 'manage_options' ) )  {
				wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-unsplash-profile' ) );
			}
			
			// Admin Page Layout
			echo '<div class="wrap">' . "\n";
			echo '	<h1>' . get_admin_page_title() . '</h1>' . "\n";
			
			echo '<div class="notice notice-warning" style="margin-top: 20px;">';
			echo '<p class="description">' . __( 'You can provide your own Access Key from the Unsplash API. If not provided the community one would be used.', 'wp-unsplash-profile' ) . '</p>';
			echo '<p class="description"><strong>' . __( 'Please do not abuse it. It is shared by the community.', 'wp-unsplash-profile' ) . '</strong></p>';
			echo '<p class="description">' . __( 'If you feel that you could have a lot of visits in your website you can create your own Unsplash API Access Key from <a target="_blank" href="https://unsplash.com/developers">https://unsplash.com/developers</a>', 'wp-unsplash-profile' ) . '</p>';
			echo '</div>';
			
			echo '	<form action="options.php" method="post">' . "\n";
			
			settings_fields( 'wp_unsplash_settings_group' );
			do_settings_sections( 'wp_unsplash_settings' );
			submit_button();
			
			echo '	</form>' . "\n";
			
			if ($this->settings['user_name']) {
				
				echo '<div class="notice notice-success" style="margin-top: 20px;">';
				echo '<p class="description">' . __( 'Now you can show your Unsplash gallery inserting this shortcode on your pages or posts: <strong>[wp_unsplash_profile]</strong>', 'wp-unsplash-profile' ) . '</p>';
				echo '</div>';
				
			}
			
			echo '</div>' . "\n";
			
		}
		
		public function wp_unsplash_render_access_key_field() {
			
			// Retrieve data from the database.
			$options = get_option( 'wp_unsplash_settings' );
			
			// Set default value.
			$value = isset( $options['access_key'] ) ? $options['access_key'] : '';
			
			// Field output.
			echo '<input type="text" name="wp_unsplash_settings[access_key]" class="regular-text access_key_field" placeholder="' . esc_attr__( '', 'wp-unsplash-profile' ) . '" value="' . esc_attr( $value ) . '">';
			echo '<p class="description">' . __( '(Optional) Your own API Access Key', 'wp-unsplash-profile' ) . '</p>';
			
			
		}
		
		public function wp_unsplash_render_user_name_field() {
			
			// Retrieve data from the database.
			$options = get_option( 'wp_unsplash_settings' );
			
			// Set default value.
			$value = isset( $options['user_name'] ) ? $options['user_name'] : '';
			
			// Field output.
			echo '<input required type="text" name="wp_unsplash_settings[user_name]" class="regular-text user_name_field" placeholder="' . esc_attr__( '', 'wp-unsplash-profile' ) . '" value="' . esc_attr( $value ) . '">';
			echo '<p class="description">' . __( '(Required) Your Username at Unsplash without @ (get it from <a target="_blank" href="https://unsplash.com/account">https://unsplash.com/account</a>)', 'wp-unsplash-profile' ) . '</p>';
			
			
		}
		
		public function wp_unsplash_render_number_photos_field() {
			
			// Retrieve data from the database.
			$options = get_option( 'wp_unsplash_settings' );
			
			// Set default value.
			$value = isset( $options['number_photos'] ) ? $options['number_photos'] : 10;
			
			// Field output.
			echo '<input type="number" min="1" max="30" name="wp_unsplash_settings[number_photos]" class="regular-text user_name_field" placeholder="' . esc_attr__( '', 'wp-unsplash-profile' ) . '" value="' . esc_attr( $value ) . '">';
			echo '<p class="description">' . __( '(Optional) Number of photos to show on your gallery (default:10 / max:30)', 'wp-unsplash-profile' ) . '</p>';
			
		}
		
	}
	
	new WP_Unsplash_Profile;
