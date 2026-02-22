<?php

declare(strict_types=1);

namespace VerifiedClientIp;

/**
 * WordPress admin page for the "Verified Client IP" plugin.
 *
 * Registers a Settings sub-menu, renders the settings form, and processes
 * form submissions with nonce + capability checks.
 */
final class AdminPage
{
    /** The menu slug used for the settings page. */
    public const MENU_SLUG = 'verified-client-ip';

    /** Nonce action for the settings form. */
    private const NONCE_ACTION = 'vcip_save_settings';

    /** Nonce field name. */
    private const NONCE_FIELD = 'vcip_nonce';

    /**
     * Register hooks.  Call this from the main plugin file or Plugin class.
     */
    public static function register(): void
    {
        if (!\function_exists('add_action')) {
            return;
        }

        \add_action('admin_menu', [self::class, 'addMenuPage']);
        \add_action('admin_init', [self::class, 'handleFormSubmission']);
    }

    /**
     * Add the plugin settings page under the WordPress Settings menu.
     */
    public static function addMenuPage(): void
    {
        if (!\function_exists('add_options_page')) {
            return; // @codeCoverageIgnore
        }

        \add_options_page(
            __('Verified Client IP', 'verified-client-ip'),
            __('Verified Client IP', 'verified-client-ip'),
            'manage_options',
            self::MENU_SLUG,
            [self::class, 'renderPage'],
        );
    }

    /**
     * Process the form submission (save settings).
     */
    public static function handleFormSubmission(): void
    {
        // Only act on our form.
        if (!isset($_POST[self::NONCE_FIELD])) {
            return;
        }

        if (!\function_exists('current_user_can') || !\current_user_can('manage_options')) {
            return;
        }

        if (!\function_exists('wp_verify_nonce')
            || !\wp_verify_nonce(\sanitize_text_field(\wp_unslash($_POST[self::NONCE_FIELD])), self::NONCE_ACTION)
        ) {
            \add_settings_error(
                'vcip_settings',
                'vcip_nonce_error',
                __('Security check failed. Please try again.', 'verified-client-ip'),
                'error',
            );

            return;
        }

        $raw = self::parseFormInput($_POST);

        $result = Settings::validate($raw);

        if ($result['errors'] !== []) {
            foreach ($result['errors'] as $error) {
                if (\function_exists('add_settings_error')) {
                    \add_settings_error('vcip_settings', 'vcip_validation', $error, 'error');
                }
            }
        }

        // Save even when there are validation warnings — the validate()
        // method always returns a usable Settings, clamping invalid values.
        $result['settings']->save();

        if ($result['errors'] === [] && \function_exists('add_settings_error')) {
            \add_settings_error(
                'vcip_settings',
                'vcip_saved',
                __('Settings saved.', 'verified-client-ip'),
                'success',
            );
        }
    }

    /**
     * Render the full settings page.
     */
    public static function renderPage(): void
    {
        $settings = Settings::load();
        ?>
        <div class="wrap">
            <h1><?php echo \esc_html__('Verified Client IP', 'verified-client-ip'); ?></h1>

            <?php
            if (\function_exists('settings_errors')) {
                \settings_errors('vcip_settings');
            }
            ?>

            <form method="post" action="">
                <?php
                if (\function_exists('wp_nonce_field')) {
                    \wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);
                }
                ?>

                <h2><?php echo \esc_html__('General', 'verified-client-ip'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo \esc_html__('Enable plugin', 'verified-client-ip'); ?></th>
                        <td>
                            <label>
                                <input type="hidden" name="vcip_enabled" value="0">
                                <input type="checkbox" name="vcip_enabled" value="1"
                                    <?php echo $settings->enabled ? 'checked' : ''; ?>>
                                <?php echo \esc_html__('Enable IP resolution', 'verified-client-ip'); ?>
                            </label>
                            <p class="description">
                                <?php echo \esc_html__('When disabled, the plugin calculates the result (for diagnostics) but does not replace REMOTE_ADDR.', 'verified-client-ip'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="vcip_forward_limit"><?php echo \esc_html__('Forward Limit', 'verified-client-ip'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="vcip_forward_limit" name="vcip_forward_limit"
                                   value="<?php echo \esc_attr((string) $settings->forwardLimit); ?>"
                                   min="<?php echo Settings::FORWARD_LIMIT_MIN; ?>"
                                   max="<?php echo Settings::FORWARD_LIMIT_MAX; ?>"
                                   class="small-text">
                            <p class="description">
                                <?php echo \esc_html__('Maximum number of trusted proxy hops to traverse (1–20).', 'verified-client-ip'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo \esc_html__('Process Proto', 'verified-client-ip'); ?></th>
                        <td>
                            <label>
                                <input type="hidden" name="vcip_process_proto" value="0">
                                <input type="checkbox" name="vcip_process_proto" value="1"
                                    <?php echo $settings->processProto ? 'checked' : ''; ?>>
                                <?php echo \esc_html__('Set HTTPS and REQUEST_SCHEME from proxy headers', 'verified-client-ip'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo \esc_html__('Process Host', 'verified-client-ip'); ?></th>
                        <td>
                            <label>
                                <input type="hidden" name="vcip_process_host" value="0">
                                <input type="checkbox" name="vcip_process_host" value="1"
                                    <?php echo $settings->processHost ? 'checked' : ''; ?>>
                                <?php echo \esc_html__('Set HTTP_HOST and SERVER_NAME from proxy headers', 'verified-client-ip'); ?>
                            </label>
                            <p class="description">
                                <?php echo \esc_html__('Off by default. Enable only if your reverse proxy reports the original host.', 'verified-client-ip'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <h2><?php echo \esc_html__('Forwarding Schemes', 'verified-client-ip'); ?></h2>
                <p class="description">
                    <?php echo \esc_html__('Schemes are checked in priority order (top = highest). Use the arrows to reorder.', 'verified-client-ip'); ?>
                </p>

                <div id="vcip-schemes">
                    <?php
                    foreach ($settings->schemes as $index => $scheme) {
                        self::renderSchemePanel($scheme, $index);
                    }
                    ?>
                </div>

                <p>
                    <button type="button" class="button" id="vcip-add-scheme">
                        <?php echo \esc_html__('+ Add Scheme', 'verified-client-ip'); ?>
                    </button>
                </p>

                <?php
                if (\function_exists('submit_button')) {
                    \submit_button(__('Save Settings', 'verified-client-ip'));
                } else {
                    echo '<p><input type="submit" class="button button-primary" value="'
                        . \esc_attr__('Save Settings', 'verified-client-ip') . '"></p>';
                }
                ?>
            </form>
        </div>

        <?php self::renderInlineScript(); ?>
        <?php
    }

    // ------------------------------------------------------------------
    // Scheme panel rendering
    // ------------------------------------------------------------------

    /**
     * Render a single scheme configuration panel.
     */
    private static function renderSchemePanel(Scheme $scheme, int $index): void
    {
        $prefix = "vcip_schemes[{$index}]";
        ?>
        <div class="vcip-scheme-panel postbox" data-index="<?php echo $index; ?>">
            <div class="postbox-header">
                <h3 class="hndle">
                    <span class="vcip-scheme-name"><?php echo \esc_html($scheme->name ?: __('New Scheme', 'verified-client-ip')); ?></span>
                    <?php if (!$scheme->enabled): ?>
                        <span class="vcip-scheme-badge" style="color:#999;font-weight:normal;margin-left:8px;">(<?php echo \esc_html__('disabled', 'verified-client-ip'); ?>)</span>
                    <?php endif; ?>
                </h3>
                <div class="vcip-scheme-controls" style="position:absolute;right:10px;top:6px;">
                    <button type="button" class="button button-small vcip-move-up" title="<?php echo \esc_attr__('Move up', 'verified-client-ip'); ?>">&uarr;</button>
                    <button type="button" class="button button-small vcip-move-down" title="<?php echo \esc_attr__('Move down', 'verified-client-ip'); ?>">&darr;</button>
                    <button type="button" class="button button-small vcip-delete-scheme" title="<?php echo \esc_attr__('Delete', 'verified-client-ip'); ?>">&times;</button>
                </div>
            </div>
            <div class="inside">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label><?php echo \esc_html__('Name', 'verified-client-ip'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="<?php echo \esc_attr($prefix); ?>[name]"
                                   value="<?php echo \esc_attr($scheme->name); ?>"
                                   class="regular-text vcip-scheme-name-input"
                                   maxlength="<?php echo Settings::SCHEME_NAME_MAX_LENGTH; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo \esc_html__('Enabled', 'verified-client-ip'); ?></th>
                        <td>
                            <input type="hidden" name="<?php echo \esc_attr($prefix); ?>[enabled]" value="0">
                            <input type="checkbox" name="<?php echo \esc_attr($prefix); ?>[enabled]" value="1"
                                <?php echo $scheme->enabled ? 'checked' : ''; ?>>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php echo \esc_html__('Header', 'verified-client-ip'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="<?php echo \esc_attr($prefix); ?>[header]"
                                   value="<?php echo \esc_attr($scheme->header); ?>"
                                   class="regular-text"
                                   maxlength="<?php echo Settings::HEADER_NAME_MAX_LENGTH; ?>"
                                   placeholder="e.g. X-Forwarded-For">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php echo \esc_html__('Token', 'verified-client-ip'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="<?php echo \esc_attr($prefix); ?>[token]"
                                   value="<?php echo \esc_attr($scheme->token ?? ''); ?>"
                                   class="regular-text"
                                   placeholder="<?php echo \esc_attr__('e.g. for (leave blank for plain lists)', 'verified-client-ip'); ?>">
                            <p class="description">
                                <?php echo \esc_html__('For structured headers like RFC 7239 Forwarded, specify the token (e.g. "for").', 'verified-client-ip'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php echo \esc_html__('Trusted Proxies', 'verified-client-ip'); ?></label>
                        </th>
                        <td>
                            <textarea name="<?php echo \esc_attr($prefix); ?>[proxies]"
                                      rows="6" class="large-text code"
                                      placeholder="<?php echo \esc_attr__("One IP or CIDR per line, e.g.\n10.0.0.0/8\n192.168.1.1\n::1/128", 'verified-client-ip'); ?>"
                            ><?php echo \esc_textarea(\implode("\n", $scheme->proxies)); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php echo \esc_html__('Notes', 'verified-client-ip'); ?></label>
                        </th>
                        <td>
                            <textarea name="<?php echo \esc_attr($prefix); ?>[notes]"
                                      rows="2" class="large-text"
                                      maxlength="<?php echo Settings::NOTES_MAX_LENGTH; ?>"
                            ><?php echo \esc_textarea($scheme->notes); ?></textarea>
                        </td>
                    </tr>
                </table>
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
    private static function renderInlineScript(): void
    {
        ?>
        <script>
        (function () {
            var container = document.getElementById('vcip-schemes');
            if (!container) return;

            var schemeIndex = container.querySelectorAll('.vcip-scheme-panel').length;

            // Add scheme
            document.getElementById('vcip-add-scheme').addEventListener('click', function () {
                var tpl = <?php echo \wp_json_encode(self::schemeTemplate()); ?>;
                tpl = tpl.replace(/__INDEX__/g, schemeIndex);
                var wrapper = document.createElement('div');
                wrapper.innerHTML = tpl;
                container.appendChild(wrapper.firstElementChild);
                schemeIndex++;
                reindex();
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
                    if (confirm(<?php echo \wp_json_encode(__('Delete this scheme?', 'verified-client-ip')); ?>)) {
                        panel.remove();
                        reindex();
                    }
                }
            });

            // Update live panel title
            container.addEventListener('input', function (e) {
                if (e.target.classList.contains('vcip-scheme-name-input')) {
                    var panel = e.target.closest('.vcip-scheme-panel');
                    var title = panel.querySelector('.vcip-scheme-name');
                    title.textContent = e.target.value || <?php echo \wp_json_encode(__('New Scheme', 'verified-client-ip')); ?>;
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
            .vcip-scheme-panel .postbox-header { display: flex; align-items: center; }
            .vcip-scheme-controls { display: flex; gap: 4px; }
        </style>
        <?php
    }

    /**
     * Return the HTML template for a new (empty) scheme panel.
     *
     * Uses `__INDEX__` as placeholder for the scheme array index.
     */
    private static function schemeTemplate(): string
    {
        $scheme = new Scheme(
            name: '',
            enabled: true,
            proxies: [],
            header: '',
        );

        \ob_start();
        self::renderSchemePanel($scheme, 0);
        $html = \ob_get_clean();

        // Replace the concrete index 0 with the placeholder.
        return \str_replace(
            ['vcip_schemes[0]', 'data-index="0"'],
            ['vcip_schemes[__INDEX__]', 'data-index="__INDEX__"'],
            $html ?: '',
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
    public static function parseFormInput(array $post): array
    {
        $input = [
            'enabled'       => !empty($post['vcip_enabled']),
            'forward_limit' => $post['vcip_forward_limit'] ?? 1,
            'process_proto' => !empty($post['vcip_process_proto']),
            'process_host'  => !empty($post['vcip_process_host']),
        ];

        $rawSchemes = $post['vcip_schemes'] ?? [];
        if (\is_array($rawSchemes)) {
            $schemes = [];
            foreach ($rawSchemes as $raw) {
                if (!\is_array($raw)) {
                    continue;
                }

                $proxies = $raw['proxies'] ?? '';
                if (\is_string($proxies)) {
                    $proxies = \array_filter(\array_map('trim', \preg_split('/[\r\n]+/', $proxies)));
                    $proxies = \array_values($proxies);
                }

                $schemes[] = [
                    'name'    => $raw['name'] ?? '',
                    'enabled' => !empty($raw['enabled']),
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
}
