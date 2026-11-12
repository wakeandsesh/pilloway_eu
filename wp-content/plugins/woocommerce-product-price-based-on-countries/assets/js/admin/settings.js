;( function( $ ) {

	// True/False switch
	$.fn.wcpbc_true_false = function() {
		function toogleClass( $toggleEl ) {
			var target        = $toggleEl.attr('href');
			var toggleclass   = 'yes' === $(target).val() ? 'enabled' : 'disabled';
			$toggleEl.find('span.woocommerce-input-toggle').removeClass('woocommerce-input-toggle--enabled').removeClass('woocommerce-input-toggle--disabled').addClass('woocommerce-input-toggle--' + toggleclass);
		};

		this.each( function(){
			$(this).on('click', function(e) {
				e.preventDefault();
				var target = $(this).attr('href');
				value      = 'yes' == $(target).val() ? 'no' : 'yes';
				$(target).val(value);
				toogleClass($(this));
				// Change envent.
				$(target).trigger('change');
			});

			toogleClass($(this));
		});

		return this;
	};

	/**
	 * Collapse
	 */
	$.fn.wcpbc_collapse = function() {
		// Toggle btn expanded attr..
		function toggleExpanded($btn) {
			const expanded = 'true' !== $btn.attr('aria-expanded'),
				attrValue = expanded ? 'true' : 'false';
			$btn.attr('aria-expanded', attrValue);
		};

		// Show/Hide controls.
		function showHide($btn) {
			const expanded = 'true' === $btn.attr('aria-expanded'),
				target = $btn.data('target');
			$(target).toggle(expanded);
		};

		// Add/Remove Location hash
		function setLocationHash($btn) {
			const noHashURL = window.location.href.replace(/#.*$/, ''),
				expanded = 'true' === $btn.attr('aria-expanded');
    		window.history.replaceState('', document.title, noHashURL);
			if (expanded && $btn.attr('href').length>1) {
				window.location.hash = $btn.attr('href');
			}
		}

		// Init aria-expanded.
		function initAriaExpanded($btn) {
			if (! window.location.hash) {
				return;
			}
			const expanded = ( window.location.hash === $btn.attr('href') );
			if ( expanded ) {
				$btn.attr('aria-expanded', 'true');
			}
		}

		// Init.
		this.each( function(){
			initAriaExpanded($(this));
			showHide($(this));
			$(this).on('click', function(e){
				e.preventDefault();
				toggleExpanded($(this));
				showHide($(this));
				setLocationHash($(this));
			});
		});
		return this;
	};

	/**
	 * "show if".
	 */
	$.fn.wcpbc_show_if = function() {
		//Evaluate a condition.
		function conditionEval(field, operator, value) {
			var result = false;
			var $field = $('[name="' + field + '"]').length ? $('[name="' + field + '"]') : $('#' + field);
			if ( $field.length ) {
				var field_val = $field.val();
				if ('=' == operator) {
					result = (field_val == value);
				} else {
					// operator !=
					result = (field_val != value);
				}
			}
			return result;
		}

		// On field change.
		$(this).on('show-if-field-change', function() {
			var conditions = $(this).data('show-if');
			if ( conditions instanceof Array ) {
				var showHide = true;
				conditions.forEach(function(condition){
					showHide = showHide && conditionEval( condition.field, condition.operator, condition.value);
				});
				$(this).toggle(showHide);
			}
		});

		// Listen to the change event.
		this.each(function(){
			var $that = $(this);
			var conditions = $that.data('show-if');
			if ( conditions instanceof Array ) {
				conditions.forEach(function(condition){
					var $field = $('[name="' + condition.field + '"]').length ? $('[name="' + condition.field + '"]') : $('#' + condition.field);
					$field.on('change', function(){
						$that.triggerHandler('show-if-field-change');
					});
				});
			}
		});

		// Show/Hide the element.
		this.each(function(){
			$(this).triggerHandler('show-if-field-change');
		});

		return this;
	};

	/**
	 * Init plugins.
	 */
	$('[data-show-if]:not(.-wcpbc-upgrade-pro)').wcpbc_show_if();
	$('a[data-toggle="collapse"]').wcpbc_collapse();
	$('.wcpbc-input-container.-container-true-false:not(.-wcpbc-upgrade-pro)>a[role="switch"]').wcpbc_true_false();

	/**
	 * Disable true/false switch when no pro.
	 */
	 $('.wcpbc-input-container.-container-true-false.-wcpbc-upgrade-pro > a[role="switch"]').on('click', function(e){
		e.preventDefault();
		$( document.body ).trigger( 'wcpbc_show_upgrade_pro_popup' );
	 });


})( jQuery );