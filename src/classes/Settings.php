<?php

/**
 * Next.js WordPress Plugin: settings functionality
 *
 * Handles the admin settings page for the Next.js WordPress plugin.
 * Provides configuration options and manual revalidation tools.
 *
 * @package NextJS_WordPress_Plugin
 * @since 1.0.0
 */

namespace NextJS_WordPress_Plugin;

/**
 * Manages the admin settings page and manual revalidation functionality.
 *
 * @author Greg Rickaby
 * @since 1.0.6
 */
class Settings
{

  /**
   * Option name for storing plugin settings.
   */
  const OPTION_NAME = 'nextjs_wordpress_plugin_settings';

  /**
   * Constructor.
   */
  public function __construct()
  {
    $this->hooks();
  }

  /**
   * Registers hooks for the class.
   *
   * @return void
   */
  public function hooks(): void
  {
    add_action('admin_menu', [$this, 'add_admin_menu']);
    add_action('admin_init', [$this, 'register_settings']);
    add_action('wp_ajax_nextjs_manual_revalidate', [$this, 'handle_manual_revalidation']);
    add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
  }

  /**
   * Add admin menu page.
   *
   * @return void
   */
  public function add_admin_menu(): void
  {
    add_options_page(
      'Next.js WordPress Plugin',
      'Next.js Plugin',
      'manage_options',
      'nextjs-wordpress-plugin',
      [$this, 'render_settings_page']
    );
  }

  /**
   * Register plugin settings.
   *
   * @return void
   */
  public function register_settings(): void
  {
    register_setting('nextjs_wordpress_plugin', self::OPTION_NAME);

    add_settings_section(
      'nextjs_general_settings',
      'General Settings',
      [$this, 'render_general_section'],
      'nextjs_wordpress_plugin'
    );

    add_settings_field(
      'frontend_url',
      'Frontend URL',
      [$this, 'render_frontend_url_field'],
      'nextjs_wordpress_plugin',
      'nextjs_general_settings'
    );

    add_settings_field(
      'revalidation_secret',
      'Revalidation Secret',
      [$this, 'render_revalidation_secret_field'],
      'nextjs_wordpress_plugin',
      'nextjs_general_settings'
    );

    add_settings_field(
      'preview_secret',
      'Preview Secret',
      [$this, 'render_preview_secret_field'],
      'nextjs_wordpress_plugin',
      'nextjs_general_settings'
    );

    add_settings_section(
      'nextjs_revalidation_settings',
      'Manual Revalidation',
      [$this, 'render_revalidation_section'],
      'nextjs_wordpress_plugin'
    );

    add_settings_field(
      'manual_revalidation',
      'Revalidate Pages',
      [$this, 'render_manual_revalidation_field'],
      'nextjs_wordpress_plugin',
      'nextjs_revalidation_settings'
    );
  }

  /**
   * Render the settings page.
   *
   * @return void
   */
  public function render_settings_page(): void
  {
?>
    <div class="wrap">
      <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
      <form action="options.php" method="post">
        <?php
        settings_fields('nextjs_wordpress_plugin');
        do_settings_sections('nextjs_wordpress_plugin');
        submit_button();
        ?>
      </form>
    </div>
  <?php
  }

  /**
   * Render general settings section description.
   *
   * @return void
   */
  public function render_general_section(): void
  {
    echo '<p>Configure your Next.js frontend connection settings.</p>';
  }

  /**
   * Render revalidation section description.
   *
   * @return void
   */
  public function render_revalidation_section(): void
  {
    echo '<p>Manually trigger revalidation for specific pages or clear all cached pages.</p>';
  }

  /**
   * Render frontend URL field.
   *
   * @return void
   */
  public function render_frontend_url_field(): void
  {
    $settings = get_option(self::OPTION_NAME, []);
    $value = $settings['frontend_url'] ?? '';
  ?>
    <input type="url"
      name="<?php echo esc_attr(self::OPTION_NAME); ?>[frontend_url]"
      value="<?php echo esc_attr($value); ?>"
      class="regular-text"
      placeholder="https://your-nextjs-site.com" />
    <p class="description">The URL of your Next.js frontend application.</p>
  <?php
  }

  /**
   * Render revalidation secret field.
   *
   * @return void
   */
  public function render_revalidation_secret_field(): void
  {
    $settings = get_option(self::OPTION_NAME, []);
    $value = $settings['revalidation_secret'] ?? '';
  ?>
    <input type="password"
      name="<?php echo esc_attr(self::OPTION_NAME); ?>[revalidation_secret]"
      value="<?php echo esc_attr($value); ?>"
      class="regular-text"
      placeholder="your-secret-token" />
    <p class="description">Secret token for authenticating revalidation requests.</p>
  <?php
  }

  /**
   * Render preview secret field.
   *
   * @return void
   */
  public function render_preview_secret_field(): void
  {
    $settings = get_option(self::OPTION_NAME, []);
    $value = $settings['preview_secret'] ?? '';
  ?>
    <input type="password"
      name="<?php echo esc_attr(self::OPTION_NAME); ?>[preview_secret]"
      value="<?php echo esc_attr($value); ?>"
      class="regular-text"
      placeholder="your-preview-secret" />
    <p class="description">Secret token for authenticating preview requests.</p>
  <?php
  }

  /**
   * Render manual revalidation field.
   *
   * @return void
   */
  public function render_manual_revalidation_field(): void
  {
  ?>
    <div id="nextjs-revalidation-controls">
      <div class="nextjs-revalidation-group">
        <label for="revalidation-slug">Specific Page Slug:</label>
        <input type="text"
          id="revalidation-slug"
          placeholder="/blog/my-post"
          class="regular-text" />
        <button type="button"
          id="revalidate-single"
          class="button button-primary">
          Revalidate Page
        </button>
      </div>

      <div class="nextjs-revalidation-group">
        <label>Common Pages:</label>
        <div class="nextjs-quick-actions">
          <button type="button"
            class="button"
            data-slug="/">
            Homepage
          </button>
          <button type="button"
            class="button"
            data-slug="/blog">
            Blog
          </button>
          <button type="button"
            class="button"
            data-slug="/about">
            About
          </button>
        </div>
      </div>

      <div class="nextjs-revalidation-group">
        <button type="button"
          id="revalidate-all"
          class="button button-secondary">
          Clear All Cache
        </button>
        <p class="description">This will attempt to revalidate common page types.</p>
      </div>

      <div id="revalidation-status" class="nextjs-status"></div>
    </div>

    <style>
      .nextjs-revalidation-group {
        margin-bottom: 20px;
        padding: 15px;
        border: 1px solid #ddd;
        background: #f9f9f9;
      }

      .nextjs-quick-actions {
        margin-top: 5px;
      }

      .nextjs-quick-actions .button {
        margin-right: 10px;
        margin-bottom: 5px;
      }

      .nextjs-status {
        margin-top: 15px;
        padding: 10px;
        border-radius: 4px;
        display: none;
      }

      .nextjs-status.success {
        background: #d4edda;
        border: 1px solid #c3e6cb;
        color: #155724;
      }

      .nextjs-status.error {
        background: #f8d7da;
        border: 1px solid #f5c6cb;
        color: #721c24;
      }

      .nextjs-status.loading {
        background: #d1ecf1;
        border: 1px solid #bee5eb;
        color: #0c5460;
      }
    </style>
<?php
  }

  /**
   * Handle manual revalidation AJAX request.
   *
   * @return void
   */
  public function handle_manual_revalidation(): void
  {
    check_ajax_referer('nextjs_revalidation_nonce', 'nonce');

    if (! current_user_can('manage_options')) {
      wp_die('Unauthorized');
    }

    $slug = sanitize_text_field($_POST['slug'] ?? '');
    $action_type = sanitize_text_field($_POST['action_type'] ?? '');

    if (empty($slug) && 'clear_all' !== $action_type) {
      wp_send_json_error(['message' => 'Slug is required']);
    }

    $revalidation = new Revalidation();

    if ('clear_all' === $action_type) {
      $this->revalidate_common_pages($revalidation);
    } else {
      $revalidation->on_demand_revalidation($slug);
    }

    wp_send_json_success([
      'message' => 'clear_all' === $action_type
        ? 'Cache clearing initiated'
        : "Revalidation triggered for: {$slug}"
    ]);
  }

  /**
   * Revalidate common pages.
   *
   * @param Revalidation $revalidation Revalidation instance.
   * @return void
   */
  private function revalidate_common_pages(Revalidation $revalidation): void
  {
    $common_slugs = ['/', '/blog', '/about'];

    $recent_posts = get_posts([
      'numberposts' => 10,
      'post_status' => 'publish'
    ]);

    foreach ($recent_posts as $post) {
      $common_slugs[] = "/blog/{$post->post_name}";
    }

    foreach ($common_slugs as $slug) {
      $revalidation->on_demand_revalidation($slug);
    }
  }

  /**
   * Enqueue admin scripts.
   *
   * @param string $hook_suffix Current admin page hook suffix.
   * @return void
   */
  public function enqueue_admin_scripts(string $hook_suffix): void
  {
    if ('settings_page_nextjs-wordpress-plugin' !== $hook_suffix) {
      return;
    }

    wp_enqueue_script('jquery');

    $script = "
		jQuery(document).ready(function($) {
			const statusDiv = $('#revalidation-status');
			
			function showStatus(message, type) {
				statusDiv.removeClass('success error loading').addClass(type);
				statusDiv.text(message).show();
				
				if (type !== 'loading') {
					setTimeout(() => statusDiv.fadeOut(), 3000);
				}
			}
			
			function performRevalidation(slug, actionType = 'single') {
				showStatus('Processing...', 'loading');
				
				$.post(ajaxurl, {
					action: 'nextjs_manual_revalidate',
					slug: slug,
					action_type: actionType,
					nonce: '" . wp_create_nonce('nextjs_revalidation_nonce') . "'
				})
				.done(function(response) {
					if (response.success) {
						showStatus(response.data.message, 'success');
					} else {
						showStatus(response.data.message || 'Error occurred', 'error');
					}
				})
				.fail(function() {
					showStatus('Network error occurred', 'error');
				});
			}
			
			$('#revalidate-single').click(function() {
				const slug = $('#revalidation-slug').val().trim();
				if (!slug) {
					showStatus('Please enter a slug', 'error');
					return;
				}
				performRevalidation(slug);
			});
			
			$('.nextjs-quick-actions .button').click(function() {
				const slug = $(this).data('slug');
				performRevalidation(slug);
			});
			
			$('#revalidate-all').click(function() {
				if (confirm('This will attempt to clear cache for common pages. Continue?')) {
					performRevalidation('', 'clear_all');
				}
			});
		});
		";

    wp_add_inline_script('jquery', $script);
  }

  /**
   * Get plugin setting value.
   *
   * @param string $key Setting key.
   * @param mixed  $default Default value if setting doesn't exist.
   * @return mixed Setting value.
   */
  public static function get_setting(string $key, $default = '')
  {
    $settings = get_option(self::OPTION_NAME, []);
    return $settings[$key] ?? $default;
  }
}
