<?php
/**
 * BRS Public GitHub Updater.
 *
 * Provides GitHub release updates for BRS WordPress plugins, and always
 * provides the WordPress "View details" modal whether or not the GitHub
 * repository is public, reachable, or has a published release.
 *
 * Behaviour:
 * - Updates require a public repository, a published release, and a matching
 *   ZIP asset. Anything else results in "no update available" and never an
 *   error.
 * - View details is always available. It is built from the plugin header, then
 *   enriched with the GitHub README when reachable, and falls back to the
 *   local README.md when it is not.
 *
 * @package   BRS_Public_GitHub_Updater
 * @author    Big Red SEO
 * @copyright Copyright (c) 2026 Big Red SEO
 * @license   GPL-2.0-or-later
 * @link      https://bigredseo.com/
 * @version   2.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BRS_Public_GitHub_Updater', false ) ) {
	final class BRS_Public_GitHub_Updater {
		public const VERSION = '2.0.0';

		private const RELEASE_CACHE_TTL = 6 * HOUR_IN_SECONDS;
		private const README_CACHE_TTL  = 12 * HOUR_IN_SECONDS;
		private const ERROR_CACHE_TTL   = 15 * MINUTE_IN_SECONDS;

		private string $plugin_file;
		private string $plugin_basename;
		private string $plugin_dir;
		private string $slug;
		private string $name;
		private string $description;
		private string $version;
		private string $author;
		private string $homepage;
		private string $owner;
		private string $repository;
		private string $asset_name;
		private string $update_uri;
		private string $repository_url;
		private string $api_url;
		private string $readme_api_url;
		private string $release_cache_key;
		private string $readme_cache_key;
		private string $requires_php;
		private string $requires_wp;
		private string $tested_wp;

		/**
		 * Register an updater instance.
		 *
		 * Required:
		 * - plugin_file: Main plugin file, normally __FILE__.
		 *
		 * Everything else is read from the plugin header when omitted. The
		 * GitHub owner and repository are parsed from the Update URI header.
		 *
		 * Optional overrides:
		 * - owner, repository, asset_name, slug, name, description, author,
		 *   homepage, requires_php, requires_wp, tested_wp.
		 *
		 * @param array $args Registration arguments.
		 * @return self|null
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
			if ( empty( $args['plugin_file'] ) || ! is_string( $args['plugin_file'] ) ) {
				throw new InvalidArgumentException( 'Missing required updater argument: plugin_file' );
			}

			$this->plugin_file     = wp_normalize_path( $args['plugin_file'] );
			$this->plugin_basename = plugin_basename( $this->plugin_file );
			$this->plugin_dir      = trailingslashit( dirname( $this->plugin_file ) );

			$headers = self::read_plugin_headers( $this->plugin_file );

			$this->slug = sanitize_key(
				$args['slug'] ?? dirname( $this->plugin_basename )
			);

			if ( '' === $this->slug || '.' === $this->slug ) {
				throw new InvalidArgumentException( 'Unable to determine the plugin slug.' );
			}

			$repo = self::parse_github_uri( $args['update_uri'] ?? $headers['UpdateURI'] );

			$owner      = (string) ( $args['owner'] ?? $repo['owner'] );
			$repository = (string) ( $args['repository'] ?? $repo['repository'] );

			$this->owner      = preg_replace( '/[^A-Za-z0-9_.-]/', '', $owner );
			$this->repository = preg_replace( '/[^A-Za-z0-9_.-]/', '', $repository );

			if ( '' === $this->owner || '' === $this->repository ) {
				throw new InvalidArgumentException(
					'Unable to determine the GitHub owner and repository. Add an "Update URI: https://github.com/owner/repo" plugin header.'
				);
			}

			$this->asset_name = trim(
				(string) ( $args['asset_name'] ?? $this->slug . '-{version}.zip' )
			);

			$validation_name = str_replace( '{version}', '1.0.0', $this->asset_name );

			if (
				'' === $this->asset_name
				|| basename( $validation_name ) !== $validation_name
				|| ! str_ends_with( strtolower( $validation_name ), '.zip' )
			) {
				throw new InvalidArgumentException(
					'The updater asset_name must be a ZIP filename without a directory path.'
				);
			}

			$this->update_uri     = sprintf( 'https://github.com/%s/%s', $this->owner, $this->repository );
			$this->repository_url = $this->update_uri;

			$this->api_url = sprintf(
				'https://api.github.com/repos/%s/%s/releases/latest',
				rawurlencode( $this->owner ),
				rawurlencode( $this->repository )
			);

			$this->readme_api_url = sprintf(
				'https://api.github.com/repos/%s/%s/readme',
				rawurlencode( $this->owner ),
				rawurlencode( $this->repository )
			);

			$repo_hash               = md5( strtolower( $this->owner . '/' . $this->repository ) );
			$this->release_cache_key = 'brs_gh_release_' . $repo_hash;
			$this->readme_cache_key  = 'brs_gh_readme_' . $repo_hash;

			$this->name         = sanitize_text_field( $args['name'] ?? $headers['Name'] );
			$this->version      = $this->normalize_version( (string) ( $args['version'] ?? $headers['Version'] ) );
			$this->author       = wp_kses_post( $args['author'] ?? $headers['Author'] );
			$this->requires_php = sanitize_text_field( $args['requires_php'] ?? $headers['RequiresPHP'] );
			$this->requires_wp  = sanitize_text_field( $args['requires_wp'] ?? $headers['RequiresWP'] );
			$this->tested_wp    = sanitize_text_field( $args['tested_wp'] ?? $headers['TestedWP'] );

			$description = (string) ( $args['description'] ?? $headers['Description'] );
			if ( '' !== $description && ! str_contains( $description, '<' ) ) {
				$description = wpautop( $description );
			}
			$this->description = wp_kses_post( $description );

			$homepage       = $args['homepage'] ?? $headers['PluginURI'];
			$homepage       = '' !== (string) $homepage ? (string) $homepage : $this->repository_url;
			$this->homepage = esc_url_raw( $homepage );

			if ( '' === $this->name ) {
				$this->name = $this->repository;
			}

			add_filter( 'update_plugins_github.com', array( $this, 'filter_update' ), 20, 4 );
			add_filter( 'plugins_api', array( $this, 'filter_plugin_information' ), 20, 3 );
			add_action( 'upgrader_process_complete', array( $this, 'clear_cache_after_update' ), 10, 2 );
		}

		/**
		 * Read the WordPress plugin headers this updater relies on.
		 *
		 * get_file_data() is used rather than get_plugin_data() because it is
		 * available outside the admin without loading admin includes.
		 *
		 * @param string $plugin_file Absolute path to the main plugin file.
		 * @return array<string,string>
		 */
		public static function read_plugin_headers( string $plugin_file ): array {
			$defaults = array(
				'Name'        => '',
				'PluginURI'   => '',
				'Description' => '',
				'Version'     => '',
				'Author'      => '',
				'AuthorURI'   => '',
				'RequiresWP'  => '',
				'RequiresPHP' => '',
				'TestedWP'    => '',
				'UpdateURI'   => '',
			);

			if ( ! is_readable( $plugin_file ) ) {
				return $defaults;
			}

			$headers = get_file_data(
				$plugin_file,
				array(
					'Name'        => 'Plugin Name',
					'PluginURI'   => 'Plugin URI',
					'Description' => 'Description',
					'Version'     => 'Version',
					'Author'      => 'Author',
					'AuthorURI'   => 'Author URI',
					'RequiresWP'  => 'Requires at least',
					'RequiresPHP' => 'Requires PHP',
					'TestedWP'    => 'Tested up to',
					'UpdateURI'   => 'Update URI',
				),
				'plugin'
			);

			return wp_parse_args( $headers, $defaults );
		}

		/**
		 * Split a GitHub repository URL into owner and repository.
		 *
		 * @param mixed $uri Candidate Update URI value.
		 * @return array{owner:string,repository:string}
		 */
		private static function parse_github_uri( $uri ): array {
			$empty = array(
				'owner'      => '',
				'repository' => '',
			);

			if ( ! is_string( $uri ) || '' === trim( $uri ) ) {
				return $empty;
			}

			$host = wp_parse_url( $uri, PHP_URL_HOST );

			if ( ! is_string( $host ) ) {
				return $empty;
			}

			$host = strtolower( $host );

			if ( 'github.com' !== $host && 'www.github.com' !== $host ) {
				return $empty;
			}

			$path = wp_parse_url( $uri, PHP_URL_PATH );

			if ( ! is_string( $path ) ) {
				return $empty;
			}

			$segments = array_values( array_filter( explode( '/', $path ) ) );

			if ( count( $segments ) < 2 ) {
				return $empty;
			}

			return array(
				'owner'      => $segments[0],
				'repository' => preg_replace( '/\.git$/i', '', $segments[1] ),
			);
		}

		/**
		 * Supply update data to WordPress for this plugin only.
		 *
		 * Returns false whenever an update cannot be confirmed, which includes
		 * private or unreachable repositories.
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

			if (
				empty( $plugin_data['UpdateURI'] )
				|| untrailingslashit( $plugin_data['UpdateURI'] ) !== untrailingslashit( $this->update_uri )
			) {
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
			$latest_version  = $this->normalize_version( (string) $release['tag_name'] );

			if (
				'' === $current_version
				|| '' === $latest_version
				|| ! version_compare( $latest_version, $current_version, '>' )
			) {
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
		 * This always returns plugin information for managed plugins. It does
		 * not depend on a reachable repository, a published release, or a
		 * matching ZIP asset. Those only add the download link and the release
		 * changelog when they are available.
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

			$sections = array(
				'description' => $this->get_description_section(),
			);

			$information = array(
				'name'          => $this->name,
				'slug'          => $this->slug,
				'version'       => $this->version,
				'author'        => $this->author,
				'homepage'      => $this->homepage,
				'requires'      => $this->requires_wp,
				'tested'        => $this->tested_wp,
				'requires_php'  => $this->requires_php,
				'external'      => true,
				'download_link' => '',
			);

			$release = $this->get_release();

			if ( ! is_wp_error( $release ) ) {
				$latest_version = $this->normalize_version( (string) $release['tag_name'] );

				if ( '' !== $latest_version ) {
					$information['version'] = $latest_version;
				}

				if ( ! empty( $release['published_at'] ) && is_string( $release['published_at'] ) ) {
					$information['last_updated'] = $release['published_at'];
				}

				if ( ! empty( $release['body'] ) && is_string( $release['body'] ) ) {
					$sections['changelog'] = $this->render_markdown( $release['body'] );
				}

				$package = $this->find_release_asset_url( $release );

				if ( '' !== $package ) {
					$information['download_link'] = $package;
				}
			}

			if ( ! isset( $sections['changelog'] ) ) {
				$sections['changelog'] = $this->get_local_changelog_section();
			}

			$information['sections'] = array_filter(
				$sections,
				static function ( $value ): bool {
					return is_string( $value ) && '' !== $value;
				}
			);

			return (object) $this->remove_empty_optional_values( $information );
		}

		/**
		 * Build the description tab from the README, then the plugin header.
		 */
		private function get_description_section(): string {
			$readme = $this->get_readme_html();

			if ( '' !== $readme ) {
				return $readme;
			}

			return $this->description;
		}

		/**
		 * Read the local CHANGELOG.md when GitHub release notes are absent.
		 */
		private function get_local_changelog_section(): string {
			$path = $this->plugin_dir . 'CHANGELOG.md';

			if ( ! is_readable( $path ) ) {
				return '';
			}

			$contents = file_get_contents( $path );

			if ( ! is_string( $contents ) || '' === trim( $contents ) ) {
				return '';
			}

			return $this->render_markdown( $contents );
		}

		/**
		 * Return README HTML from GitHub, falling back to the local README.md.
		 *
		 * The resolved HTML is cached regardless of source so that a private or
		 * unreachable repository does not trigger a request on every page view.
		 */
		private function get_readme_html(): string {
			$cached = get_site_transient( $this->readme_cache_key );

			if ( is_string( $cached ) ) {
				return $cached;
			}

			$html = $this->fetch_github_readme_html();

			if ( '' !== $html ) {
				set_site_transient( $this->readme_cache_key, $html, self::README_CACHE_TTL );

				return $html;
			}

			$html = $this->read_local_readme_html();

			// Cache the local fallback briefly so GitHub is retried later.
			set_site_transient( $this->readme_cache_key, $html, self::ERROR_CACHE_TTL );

			return $html;
		}

		/**
		 * Request the rendered README from the public GitHub API.
		 *
		 * Returns an empty string for private, missing, or rate-limited
		 * repositories rather than raising an error.
		 */
		private function fetch_github_readme_html(): string {
			$response = wp_safe_remote_get(
				$this->readme_api_url,
				array(
					'timeout' => 15,
					'headers' => array(
						'Accept'               => 'application/vnd.github.html+json',
						'X-GitHub-Api-Version' => '2022-11-28',
						'User-Agent'           => sprintf( '%s WordPress updater', $this->slug ),
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				return '';
			}

			if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
				return '';
			}

			$body = wp_remote_retrieve_body( $response );

			if ( ! is_string( $body ) || '' === trim( $body ) ) {
				return '';
			}

			return wp_kses_post( $body );
		}

		/**
		 * Render the local README.md shipped inside the plugin ZIP.
		 */
		private function read_local_readme_html(): string {
			$path = $this->plugin_dir . 'README.md';

			if ( ! is_readable( $path ) ) {
				return '';
			}

			$contents = file_get_contents( $path );

			if ( ! is_string( $contents ) || '' === trim( $contents ) ) {
				return '';
			}

			return $this->render_markdown( $contents );
		}

		/**
		 * Convert a useful subset of Markdown to HTML.
		 *
		 * WordPress ships no Markdown parser. This covers the constructs used
		 * by BRS README and CHANGELOG files: headings, lists, fenced and inline
		 * code, links, emphasis, and paragraphs. Output passes through
		 * wp_kses_post().
		 */
		private function render_markdown( string $markdown ): string {
			$markdown = str_replace( array( "\r\n", "\r" ), "\n", $markdown );

			$code_blocks = array();

			$markdown = preg_replace_callback(
				'/```[a-zA-Z0-9_-]*\n(.*?)```/s',
				static function ( array $matches ) use ( &$code_blocks ): string {
					$token                 = sprintf( '@@BRSCODE%d@@', count( $code_blocks ) );
					$code_blocks[ $token ] = sprintf(
						'<pre><code>%s</code></pre>',
						esc_html( rtrim( $matches[1] ) )
					);

					return "\n" . $token . "\n";
				},
				$markdown
			);

			$lines      = explode( "\n", $markdown );
			$html       = '';
			$list_type  = '';
			$paragraph  = array();

			$flush_paragraph = static function () use ( &$paragraph, &$html ): void {
				if ( empty( $paragraph ) ) {
					return;
				}

				$html .= '<p>' . implode( '<br />', $paragraph ) . '</p>';
				$paragraph = array();
			};

			$close_list = static function () use ( &$list_type, &$html ): void {
				if ( '' === $list_type ) {
					return;
				}

				$html     .= sprintf( '</%s>', $list_type );
				$list_type = '';
			};

			foreach ( $lines as $line ) {
				$trimmed = trim( $line );

				if ( '' === $trimmed ) {
					$flush_paragraph();
					$close_list();
					continue;
				}

				if ( isset( $code_blocks[ $trimmed ] ) ) {
					$flush_paragraph();
					$close_list();
					$html .= $code_blocks[ $trimmed ];
					continue;
				}

				if ( preg_match( '/^(#{1,6})\s+(.*)$/', $trimmed, $matches ) ) {
					$flush_paragraph();
					$close_list();
					$level = min( 6, max( 2, strlen( $matches[1] ) + 1 ) );
					$html .= sprintf(
						'<h%1$d>%2$s</h%1$d>',
						$level,
						$this->render_inline_markdown( $matches[2] )
					);
					continue;
				}

				if ( preg_match( '/^(-{3,}|\*{3,}|_{3,})$/', $trimmed ) ) {
					$flush_paragraph();
					$close_list();
					$html .= '<hr />';
					continue;
				}

				if ( preg_match( '/^[-*+]\s+(.*)$/', $trimmed, $matches ) ) {
					$flush_paragraph();

					if ( 'ul' !== $list_type ) {
						$close_list();
						$html     .= '<ul>';
						$list_type = 'ul';
					}

					$html .= '<li>' . $this->render_inline_markdown( $matches[1] ) . '</li>';
					continue;
				}

				if ( preg_match( '/^\d+[.)]\s+(.*)$/', $trimmed, $matches ) ) {
					$flush_paragraph();

					if ( 'ol' !== $list_type ) {
						$close_list();
						$html     .= '<ol>';
						$list_type = 'ol';
					}

					$html .= '<li>' . $this->render_inline_markdown( $matches[1] ) . '</li>';
					continue;
				}

				$close_list();
				$paragraph[] = $this->render_inline_markdown( $trimmed );
			}

			$flush_paragraph();
			$close_list();

			return wp_kses_post( $html );
		}

		/**
		 * Convert inline Markdown within a single line.
		 */
		private function render_inline_markdown( string $text ): string {
			$text = esc_html( $text );

			$text = preg_replace(
				'/`([^`]+)`/',
				'<code>$1</code>',
				$text
			);

			$text = preg_replace(
				'/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/',
				'<a href="$2" rel="nofollow noopener" target="_blank">$1</a>',
				$text
			);

			$text = preg_replace( '/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text );
			$text = preg_replace( '/(?<!\*)\*([^*\n]+)\*(?!\*)/', '<em>$1</em>', $text );

			return is_string( $text ) ? $text : '';
		}

		/**
		 * Clear cached GitHub data after this plugin updates.
		 */
		public function clear_cache_after_update( WP_Upgrader $upgrader, array $hook_extra ): void {
			unset( $upgrader );

			if ( 'update' !== ( $hook_extra['action'] ?? '' ) || 'plugin' !== ( $hook_extra['type'] ?? '' ) ) {
				return;
			}

			$plugins = $hook_extra['plugins'] ?? array( $hook_extra['plugin'] ?? '' );

			if ( in_array( $this->plugin_basename, (array) $plugins, true ) ) {
				delete_site_transient( $this->release_cache_key );
				delete_site_transient( $this->readme_cache_key );
			}
		}

		/**
		 * Fetch and cache the latest published GitHub release.
		 *
		 * @return array|WP_Error
		 */
		private function get_release() {
			$cached = get_site_transient( $this->release_cache_key );

			if ( is_array( $cached ) && isset( $cached['brs_error'] ) && true === $cached['brs_error'] ) {
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
						'User-Agent'           => sprintf( '%s WordPress updater', $this->slug ),
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
					sprintf( 'GitHub release request returned HTTP %d.', $status )
				);
			}

			$data = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( ! is_array( $data ) || empty( $data['tag_name'] ) || empty( $data['html_url'] ) ) {
				$this->cache_release_error();

				return new WP_Error(
					'brs_github_release_invalid',
					'GitHub returned invalid release data.'
				);
			}

			set_site_transient( $this->release_cache_key, $data, self::RELEASE_CACHE_TTL );

			return $data;
		}

		/**
		 * Briefly cache a failed GitHub release request.
		 */
		private function cache_release_error(): void {
			set_site_transient(
				$this->release_cache_key,
				array( 'brs_error' => true ),
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

			$expected_asset_name = str_replace( '{version}', $version, $this->asset_name );

			foreach ( $release['assets'] as $asset ) {
				if (
					! is_array( $asset )
					|| ! isset( $asset['name'], $asset['browser_download_url'] )
					|| ! is_string( $asset['name'] )
					|| ! is_string( $asset['browser_download_url'] )
				) {
					continue;
				}

				if ( $expected_asset_name === $asset['name'] ) {
					return esc_url_raw( $asset['browser_download_url'] );
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
