;( function( $ ) {

	if ( typeof wcpbc_settings_edit_zone_params === 'undefined' ) {
		return false;
	}

	/**
	 * Country tool buttons.
	 */
	$('.-container-country-select .button.-select-all').on( 'click', function(e){
		e.preventDefault();
		$(this).closest( '.-container-country-select' ).find( 'select option' ).prop( 'selected', true );
		$(this).closest( '.-container-country-select' ).find('select').trigger( 'change' );
	});

	$('.-container-country-select .button.-select-none').on( 'click', function(e){
		e.preventDefault();
		$(this).closest( '.-container-country-select' ).find( 'select option' ).prop( 'selected', false );
		$(this).closest( '.-container-country-select' ).find('select').trigger( 'change' );
	});

	$('.-container-country-select .button.-select-eur').on( 'click', function(e){
		e.preventDefault();

		if ( ! wcpbc_settings_edit_zone_params.eur_countries instanceof Array ) {
			return;
		}

		$(this).closest( '.-container-country-select' ).find( 'select option' ).each( function( index, that ) {
			if ( wcpbc_settings_edit_zone_params.eur_countries.indexOf( $(that).attr( 'value' ) ) > -1 ) {
				$( that ).prop( 'selected', true );
			}
		});
		$(this).closest( '.-container-country-select' ).find('select').trigger( 'change' );
	});

	$('.-container-country-select .button.-select-eur-none').on( 'click', function(e){
		e.preventDefault();

		if ( ! wcpbc_settings_edit_zone_params.eur_countries instanceof Array ) {
			return;
		}

		$(this).closest( '.-container-country-select' ).find( 'select option' ).each( function( index, that ) {
			if ( wcpbc_settings_edit_zone_params.eur_countries.indexOf( $(that).attr( 'value' ) ) > -1 ) {
				$( that ).prop( 'selected', false );
			}
		});
		$(this).closest( '.-container-country-select' ).find('select').trigger( 'change' );
	});

	/**
	 * Change exchange rate append text on currency change.
	 */
	 $('select#currency').on('change', function(){
		$('.-container-exchange_rate span.wcpbc-input-append').text( $(this).val() );
	});

	/**
	 * Change title on type.
	 */
	$('input#name').on('keyup', function(){
		let name = $( this ).val();
		$( '.wcpbc-settings-section-container.-heading .wcpbc-zone-name' ).text( name ? name : 'Zone' );
	});

	/**
	 * Not in allowed countries warning.
	 */
	const allowed_countries_warning = {

		target: null,
		not_allowed: [],
		validate: function() {
			try {
				const that = allowed_countries_warning;
				that.not_allowed = that.target.val().filter(
					function (country) {
						return wcpbc_settings_edit_zone_params.allowed_countries.indexOf(country)<0;
					}
				);
				if ( that.not_allowed.length ) {
					that.show();
				} else {
					that.remove();
				}
			} catch(error) {
				console.log(error);
			}
		},

		remove: function () {
			if ( $('#wcpbc-allowed-countries-warning').length ) {
				$('#wcpbc-allowed-countries-warning').remove();
			}
		},

		show: function() {
			const container = this.target.closest('div.wcpbc-input-container');
			if ( ! container.find('#wcpbc-allowed-countries-warning').length )  {
				container.append('<div id="wcpbc-allowed-countries-warning"></div>');
			}
			const warning = container.find('#wcpbc-allowed-countries-warning'),
				template_id = 'allowed-countries-warning-' + ( this.not_allowed.length > 1 ? 'plural' : 'singular' ),
				template = wp.template(template_id);
				warning.html(template({countries:this.get_countries_names()}));

				$( document.body ).trigger( 'init_tooltips' );
		},

		get_countries_names: function() {
			const names = [];
			this.not_allowed.forEach((code) => {
				names.push( this.target.find(`option[value="${code}"]`).text() );
			});

			const last = names.pop();
			if ( names.length ) {
				return this.truncate( names.join(', ') + ` ${wcpbc_settings_edit_zone_params.i18n.and} ` + last );
			} else {
				return last;
			}
		},

		truncate: function(text) {
			if ( text.length > 100 ) {
				return text.substring(0, 100) + '...';
			}
			return text;
		},

		init: function() {
			if ( ! Array.isArray( wcpbc_settings_edit_zone_params.allowed_countries ) ) {
				return false;
			}

			this.target = $('select#countries');
			this.target.on('change', this.validate);
			this.validate();
		}
	};
	allowed_countries_warning.init();

})( jQuery );