<?php
/**
 * Tools → Mobilia admin screen with a read-only connection test.
 *
 * @package Laborde\Mobilia
 */

namespace Laborde\Mobilia;

defined( 'ABSPATH' ) || exit;

/**
 * Admin page and test-connection handler.
 */
class Admin {

	const SLUG        = 'laborde-mobilia';
	const ACTION      = 'laborde_mobilia_test';
	const CAPABILITY  = 'manage_options';
	const RESULT_KEY  = 'laborde_mobilia_test_result_';
	const MAX_DISPLAY = 500000; // Bytes of response body shown on screen.

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_test' ) );
	}

	/**
	 * Register Tools → Mobilia.
	 */
	public function add_page(): void {
		add_management_page(
			__( 'Mobilia', 'laborde-mobilia' ),
			__( 'Mobilia', 'laborde-mobilia' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Run the test, store the result for this user briefly, redirect back (PRG).
	 */
	public function handle_test(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'laborde-mobilia' ), 403 );
		}
		check_admin_referer( self::ACTION );

		$path      = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : 'inmuebles';
		$path      = trim( preg_replace( '#[^A-Za-z0-9_\-/{}.]#', '', $path ), '/' );
		$path      = '' === $path ? 'inmuebles' : $path;
		$page_size = isset( $_POST['page_size'] ) ? absint( $_POST['page_size'] ) : 5;
		$page_size = min( max( $page_size, 1 ), 100 );
		$extra     = isset( $_POST['extra_query'] ) ? sanitize_text_field( wp_unslash( $_POST['extra_query'] ) ) : '';
		$fresh     = ! empty( $_POST['fresh_token'] );

		$query = array();
		if ( '' !== $extra ) {
			wp_parse_str( $extra, $query );
		}
		if ( 'inmuebles' === $path ) {
			$query = array_merge(
				array(
					'pagina'       => 1,
					'tamanoPagina' => $page_size,
				),
				$query
			);
		}

		$client = new Client();
		if ( $fresh ) {
			delete_transient( Client::TOKEN_TRANSIENT );
		}

		$result = array(
			'time'  => current_time( 'mysql' ),
			'path'  => $path,
			'query' => $query,
		);

		$response = $client->get( $path, $query );
		if ( is_wp_error( $response ) ) {
			$result['error']   = $response->get_error_message();
			$result['details'] = (array) $response->get_error_data();
		} else {
			$result['response'] = $response;
		}

		set_transient( self::RESULT_KEY . get_current_user_id(), $result, 15 * MINUTE_IN_SECONDS );

		wp_safe_redirect( add_query_arg( 'page', self::SLUG, admin_url( 'tools.php' ) ) . '#result' );
		exit;
	}

	/**
	 * Render the admin page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		$result = get_transient( self::RESULT_KEY . get_current_user_id() );
		$path   = is_array( $result ) && ! empty( $result['path'] ) ? $result['path'] : 'inmuebles';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Mobilia', 'laborde-mobilia' ); ?></h1>

			<h2><?php esc_html_e( 'Configuration', 'laborde-mobilia' ); ?></h2>
			<table class="widefat striped" style="max-width:720px">
				<tbody>
					<tr>
						<th scope="row">MOBILIA_CLIENT_ID</th>
						<td><?php echo defined( 'MOBILIA_CLIENT_ID' ) && MOBILIA_CLIENT_ID ? '✅ ' . esc_html__( 'defined', 'laborde-mobilia' ) : '❌ ' . esc_html__( 'missing', 'laborde-mobilia' ); ?></td>
					</tr>
					<tr>
						<th scope="row">MOBILIA_CLIENT_SECRET</th>
						<td><?php echo defined( 'MOBILIA_CLIENT_SECRET' ) && MOBILIA_CLIENT_SECRET ? '✅ ' . esc_html__( 'defined', 'laborde-mobilia' ) : '❌ ' . esc_html__( 'missing', 'laborde-mobilia' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'API base URL', 'laborde-mobilia' ); ?></th>
						<td><code><?php echo esc_html( Client::base_url() ); ?></code></td>
					</tr>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Test connection', 'laborde-mobilia' ); ?></h2>
			<p><?php esc_html_e( 'Gets an access token and makes one read-only GET request. Nothing is saved or changed, in WordPress or in Mobilia.', 'laborde-mobilia' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
				<?php wp_nonce_field( self::ACTION ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="lm-path"><?php esc_html_e( 'Endpoint (GET)', 'laborde-mobilia' ); ?></label></th>
						<td>
							<code><?php echo esc_html( Client::base_url() ); ?>/</code>
							<input type="text" id="lm-path" name="path" class="regular-text" value="<?php echo esc_attr( $path ); ?>">
							<p class="description"><?php esc_html_e( 'Default: inmuebles (property listings, first page).', 'laborde-mobilia' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="lm-size"><?php esc_html_e( 'Page size', 'laborde-mobilia' ); ?></label></th>
						<td>
							<input type="number" id="lm-size" name="page_size" min="1" max="100" value="5" class="small-text">
							<p class="description"><?php esc_html_e( 'Only used for inmuebles (pagina=1&tamanoPagina=N).', 'laborde-mobilia' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="lm-extra"><?php esc_html_e( 'Extra query', 'laborde-mobilia' ); ?></label></th>
						<td>
							<input type="text" id="lm-extra" name="extra_query" class="regular-text" placeholder="e.g. venta=1">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Token', 'laborde-mobilia' ); ?></th>
						<td><label><input type="checkbox" name="fresh_token" value="1"> <?php esc_html_e( 'Request a fresh token (ignore cached one)', 'laborde-mobilia' ); ?></label></td>
					</tr>
				</table>
				<?php submit_button( __( 'Test connection', 'laborde-mobilia' ) ); ?>
			</form>

			<?php
			if ( is_array( $result ) ) {
				$this->render_result( $result );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render the stored test result.
	 *
	 * @param array $result Result stored by handle_test().
	 */
	private function render_result( array $result ): void {
		echo '<h2 id="result">' . esc_html__( 'Last result', 'laborde-mobilia' ) . '</h2>';
		echo '<p>' . esc_html( $result['time'] ) . '</p>';

		if ( ! empty( $result['error'] ) ) {
			echo '<div class="notice notice-error inline"><p><strong>' . esc_html( $result['error'] ) . '</strong></p>';
			if ( ! empty( $result['details'] ) ) {
				echo '<ul>';
				foreach ( $result['details'] as $line ) {
					echo '<li><code>' . esc_html( is_scalar( $line ) ? (string) $line : wp_json_encode( $line ) ) . '</code></li>';
				}
				echo '</ul>';
			}
			echo '</div>';
			return;
		}

		$r     = $result['response'];
		$ok    = $r['code'] >= 200 && $r['code'] < 300;
		$data  = json_decode( $r['body'], true );
		$class = $ok ? 'notice-success' : 'notice-error';

		printf(
			'<div class="notice %1$s inline"><p><strong>HTTP %2$d</strong> · %3$s s · %4$s</p></div>',
			esc_attr( $class ),
			(int) $r['code'],
			esc_html( (string) $r['duration'] ),
			esc_html(
				sprintf(
					/* translators: 1: auth method, 2: seconds. */
					__( 'Token: %1$s, expires in %2$d s', 'laborde-mobilia' ),
					$r['token_method'],
					$r['expires_in']
				)
			)
		);

		echo '<p><code>GET ' . esc_html( $r['url'] ) . '</code></p>';

		// Quick summary for list-style responses.
		if ( is_array( $data ) ) {
			$items = isset( $data['elementos'] ) && is_array( $data['elementos'] ) ? $data['elementos'] : ( array_is_list( $data ) ? $data : null );
			echo '<table class="widefat striped" style="max-width:720px"><tbody>';
			echo '<tr><th>' . esc_html__( 'Top-level keys', 'laborde-mobilia' ) . '</th><td><code>' . esc_html( implode( ', ', array_map( 'strval', array_keys( $data ) ) ) ) . '</code></td></tr>';
			foreach ( array( 'totalElementos', 'pagina', 'tamanoPagina' ) as $key ) {
				if ( isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) ) {
					echo '<tr><th>' . esc_html( $key ) . '</th><td>' . esc_html( (string) $data[ $key ] ) . '</td></tr>';
				}
			}
			if ( null !== $items ) {
				echo '<tr><th>' . esc_html__( 'Items in this page', 'laborde-mobilia' ) . '</th><td>' . count( $items ) . '</td></tr>';
				if ( isset( $items[0] ) && is_array( $items[0] ) ) {
					echo '<tr><th>' . esc_html__( 'Fields of first item', 'laborde-mobilia' ) . '</th><td><code>' . esc_html( implode( ', ', array_keys( $items[0] ) ) ) . '</code></td></tr>';
				}
			}
			echo '</tbody></table>';
		}

		// Response headers (useful for rate limits and pagination).
		echo '<details style="margin-top:1em"><summary>' . esc_html__( 'Response headers', 'laborde-mobilia' ) . '</summary><pre style="background:#fff;padding:1em;overflow:auto;max-height:300px">';
		foreach ( (array) $r['headers'] as $name => $value ) {
			echo esc_html( $name . ': ' . ( is_array( $value ) ? implode( ', ', $value ) : $value ) ) . "\n";
		}
		echo '</pre></details>';

		// Raw body, pretty-printed when JSON.
		$body = is_array( $data ) ? wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : $r['body'];
		$len  = strlen( (string) $body );
		echo '<h3>' . esc_html__( 'Response body', 'laborde-mobilia' ) . '</h3>';
		if ( $len > self::MAX_DISPLAY ) {
			/* translators: %s: size. */
			echo '<p>' . esc_html( sprintf( __( 'Showing the first %s of the response.', 'laborde-mobilia' ), size_format( self::MAX_DISPLAY ) ) ) . '</p>';
			$body = substr( $body, 0, self::MAX_DISPLAY );
		}
		echo '<pre style="background:#fff;padding:1em;overflow:auto;max-height:70vh;white-space:pre-wrap">' . esc_html( (string) $body ) . '</pre>';
	}
}
