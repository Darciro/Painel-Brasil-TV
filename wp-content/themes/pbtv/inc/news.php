<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gets the list of RSS feeds queried for the news block.
 *
 * @return array<int, array<string, string>> List of feeds, each with
 *                                            name, url, color and an
 *                                            optional require_keyword key
 *                                            used to filter items (e.g. a
 *                                            general feed that must only
 *                                            surface items mentioning
 *                                            Brazil).
 */
function pbtv_news_feeds(): array {
	$feeds = array(
		array(
			'name'  => 'Folha',
			'url'   => 'https://feeds.folha.uol.com.br/emcimadahora/rss091.xml',
			'color' => '#1a1a1a',
		),
		array(
			'name'  => 'Brasil 247',
			'url'   => 'https://www.brasil247.com/feed.rss',
			'color' => '#a4123f',
		),
		array(
			'name'  => 'Poder360',
			'url'   => 'https://www.poder360.com.br/feed/',
			'color' => '#e8552f',
		),
		array(
			'name'  => 'CNN Brasil',
			'url'   => 'https://www.cnnbrasil.com.br/feed/',
			'color' => '#cc0000',
		),
		array(
			'name'  => 'CartaCapital',
			'url'   => 'https://www.cartacapital.com.br/feed/',
			'color' => '#c1121f',
		),
		array(
			'name'  => 'Congresso em Foco',
			'url'   => 'https://www.congressoemfoco.com.br/feed/',
			'color' => '#1d5fad',
		),
		array(
			'name'  => 'Estadão',
			'url'   => 'https://www.estadao.com.br/arc/outboundfeeds/feeds/rss/sections/brasil/',
			'color' => '#003865',
		),
		array(
			'name'  => 'BBC Brasil',
			'url'   => 'https://feeds.bbci.co.uk/portuguese/rss.xml',
			'color' => '#bb1919',
		),
		array(
			'name'            => 'Euronews',
			'url'             => 'https://pt.euronews.com/rss?format=mrss&level=theme&name=news',
			'color'           => '#002169',
			'require_keyword' => 'brasil',
		),
	);

	/**
	 * Filters the RSS feeds queried for the news block.
	 *
	 * @param array<int, array<string, string>> $feeds The feeds.
	 */
	return (array) apply_filters( 'pbtv_news_feeds', $feeds );
}

/**
 * Gets the topics used to classify news items, each mapped to the
 * keywords checked against an item's title and categories.
 *
 * @return array<string, array<int, string>>
 */
function pbtv_news_topics(): array {
	$topics = array(
		'Política'      => array(
			'politica',
			'polític',
			'governo',
			'congresso',
			'senado',
			'senador',
			'câmara dos deputados',
			'deputado',
			'eleiç',
			'presidente da república',
			'ministro',
			'stf',
			'supremo tribunal',
			'planalto',
			'partido',
			'eleitoral',
			'prefeit',
			'governador',
		),
		'Economia'      => array(
			'economia',
			'econôm',
			'mercado financeiro',
			'inflaç',
			'juros',
			'pib',
			'dólar',
			'bolsa de valores',
			'imposto',
			'orçamento',
			'banco central',
			'selic',
			'fiscal',
			'tarifa',
			'emprego',
			'desemprego',
			'investiment',
		),
		'Mundo'         => array(
			'mundo',
			'internacional',
			'exterior',
			'eua',
			'estados unidos',
			'china',
			'rússia',
			'europa',
			'ucrânia',
			'israel',
			'gaza',
			'oriente médio',
			'trump',
			'onu',
			'otan',
			'guerra em',
			'união europeia',
		),
		'Meio Ambiente' => array(
			'meio ambiente',
			'clima',
			'climátic',
			'desmatamento',
			'amazônia',
			'sustentabilidade',
			'aquecimento global',
			'ambiental',
			'poluição',
			'energia renovável',
			'biodiversidade',
			'queimada',
		),
	);

	/**
	 * Filters the topics used to classify news items.
	 *
	 * @param array<string, array<int, string>> $topics The topics.
	 */
	return (array) apply_filters( 'pbtv_news_topics', $topics );
}

/**
 * Classifies a news item into one of the configured topics based on
 * keyword matches against its title and categories.
 *
 * @param string               $title      Item title.
 * @param array<int, string>   $categories Item categories.
 *
 * @return string The matched topic name, or an empty string when none
 *                 of the configured topics match.
 */
function pbtv_news_classify_topic( string $title, array $categories ): string {
	$haystack = mb_strtolower( $title . ' ' . implode( ' ', $categories ), 'UTF-8' );

	foreach ( pbtv_news_topics() as $topic => $keywords ) {
		foreach ( $keywords as $keyword ) {
			if ( str_contains( $haystack, $keyword ) ) {
				return $topic;
			}
		}
	}

	return '';
}

/**
 * Extracts an illustrative image URL from a feed item, checking its
 * enclosure, Media RSS tags and, as a last resort, the first image found
 * in its content.
 *
 * @param SimplePie_Item $item Feed item.
 *
 * @return string Image URL, or an empty string when none was found.
 */
function pbtv_news_extract_image( SimplePie_Item $item ): string {
	$enclosure = $item->get_enclosure();

	if ( $enclosure ) {
		$thumbnail = $enclosure->get_thumbnail();

		if ( $thumbnail ) {
			return esc_url_raw( $thumbnail );
		}

		if ( $enclosure->get_link() && str_starts_with( (string) $enclosure->get_type(), 'image/' ) ) {
			return esc_url_raw( $enclosure->get_link() );
		}
	}

	$media_thumbnail = $item->get_item_tags( SIMPLEPIE_NAMESPACE_MEDIARSS, 'thumbnail' );

	if ( ! empty( $media_thumbnail[0]['attribs']['']['url'] ) ) {
		return esc_url_raw( $media_thumbnail[0]['attribs']['']['url'] );
	}

	$media_content = $item->get_item_tags( SIMPLEPIE_NAMESPACE_MEDIARSS, 'content' );

	if ( ! empty( $media_content[0]['attribs']['']['url'] ) ) {
		return esc_url_raw( $media_content[0]['attribs']['']['url'] );
	}

	$content = $item->get_content() ? $item->get_content() : $item->get_description();

	if ( $content && preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $matches ) ) {
		return esc_url_raw( $matches[1] );
	}

	return '';
}

/**
 * Retrieves the latest classified news items across all configured
 * feeds. Results are cached in a transient so the feeds are not fetched
 * on every pageview.
 *
 * @param int $max_items Maximum number of items to return.
 *
 * @return array<int, array<string, mixed>> List of items, each with
 *                                           title, link, timestamp,
 *                                           image, source, color and
 *                                           topic keys.
 */
function pbtv_get_news_items( int $max_items = 8 ): array {
	$cache_key = 'pbtv_news_' . md5( (string) $max_items );
	$cached    = get_transient( $cache_key );

	if ( false !== $cached ) {
		return $cached;
	}

	$items = pbtv_fetch_news_items( $max_items );

	set_transient( $cache_key, $items, 10 * MINUTE_IN_SECONDS );

	return $items;
}

/**
 * Fetches, classifies, sorts and trims news items from every configured
 * feed. Feeds that fail to load are silently skipped so the block keeps
 * working with whichever feeds are available.
 *
 * @param int $max_items Maximum number of items to return.
 *
 * @return array<int, array<string, mixed>>
 */
function pbtv_fetch_news_items( int $max_items ): array {
	$items = array();

	foreach ( pbtv_news_feeds() as $feed_config ) {
		$items = array_merge( $items, pbtv_fetch_news_feed_items( $feed_config ) );
	}

	usort(
		$items,
		static function ( array $a, array $b ): int {
			return $b['timestamp'] <=> $a['timestamp'];
		}
	);

	return array_slice( $items, 0, $max_items );
}

/**
 * Fetches and classifies the items of a single RSS feed.
 *
 * @param array<string, string> $feed_config Feed configuration, see
 *                                            pbtv_news_feeds().
 *
 * @return array<int, array<string, mixed>>
 */
function pbtv_fetch_news_feed_items( array $feed_config ): array {
	$feed = fetch_feed( $feed_config['url'] );

	if ( is_wp_error( $feed ) ) {
		return array();
	}

	$feed->set_item_limit( 25 );

	$require_keyword = isset( $feed_config['require_keyword'] ) ? mb_strtolower( $feed_config['require_keyword'], 'UTF-8' ) : '';
	$items            = array();

	foreach ( (array) $feed->get_items() as $feed_item ) {
		$title = wp_strip_all_tags( (string) $feed_item->get_title() );

		if ( '' === $title ) {
			continue;
		}

		if ( $require_keyword && ! str_contains( mb_strtolower( $title, 'UTF-8' ), $require_keyword ) ) {
			continue;
		}

		$categories = array_map(
			static function ( SimplePie_Category $category ): string {
				return (string) $category->get_label();
			},
			(array) $feed_item->get_categories()
		);

		$topic = pbtv_news_classify_topic( $title, $categories );

		if ( '' === $topic ) {
			continue;
		}

		$items[] = array(
			'title'     => sanitize_text_field( $title ),
			'link'      => esc_url_raw( (string) $feed_item->get_permalink() ),
			'timestamp' => (int) ( $feed_item->get_date( 'U' ) ?: time() ),
			'image'     => pbtv_news_extract_image( $feed_item ),
			'source'    => sanitize_text_field( $feed_config['name'] ),
			'color'     => sanitize_hex_color( $feed_config['color'] ) ? $feed_config['color'] : '#1a1a1a',
			'topic'     => $topic,
		);
	}

	return $items;
}
