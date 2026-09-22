/**
 * Interactivity API store for the pbtv/news block.
 *
 * Lets visitors filter the news grid by topic without a full page reload:
 * clicking a topic link re-fetches items from the pbtv/v1/news REST route
 * and swaps them into the grid.
 */

import { store, getContext, getElement } from '@wordpress/interactivity';

const NAMESPACE = 'pbtv/news';

/**
 * Builds a single news card as DOM nodes, using textContent/attribute
 * assignment (never innerHTML) so feed-sourced titles and sources can
 * never be interpreted as markup.
 *
 * @param {Object} item Prepared news item (see pbtv_news_prepare_item_for_display()).
 * @return {HTMLAnchorElement} The card element.
 */
function buildCard(item) {
	const card = document.createElement('a');
	card.className =
		'block rounded-xl overflow-hidden bg-white shadow-sm hover:shadow-md hover:-translate-y-0.5 transition';
	card.href = isSafeUrl(item.link) ? item.link : '#';
	card.target = '_blank';
	card.rel = 'noopener noreferrer';

	const media = document.createElement('div');
	media.className = 'relative aspect-video bg-gray-100';

	if (item.image && isSafeUrl(item.image)) {
		const img = document.createElement('img');
		img.className = 'w-full h-full object-cover';
		img.src = item.image;
		img.alt = '';
		img.loading = 'lazy';
		media.append(img);
	} else {
		const fallback = document.createElement('div');
		fallback.className =
			'w-full h-full flex items-center justify-center text-white text-3xl font-bold';
		fallback.style.backgroundColor = item.color || '#1a1a1a';
		fallback.textContent = item.initial || '';
		media.append(fallback);
	}

	const sourceBadge = document.createElement('span');
	sourceBadge.className =
		'absolute top-2 left-2 rounded-full bg-black/65 text-white text-[10px] font-bold uppercase tracking-wide px-2 py-1';
	sourceBadge.textContent = item.source || '';

	const topicBadge = document.createElement('span');
	topicBadge.className =
		'absolute top-2 right-2 rounded-full bg-white/90 text-gray-900 text-[10px] font-bold uppercase tracking-wide px-2 py-1';
	topicBadge.textContent = item.topic || '';

	media.append(sourceBadge, topicBadge);

	const body = document.createElement('div');
	body.className = 'p-3';

	const title = document.createElement('p');
	title.className = 'text-sm font-semibold text-gray-900 leading-snug mb-2 line-clamp-3';
	title.textContent = item.title || '';

	const date = document.createElement('p');
	date.className = 'text-xs text-gray-500';
	date.textContent = item.date || '';

	body.append(title, date);
	card.append(media, body);

	return card;
}

/**
 * Rejects non-http(s) URLs so a compromised feed/response can never
 * inject a javascript: or data: URL into an href/src.
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
 * Replaces the grid's contents with the given items, or an empty-state
 * message when there are none.
 *
 * @param {HTMLElement} grid  The news grid element.
 * @param {Array}       items Prepared news items.
 */
function renderItems(grid, items) {
	grid.replaceChildren();

	if (!items.length) {
		const empty = document.createElement('p');
		empty.className = 'text-sm text-gray-500 col-span-full';
		empty.textContent = grid.dataset.emptyLabel || '';
		grid.append(empty);

		return;
	}

	grid.append(...items.map(buildCard));
}

/**
 * Fetches news items for the given topic and swaps them into the grid,
 * shared by both the desktop topic buttons and the mobile select.
 *
 * @param {Object}      context Interactivity context for the block instance.
 * @param {HTMLElement} root    The block's root element.
 * @param {string}      topic   Topic to filter by, or '' for the default view.
 */
async function fetchTopic(context, root, topic) {
	const grid = root ? root.querySelector('.news-grid') : null;

	context.activeTopic = topic;
	context.isLoading = true;
	context.hasError = false;

	try {
		const url = new URL(context.restUrl);
		url.searchParams.set('maxItems', String(context.maxItems));

		if (topic) {
			url.searchParams.set('topic', topic);
		}

		const response = await window.fetch(url.toString(), {
			headers: { Accept: 'application/json' },
		});

		if (!response.ok) {
			throw new Error(`Request failed with status ${response.status}`);
		}

		const data = await response.json();

		if (grid) {
			renderItems(grid, Array.isArray(data.items) ? data.items : []);
		}
	} catch (error) {
		context.hasError = true;
	} finally {
		context.isLoading = false;
	}
}

const { state } = store(NAMESPACE, {
	state: {
		get isTopicActive() {
			const { activeTopic, topic } = getContext();

			return (activeTopic || '') === (topic || '');
		},
		get ariaCurrent() {
			return state.isTopicActive ? 'true' : 'false';
		},
	},
	actions: {
		*setTopic(event) {
			event.preventDefault();

			const context = getContext();
			const { topic } = context;

			if (context.isLoading || topic === context.activeTopic) {
				return;
			}

			const { ref } = getElement();
			const root = ref.closest('.wp-block-pbtv-news');

			yield fetchTopic(context, root, topic);
		},
		*setTopicFromSelect(event) {
			const context = getContext();
			const topic = event.target.value;

			if (context.isLoading || topic === context.activeTopic) {
				return;
			}

			const { ref } = getElement();
			const root = ref.closest('.wp-block-pbtv-news');

			yield fetchTopic(context, root, topic);
		},
	},
});
