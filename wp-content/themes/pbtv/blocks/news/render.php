<?php

/**
 * Server-side render for the pbtv/news block.
 *
 * @var array<string, mixed> $attributes Block attributes.
 */

if (! defined('ABSPATH')) {
	exit;
}

$heading   = isset($attributes['heading']) ? sanitize_text_field($attributes['heading']) : '';
$max_items = isset($attributes['maxItems']) ? absint($attributes['maxItems']) : 8;
$max_items = max(1, $max_items);

$items = pbtv_get_news_items($max_items);

if (! $items) {
	if (is_admin()) {
		printf(
			'<p %s>%s</p>',
			wp_kses_post(get_block_wrapper_attributes()),
			esc_html__('No news items available at the moment.', 'pbtv')
		);
	}

	return;
}

$display_items = array_map('pbtv_news_prepare_item_for_display', $items);
$topic_select_id = wp_unique_id('pbtv-news-topic-');

$news_context = array(
	'activeTopic' => '',
	'isLoading'   => false,
	'hasError'    => false,
	'maxItems'    => $max_items,
	'restUrl'     => esc_url_raw(rest_url('pbtv/v1/news')),
	'emptyLabel'  => __('No news items found for this topic.', 'pbtv'),
	'errorLabel'  => __('Could not load news right now. Please try again.', 'pbtv'),
);

/*
 * Mirrors view.js's `state.isTopicActive` getter so the server-rendered
 * markup already has the right active/inactive classes before the view
 * script hydrates, instead of every topic starting out looking inactive.
 */
wp_interactivity_state(
	'pbtv/news',
	array(
		'isTopicActive' => static function () {
			$item_context = wp_interactivity_get_context();

			return ( $item_context['activeTopic'] ?? '' ) === ( $item_context['topic'] ?? '' );
		},
	)
);
?>
<div <?php echo wp_kses_post(get_block_wrapper_attributes()); ?> data-wp-interactive="pbtv/news" <?php echo wp_interactivity_data_wp_context($news_context); ?>>
	<div class="container py-10 mx-auto px-4 xl:px-0">

		<div class="flex justify-between gap-2 mb-4">
			<div class="flex items-center gap-2 mb-4">
				<span class="w-2 h-2 rounded-full bg-pbtv-red" aria-hidden="true"></span>
				<h3 class="text-pbtv-green uppercase font-bold"><?php echo esc_html($heading); ?></h3>
			</div>

			<nav class="topics hidden md:flex justify-between gap-1 mb-4" aria-label="<?php echo esc_attr__('Filter news by topic', 'pbtv'); ?>">
				<h4 class="text-sm font-semibold">
					<button
						type="button"
						class="rounded px-4 py-2 transition-all duration-250 cursor-pointer"
						data-wp-on--click="actions.setTopic"
						data-wp-class--bg-pbtv-green="state.isTopicActive"
						data-wp-class--text-white="state.isTopicActive"
						data-wp-class--bg-pbtv-green-light="!state.isTopicActive"
						data-wp-class--text-pbtv-green="!state.isTopicActive"
						data-wp-class--opacity-50="context.isLoading"
						data-wp-bind--aria-current="state.ariaCurrent"
						data-wp-bind--disabled="context.isLoading"
						<?php echo wp_interactivity_data_wp_context(array('topic' => '')); ?>
					><?php esc_html_e('Destaques', 'pbtv'); ?></button>
				</h4>

				<?php $topics = pbtv_news_topics();
				foreach ($topics as $topic => $subtopics) : ?>
					<h4 class="text-sm font-semibold">
						<button
							type="button"
							class="rounded px-4 py-2 transition-all duration-250 cursor-pointer"
							data-wp-on--click="actions.setTopic"
							data-wp-class--bg-pbtv-green="state.isTopicActive"
							data-wp-class--text-white="state.isTopicActive"
							data-wp-class--bg-pbtv-green-light="!state.isTopicActive"
							data-wp-class--text-pbtv-green="!state.isTopicActive"
							data-wp-class--opacity-50="context.isLoading"
							data-wp-bind--aria-current="state.ariaCurrent"
							data-wp-bind--disabled="context.isLoading"
							<?php echo wp_interactivity_data_wp_context(array('topic' => $topic)); ?>
						><?php echo esc_html($topic); ?></button>
					</h4>
				<?php endforeach; ?>
			</nav>

			<div class="topics-mobile md:hidden mb-4 w-full max-w-40">
				<label class="sr-only" for="<?php echo esc_attr($topic_select_id); ?>"><?php echo esc_html__('Filter news by topic', 'pbtv'); ?></label>
				<div class="relative">
					<select
						id="<?php echo esc_attr($topic_select_id); ?>"
						class="w-full appearance-none rounded px-3 py-2 pr-8 text-sm font-semibold bg-pbtv-green text-white transition-all duration-250"
						data-wp-on--change="actions.setTopicFromSelect"
						data-wp-bind--value="context.activeTopic"
						data-wp-class--opacity-50="context.isLoading"
						data-wp-bind--disabled="context.isLoading"
					>
						<option value=""><?php esc_html_e('Destaques', 'pbtv'); ?></option>
						<?php foreach ($topics as $topic => $subtopics) : ?>
							<option value="<?php echo esc_attr($topic); ?>"><?php echo esc_html($topic); ?></option>
						<?php endforeach; ?>
					</select>
					<svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-3 h-3 text-white" viewBox="0 0 12 12" fill="none" aria-hidden="true">
						<path d="M2.5 4.5L6 8L9.5 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
					</svg>
				</div>
			</div>
		</div>

		<p class="text-sm text-pbtv-red mb-4" data-wp-bind--hidden="!context.hasError" aria-live="polite"><?php echo esc_html($news_context['errorLabel']); ?></p>

		<div class="relative">
			<div
				class="absolute inset-0 z-10 flex items-center justify-center gap-2 rounded-xl bg-white/70"
				data-wp-bind--hidden="!context.isLoading"
				aria-hidden="true"
			>
				<span class="h-6 w-6 rounded-full border-2 border-gray-300 border-t-pbtv-red animate-spin"></span>
			</div>

			<div
				class="news-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4"
				data-wp-class--opacity-40="context.isLoading"
				data-wp-bind--aria-busy="context.isLoading"
				aria-live="polite"
				data-empty-label="<?php echo esc_attr($news_context['emptyLabel']); ?>"
			>
				<?php foreach ($display_items as $item) : ?>
				<a
					class="block rounded-xl overflow-hidden bg-white shadow-sm hover:shadow-md hover:-translate-y-0.5 transition"
					href="<?php echo esc_url($item['link']); ?>"
					target="_blank"
					rel="noopener noreferrer">
					<div class="relative aspect-video bg-gray-100">
						<?php if ($item['image']) : ?>
							<img
								class="w-full h-full object-cover"
								src="<?php echo esc_url($item['image']); ?>"
								alt=""
								loading="lazy" />
						<?php else : ?>
							<div
								class="w-full h-full flex items-center justify-center text-white text-3xl font-bold"
								style="background-color: <?php echo esc_attr($item['color']); ?>;">
								<?php echo esc_html($item['initial']); ?>
							</div>
						<?php endif; ?>
						<span class="absolute top-2 left-2 rounded-full bg-black/65 text-white text-[10px] font-bold uppercase tracking-wide px-2 py-1"><?php echo esc_html($item['source']); ?></span>
						<span class="absolute top-2 right-2 rounded-full bg-white/90 text-gray-900 text-[10px] font-bold uppercase tracking-wide px-2 py-1"><?php echo esc_html($item['topic']); ?></span>
					</div>
					<div class="p-3">
						<p class="text-sm font-semibold text-gray-900 leading-snug mb-2 line-clamp-3"><?php echo esc_html($item['title']); ?></p>
						<p class="text-xs text-gray-500"><?php echo esc_html($item['date']); ?></p>
					</div>
				</a>
			<?php endforeach; ?>
			</div>
		</div>
	</div>
</div>