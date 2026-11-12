;( function( $ ) {

	if ( typeof wcpbc_settings_zone_list_params === 'undefined' ) {
		return false;
	}

	$('table.pricingzones tbody').sortable( {
			items: 'tr:not(.zone-worldwide)',
			cursor: 'move',
			axis: 'y',
			handle: 'td.check-column,th.check-column',
			scrollSensitivity: 40,
			start: function ( event, ui ) {
				ui.item.css( 'background-color', '#f6f6f6' );
			},
			stop: function ( event, ui ) {
				ui.item.removeAttr( 'style' );
				ui.item.trigger( 'updateMoveButtons' );
			},
	} );

	$('a#export-pricingzones').click(function(e){

		const fields = $('.zone-worldwide').data('export-fields');
		let rows = fields.map( field => {return field;}).join(',') + "\r\n";

		$('table.pricingzones tbody tr[data-export-data]').each( function(){
			let data = $(this).data('export-data');
			rows += fields.map( field => {return '"' + data[ field ] + '"';}).join(',') + "\r\n";
		});

		const href = encodeURI('data:text/csv;charset=utf-8,' + rows );

		$(this).attr('download', 'pricing_zones.csv');
		$(this).attr('href', href);
	});

	$('table.pricingzones td.column-enabled a[role="switch"]').on('click', function(e) {
		e.preventDefault();
		var target = $(this).closest('td').find('input[name="enabled[]"');
		value      = 'yes' == $(target).val() ? 'no' : 'yes';

		$(target).val(value);

		let toggleclass   = 'yes' === $(target).val() ? 'enabled' : 'disabled';
		$(this).find('span.woocommerce-input-toggle').removeClass('woocommerce-input-toggle--enabled').removeClass('woocommerce-input-toggle--disabled').addClass('woocommerce-input-toggle--' + toggleclass);

		// Change envent.
		$(target).trigger('change');
	});

	// editPrompt
	function editPrompt () {
		let changed = false;
		$('input[name="enabled[]').on('change', function(){
			if ( ! changed ) {
				window.onbeforeunload = function () {
					return woocommerce_settings_params.i18n_nav_warning;
				};
				changed = true;
				$( '.woocommerce-save-button' ).removeAttr( 'disabled' );
			}
		});

	};
	editPrompt();

	// Add a help-tip to the reorder header.
	 $('<span class="woocommerce-help-tip" data-tip="' + wcpbc_settings_zone_list_params.i18n.reorder_helptip + '"></span>')
	 	.appendTo( $('table.pricingzones thead #cb') );

})( jQuery );