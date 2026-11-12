;( function( $ ) {

	if ( typeof wcpbc_setup_wizard_params === 'undefined' ) {
		return false;
	}

	/**
	 * Setup Wizard.
	 */
	$.fn.wcpbc_setup_wizard = function() {

		/**
		 * Current step.
		 */
		var currentStep = '';

		/**
		 * Current object.
		 */
		var that = this;

		/**
		 * Get step from hash.
		 *
		 * @return string
		 */
		function getStepFromHash() {
			let hash = false;
			let step = false;

			if ( window.location.hash.length > 1 ) {
				hash = window.location.hash.substring(1);
			}

			if ( hash && that.find('[data-step='+hash+']').length) {
				step = hash;
			} else {
				step = that.find('[data-step]').first().data('step');
			}

			return step;
		};

		/**
		 * Reset step pagination.
		 */
		function reset() {
			currentStep = getStepFromHash();
			showStep();
		};

		/**
		 * Return the current step Jquery element.
		 */
		function getStepEl() {
			return that.find('[data-step=' + currentStep + ']');
		}

		/**
		 * Show the current step.
		 */
		function showStep() {
			that.find('[data-step]').hide();
			getStepEl().show();
		}

		/**
		 * Paginate to the next step.
		 */
		function moveNext() {
			currentStep = getStepEl().next('[data-step]').data('step');
			// Update hash.
			const url = window.location.toString().split('#')[0];
			history.pushState( {step: currentStep}, '' , url + '#' + currentStep );
			// Show step.
			showStep();
		}

		/**
		 * Show error
		 */
	    function showError( error ) {
			const template = wp.template('wcpbc-setup-wizard-notice');
			const notice   = template(error);
			that.find('.wcpbc-input-container.-container-' + error.code).prepend(notice);
		}

		/**
		 * Process the step.
		 *
		 * @param {jQuery} $submitpost
		 */
		function processStep( $submitpost ) {
			const $wrap    = $submitpost.closest('div[data-step]');
			const step     = $wrap.data('step');
			const ajaxData = $submitpost.serializeArray();

			ajaxData.push(
				{name: 'action', value: 'wcpbc_setup_wizard_process_step'},
				{name: 'security', value: wcpbc_setup_wizard_params.security},
				{name: 'step', value: step}
			);

			that.find('.wcpbc-setup-wizard-notice').remove();

			$('#wcpbc-setup-wizard-content').block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});

			$.ajax( {
				type: 'POST',
				url: ajaxurl,
				data: $.param(ajaxData),
				success: function( response ) {
					if ( response.success ) {
						moveNext();
					} else {
						response.data.forEach(function(error){showError(error)});
					}
				},
				complete: function() {
					$('#wcpbc-setup-wizard-content').unblock();
				}
			}).fail(function(response){
				window.console.log( response );
			});
		};

		/**
		 * Add CSS classes to body. Some plugins overwrites the admin_body_class filter. Ensure the body has the required classes.
		 */
		function bodyClasses() {
			['wcpbc-admin-full-screen', 'wcpbc-setup-wizard'].forEach(
				function(className){
					if ( ! $('body').hasClass(className) ) {
						$('body').addClass(className);
					}
				}
			);
		}

		/**
		 * Init wizard.
		 */
		bodyClasses();
		reset();
		showStep();

		this.on('submit', 'form', function(e){
			e.preventDefault();
			processStep( $(this) );
		});

		this.on( 'click', '.wcpbc-setup-wizard-notice .wcpbc-setup-wizard-notice-dismiss', function(e){
			e.preventDefault();
			$(this).closest('.wcpbc-setup-wizard-notice').fadeOut();
		});

		window.addEventListener('popstate', function(){
			reset();
			showStep();
		});
	};

	// True/False switch
	$.fn.wcpbc_true_false = function() {
		function toogleClass( $toggleEl ) {
			var target        = $toggleEl.attr('href');
			var toggleclass   = 'yes' === $(target).val() ? 'enabled' : 'disabled';
			$toggleEl.find('span.wcpbc-input-toggle').removeClass('-input-toggle--enabled').removeClass('-input-toggle--disabled').addClass('-input-toggle--' + toggleclass);
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

	$('.wcpbc-input-container.-container-true-false>a[role="switch"]').wcpbc_true_false();
	$('#wcpbc-setup-wizard-content').wcpbc_setup_wizard();

})( jQuery, ajaxurl );