<?php
/**
 * BRS Public GitHub Updater.
 *
 * Provides GitHub release updates for publicly distributed WordPress plugins.
 *
 * @package   BRS_Public_GitHub_Updater
 * @author    Big Red SEO
 * @copyright Copyright (c) 2026 Big Red SEO
 * @license   GPL-2.0-or-later
 * @link      https://bigredseo.com/
 * @version   1.2.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BRS_Public_GitHub_Updater', false ) ) {
	final class BRS_Public_GitHub_Updater {
		public const VERSION = '1.2.0';

		private const CACHE_TTL = 6 * HOUR_IN_SECONDS;
		private const ERROR_CACHE_TTL = 15 * MINUTE_IN_SECONDS;

		private string $plugin_file;
		private string $plugin_basename;
		private string $slug;
		private string $description;
		private string $owner;
		private string $repository;
		private string $asset_name;
		private string $update_uri;
		private string $repository_url;
		private string $api_url;
		private string $cache_key;
		private string $name;
		private string $author;
		private string $homepage;
		private string $requires_php;
		private string $requires_wp;
		private string $tested_wp;

		/**
		 * Register an updater instance.
		 *
		 * Required arguments:
		 * - plugin_file: Main plugin file, normally __FILE__.
		 * - owner: GitHub repository owner.
		 * - repository: GitHub repository name.
		 * - asset_name: ZIP asset filename or pattern attached to each GitHub
		 *   release. The optional {version} placeholder is replaced with the
		 *   normalized release version.
		 *
		 * Optional arguments:
		 * - slug, name, author, homepage, description,requires_php, requires_wp, tested_wp.
		 */
		public static function register( array $args ): ?self {
			try {
				return new self( $args );
			} catch ( InvalidArgumentException $exception ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log(
						sprintf(
							'BRS GitHub updater configuration error: %s',
							$exception->getMessage()
						)
					);
				}

				return null;
			}
		}

		private function __construct( array $args ) {
			$required = array( 'plugin_file', 'owner', 'repository', 'asset_name' );

			foreach ( $required as $key ) {
				if ( empty( $args[ $key ] ) || ! is_string( $args[ $key ] ) ) {
					throw new InvalidArgumentException(
						sprintf( 'Missing required updater argument: %s', $key )
					);
				}
			}

			$this->plugin_file     = wp_normalize_path( $args['plugin_file'] );
			$this->plugin_basename = plugin_basename( $this->plugin_file );
			$this->slug            = sanitize_key(
				$args['slug'] ?? dirname( $this->plugin_basename )
			);
			$this->owner = preg_replace( '/[^A-Za-z0-9_.-]/', '', $args['owner'] );
			$this->repository = preg_replace( '/[^A-Za-z0-9_.-]/', '', $args['repository'] );
			$this->asset_name = trim( $args['asset_name'] );

			$validation_name = str_replace(
				'{version}',
				'1.0.0',
				$this->asset_name
			);

			if (
				'' === $this->asset_name
				|| basename( $validation_name ) !== $validation_name
				|| ! str_ends_with( strtolower( $validation_name ), '.zip' )
			) {
				throw new InvalidArgumentException(
					'The updater asset_name must be a ZIP filename without a directory path.'
				);
			}

			$this->update_uri      = sprintf(
				'https://github.com/%s/%s',
				$this->owner,
				$this->repository
			);
			$this->repository_url  = $this->update_uri;
			$this->api_url         = sprintf(
				'https://api.github.com/repos/%s/%s/releases/latest',
				rawurlencode( $this->owner ),
				rawurlencode( $this->repository )
			);
			$this->cache_key       = 'brs_gh_release_' . md5( strtolower( $this->owner . '/' . $this->repository ) );
			$this->name            = sanitize_text_field( $args['name'] ?? $this->repository );
			$this->author          = wp_kses_post( $args['author'] ?? 'Big Red SEO' );
			$this->homepage        = esc_url_raw( $args['homepage'] ?? $this->repository_url );
			$this->description     = wp_kses_post( $args['description'] ?? '' );
			$this->requires_php    = sanitize_text_field( $args['requires_php'] ?? '' );
			$this->requires_wp     = sanitize_text_field( $args['requires_wp'] ?? '' );
			$this->tested_wp       = sanitize_text_field( $args['tested_wp'] ?? '' );

			add_filter( 'update_plugins_github.com', array( $this, 'filter_update' ), 20, 4 );
			add_filter( 'plugins_api', array( $this, 'filter_plugin_information' ), 20, 3 );
			add_action( 'upgrader_process_complete', array( $this, 'clear_cache_after_update' ), 10, 2 );
		}

		/**
		 * Supply update data to WordPress for this plugin only.
		 *
		 * @param array|false $update      Update supplied by an earlier provider.
		 * @param array       $plugin_data Parsed plugin headers.
		 * @param string      $plugin_file Plugin basename.
		 * @param string[]    $locales     Requested locales.
		 * @return array|false
		 */
		public function filter_update( $update, array $plugin_data, string $plugin_file, array $locales ) {
			unset( $locales );

			if ( $plugin_file !== $this->plugin_basename ) {
				return $update;
			}

			if ( empty( $plugin_data['UpdateURI'] ) || untrailingslashit( $plugin_data['UpdateURI'] ) !== untrailingslashit( $this->update_uri ) ) {
				return $update;
			}

			// Another updater, such as the BRS central hub, already supplied data.
			if ( is_array( $update ) && ! empty( $update ) ) {
				return $update;
			}

			$release = $this->get_release();
			if ( is_wp_error( $release ) ) {
				return false;
			}

			$current_version = isset( $plugin_data['Version'] )
				? $this->normalize_version( (string) $plugin_data['Version'] )
				: '';
			$latest_version = $this->normalize_version( (string) $release['tag_name'] );

			if ( '' === $current_version || '' === $latest_version || ! version_compare( $latest_version, $current_version, '>' ) ) {
				return false;
			}

			$package = $this->find_release_asset_url( $release );
			if ( '' === $package ) {
				return false;
			}

			return $this->remove_empty_optional_values(
				array(
					'id'           => $this->update_uri,
					'slug'         => $this->slug,
					'plugin'       => $this->plugin_basename,
					'version'      => $latest_version,
					'url'          => $this->repository_url,
					'package'      => $package,
					'icons'        => array(),
					'banners'      => array(),
					'banners_rtl'  => array(),
					'tested'       => $this->tested_wp,
					'requires_php' => $this->requires_php,
					'requires'     => $this->requires_wp,
				)
			);
		}

		/**
		 * Supply the "View details" modal shown by WordPress.
		 *
		 * @param false|object|array $result Existing API result.
		 * @param string             $action API action.
		 * @param object             $args   API request arguments.
		 * @return false|object|array
		 */
		public function filter_plugin_information( $result, string $action, object $args ) {
			if ( 'plugin_information' !== $action || empty( $args->slug ) || $this->slug !== $args->slug ) {
				return $result;
			}

			// Preserve data supplied by the central hub or another provider.
			if ( false !== $result && null !== $result ) {
				return $result;
			}

			$release = $this->get_release();
			if ( is_wp_error( $release ) ) {
				return $result;
			}

			$package = $this->find_release_asset_url( $release );
			if ( '' === $package ) {
				return $result;
			}

			$version   = $this->normalize_version( (string) $release['tag_name'] );
			$changelog = isset( $release['body'] ) && is_string( $release['body'] )
				? nl2br( esc_html( $release['body'] ) )
				: '';

			return (object) $this->remove_empty_optional_values(
				array(
					'name'          => $this->name,
					'slug'          => $this->slug,
					'version'       => $version,
					'author'        => $this->author,
					'homepage'      => $this->homepage,
					'requires'      => $this->requires_wp,
					'tested'        => $this->tested_wp,
					'requires_php'  => $this->requires_php,
					'download_link' => $package,
					'external'      => true,
					'sections'      => array(
						'description' => $this->description,
						'changelog'   => $changelog,
					),
				)
			);
		}

		/**
		 * Clear cached release data after this plugin updates.
		 */
		public function clear_cache_after_update( WP_Upgrader $upgrader, array $hook_extra ): void {
			unset( $upgrader );

			if ( 'update' !== ( $hook_extra['action'] ?? '' ) || 'plugin' !== ( $hook_extra['type'] ?? '' ) ) {
				return;
			}

			$plugins = $hook_extra['plugins'] ?? array( $hook_extra['plugin'] ?? '' );
			if ( in_array( $this->plugin_basename, (array) $plugins, true ) ) {
				delete_site_transient( $this->cache_key );
			}
		}

		/**
		 * Fetch and cache the latest published GitHub release.
		 *
		 * @return array|WP_Error
		 */
		private function get_release() {
			$cached = get_site_transient( $this->cache_key );

			if (
				is_array( $cached )
				&& isset( $cached['brs_error'] )
				&& true === $cached['brs_error']
			) {
				return new WP_Error(
					'brs_github_release_cached_error',
					'GitHub release data is temporarily unavailable.'
				);
			}

			if ( is_array( $cached ) ) {
				return $cached;
			}

			$response = wp_safe_remote_get(
				$this->api_url,
				array(
					'timeout' => 15,
					'headers' => array(
						'Accept'               => 'application/vnd.github+json',
						'X-GitHub-Api-Version' => '2022-11-28',
						'User-Agent'           => sprintf(
							'%s WordPress updater',
							$this->slug
						),
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				$this->cache_release_error();

				return $response;
			}

			$status = wp_remote_retrieve_response_code( $response );

			if ( 200 !== $status ) {
				$this->cache_release_error();

				return new WP_Error(
					'brs_github_release_http_error',
					sprintf(
						'GitHub release request returned HTTP %d.',
						$status
					)
				);
			}

			$data = json_decode(
				wp_remote_retrieve_body( $response ),
				true
			);

			if (
				! is_array( $data )
				|| empty( $data['tag_name'] )
				|| empty( $data['html_url'] )
			) {
				$this->cache_release_error();

				return new WP_Error(
					'brs_github_release_invalid',
					'GitHub returned invalid release data.'
				);
			}

			set_site_transient(
				$this->cache_key,
				$data,
				self::CACHE_TTL
			);

			return $data;
		}

		/**
		 * Briefly cache a failed GitHub release request.
		 */
		private function cache_release_error(): void {
			set_site_transient(
				$this->cache_key,
				array(
					'brs_error' => true,
				),
				self::ERROR_CACHE_TTL
			);
		}

		/**
		 * Locate the configured ZIP asset in the release response.
		 */
		private function find_release_asset_url( array $release ): string {
			if (
				empty( $release['assets'] )
				|| ! is_array( $release['assets'] )
				|| empty( $release['tag_name'] )
				|| ! is_string( $release['tag_name'] )
			) {
				return '';
			}

			$version = $this->normalize_version( $release['tag_name'] );

			if ( '' === $version ) {
				return '';
			}

			$expected_asset_name = str_replace(
				'{version}',
				$version,
				$this->asset_name
			);

			foreach ( $release['assets'] as $asset ) {
				if (
					! is_array( $asset )
					|| ! isset(
						$asset['name'],
						$asset['browser_download_url']
					)
					|| ! is_string( $asset['name'] )
					|| ! is_string( $asset['browser_download_url'] )
				) {
					continue;
				}

				if ( $expected_asset_name === $asset['name'] ) {
					return esc_url_raw(
						$asset['browser_download_url']
					);
				}
			}

			return '';
		}

		/**
		 * Remove only empty optional values.
		 *
		 * @param array $data Data to filter.
		 * @return array
		 */
		private function remove_empty_optional_values( array $data ): array {
			return array_filter(
				$data,
				static function ( $value ): bool {
					return null !== $value && '' !== $value;
				}
			);
		}

		/**
		 * Convert tags such as "v1.2.3" to "1.2.3".
		 */
		private function normalize_version( string $version ): string {
			$version = trim( $version );
			$version = preg_replace( '/^[vV](?=\d)/', '', $version );

			return is_string( $version ) ? $version : '';
		}
	}
}
