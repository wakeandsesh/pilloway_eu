<?php
/**
 * Storefront block.
 *
 * Every value is a real <a href> to the URL that selection produces. The script
 * only intercepts those links to swap the listing in place; with JS off, or a
 * theme that drops plugin scripts, the filter still works as plain navigation
 * and stays crawlable.
 *
 * Overridable from a theme: yourtheme/catcode-smart-filter/filter.php
 *
 * @var array<string,mixed> $data
 * @package CatCodeSmartFilter
 */

defined( 'ABSPATH' ) || exit;

?>
<div id="ccf-filter"
	class="ccf<?php echo $data['mobile_drawer'] ? ' ccf--drawer' : ''; ?>"
	data-ajax="<?php echo $data['ajax'] ? 1 : 0; ?>"
	data-auto-apply="<?php echo $data['auto_apply'] ? 1 : 0; ?>"
	data-container="<?php echo esc_attr( $data['container'] ); ?>"
	data-container-extra="<?php echo esc_attr( $data['container_extra'] ); ?>"
	data-count-url="<?php echo esc_url( $data['count_url'] ); ?>"
	data-zero="<?php echo esc_attr( $data['zero_values'] ); ?>">

	<?php if ( $data['mobile_drawer'] ) : ?>
		<button type="button" class="ccf__toggle" data-ccf-drawer>
			<span><?php esc_html_e( 'Filters', 'catcode-smart-filter' ); ?></span>
			<?php if ( $data['has_selection'] ) : ?>
				<span class="ccf__badge"><?php echo count( $data['chips'] ); ?></span>
			<?php endif; ?>
		</button>
	<?php endif; ?>

	<div class="ccf__panel">
		<div class="ccf__head">
			<span class="ccf__heading"><?php echo esc_html( $data['heading'] ); ?></span>
			<span class="ccf__total" data-ccf-total>
				<?php
				printf(
					/* translators: %s: number of products found. */
					esc_html__( 'Found: %s', 'catcode-smart-filter' ),
					esc_html( number_format_i18n( $data['total'] ) )
				);
				?>
			</span>
		</div>

		<?php if ( $data['chips_enabled'] && ( $data['chips'] || $data['has_selection'] ) ) : ?>
			<div class="ccf__chips">
				<?php foreach ( $data['chips'] as $catcode_sf_chip ) : ?>
					<a href="<?php echo esc_url( $catcode_sf_chip['remove_url'] ); ?>" class="ccf__chip" data-ccf-link rel="nofollow">
						<span><?php echo esc_html( $catcode_sf_chip['label'] ); ?></span>
						<i class="ccf__chip-x" aria-hidden="true">&times;</i>
					</a>
				<?php endforeach; ?>
				<a href="<?php echo esc_url( $data['clear_url'] ); ?>" class="ccf__chip ccf__chip--clear" data-ccf-link rel="nofollow">
					<?php esc_html_e( 'Reset all', 'catcode-smart-filter' ); ?>
				</a>
			</div>
		<?php endif; ?>

		<div class="ccf__body">
			<?php if ( ! $data['facets'] ) : ?>
				<p class="ccf__empty"><?php esc_html_e( 'Nothing matches this set of filters.', 'catcode-smart-filter' ); ?></p>
			<?php endif; ?>

			<?php foreach ( $data['facets'] as $catcode_sf_facet ) : ?>
				<div class="ccf__facet<?php echo $catcode_sf_facet['collapsed'] ? ' is-collapsed' : ''; ?>" data-ccf-facet="<?php echo esc_attr( $catcode_sf_facet['key'] ); ?>">
					<button type="button" class="ccf__facet-head" data-ccf-collapse>
						<span class="ccf__facet-name">
							<?php echo esc_html( $catcode_sf_facet['name'] ); ?>
							<?php if ( $catcode_sf_facet['unit'] ) : ?>
								<small><?php echo esc_html( $catcode_sf_facet['unit'] ); ?></small>
							<?php endif; ?>
						</span>
						<i class="ccf__caret" aria-hidden="true"></i>
					</button>

					<?php if ( $catcode_sf_facet['hint'] ) : ?>
						<div class="ccf__hint"><?php echo esc_html( $catcode_sf_facet['hint'] ); ?></div>
					<?php endif; ?>

					<div class="ccf__facet-body">
						<?php if ( 'range' === $catcode_sf_facet['widget'] ) : ?>
							<div class="ccf__range" data-ccf-range
								data-key="<?php echo esc_attr( $catcode_sf_facet['key'] ); ?>"
								data-base="<?php echo esc_url( $catcode_sf_facet['range_url'] ); ?>"
								data-min="<?php echo esc_attr( $catcode_sf_facet['range']['min'] ); ?>"
								data-max="<?php echo esc_attr( $catcode_sf_facet['range']['max'] ); ?>"
								data-step="<?php echo esc_attr( $catcode_sf_facet['range']['step'] ); ?>">
								<div class="ccf__range-inputs">
									<label class="ccf__range-field">
										<span><?php esc_html_e( 'from', 'catcode-smart-filter' ); ?></span>
										<input type="text" inputmode="decimal" value="<?php echo esc_attr( $catcode_sf_facet['range']['from'] ); ?>" data-ccf-range-from>
									</label>
									<label class="ccf__range-field">
										<span><?php esc_html_e( 'to', 'catcode-smart-filter' ); ?></span>
										<input type="text" inputmode="decimal" value="<?php echo esc_attr( $catcode_sf_facet['range']['to'] ); ?>" data-ccf-range-to>
									</label>
								</div>
								<div class="ccf__slider">
									<div class="ccf__slider-track"><span class="ccf__slider-fill" data-ccf-fill></span></div>
									<input type="range" min="<?php echo esc_attr( $catcode_sf_facet['range']['min'] ); ?>" max="<?php echo esc_attr( $catcode_sf_facet['range']['max'] ); ?>" step="<?php echo esc_attr( $catcode_sf_facet['range']['step'] ); ?>" value="<?php echo esc_attr( $catcode_sf_facet['range']['from'] ); ?>" data-ccf-slider-from aria-label="<?php esc_attr_e( 'from', 'catcode-smart-filter' ); ?>">
									<input type="range" min="<?php echo esc_attr( $catcode_sf_facet['range']['min'] ); ?>" max="<?php echo esc_attr( $catcode_sf_facet['range']['max'] ); ?>" step="<?php echo esc_attr( $catcode_sf_facet['range']['step'] ); ?>" value="<?php echo esc_attr( $catcode_sf_facet['range']['to'] ); ?>" data-ccf-slider-to aria-label="<?php esc_attr_e( 'to', 'catcode-smart-filter' ); ?>">
								</div>
							</div>

						<?php elseif ( 'select' === $catcode_sf_facet['widget'] ) : ?>
							<select class="ccf__select" data-ccf-select>
								<option value="">&mdash;</option>
								<?php foreach ( $catcode_sf_facet['values'] as $catcode_sf_value ) : ?>
									<option value="<?php echo esc_url( $catcode_sf_value['url'] ); ?>"<?php echo $catcode_sf_value['selected'] ? ' selected' : ''; ?><?php echo $catcode_sf_value['disabled'] ? ' disabled' : ''; ?>>
										<?php echo esc_html( $catcode_sf_value['label'] ); ?><?php echo $data['show_counts'] ? ' (' . (int) $catcode_sf_value['count'] . ')' : ''; ?>
									</option>
								<?php endforeach; ?>
							</select>

						<?php elseif ( 'swatch_color' === $catcode_sf_facet['widget'] || 'swatch_image' === $catcode_sf_facet['widget'] ) : ?>
							<div class="ccf__swatches">
								<?php foreach ( $catcode_sf_facet['values'] as $catcode_sf_value ) : ?>
									<a href="<?php echo esc_url( $catcode_sf_value['url'] ); ?>" data-ccf-link rel="nofollow"
										title="<?php echo esc_attr( $catcode_sf_value['label'] ); ?>"
										class="ccf__swatch<?php echo $catcode_sf_value['selected'] ? ' is-selected' : ''; ?><?php echo $catcode_sf_value['disabled'] ? ' is-disabled' : ''; ?>">
										<?php if ( $catcode_sf_value['image'] ) : ?>
											<img src="<?php echo esc_url( $catcode_sf_value['image'] ); ?>" alt="<?php echo esc_attr( $catcode_sf_value['label'] ); ?>">
										<?php else : ?>
											<span class="ccf__swatch-color" style="background: <?php echo esc_attr( $catcode_sf_value['color'] ? $catcode_sf_value['color'] : '#ddd' ); ?>"></span>
										<?php endif; ?>
										<span class="ccf__swatch-label"><?php echo esc_html( $catcode_sf_value['label'] ); ?></span>
									</a>
								<?php endforeach; ?>
							</div>

						<?php else : ?>
							<?php if ( $catcode_sf_facet['search_box'] ) : ?>
								<input type="search" class="ccf__search" placeholder="<?php esc_attr_e( 'Search values', 'catcode-smart-filter' ); ?>" data-ccf-search>
							<?php endif; ?>
							<ul class="ccf__values" data-ccf-values data-visible="<?php echo (int) $catcode_sf_facet['visible_limit']; ?>">
								<?php foreach ( $catcode_sf_facet['values'] as $catcode_sf_value ) : ?>
									<li class="ccf__value<?php echo $catcode_sf_value['selected'] ? ' is-selected' : ''; ?><?php echo $catcode_sf_value['disabled'] ? ' is-disabled' : ''; ?>">
										<a href="<?php echo esc_url( $catcode_sf_value['url'] ); ?>" data-ccf-link rel="nofollow" class="ccf__value-link">
											<span class="ccf__box<?php echo 'radio' === $catcode_sf_facet['widget'] ? ' ccf__box--radio' : ''; ?>" aria-hidden="true"></span>
											<span class="ccf__label"><?php echo esc_html( $catcode_sf_value['label'] ); ?></span>
											<?php if ( $data['show_counts'] ) : ?>
												<span class="ccf__count"><?php echo (int) $catcode_sf_value['count']; ?></span>
											<?php endif; ?>
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
							<?php if ( $catcode_sf_facet['visible_limit'] > 0 && count( $catcode_sf_facet['values'] ) > $catcode_sf_facet['visible_limit'] ) : ?>
								<button type="button" class="ccf__more" data-ccf-more
									data-more="<?php esc_attr_e( 'Show more', 'catcode-smart-filter' ); ?>"
									data-less="<?php esc_attr_e( 'Show less', 'catcode-smart-filter' ); ?>">
									<?php esc_html_e( 'Show more', 'catcode-smart-filter' ); ?>
								</button>
							<?php endif; ?>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="ccf__footer">
			<?php if ( ! $data['auto_apply'] ) : ?>
				<a href="#" class="ccf__apply button" data-ccf-apply hidden><?php esc_html_e( 'Show results', 'catcode-smart-filter' ); ?></a>
			<?php endif; ?>
			<?php if ( $data['has_selection'] ) : ?>
				<a href="<?php echo esc_url( $data['clear_url'] ); ?>" class="ccf__clear" data-ccf-link rel="nofollow">
					<?php esc_html_e( 'Reset all', 'catcode-smart-filter' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>
</div>
