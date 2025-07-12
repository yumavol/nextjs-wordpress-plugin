<?php

/**
 * Next.js WordPress Plugin: revalidation functionality
 *
 * Handles the revalidation of Next.js pages when WordPress content changes.
 * This class manages the transition of post status and triggers revalidation
 * on the Next.js frontend.
 *
 * @package NextJS_WordPress_Plugin
 * @since 1.0.0
 */

namespace NextJS_WordPress_Plugin;

/**
 * Manages the revalidation of Next.js pages in response to WordPress post updates.
 *
 * @author Greg Rickaby
 * @since 1.0.6
 */
class Revalidation
{

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
		add_action('transition_post_status', [$this, 'transition_handler'], 10, 3);
		add_action('created_category', [$this, 'category_change_handler'], 10, 3);
		add_action('edited_category', [$this, 'category_change_handler'], 10, 3);
		add_action('delete_category', [$this, 'category_delete_handler'], 10, 4);
	}

	/**
	 * Handles the post status transition for revalidation purposes.
	 *
	 * This method is triggered when a post's status transitions. It determines
	 * the appropriate slug for revalidation based on the post type and initiates
	 * the revalidation process.
	 *
	 * @param string $new_status New status of the post.
	 * @param string $old_status Old status of the post.
	 * @param object $post       The post object.
	 *
	 * @return void
	 */
	public function transition_handler(string $new_status, string $old_status, object $post): void
	{
		// Do not run on autosave or cron.
		if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || (defined('DOING_CRON') && DOING_CRON)) {
			return;
		}

		// Ignore drafts and inherited posts.
		if (('draft' === $new_status && 'draft' === $old_status) || 'inherit' === $new_status) {
			return;
		}

		// Determine the slug based on post type.
		$post_type = $post->post_type;
		$post_name = $post->post_name;

		/**
		 * Configure the $slug based on your post types and front-end routing.
		 */
		switch ($post_type) {
			case 'post':
				$slug = "/blog/{$post_name}";
				break;
			default:
				$slug = $post_name;
				break;
		}

		// Trigger revalidation.
		$this->on_demand_revalidation($slug);
	}

	/**
	 * Handles category creation and editing for revalidation purposes.
	 *
	 * This method is triggered when a category is created or edited.
	 * It revalidates the blog page since category changes may affect the blog listing.
	 *
	 * @param int   $term_id Term ID.
	 * @param int   $tt_id   Term taxonomy ID.
	 * @param array $args    Arguments passed to wp_insert_term() or wp_update_term().
	 *
	 * @return void
	 */
	public function category_change_handler(int $term_id, int $tt_id, array $args): void
	{
		// Trigger revalidation of the blog page.
		$this->on_demand_revalidation('/blog');
	}

	/**
	 * Handles category deletion for revalidation purposes.
	 *
	 * This method is triggered when a category is deleted.
	 * It revalidates the blog page since category deletion may affect the blog listing.
	 *
	 * @param int     $term_id       Term ID.
	 * @param int     $tt_id         Term taxonomy ID.
	 * @param mixed   $deleted_term  Copy of the already-deleted term.
	 * @param array   $object_ids    List of term object IDs.
	 *
	 * @return void
	 */
	public function category_delete_handler(int $term_id, int $tt_id, $deleted_term, array $object_ids): void
	{
		// Trigger revalidation of the blog page.
		$this->on_demand_revalidation('/blog');
	}

	/**
	 * Performs on-demand revalidation of a Next.js page.
	 *
	 * Sends a request to the Next.js revalidation endpoint to update the static
	 * content for a given slug.
	 *
	 * @param string $slug The slug of the post to revalidate.
	 *
	 * @return void
	 */
	public function on_demand_revalidation(string $slug): void
	{
		// Get frontend URL and revalidation secret from constants or settings.
		$frontend_url = $this->get_frontend_url();
		$revalidation_secret = $this->get_revalidation_secret();

		// Check necessary values and slug.
		if (! $frontend_url || ! $revalidation_secret || ! $slug) {
			return;
		}

		// Construct the revalidation URL.
		$revalidation_url = add_query_arg('slug', $slug, esc_url_raw(rtrim($frontend_url, '/') . '/api/revalidate'));

		// Make a GET request to the revalidation endpoint.
		$response = wp_remote_get(
			$revalidation_url,
			[
				'headers' => [
					'x-vercel-revalidation-secret' => $revalidation_secret,
				],
			]
		);

		// Handle response errors.
		if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
			error_log('Revalidation error: ' . wp_remote_retrieve_response_message($response)); // phpcs:ignore
		}
	}

	/**
	 * Get the frontend URL from constants or settings.
	 *
	 * @return string|null Frontend URL or null if not available.
	 */
	private function get_frontend_url(): ?string
	{
		if (defined('NEXTJS_FRONTEND_URL')) {
			return NEXTJS_FRONTEND_URL;
		}

		if (class_exists('NextJS_WordPress_Plugin\Settings')) {
			return Settings::get_setting('frontend_url');
		}

		return null;
	}

	/**
	 * Get the revalidation secret from constants or settings.
	 *
	 * @return string|null Revalidation secret or null if not available.
	 */
	private function get_revalidation_secret(): ?string
	{
		if (defined('NEXTJS_REVALIDATION_SECRET')) {
			return NEXTJS_REVALIDATION_SECRET;
		}

		if (class_exists('NextJS_WordPress_Plugin\Settings')) {
			return Settings::get_setting('revalidation_secret');
		}

		return null;
	}
}
