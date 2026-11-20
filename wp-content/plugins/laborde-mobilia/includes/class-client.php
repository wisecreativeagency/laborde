<?php
/**
 * Minimal read-only HTTP client for the Mobilia Gestión API.
 *
 * @package Laborde\Mobilia
 */

namespace Laborde\Mobilia;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles OAuth2 client_credentials auth and GET requests.
 */
class Client {

	const DEFAULT_BASE_URL = 'https://api.mobiliagestion.es/api/v1';
	const TOKEN_TRANSIENT  = 'laborde_mobilia_token';
	const TIMEOUT          = 30;

	/**
	 * Whether both credential constants are defined and non-empty.
	 */
	public static function has_credentials(): bool {
		return defined( 'MOBILIA_CLIENT_ID' ) && '' !== (string) MOBILIA_CLIENT_ID
			&& defined( 'MOBILIA_CLIENT_SECRET' ) && '' !== (string) MOBILIA_CLIENT_SECRET;
	}

	/**
	 * API base URL without trailing slash.
	 */
	public static function base_url(): string {
		$url = defined( 'MOBILIA_BASE_URL' ) && MOBILIA_BASE_URL ? (string) MOBILIA_BASE_URL : self::DEFAULT_BASE_URL;
		return untrailingslashit( $url );
	}

	/**
	 * Request a new access token. Returns token info (never logged or displayed).
	 *
	 * Tries client credentials in the form body first, then HTTP Basic, since
	 * the exact format Mobilia expects is not yet confirmed.
	 *
	 * @return array{access_token:string,token_type:string,expires_in:int,method:string}|WP_Error
	 */
	public function request_token( bool $force = false ) {
		if ( ! self::has_credentials() ) {
			return new WP_Error( 'laborde_mobilia_no_credentials', __( 'MOBILIA_CLIENT_ID and MOBILIA_CLIENT_SECRET are not defined in wp-config.php.', 'laborde-mobilia' ) );
		}

		if ( ! $force ) {
			$cached = get_transient( self::TOKEN_TRANSIENT );
			if ( is_array( $cached ) && ! empty( $cached['access_token'] ) ) {
				$cached['method'] = 'cached';
				return $cached;
			}
		}

		$url      = self::base_url() . '/token';
		$attempts = array(
			'form body'  => array(
				'headers' => array( 'Accept' => 'application/json' ),
				'body'    => array(
					'grant_type'    => 'client_credentials',
					'client_id'     => MOBILIA_CLIENT_ID,
					'client_secret' => MOBILIA_CLIENT_SECRET,
				),
			),
			'HTTP Basic' => array(
				'headers' => array(
					'Accept'        => 'application/json',
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic auth.
					'Authorization' => 'Basic ' . base64_encode( MOBILIA_CLIENT_ID . ':' . MOBILIA_CLIENT_SECRET ),
				),
				'body'    => array( 'grant_type' => 'client_credentials' ),
			),
		);

		$errors = array();
		foreach ( $attempts as $method => $args ) {
			$response = wp_remote_post( $url, $args + array( 'timeout' => self::TIMEOUT ) );
			if ( is_wp_error( $response ) ) {
				$errors[] = $method . ': ' . $response->get_error_message();
				continue;
			}
			$code = (int) wp_remote_retrieve_response_code( $response );
			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( 200 === $code && is_array( $data ) && ! empty( $data['access_token'] ) ) {
				$token = array(
					'access_token' => (string) $data['access_token'],
					'token_type'   => isset( $data['token_type'] ) ? (string) $data['token_type'] : 'Bearer',
					'expires_in'   => isset( $data['expires_in'] ) ? (int) $data['expires_in'] : 3600,
				);
				// Cache with a 5 minute safety margin.
				set_transient( self::TOKEN_TRANSIENT, $token, max( 60, $token['expires_in'] - 300 ) );
				$token['method'] = $method;
				return $token;
			}
			$errors[] = sprintf( '%s: HTTP %d %s', $method, $code, self::redact( wp_remote_retrieve_body( $response ) ) );
		}

		return new WP_Error( 'laborde_mobilia_token_failed', __( 'Could not obtain an access token.', 'laborde-mobilia' ), $errors );
	}

	/**
	 * Perform an authenticated GET request. Read-only by design.
	 *
	 * @param string $path  Path relative to the base URL, e.g. "inmuebles".
	 * @param array  $query Query parameters.
	 * @return array{url:string,code:int,headers:array,body:string,duration:float}|WP_Error
	 */
	public function get( string $path, array $query = array() ) {
		$token = $this->request_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$url = add_query_arg( array_map( 'rawurlencode', $query ), self::base_url() . '/' . ltrim( $path, '/' ) );

		$start    = microtime( true );
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array(
					'Accept'        => 'application/json',
					'Authorization' => $token['token_type'] . ' ' . $token['access_token'],
				),
			)
		);

		// Expired or revoked token: refresh once.
		if ( ! is_wp_error( $response ) && 401 === (int) wp_remote_retrieve_response_code( $response ) && 'cached' === $token['method'] ) {
			delete_transient( self::TOKEN_TRANSIENT );
			return $this->get( $path, $query );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'url'          => $url,
			'code'         => (int) wp_remote_retrieve_response_code( $response ),
			'headers'      => wp_remote_retrieve_headers( $response )->getAll(),
			'body'         => self::redact( wp_remote_retrieve_body( $response ) ),
			'duration'     => round( microtime( true ) - $start, 2 ),
			'token_method' => $token['method'],
			'expires_in'   => $token['expires_in'],
		);
	}

	/**
	 * Mask anything that looks like a credential or token in a string.
	 */
	public static function redact( string $text ): string {
		$secrets = array();
		foreach ( array( 'MOBILIA_CLIENT_ID', 'MOBILIA_CLIENT_SECRET' ) as $const ) {
			if ( defined( $const ) && '' !== (string) constant( $const ) ) {
				$secrets[] = (string) constant( $const );
			}
		}
		$cached = get_transient( self::TOKEN_TRANSIENT );
		if ( is_array( $cached ) && ! empty( $cached['access_token'] ) ) {
			$secrets[] = $cached['access_token'];
		}
		$text = str_replace( $secrets, '[redacted]', $text );
		return (string) preg_replace( '/("(?:access_token|refresh_token|client_secret)"\s*:\s*")[^"]*"/i', '$1[redacted]"', $text );
	}
}
