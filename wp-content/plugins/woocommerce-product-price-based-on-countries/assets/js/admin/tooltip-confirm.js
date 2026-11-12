;( function( $ ) {

	/**
	 * Tooltip confirm.
	 */
	 $.fn.wcpbc_tooltip_confirm = function() {

		const targets = this;

		function create($target) {
			const toolTipId = 'wcpbc-tooltip-' + ( $('.wcpbc-tooltip-confirm').length + 1 );
			let $toolTip = $('<div class="wcpbc-tooltip-confirm"></div>');

			$toolTip.attr('id', toolTipId);

			$toolTip.html($target.data('title'));
			$target.data('tooltipId', '#' + $toolTip.attr('id'));

			$toolTip.find('a[data-event="confirm"]').attr('href', $target.attr('href'));
			$toolTip.find('a[data-event="cancel"]').on('click', function(e){
				e.preventDefault();
				hide($target);
			});

			return $toolTip.appendTo('body');
		}

		function show($target){

			targets.each( function(){
				if ( ! $target.is( $(this) ) && $(this).hasClass('-hover') ) {
					hide( $(this) );
				}
			});

			const tooltipId = $target.data('tooltipId');
			let $toolTip = false;

			if (tooltipId && $(tooltipId).length ) {
				$toolTip = $(tooltipId);
				$toolTip.show();
			} else {
				$toolTip = create( $target);
				position($toolTip, $target);
			}

			$target.removeClass('-hover').addClass('-hover');

			if ( $target.data('toggle-parent') ) {
				const $parent = $target.closest( $target.data('toggle-parent') );
				$parent.removeClass('-hover').addClass('-hover');
			}
		}

		function hide($target) {
			const tooltipId = $target.data('tooltipId');
			if (tooltipId && $(tooltipId).length ) {
				$(tooltipId).hide();
			}

			$target.removeClass('-hover');

			if ( $target.data('toggle-parent') ) {
				const $parent = $target.closest( $target.data('toggle-parent') );
				$parent.removeClass('-hover');
			}
		}

		function position($tooltip, $target) {
			var tolerance = 8; // Find target position.

			var targetWidth = $target.outerWidth();
			var targetTop = $target.offset().top;
			var targetLeft = $target.offset().left; // Find tooltip position.

			var tooltipWidth = $tooltip.outerWidth();
			var tooltipHeight = $tooltip.outerHeight();

			var top = targetTop - tooltipHeight - tolerance;
			var left = targetLeft + targetWidth / 2 - tooltipWidth / 2;

			$tooltip.css({
				top: top,
				left: left
			  });
		}

		this.each(function(){
			$(this).on('click', function(e){
				e.preventDefault();
				if ( $(this).hasClass('-hover')) {
					hide($(this));
				} else {
					show($(this));
				}
			});
		});
	};

	// Init tooltips.
	$('a[data-toggle="tooltip-confirm"]').wcpbc_tooltip_confirm();

})( jQuery );