/**
 * Interactivity API store for the pbtv/latest-videos block.
 *
 * Fetches a channel's latest live videos from the pbtv/v1/latest-videos
 * REST route on mount and swaps them into the grid, replacing the
 * server-rendered loading skeleton once they arrive.
 */

import { store, getContext, getElement } from '@wordpress/interactivity';

const NAMESPACE = 'pbtv/latest-videos';

/**
 * Rejects non-http(s) URLs so a malformed API response can never inject a
 * javascript: or data: URL into an href/src.
 *
 * @param {string} url URL to check.
 * @return {boolean} True when the URL uses http or https.
 */
function isSafeUrl(url) {
	try {
		return ['http:', 'https:'].includes(new URL(url).protocol);
	} catch (error) {
		return false;
	}
}

/**
 * Builds a video's media element: a linked thumbnail with a play badge, or
 * an embedded iframe, depending on the block's format setting.
 *
 * @param {Object} video  Prepared video (see pbtv_latest_video_prepare_item_for_display()).
 * @param {string} format Either 'embed' or 'thumbnail'.
 * @return {HTMLElement} The media element.
 */
function buildMedia(video, format) {
	if ('thumbnail' === format) {
		const link = document.createElement('a');
		link.className = 'video-thumbnail relative block w-full h-full';
		link.href = isSafeUrl(video.url) ? video.url : '#';
		link.setAttribute('aria-label', video.title || '');

		const img = document.createElement('img');
		img.className = 'w-full h-full object-cover';
		img.src = isSafeUrl(video.thumbnail) ? video.thumbnail : '';
		img.alt = video.title || '';
		img.loading = 'lazy';

		const play = document.createElement('span');
		play.className = 'video-thumbnail__play';
		play.setAttribute('aria-hidden', 'true');

		link.append(img, play);

		return link;
	}

	const iframe = document.createElement('iframe');
	iframe.className = 'w-full h-full';
	iframe.src = isSafeUrl(video.embedUrl) ? video.embedUrl : '';
	iframe.title = video.title || '';
	iframe.loading = 'lazy';
	iframe.allow =
		'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
	iframe.allowFullscreen = true;

	return iframe;
}

/**
 * Builds a single video card: its media plus a linked heading.
 *
 * @param {Object} video        Prepared video.
 * @param {string} format       Either 'embed' or 'thumbnail'.
 * @param {string} headingClass Tailwind classes for the heading.
 * @param {string} wrapperClass Wrapper element class.
 * @return {HTMLElement} The card element.
 */
function buildCard(video, format, headingClass, wrapperClass) {
	const wrapper = document.createElement('div');
	wrapper.className = wrapperClass;

	const media = document.createElement('div');
	media.className = 'aspect-video';
	media.append(buildMedia(video, format));

	const heading = document.createElement('h2');
	heading.className = headingClass;

	const link = document.createElement('a');
	link.href = isSafeUrl(video.url) ? video.url : '#';
	link.textContent = video.title || '';

	heading.append(link);
	wrapper.append(media, heading);

	return wrapper;
}

/**
 * Replaces the grid's contents with the fetched videos, or an empty-state
 * message when there are none: the first video large on the left, the
 * rest stacked on the right, mirroring the block's previous server-rendered
 * layout.
 *
 * @param {HTMLElement} grid   The grid element.
 * @param {Array}       videos Prepared videos.
 * @param {string}      format Either 'embed' or 'thumbnail'.
 */
function renderVideos(grid, videos, format) {
	grid.replaceChildren();

	if (!videos.length) {
		const empty = document.createElement('p');
		empty.className = 'text-sm text-gray-500 col-span-full';
		empty.textContent = grid.dataset.emptyLabel || '';
		grid.append(empty);

		return;
	}

	const [primary, ...secondary] = videos;

	grid.append(
		buildCard(primary, format, 'mt-2 text-2xl font-bold text-pbtv-red', 'live-video')
	);

	if (secondary.length) {
		const column = document.createElement('div');
		column.className = 'grid grid-cols-1 gap-4';

		column.append(
			...secondary.map((video) =>
				buildCard(video, format, 'mt-2 text-lg font-bold text-pbtv-red', 'latest-video')
			)
		);

		grid.append(column);
	}
}

/**
 * Fetches the channel's latest videos and swaps them into the grid.
 *
 * @param {Object}      context Interactivity context for the block instance.
 * @param {HTMLElement} root    The block's root element.
 */
async function fetchVideos(context, root) {
	const grid = root ? root.querySelector('.latest-videos-grid') : null;

	context.isLoading = true;
	context.hasError = false;

	try {
		const url = new URL(context.restUrl);
		url.searchParams.set('channelId', context.channelId);
		url.searchParams.set('maxResults', String(context.maxResults));

		const response = await window.fetch(url.toString(), {
			headers: { Accept: 'application/json' },
		});

		if (!response.ok) {
			throw new Error(`Request failed with status ${response.status}`);
		}

		const data = await response.json();

		if (grid) {
			renderVideos(grid, Array.isArray(data.videos) ? data.videos : [], context.format);
		}
	} catch (error) {
		context.hasError = true;
	} finally {
		context.isLoading = false;
	}
}

store(NAMESPACE, {
	callbacks: {
		*init() {
			const context = getContext();
			const { ref } = getElement();
			const root = ref.closest('.wp-block-pbtv-latest-videos');

			yield fetchVideos(context, root);
		},
	},
});
