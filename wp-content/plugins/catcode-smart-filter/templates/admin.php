<?php
/**
 * Admin screen.
 *
 * @var array<string,mixed> $settings
 * @var array<string,mixed> $stats
 * @package CatCodeSmartFilter
 */

defined( 'ABSPATH' ) || exit;

$catcode_sf_switches = array(
	'enabled'           => __( 'Filter enabled', 'catcode-smart-filter' ),
	'ajax'              => __( 'Apply without a page reload', 'catcode-smart-filter' ),
	'auto_apply'        => __( 'Apply immediately on click', 'catcode-smart-filter' ),
	'show_counts'       => __( 'Show counts', 'catcode-smart-filter' ),
	'hide_single_value' => __( 'Hide facets that have a single value', 'catcode-smart-filter' ),
	'mobile_drawer'     => __( 'Mobile drawer', 'catcode-smart-filter' ),
	'chips'             => __( 'Show the selection as chips', 'catcode-smart-filter' ),
	'facet_price'       => __( 'Price facet', 'catcode-smart-filter' ),
	'facet_stock'       => __( 'Stock facet', 'catcode-smart-filter' ),
	'facet_rating'      => __( 'Rating facet', 'catcode-smart-filter' ),
	'reindex_on_save'   => __( 'Reindex when a product is saved', 'catcode-smart-filter' ),
	'cache_counters'    => __( 'Cache the counters', 'catcode-smart-filter' ),
	'load_css'          => __( 'Load the plugin stylesheet', 'catcode-smart-filter' ),
);

?>
<div class="wrap ccf-admin">
	<h1><?php esc_html_e( 'CatCode — Smart Filter', 'catcode-smart-filter' ); ?></h1>

	<div id="ccf-notice"></div>

	<h2 class="nav-tab-wrapper ccf-tabs">
		<a href="#ccf-general" class="nav-tab nav-tab-active"><?php esc_html_e( 'Settings', 'catcode-smart-filter' ); ?></a>
		<a href="#ccf-facets" class="nav-tab"><?php esc_html_e( 'Facets', 'catcode-smart-filter' ); ?></a>
		<a href="#ccf-index" class="nav-tab"><?php esc_html_e( 'Index', 'catcode-smart-filter' ); ?></a>
		<a href="#ccf-diag" class="nav-tab"><?php esc_html_e( 'Diagnostics', 'catcode-smart-filter' ); ?></a>
	</h2>

	<div class="ccf-pane" id="ccf-general">
		<form id="ccf-form" onsubmit="return false;">
			<table class="form-table" role="presentation">
				<?php foreach ( $catcode_sf_switches as $catcode_sf_key => $catcode_sf_label ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $catcode_sf_label ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $catcode_sf_key ); ?>" value="yes"<?php checked( 'yes', $settings[ $catcode_sf_key ] ?? 'no' ); ?>>
							</label>
						</td>
					</tr>
				<?php endforeach; ?>

				<tr>
					<th scope="row"><?php esc_html_e( 'Block heading', 'catcode-smart-filter' ); ?></th>
					<td><input type="text" class="regular-text" name="heading" value="<?php echo esc_attr( $settings['heading'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Values with no products', 'catcode-smart-filter' ); ?></th>
					<td>
						<select name="zero_values">
							<option value="hide"<?php selected( 'hide', $settings['zero_values'] ); ?>><?php esc_html_e( 'Hide', 'catcode-smart-filter' ); ?></option>
							<option value="dim"<?php selected( 'dim', $settings['zero_values'] ); ?>><?php esc_html_e( 'Show greyed out', 'catcode-smart-filter' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Values before “show more”', 'catcode-smart-filter' ); ?></th>
					<td><input type="number" min="0" name="facet_visible" value="<?php echo esc_attr( $settings['facet_visible'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Product grid selector', 'catcode-smart-filter' ); ?></th>
					<td>
						<input type="text" class="regular-text" name="container" value="<?php echo esc_attr( $settings['container'] ); ?>">
						<p class="description"><?php esc_html_e( 'What AJAX swaps in place. In most themes this is ul.products.', 'catcode-smart-filter' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Also refresh', 'catcode-smart-filter' ); ?></th>
					<td><input type="text" class="large-text" name="container_extra" value="<?php echo esc_attr( $settings['container_extra'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Canonical of a filtered page', 'catcode-smart-filter' ); ?></th>
					<td>
						<select name="canonical_mode">
							<option value="archive"<?php selected( 'archive', $settings['canonical_mode'] ); ?>><?php esc_html_e( 'The clean archive', 'catcode-smart-filter' ); ?></option>
							<option value="self"<?php selected( 'self', $settings['canonical_mode'] ); ?>><?php esc_html_e( 'The filtered page itself', 'catcode-smart-filter' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Robots: one facet / several', 'catcode-smart-filter' ); ?></th>
					<td>
						<input type="text" name="robots_single" value="<?php echo esc_attr( $settings['robots_single'] ); ?>">
						<input type="text" name="robots_multi" value="<?php echo esc_attr( $settings['robots_multi'] ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Products per indexing pass', 'catcode-smart-filter' ); ?></th>
					<td><input type="number" min="25" max="2000" name="index_batch" value="<?php echo esc_attr( $settings['index_batch'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Counter cache lifetime, seconds', 'catcode-smart-filter' ); ?></th>
					<td><input type="number" min="60" max="86400" name="cache_ttl" value="<?php echo esc_attr( $settings['cache_ttl'] ); ?>"></td>
				</tr>
			</table>

			<p>
				<button type="button" class="button button-primary" id="ccf-save"><?php esc_html_e( 'Save', 'catcode-smart-filter' ); ?></button>
				<span class="description"><?php esc_html_e( 'Place the block: the “CatCode: Smart Filter” widget or the [catcode_filter] shortcode.', 'catcode-smart-filter' ); ?></span>
			</p>
		</form>
	</div>

	<div class="ccf-pane" id="ccf-facets" hidden>
		<p>
			<button type="button" class="button" id="ccf-discover"><?php esc_html_e( 'Import from the catalogue', 'catcode-smart-filter' ); ?></button>
			<button type="button" class="button button-primary" id="ccf-save-facets"><?php esc_html_e( 'Save facets', 'catcode-smart-filter' ); ?></button>
		</p>

		<div id="ccf-import" hidden></div>

		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Facet', 'catcode-smart-filter' ); ?></th>
					<th><?php esc_html_e( 'Source', 'catcode-smart-filter' ); ?></th>
					<th><?php esc_html_e( 'Widget', 'catcode-smart-filter' ); ?></th>
					<th><?php esc_html_e( 'Logic', 'catcode-smart-filter' ); ?></th>
					<th><?php esc_html_e( 'URL key', 'catcode-smart-filter' ); ?></th>
					<th><?php esc_html_e( 'Values', 'catcode-smart-filter' ); ?></th>
					<th><?php esc_html_e( 'Order', 'catcode-smart-filter' ); ?></th>
					<th><?php esc_html_e( 'Status', 'catcode-smart-filter' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody id="ccf-facet-rows"></tbody>
		</table>

		<div id="ccf-values"></div>
	</div>

	<div class="ccf-pane" id="ccf-index" hidden>
		<p class="description">
			<?php esc_html_e( 'The index is what the storefront reads. Build it once after importing facets; from then on every product save keeps it current.', 'catcode-smart-filter' ); ?>
		</p>

		<table class="form-table" role="presentation">
			<tr><th><?php esc_html_e( 'Rows in the index', 'catcode-smart-filter' ); ?></th><td id="ccf-stat-rows"><?php echo (int) $stats['rows']; ?></td></tr>
			<tr><th><?php esc_html_e( 'Products', 'catcode-smart-filter' ); ?></th><td><?php echo (int) $stats['products']; ?></td></tr>
			<tr><th><?php esc_html_e( 'Last full build', 'catcode-smart-filter' ); ?></th><td id="ccf-stat-built"><?php echo esc_html( $stats['built_at'] ? $stats['built_at'] : __( 'never', 'catcode-smart-filter' ) ); ?></td></tr>
		</table>

		<div id="ccf-progress" hidden><progress value="0" max="100"></progress> <span></span></div>

		<p>
			<button type="button" class="button button-primary" id="ccf-rebuild"><?php esc_html_e( 'Rebuild the index', 'catcode-smart-filter' ); ?></button>
			<button type="button" class="button" id="ccf-prune"><?php esc_html_e( 'Prune stale rows', 'catcode-smart-filter' ); ?></button>
		</p>
	</div>

	<div class="ccf-pane" id="ccf-diag" hidden>
		<p><button type="button" class="button" id="ccf-check"><?php esc_html_e( 'Run the check', 'catcode-smart-filter' ); ?></button></p>
		<div id="ccf-diag-out"></div>
		<hr>
		<p><button type="button" class="button button-link-delete" id="ccf-drop"><?php esc_html_e( 'Delete all filter data', 'catcode-smart-filter' ); ?></button></p>
	</div>

</div>
