<?php

declare(strict_types=1);

namespace VerifiedClientIp;

/**
 * WordPress admin page for the "Verified Client IP" plugin.
 *
 * Registers a Settings sub-menu, renders the settings form, and processes
 * form submissions with nonce + capability checks.
 */
final class AdminPage {

	/** The menu slug used for the settings page. */
	public const MENU_SLUG = 'verified-client-ip';

	/** Nonce action for the settings form. */
	private const NONCE_ACTION = 'vcip_save_settings';

	/** Nonce field name. */
	private const NONCE_FIELD = 'vcip_nonce';

	/**
	 * Register hooks.  Call this from the main plugin file or Plugin class.
	 */
	public static function register(): void {
		if ( ! \function_exists( 'add_action' ) ) {
			return;
		}

		\add_action( 'admin_menu', [ self::class, 'add_menu_page' ] );
		\add_action( 'admin_init', [ self::class, 'handle_form_submission' ] );
	}

	/**
	 * Add the plugin settings page under the WordPress Settings menu.
	 */
	public static function add_menu_page(): void {
		if ( ! \function_exists( 'add_options_page' ) ) {
			return; // @codeCoverageIgnore
		}

		\add_options_page(
			__( 'Verified Client IP', 'verified-client-ip' ),
			__( 'Verified Client IP', 'verified-client-ip' ),
			'manage_options',
			self::MENU_SLUG,
			[ self::class, 'render_page' ],
		);
	}

	/**
	 * Process the form submission (save settings).
	 */
	public static function handle_form_submission(): void {
		// --- Diagnostics actions ---
		if ( isset( $_POST['vcip_diag_action'] ) ) {
			self::handle_diagnostics_action();
			return;
		}

		// --- Settings form ---
		// Only act on our form.
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}

		if ( ! \function_exists( 'current_user_can' ) || ! \current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! \function_exists( 'wp_verify_nonce' )
			|| ! \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION )
		) {
			\add_settings_error(
				'vcip_settings',
				'vcip_nonce_error',
				__( 'Security check failed. Please try again.', 'verified-client-ip' ),
				'error',
			);

			return;
		}

		$raw = self::parse_form_input( $_POST );

		$result = Settings::validate( $raw );

		if ( [] !== $result['errors'] ) {
			foreach ( $result['errors'] as $error ) {
				if ( \function_exists( 'add_settings_error' ) ) {
					\add_settings_error( 'vcip_settings', 'vcip_validation', $error, 'error' );
				}
			}
		}

		// Save even when there are validation warnings — the validate()
		// method always returns a usable Settings, clamping invalid values.
		$result['settings']->save();

		Logger::info( 'Settings saved', 'admin' );

		if ( [] === $result['errors'] && \function_exists( 'add_settings_error' ) ) {
			\add_settings_error(
				'vcip_settings',
				'vcip_saved',
				__( 'Settings saved.', 'verified-client-ip' ),
				'success',
			);
		}
	}

	/**
	 * Render the full settings page.
	 */
	public static function render_page(): void {
		$settings   = Settings::load();
		$active_tab = isset( $_GET['tab'] ) ? \sanitize_text_field( $_GET['tab'] ) : 'settings';
		?>
		<div class="wrap">
			<h1><?php echo \esc_html__( 'Verified Client IP', 'verified-client-ip' ); ?></h1>

			<?php
			if ( \function_exists( 'settings_errors' ) ) {
				// Pass true for $sanitize to deduplicate messages that may appear
				// in both the transient and the in-memory global.
				\settings_errors( 'vcip_settings', false, true );
			}
			?>

			<nav class="nav-tab-wrapper">
				<a href="?page=<?php echo self::MENU_SLUG; ?>&tab=settings"
					class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php echo \esc_html__( 'Settings', 'verified-client-ip' ); ?>
				</a>
				<a href="?page=<?php echo self::MENU_SLUG; ?>&tab=diagnostics"
					class="nav-tab <?php echo 'diagnostics' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php echo \esc_html__( 'Diagnostics', 'verified-client-ip' ); ?>
				</a>
				<a href="?page=<?php echo self::MENU_SLUG; ?>&tab=user-guide"
					class="nav-tab <?php echo 'user-guide' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php echo \esc_html__( 'User Guide', 'verified-client-ip' ); ?>
				</a>
			</nav>
	
			<?php if ( 'diagnostics' === $active_tab ) : ?>
				<?php self::render_diagnostics_tab(); ?>
			<?php elseif ( 'user-guide' === $active_tab ) : ?>
				<?php self::render_user_guide_tab(); ?>
			<?php else : ?>

			<form method="post" action="">
				<?php
				if ( \function_exists( 'wp_nonce_field' ) ) {
					\wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
				}
				?>

				<h2><?php echo \esc_html__( 'General', 'verified-client-ip' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo \esc_html__( 'Enable plugin', 'verified-client-ip' ); ?></th>
						<td>
							<label>
								<input type="hidden" name="vcip_enabled" value="0">
								<input type="checkbox" name="vcip_enabled" value="1"
									<?php echo $settings->enabled ? 'checked' : ''; ?>>
								<?php echo \esc_html__( 'Enable IP resolution', 'verified-client-ip' ); ?>
							</label>
							<p class="description">
								<?php echo \esc_html__( 'When disabled, the plugin calculates the result (for diagnostics) but does not replace REMOTE_ADDR.', 'verified-client-ip' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="vcip_forward_limit"><?php echo \esc_html__( 'Forward Limit', 'verified-client-ip' ); ?></label>
						</th>
						<td>
							<input type="number" id="vcip_forward_limit" name="vcip_forward_limit"
									value="<?php echo \esc_attr( (string) $settings->forward_limit ); ?>"
									min="<?php echo Settings::FORWARD_LIMIT_MIN; ?>"
									max="<?php echo Settings::FORWARD_LIMIT_MAX; ?>"
									class="small-text">
							<p class="description">
								<?php echo \esc_html__( 'Maximum number of trusted proxy hops to traverse (1–20).', 'verified-client-ip' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo \esc_html__( 'Process Proto', 'verified-client-ip' ); ?></th>
						<td>
							<label>
								<input type="hidden" name="vcip_process_proto" value="0">
								<input type="checkbox" name="vcip_process_proto" value="1"
									<?php echo $settings->process_proto ? 'checked' : ''; ?>>
								<?php echo \esc_html__( 'Set HTTPS and REQUEST_SCHEME from proxy headers', 'verified-client-ip' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo \esc_html__( 'Process Host', 'verified-client-ip' ); ?></th>
						<td>
							<label>
								<input type="hidden" name="vcip_process_host" value="0">
								<input type="checkbox" name="vcip_process_host" value="1"
									<?php echo $settings->process_host ? 'checked' : ''; ?>>
								<?php echo \esc_html__( 'Set HTTP_HOST and SERVER_NAME from proxy headers', 'verified-client-ip' ); ?>
							</label>
							<p class="description">
								<?php echo \esc_html__( 'Off by default. Enable only if your reverse proxy reports the original host.', 'verified-client-ip' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php echo \esc_html__( 'Forwarding Schemes', 'verified-client-ip' ); ?></h2>
				<p class="description">
					<?php echo \esc_html__( 'Schemes are checked in priority order (top = highest). Use the arrows to reorder.', 'verified-client-ip' ); ?>
				</p>

				<div id="vcip-schemes">
					<?php
					foreach ( $settings->schemes as $index => $scheme ) {
						self::render_scheme_panel( $scheme, $index );
					}
					?>
				</div>

				<p>
					<button type="button" class="button" id="vcip-add-scheme">
						<?php echo \esc_html__( '+ Add Scheme', 'verified-client-ip' ); ?>
					</button>
				</p>

				<?php
				if ( \function_exists( 'submit_button' ) ) {
					\submit_button( __( 'Save Settings', 'verified-client-ip' ) );
				} else {
					echo '<p><input type="submit" class="button button-primary" value="'
						. \esc_attr__( 'Save Settings', 'verified-client-ip' ) . '"></p>';
				}
				?>
			</form>

			<?php endif; ?>
		</div>

		<?php if ( 'settings' === $active_tab ) : ?>
			<?php self::render_inline_script(); ?>
		<?php endif; ?>
		<?php
	}

	// ------------------------------------------------------------------
	// Scheme panel rendering
	// ------------------------------------------------------------------

	/**
	 * Render a single scheme configuration panel.
	 */
	private static function render_scheme_panel( Scheme $scheme, int $index ): void {
		$prefix    = "vcip_schemes[{$index}]";
		$header_bg = $scheme->enabled ? 'background-color:rgb(240,246,252);' : '';
		?>
		<div class="vcip-scheme-panel postbox" data-index="<?php echo $index; ?>">
			<div class="postbox-header" style="<?php echo \esc_attr( $header_bg ); ?>">
				<h3 class="hndle">
					<span class="vcip-scheme-name"><?php echo \esc_html( $scheme->name ? $scheme->name : __( 'New Scheme', 'verified-client-ip' ) ); ?></span>
				</h3>
				<div class="vcip-scheme-controls" style="display:flex;align-items:center;gap:6px;margin-left:auto;padding-right:10px;">
					<label style="display:flex;align-items:center;gap:4px;font-weight:normal;cursor:pointer;">
						<input type="hidden" name="<?php echo \esc_attr( $prefix ); ?>[enabled]" value="0">
						<input type="checkbox" name="<?php echo \esc_attr( $prefix ); ?>[enabled]" value="1"
							class="vcip-enabled-checkbox"
							<?php echo $scheme->enabled ? 'checked' : ''; ?>>
						<?php echo \esc_html__( 'Enabled', 'verified-client-ip' ); ?>
					</label>
					<button type="button" class="button button-small vcip-move-up" title="<?php echo \esc_attr__( 'Move up', 'verified-client-ip' ); ?>">&uarr;</button>
					<button type="button" class="button button-small vcip-move-down" title="<?php echo \esc_attr__( 'Move down', 'verified-client-ip' ); ?>">&darr;</button>
				</div>
			</div>
			<div class="inside" style="display:none;">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label><?php echo \esc_html__( 'Name', 'verified-client-ip' ); ?></label>
						</th>
						<td>
							<input type="text" name="<?php echo \esc_attr( $prefix ); ?>[name]"
									value="<?php echo \esc_attr( $scheme->name ); ?>"
									class="regular-text vcip-scheme-name-input"
									maxlength="<?php echo Settings::SCHEME_NAME_MAX_LENGTH; ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label><?php echo \esc_html__( 'Header', 'verified-client-ip' ); ?></label>
						</th>
						<td>
							<input type="text" name="<?php echo \esc_attr( $prefix ); ?>[header]"
									value="<?php echo \esc_attr( $scheme->header ); ?>"
									class="regular-text"
									maxlength="<?php echo Settings::HEADER_NAME_MAX_LENGTH; ?>"
									placeholder="e.g. X-Forwarded-For">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label><?php echo \esc_html__( 'Token', 'verified-client-ip' ); ?></label>
						</th>
						<td>
							<input type="text" name="<?php echo \esc_attr( $prefix ); ?>[token]"
									value="<?php echo \esc_attr( $scheme->token ?? '' ); ?>"
									class="regular-text"
									placeholder="<?php echo \esc_attr__( 'e.g. for (leave blank for plain lists)', 'verified-client-ip' ); ?>">
							<p class="description">
								<?php echo \esc_html__( 'For structured headers like RFC 7239 Forwarded, specify the token (e.g. "for").', 'verified-client-ip' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label><?php echo \esc_html__( 'Trusted Proxies', 'verified-client-ip' ); ?></label>
						</th>
						<td>
							<textarea name="<?php echo \esc_attr( $prefix ); ?>[proxies]"
										rows="6" class="large-text code"
										placeholder="<?php echo \esc_attr__( "One IP or CIDR per line, e.g.\n10.0.0.0/8\n192.168.1.1\n::1/128", 'verified-client-ip' ); ?>"
							><?php echo \esc_textarea( \implode( "\n", $scheme->proxies ) ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label><?php echo \esc_html__( 'Notes', 'verified-client-ip' ); ?></label>
						</th>
						<td>
							<textarea name="<?php echo \esc_attr( $prefix ); ?>[notes]"
										rows="2" class="large-text"
										maxlength="<?php echo Settings::NOTES_MAX_LENGTH; ?>"
							><?php echo \esc_textarea( $scheme->notes ); ?></textarea>
						</td>
					</tr>
				</table>
				<p>
					<button type="button" class="button vcip-delete-scheme"
							style="color:#b32d2e;border-color:#b32d2e;"
							title="<?php echo \esc_attr__( 'Delete this scheme', 'verified-client-ip' ); ?>">
						<?php echo \esc_html__( 'Delete Scheme', 'verified-client-ip' ); ?>
					</button>
				</p>
			</div>
		</div>
		<?php
	}

	// ------------------------------------------------------------------
	// JavaScript for scheme management
	// ------------------------------------------------------------------

	/**
	 * Render inline JavaScript for add / delete / reorder schemes.
	 */
	private static function render_inline_script(): void {
		?>
		<script>
		(function () {
			var container = document.getElementById('vcip-schemes');
			if (!container) return;

			var schemeIndex = container.querySelectorAll('.vcip-scheme-panel').length;

			// Add scheme
			document.getElementById('vcip-add-scheme').addEventListener('click', function () {
				var tpl = <?php echo \wp_json_encode( self::scheme_template() ); ?>;
				tpl = tpl.replace(/__INDEX__/g, schemeIndex);
				var wrapper = document.createElement('div');
				wrapper.innerHTML = tpl;
				container.appendChild(wrapper.firstElementChild);
				schemeIndex++;
				reindex();
			});

			// Toggle collapse/expand on header click (but not on controls)
			container.addEventListener('click', function (e) {
				var header = e.target.closest('.postbox-header');
				if (!header) return;
				// Don't toggle if clicking a button, checkbox, or label inside controls
				if (e.target.closest('.vcip-scheme-controls')) return;
	
				var panel = header.closest('.vcip-scheme-panel');
				var inside = panel.querySelector('.inside');
				inside.style.display = inside.style.display === 'none' ? '' : 'none';
			});
	
			// Delegate move / delete
			container.addEventListener('click', function (e) {
				var btn = e.target.closest('button');
				if (!btn) return;
	
				var panel = btn.closest('.vcip-scheme-panel');
	
				if (btn.classList.contains('vcip-move-up') && panel.previousElementSibling) {
					container.insertBefore(panel, panel.previousElementSibling);
					reindex();
				} else if (btn.classList.contains('vcip-move-down') && panel.nextElementSibling) {
					container.insertBefore(panel.nextElementSibling, panel);
					reindex();
				} else if (btn.classList.contains('vcip-delete-scheme')) {
					if (confirm(<?php echo \wp_json_encode( __( 'Delete this scheme?', 'verified-client-ip' ) ); ?>)) {
						panel.remove();
						reindex();
					}
				}
			});
	
			// Enabled checkbox: update header background
			container.addEventListener('change', function (e) {
				if (e.target.classList.contains('vcip-enabled-checkbox')) {
					var header = e.target.closest('.postbox-header');
					if (header) {
						header.style.backgroundColor = e.target.checked ? 'rgb(240,246,252)' : '';
					}
				}
			});
	
			// Update live panel title
			container.addEventListener('input', function (e) {
				if (e.target.classList.contains('vcip-scheme-name-input')) {
					var panel = e.target.closest('.vcip-scheme-panel');
					var title = panel.querySelector('.vcip-scheme-name');
					title.textContent = e.target.value || <?php echo \wp_json_encode( __( 'New Scheme', 'verified-client-ip' ) ); ?>;
				}
			});
	
			function reindex() {
				var panels = container.querySelectorAll('.vcip-scheme-panel');
				panels.forEach(function (panel, i) {
					panel.dataset.index = i;
					// Update all input/select/textarea names.
					panel.querySelectorAll('[name]').forEach(function (el) {
						el.name = el.name.replace(/vcip_schemes\[\d+\]/, 'vcip_schemes[' + i + ']');
					});
				});
			}
			})();
			</script>
			<style>
				.vcip-scheme-panel { position: relative; margin-bottom: 12px; }
				.vcip-scheme-panel .postbox-header { display: flex; align-items: center; cursor: pointer; }
				.vcip-scheme-panel .postbox-header .vcip-scheme-controls { cursor: default; }
			</style>
		<?php
	}

	/**
	 * Return the HTML template for a new (empty) scheme panel.
	 *
	 * Uses `__INDEX__` as placeholder for the scheme array index.
	 */
	private static function scheme_template(): string {
		$scheme = new Scheme(
			name: '',
			enabled: true,
			proxies: [],
			header: '',
		);

		\ob_start();
		self::render_scheme_panel( $scheme, 0 );
		$html = \ob_get_clean();

		// Replace the concrete index 0 with the placeholder.
		return \str_replace(
			[ 'vcip_schemes[0]', 'data-index="0"' ],
			[ 'vcip_schemes[__INDEX__]', 'data-index="__INDEX__"' ],
			$html ? $html : '',
		);
	}

	// ------------------------------------------------------------------
	// Form parsing
	// ------------------------------------------------------------------

	/**
	 * Parse the raw $_POST data into the format expected by Settings::validate().
	 *
	 * @param array<string, mixed> $post
	 *
	 * @return array<string, mixed>
	 */
	public static function parse_form_input( array $post ): array {
		// WordPress adds magic quotes to $_POST via wp_magic_quotes().
		// Strip them before processing so that quotes and backslashes in
		// user-entered text are not double-encoded on each save.
		if ( \function_exists( 'wp_unslash' ) ) {
			/** @var array<string, mixed> $post */
			$post = \wp_unslash( $post );
		}

		$input = [
			'enabled'       => ! empty( $post['vcip_enabled'] ),
			'forward_limit' => $post['vcip_forward_limit'] ?? 1,
			'process_proto' => ! empty( $post['vcip_process_proto'] ),
			'process_host'  => ! empty( $post['vcip_process_host'] ),
		];

		$raw_schemes = $post['vcip_schemes'] ?? null;
		if ( \is_array( $raw_schemes ) ) {
			$schemes = [];
			foreach ( $raw_schemes as $raw ) {
				if ( ! \is_array( $raw ) ) {
					continue;
				}

				$proxies = $raw['proxies'] ?? '';
				if ( \is_string( $proxies ) ) {
					$split   = \preg_split( '/[\r\n]+/', $proxies );
					$proxies = \array_filter( \array_map( 'trim', false !== $split ? $split : [] ) );
					$proxies = \array_values( $proxies );
				}

				$schemes[] = [
					'name'    => $raw['name'] ?? '',
					'enabled' => ! empty( $raw['enabled'] ),
					'header'  => $raw['header'] ?? '',
					'token'   => $raw['token'] ?? '',
					'proxies' => $proxies,
					'notes'   => $raw['notes'] ?? '',
				];
			}

			$input['schemes'] = $schemes;
		}

		return $input;
	}

	// ------------------------------------------------------------------
	// Diagnostics actions
	// ------------------------------------------------------------------

	/**
	 * Handle Start / Clear diagnostics form submissions.
	 */
	private static function handle_diagnostics_action(): void {
		if ( ! \function_exists( 'current_user_can' ) || ! \current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! \function_exists( 'wp_verify_nonce' )
			|| ! isset( $_POST['vcip_diag_nonce'] )
			|| ! \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['vcip_diag_nonce'] ) ), 'vcip_diagnostics' )
		) {
			if ( \function_exists( 'add_settings_error' ) ) {
				\add_settings_error( 'vcip_settings', 'vcip_nonce_error', __( 'Security check failed.', 'verified-client-ip' ), 'error' );
			}
			return;
		}

		$action = \sanitize_text_field( $_POST['vcip_diag_action'] );

		if ( 'start' === $action ) {
			$count = isset( $_POST['vcip_diag_count'] ) ? (int) $_POST['vcip_diag_count'] : Diagnostics::DEFAULT_REQUEST_COUNT;
			Diagnostics::start_recording( $count );

			Logger::info( \sprintf( 'Diagnostics recording started (max %d requests)', $count ), 'admin' );

			if ( \function_exists( 'add_settings_error' ) ) {
				\add_settings_error( 'vcip_settings', 'vcip_diag_started', __( 'Diagnostics recording started.', 'verified-client-ip' ), 'success' );
			}
		} elseif ( 'clear' === $action ) {
			Diagnostics::clear();

			Logger::info( 'Diagnostic data cleared', 'admin' );

			if ( \function_exists( 'add_settings_error' ) ) {
				\add_settings_error( 'vcip_settings', 'vcip_diag_cleared', __( 'Diagnostic data cleared.', 'verified-client-ip' ), 'success' );
			}
		}
	}

	// ------------------------------------------------------------------
	// Diagnostics tab rendering
	// ------------------------------------------------------------------

	/**
	 * Render the diagnostics tab content.
	 */
	public static function render_diagnostics_tab(): void {
		$state = Diagnostics::get_state();
		$log   = Diagnostics::get_log();

		?>
		<h2><?php echo \esc_html__( 'Diagnostics', 'verified-client-ip' ); ?></h2>

		<div class="notice notice-warning inline" style="margin:15px 0;">
			<p>
				<strong><?php echo \esc_html__( 'Privacy Notice:', 'verified-client-ip' ); ?></strong>
				<?php echo \esc_html__( 'Diagnostic data contains IP addresses and HTTP headers, which may be considered personal data under GDPR and similar regulations. Clear diagnostics promptly after use.', 'verified-client-ip' ); ?>
			</p>
		</div>

		<form method="post" action="">
			<?php
			if ( \function_exists( 'wp_nonce_field' ) ) {
				\wp_nonce_field( 'vcip_diagnostics', 'vcip_diag_nonce' );
			}
			?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="vcip_diag_count"><?php echo \esc_html__( 'Requests to record', 'verified-client-ip' ); ?></label>
					</th>
					<td>
						<input type="number" id="vcip_diag_count" name="vcip_diag_count"
								value="<?php echo \esc_attr( (string) $state['max_requests'] ); ?>"
								min="1" max="<?php echo Diagnostics::MAX_REQUEST_COUNT; ?>"
								class="small-text"
								<?php echo $state['recording'] ? 'disabled' : ''; ?>>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo \esc_html__( 'Status', 'verified-client-ip' ); ?></th>
					<td>
						<?php if ( $state['recording'] ) : ?>
							<span style="color:green;font-weight:bold;">&#9679; <?php echo \esc_html__( 'Recording', 'verified-client-ip' ); ?></span>
							<?php
							// translators: %1$d is the number of recorded requests, %2$d is the recording limit.
							$recorded_text = \sprintf( __( '%1$d / %2$d requests recorded', 'verified-client-ip' ), \count( $log ), $state['max_requests'] );
							?>
						— <?php echo \esc_html( $recorded_text ); ?>
						<?php else : ?>
							<span style="color:#999;">&#9679; <?php echo \esc_html__( 'Stopped', 'verified-client-ip' ); ?></span>
							<?php if ( [] !== $log ) : ?>
								<?php
								// translators: %d is the number of recorded requests.
								$recorded_text = \sprintf( __( '%d requests recorded', 'verified-client-ip' ), \count( $log ) );
								?>
							— <?php echo \esc_html( $recorded_text ); ?>
							<?php endif; ?>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<p>
				<?php if ( ! $state['recording'] ) : ?>
					<button type="submit" name="vcip_diag_action" value="start" class="button button-primary">
						<?php echo \esc_html__( 'Start Diagnostics', 'verified-client-ip' ); ?>
					</button>
				<?php else : ?>
					<button type="submit" name="vcip_diag_action" value="start" class="button button-primary" disabled>
						<?php echo \esc_html__( 'Start Diagnostics', 'verified-client-ip' ); ?>
					</button>
				<?php endif; ?>

				<?php if ( [] !== $log ) : ?>
					<button type="submit" name="vcip_diag_action" value="clear" class="button"
							onclick="return confirm(<?php echo \esc_attr( (string) \wp_json_encode( __( 'Clear all diagnostic data?', 'verified-client-ip' ) ) ); ?>);">
						<?php echo \esc_html__( 'Clear Diagnostics', 'verified-client-ip' ); ?>
					</button>
				<?php endif; ?>
	
				<a href="<?php echo \esc_url( \remove_query_arg( 'vcip_diag_action' ) ); ?>" class="button">
					<?php echo \esc_html__( 'Refresh', 'verified-client-ip' ); ?>
				</a>
			</p>
		</form>

		<?php if ( [] !== $log ) : ?>
			<h3><?php echo \esc_html__( 'Recorded Requests', 'verified-client-ip' ); ?></h3>
			<table class="wp-list-table widefat striped">
				<thead>
					<tr>
						<th>#</th>
						<th><?php echo \esc_html__( 'Time', 'verified-client-ip' ); ?></th>
						<th><?php echo \esc_html__( 'Method', 'verified-client-ip' ); ?></th>
						<th><?php echo \esc_html__( 'URI', 'verified-client-ip' ); ?></th>
						<th><?php echo \esc_html__( 'Original IP', 'verified-client-ip' ); ?></th>
						<th><?php echo \esc_html__( 'Resolved IP', 'verified-client-ip' ); ?></th>
						<th><?php echo \esc_html__( 'Changed', 'verified-client-ip' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $log as $i => $entry ) : ?>
						<tr class="vcip-diag-row" data-index="<?php echo $i; ?>" style="cursor:pointer;">
							<td><?php echo $i + 1; ?></td>
							<td><?php echo \esc_html( $entry['timestamp'] ?? '' ); ?></td>
							<td><?php echo \esc_html( $entry['method'] ?? '' ); ?></td>
							<td><?php echo \esc_html( $entry['request_uri'] ?? '' ); ?></td>
							<td><code><?php echo \esc_html( $entry['original_ip'] ?? $entry['remote_addr'] ?? '' ); ?></code></td>
							<td><code><?php echo \esc_html( $entry['resolved_ip'] ?? $entry['remote_addr'] ?? '' ); ?></code></td>
							<td><?php echo ! empty( $entry['changed'] ) ? '&#10004;' : '—'; ?></td>
						</tr>
						<tr class="vcip-diag-detail" id="vcip-detail-<?php echo $i; ?>" style="display:none;">
							<td colspan="7">
								<?php self::render_diagnostic_detail( $entry ); ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<script>
			(function () {
				document.querySelectorAll('.vcip-diag-row').forEach(function (row) {
					row.addEventListener('click', function () {
						var detail = document.getElementById('vcip-detail-' + row.dataset.index);
						detail.style.display = detail.style.display === 'none' ? '' : 'none';
					});
				});
			})();
			</script>
			<?php
		endif;
	}

	/**
	 * Render the expandable detail panel for a single diagnostic entry.
	 *
	 * @param array<string, mixed> $entry
	 */
	private static function render_diagnostic_detail( array $entry ): void {
		// Step trace.
		if ( ! empty( $entry['steps'] ) && \is_array( $entry['steps'] ) ) {
			echo '<h4>' . \esc_html__( 'Client IP calculation', 'verified-client-ip' ) . '</h4>';
			echo '<p>' . \esc_html__( 'Note: If initial REMOTE_ADDR is not as you expect, then it may already be resolved by Apache <pre>mod_remoteip</pre> or nginx <pre>set_real_ip_from</pre>. See the user guide for details.', 'verified-client-ip' ) . '</p>';
			echo '<ol>';
			foreach ( $entry['steps'] as $step ) {
				echo '<li>';
				echo \esc_html( ( $step['description'] ?? $step['action'] ?? '' ) );
				if ( ! empty( $step['scheme'] ) ) {
					echo ' <em>(' . \esc_html( $step['scheme'] ) . ')</em>';
				}
				echo '</li>';
			}
			echo '</ol>';
		}

		// Proto info.
		if ( ! empty( $entry['proto'] ) && \is_array( $entry['proto'] ) ) {
			echo '<h4>' . \esc_html__( 'Proto / Host', 'verified-client-ip' ) . '</h4>';
			echo '<pre>' . \esc_html( (string) \wp_json_encode( $entry['proto'], \JSON_PRETTY_PRINT ) ) . '</pre>';
		}

		// All headers.
		if ( ! empty( $entry['headers'] ) && \is_array( $entry['headers'] ) ) {
			echo '<h4>' . \esc_html__( 'Original Headers', 'verified-client-ip' ) . '</h4>';
			echo '<table class="widefat" style="max-width:800px;">';
			foreach ( $entry['headers'] as $key => $value ) {
				echo '<tr><td><code>' . \esc_html( (string) $key ) . '</code></td>';
				echo '<td>' . \esc_html( (string) $value ) . '</td></tr>';
			}
			echo '</table>';
		}
	}

	// ------------------------------------------------------------------
	// User Guide tab
	// ------------------------------------------------------------------

	/**
		* Render the User Guide tab from the pre-built HTML file.
		*/
	private static function render_user_guide_tab(): void {
		$html_file = \plugin_dir_path( __DIR__ ) . 'src/user-guide.html';

		if ( ! \file_exists( $html_file ) ) {
			echo '<div class="notice notice-warning"><p>';
			echo \esc_html__( 'User guide not available. Run the build script to generate it.', 'verified-client-ip' );
			echo '</p></div>';
			return;
		}

		$html = \file_get_contents( $html_file );
		if ( false === $html ) {
			echo '<div class="notice notice-error"><p>';
			echo \esc_html__( 'Could not read user guide file.', 'verified-client-ip' );
			echo '</p></div>';
			return;
		}

		// The HTML was generated from trusted source (docs/user-guide.md) at
		// build time.  Output it directly inside a scoped wrapper.
		echo '<div class="vcip-user-guide-tab" style="max-width:900px;">';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted build-time HTML
		echo $html;
		echo '</div>';
	}
}
