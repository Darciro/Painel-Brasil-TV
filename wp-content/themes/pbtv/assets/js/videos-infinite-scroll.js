/**
 * Interactivity API store for the [pbtv-videos] shortcode.
 *
 * Watches a sentinel element at the end of the video grid and fetches the
 * next page from the pbtv/v1/videos REST route as it scrolls into view,
 * appending the results without a full page reload.
 */

import { store, getContext, getElement } from '@wordpress/interactivity';

const NAMESPACE = 'pbtv/videos';

/**
 * Rejects non-http(s) URLs so a malformed or unexpected API response can
 * never inject a javascript: or data: URL into an href/src.
 *
 * @param {string} url URL to check.
 * @return {boolean} True when the URL uses http or https.
 */
function isSafeUrl(url) {
	try {
		return ['http:', 'https:'].includes(new URL(url, window.location.href).protocol);
	} catch (error) {
		return false;
	}
}

/**
 * Builds a video's publish date line, wrapping the display date in a
 * <time> element carrying the machine-readable datetime. Mirrors
 * pbtv_render_video_date() for the server-rendered cards.
 *
 * @param {Object} video Prepared video item.
 * @return {HTMLElement|null} The date element, or null when the video has no date.
 */
function buildDate(video) {
	if (!video.date) {
		return null;
	}

	const paragraph = document.createElement('p');
	paragraph.className = 'text-[10px] text-pbtv-green mt-2 font-bold uppercase';

	const time = document.createElement('time');
	time.textContent = video.date;

	if (video.datetime) {
		time.dateTime = video.datetime;
	}

	paragraph.append(time);

	return paragraph;
}

/**
 * Builds a single video card as DOM nodes, using textContent/attribute
 * assignment (never innerHTML) so API-sourced titles can never be
 * interpreted as markup.
 *
 * @param {Object} video Prepared video item (see pbtv_videos_shortcode_prepare_item_for_display()).
 * @return {HTMLAnchorElement} The card element.
 */
function buildVideoCard(video) {
	const card = document.createElement('a');
	card.className = 'block hover:-translate-y-0.5 transition';
	card.href = isSafeUrl(video.url) ? video.url : '#';

	const media = document.createElement('div');
	media.className = 'relative aspect-video bg-gray-100';

	if (video.thumbnail && isSafeUrl(video.thumbnail)) {
		const img = document.createElement('img');
		img.className = 'w-full h-full object-cover';
		img.src = video.thumbnail;
		img.alt = '';
		img.loading = 'lazy';
		media.append(img);
	}

	const body = document.createElement('div');
	body.className = 'py-3';

	const title = document.createElement('p');
	title.className = 'text-sm font-bold text-pbtv-red leading-snug line-clamp-3';
	title.textContent = video.title || '';

	const date = buildDate(video);
	body.append(...(date ? [date, title] : [title]));
	card.append(media, body);

	return card;
}

/**
 * Fetches the next page of videos and appends them to the grid.
 *
 * @param {Object}      context Interactivity context for the shortcode instance.
 * @param {HTMLElement} grid    The video grid element.
 */
async function loadMoreVideos(context, grid) {
	if (context.isLoading || !context.hasMore) {
		return;
	}

	context.isLoading = true;
	context.hasError = false;

	try {
		const url = new URL(context.restUrl);
		url.searchParams.set('channelId', context.channelId);
		url.searchParams.set('offset', String(context.offset));
		url.searchParams.set('count', String(context.perPage));

		const response = await window.fetch(url.toString(), {
			headers: { Accept: 'application/json' },
		});

		if (!response.ok) {
			throw new Error(`Request failed with status ${response.status}`);
		}

		const data = await response.json();
		const videos = Array.isArray(data.videos) ? data.videos : [];

		if (grid && videos.length) {
			grid.append(...videos.map(buildVideoCard));
		}

		context.offset += videos.length;
		context.hasMore = Boolean(data.hasMore);
	} catch (error) {
		context.hasError = true;
		context.hasMore = false;
	} finally {
		context.isLoading = false;
	}
}

store(NAMESPACE, {
	callbacks: {
		/**
		 * Observes the grid's sentinel element and loads the next page of
		 * videos once it scrolls into view. Runs once when the shortcode
		 * markup mounts.
		 */
		initInfiniteScroll() {
			const context = getContext();
			const { ref } = getElement();
			const grid = ref.querySelector('.pbtv-videos-shortcode__grid');
			const sentinel = ref.querySelector('.pbtv-videos-shortcode__sentinel');

			if (!grid || !sentinel) {
				return;
			}

			const observer = new IntersectionObserver((entries) => {
				if (!context.hasMore) {
					observer.disconnect();

					return;
				}

				if (entries.some((entry) => entry.isIntersecting)) {
					loadMoreVideos(context, grid).then(() => {
						if (!context.hasMore) {
							observer.disconnect();
						}
					});
				}
			});

			observer.observe(sentinel);

			return () => observer.disconnect();
		},
	},
});
