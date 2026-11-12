<?php
/**
 * Add a localized review preview after the collection cards on the shop landing page.
 * The review content and total are read from each language's existing review archive.
 */
defined( 'ABSPATH' ) || exit;

function pilloway_catalog_home_reviews_page_id(): int {
	static $page_ids = array();
	$language = function_exists( 'pilloway_catalog_collections_language' )
		? pilloway_catalog_collections_language()
		: ( function_exists( 'pll_current_language' ) ? (string) pll_current_language( 'slug' ) : 'uk' );

	if ( isset( $page_ids[ $language ] ) ) {
		return $page_ids[ $language ];
	}

	$source_page = get_page_by_path( 'vidguky-pilloway', OBJECT, 'page' );
	if ( ! $source_page ) {
		$page_ids[ $language ] = 0;
		return 0;
	}

	$page_id = function_exists( 'pll_get_post' )
		? absint( pll_get_post( (int) $source_page->ID, $language ) )
		: absint( $source_page->ID );
	$page    = $page_id ? get_post( $page_id ) : null;

	if ( ! $page || 'publish' !== $page->post_status ) {
		$page_ids[ $language ] = 0;
		return 0;
	}

	if ( function_exists( 'pll_get_post_language' ) && $language !== pll_get_post_language( $page_id, 'slug' ) ) {
		$page_ids[ $language ] = 0;
		return 0;
	}

	$page_ids[ $language ] = $page_id;
	return $page_id;
}

function pilloway_catalog_home_review_has_comment( string $card ): bool {
	if ( ! preg_match( '~<p\b[^>]*class="[^"]*\bpilloway-review-body\b[^"]*"[^>]*>(.*?)</p>~s', $card, $body_match ) ) {
		return false;
	}

	$body = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( html_entity_decode( $body_match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ) );
	if ( '' === $body ) {
		return false;
	}

	$normalized_body = function_exists( 'mb_strtolower' ) ? mb_strtolower( $body, 'UTF-8' ) : strtolower( $body );
	$empty_review_messages = array(
		'покупець залишив оцінку без текстового коментаря.',
		'покупатель оставил оценку без текстового комментария.',
		'the customer left a rating without a written comment.',
		'klient pozostawił ocenę bez komentarza tekstowego.',
		'l’acheteur a laissé une note sans commentaire écrit.',
		"l'acheteur a laissé une note sans commentaire écrit.",
		'пользователь не написал текст отзыва.',
		'пользователь не оставил текст отзыва.',
		'користувач не написав текст відгуку.',
		'користувач не залишив текст відгуку.',
		'no review text.',
		'brak treści opinii.',
		'aucun texte d’avis.',
	);

	// Imported review feeds sometimes store a sentence such as “the user did not
	// write a review text” as the comment itself. Treat that as empty feedback.
	$empty_review_patterns = array(
		'~^(?:пользователь|покупатель|клиент|автор)\\s+.*(?:не|без|нет|отсутствует)\\s+.*(?:текст|комментар|отзыв)\\p{L}*.*[.!?…]*$~iu',
		'~^(?:користувач|покупець|клієнт|автор)\\s+.*(?:не|без|немає|нема|відсутн\\p{L}*)\\s+.*(?:текст|коментар|відгук)\\p{L}*.*[.!?…]*$~iu',
		'~^(?:(?:the\\s+)?(?:user|customer|client|reviewer|author)\\b.*\\b(?:did\\s+not|didn.t|has\\s+not|hasn.t|does\\s+not|doesn.t|not|without|no)\\b.*\\b(?:text|comment|review)\\b|(?:no|without)\\s+(?:written\\s+)?(?:text|comment|review)\\b)[.!?…]*$~iu',
		'~^(?:użytkownik|klient|autor)\\s+.*(?:nie|bez|brak)\\s+.*(?:tekst|komentar|opini|treś)\\p{L}*.*[.!?…]*$~iu',
		'~^(?:l.?utilisateur|le client|l.?acheteur|auteur)\\b.*\\b(?:ne|sans|aucun|aucune|pas)\\b.*\\b(?:texte|commentaire|avis)\\w*.*[.!?…]*$~iu',
		'~^(?:без|немає|нема)\\s+(?:тексту|коментаря|відгуку)[.!?…]*$~u',
		'~^(?:без|нет)\\s+(?:текста|комментария|отзыва)[.!?…]*$~u',
		'~^(?:no|without)\\s+(?:written\\s+)?(?:text|comment|review)[.!?…]*$~iu',
	);

	foreach ( $empty_review_patterns as $pattern ) {
		if ( 1 === preg_match( $pattern, $normalized_body ) ) {
			return false;
		}
	}

	return ! in_array( $normalized_body, $empty_review_messages, true );
}

/** Keep rating-only feedback free of placeholder comments and unrelated detail. */
function pilloway_catalog_keep_rating_only_review_cards( string $content ): string {
	if ( false === strpos( $content, 'pilloway-review-card' ) ) {
		return $content;
	}

	$filtered = preg_replace_callback(
		'~<article\\b(?=[^>]*\\bclass="[^"]*\\bpilloway-review-card\\b[^"]*")[^>]*>.*?</article>~s',
		static function ( array $match ): string {
			$card = $match[0];
			if ( pilloway_catalog_home_review_has_comment( $card ) ) {
				return $card;
			}

			if (
				! preg_match(
					'~<header\\b(?=[^>]*\\bclass="[^"]*\\bpilloway-review-header\\b[^"]*")[^>]*>.*?</header>~s',
					$card,
					$header_match
				)
				|| ! preg_match(
					'~<div\\b(?=[^>]*\\bclass="[^"]*\\bpilloway-review-stars\\b[^"]*")[^>]*>.*?</div>~s',
					$card,
					$stars_match
				)
				|| ! preg_match( '~^(<article\\b[^>]*>).*?(</article>)$~s', $card, $article_match )
			) {
				return $card;
			}

			// Keep the real reviewer and date, while omitting empty/placeholder text and imported links.
			return $article_match[1] . $header_match[0] . $stars_match[0] . $article_match[2];
		},
		$content
	);

	return is_string( $filtered ) ? $filtered : $content;
}
add_filter( 'the_content', 'pilloway_catalog_keep_rating_only_review_cards', 20 );

function pilloway_catalog_home_reviews_data( int $page_id ): array {
	$page    = get_post( $page_id );
	$content = $page ? (string) $page->post_content : '';
	if ( '' === $content || ! preg_match_all( '~<article class="pilloway-review-card">.*?</article>~s', $content, $matches ) ) {
		return array();
	}

	$review_count = count( $matches[0] );
	$intro = '';
	$note  = '';

	if ( preg_match( '~<p class="pilloway-reviews-intro">(.*?)</p>~s', $content, $intro_match ) ) {
		$intro = trim( wp_strip_all_tags( html_entity_decode( $intro_match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
		$intro = preg_replace_callback(
			'/(?<![\\p{L}\\d])\\d+(?:[,.]\\d+)?/u',
			static function ( $number_match ) use ( $review_count ) {
				return number_format_i18n( $review_count );
			},
			$intro,
			1
		);
	}

	if ( preg_match( '~<p class="pilloway-reviews-note">(.*?)</p>~s', $content, $note_match ) ) {
		$note = trim( wp_strip_all_tags( html_entity_decode( $note_match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
	}

	$cta = trim( (string) get_post_meta( $page_id, '_pilloway_home_reviews_cta', true ) );
	if ( '' === $cta ) {
		return array();
	}

	$cards = array();
	foreach ( $matches[0] as $card ) {
		if ( ! pilloway_catalog_home_review_has_comment( $card ) ) {
			continue;
		}

		// The review archive's related-product links are unnecessary in a short preview
		// and may contain stale hostnames from the import environment.
		$card = preg_replace( '~<div class="pilloway-review-products">.*?</div>~s', '', $card );
		$cards[] = wp_kses_post( $card );
		if ( 3 === count( $cards ) ) {
			break;
		}
	}

	return array(
		'count'      => $review_count,
		'intro'      => (string) $intro,
		'note'       => $note,
		'title'      => get_the_title( $page_id ),
		'url'        => get_permalink( $page_id ),
		'cta'        => $cta,
		'cards'      => $cards,
	);
}


function pilloway_catalog_render_home_reviews(): string {
	$page_id = pilloway_catalog_home_reviews_page_id();
	if ( ! $page_id ) {
		return '';
	}
	$data = pilloway_catalog_home_reviews_data( $page_id );
	if ( empty( $data['cards'] ) || empty( $data['count'] ) || empty( $data['url'] ) || empty( $data['cta'] ) ) {
		return '';
	}

	$title_id = wp_unique_id( 'pilloway-home-reviews-title-' );
	ob_start();
	?>
	<section class="pilloway-home-reviews alignfull" aria-labelledby="<?php echo esc_attr( $title_id ); ?>">
		<div class="pilloway-home-reviews__inner">
			<header class="pilloway-home-reviews__header">
				<p class="pilloway-home-reviews__count"><?php echo esc_html( $data['intro'] ); ?></p>
				<h2 id="<?php echo esc_attr( $title_id ); ?>"><?php echo esc_html( $data['title'] ); ?></h2>
				<?php if ( '' !== $data['note'] ) : ?>
					<p class="pilloway-home-reviews__note"><?php echo esc_html( $data['note'] ); ?></p>
				<?php endif; ?>
			</header>
			<div class="pilloway-home-reviews__grid">
				<?php foreach ( $data['cards'] as $card ) : ?>
					<?php echo $card; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Existing translated review markup is sanitized with wp_kses_post(). ?>
				<?php endforeach; ?>
			</div>
			<div class="pilloway-home-reviews__action">
				<a class="pilloway-home-reviews__button" href="<?php echo esc_url( $data['url'] ); ?>">
					<?php echo esc_html( $data['cta'] ); ?>
					<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 12h14M12 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</a>
			</div>
		</div>
	</section>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'pilloway_home_reviews', 'pilloway_catalog_render_home_reviews' );

function pilloway_catalog_append_home_reviews_after_collections( string $block_content, array $block ): string {
	if (
		'woocommerce/product-collection' !== ( $block['blockName'] ?? '' )
		|| ! function_exists( 'pilloway_catalog_is_collections_home' )
		|| ! pilloway_catalog_is_collections_home()
		|| false === strpos( $block_content, 'pilloway-collections__list' )
	) {
		return $block_content;
	}

	return $block_content . pilloway_catalog_render_home_reviews();
}
add_filter( 'render_block', 'pilloway_catalog_append_home_reviews_after_collections', 20, 2 );

function pilloway_catalog_enqueue_home_reviews_style(): void {
	if ( ! function_exists( 'pilloway_catalog_is_collections_home' ) || ! pilloway_catalog_is_collections_home() ) {
		return;
	}

	$path = dirname( __DIR__ ) . '/assets/home-reviews.css';
	if ( ! is_readable( $path ) ) {
		return;
	}

	wp_enqueue_style(
		'pilloway-home-reviews',
		plugins_url( 'assets/home-reviews.css', dirname( __DIR__ ) . '/pilloway-catalog-bridge.php' ),
		array(),
		(string) filemtime( $path )
	);
}
add_action( 'wp_enqueue_scripts', 'pilloway_catalog_enqueue_home_reviews_style', 20 );
