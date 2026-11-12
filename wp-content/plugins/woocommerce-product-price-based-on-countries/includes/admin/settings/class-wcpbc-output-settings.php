<?php
/**
 * Output the plugin settings.
 *
 * @package WCPBC
 */

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.


/**
 * WCPBC_Output_Settings Class
 */
class WCPBC_Output_Settings {

	/**
	 * Constructor
	 *
	 * @param array $settings Array of settings.
	 */
	public function __construct( $settings ) {
		$this->output_html( $settings );
	}

	/**
	 * Add a class to the "class" prop of the field.
	 *
	 * @param string $class Class to append.
	 * @param string $newclass Class to add.
	 * @return string.
	 */
	protected function add_class( $class, $newclass ) {
		$class .= empty( $class ) ? '' : ' ';
		return $class . $newclass;
	}

	/**
	 * Output a input HTML.
	 *
	 * @param array $field Field data.
	 */
	protected function output_input_html( $field ) {
		$before = '';
		$after  = '';

		$custom_attr = $field['custom_attributes'];

		$custom_attr['class'] = $field['class'];
		$custom_attr['type']  = $field['type'];
		$custom_attr['id']    = $field['id'];
		$custom_attr['name']  = $field['name'];
		$custom_attr['value'] = $field['value'];

		if ( ! empty( $field['placeholder'] ) ) {
			$custom_attr['placeholder'] = $field['placeholder'];
		}

		$wrap_class = 'wcpbc-input-wrap';
		$prepend    = '';
		$append     = '';

		if ( ! empty( $field['prepend'] ) ) {
			$wrap_class .= ' -has-prepend';
			$prepend     = sprintf( '<span class="wcpbc-input-prepend -prepend-' . esc_attr( $field['id'] ) . '">%s</span>', esc_html( $field['prepend'] ) );
		}
		if ( ! empty( $field['append'] ) ) {
			$wrap_class .= ' -has-append';
			$append      = sprintf( '<span class="wcpbc-input-append -append-' . esc_attr( $field['id'] ) . '">%s</span>', esc_html( $field['append'] ) );
		}

		$before = sprintf( '<div class="%s">%s', $wrap_class, $prepend );
		$after  = sprintf( '%s</div>', $append );

		printf( '%s<input %s />%s', $before, wc_implode_html_attributes( $custom_attr ), $after ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Output a input label.
	 *
	 * @param array $field Field data.
	 */
	protected function output_label_html( $field ) {
		if ( empty( $field['label'] ) ) {
			return;
		}

		$text  = sprintf( '<span class="-input-label-text">%s</span>', esc_html( $field['label'] ) );
		$class = $field['type'];
		if ( ! empty( $field['is_pro'] ) && ! wcpbc_is_pro() ) {
			$text .= '<a href="' . wcpbc_home_url( 'settings' ) . '" target="_blank" rel="external noreferrer noopener" class="wcpbc-upgrade-pro">PRO</a>';
		}
		printf( '<label class="wcpbc-input-label -label-%s">%s</label>', esc_attr( $class ), $text ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Output a input checkbox HTML.
	 *
	 * @param array $field Field data.
	 */
	protected function output_checkbox_html( $field ) {
		echo '<span class="wcpbc-input-checkbox-container">';
		$this->output_input_html( $field );
		echo '</span>';
		$this->output_label_html( $field );
	}


	/**
	 * Output a True/False input HTML.
	 *
	 * @param array $field Field data.
	 */
	protected function output_true_false_html( $field ) {

		$value = wc_string_to_bool( $field['value'] );

		printf( '<input type="hidden" id="%1$s" name="%2$s" value="%3$s" />', esc_attr( $field['id'] ), esc_attr( $field['name'] ), esc_attr( ( $value ? 'yes' : 'no' ) ) );
		printf( '<a href="#%s" role="switch"><span class="woocommerce-input-toggle woocommerce-input-toggle--%s" aria-label="%s"></span></a>', esc_attr( $field['id'] ), esc_attr( ( $value ? 'enabled' : 'disabled' ) ), esc_attr( $field['label'] ) );
		$this->output_label_html( $field );
	}

	/**
	 * Output a select HTML.
	 *
	 * @param array $field Field data.
	 */
	protected function output_select_html( $field ) {
		$attr = $field['custom_attributes'];

		$attr['class'] = $field['class'];
		$attr['id']    = $field['id'];
		$attr['name']  = $field['name'];

		if ( isset( $field['multiple'] ) && $field['multiple'] ) {
			$attr['multiple'] = 'multiple';
			$attr['name']    .= '[]';
		}

		$disabled = empty( $field['disabled_options'] ) ? [] : $field['disabled_options'];
		if ( ! empty( $attr['disabled'] ) ) {
			$disabled = array_keys( $field['options'] );
			unset( $attr['disabled'] );
		}

		$this->output_label_html( $field );

		echo '<div class="wcpbc-input-wrap">';
		echo '<select ' . wc_implode_html_attributes( $attr ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( isset( $field['options'] ) ) {
			$this->output_options( $field['options'], $field['value'], $disabled );
		}
		echo '</select>';
		echo '</div>';
	}

	/**
	 * Output a select HTML.
	 *
	 * @param array $field Field data.
	 */
	protected function output_enhanced_select_html( $field ) {
		$field['class'] = $this->add_class( $field['class'], 'wc-enhanced-select' );

		$field['custom_attributes']['data-placeholder'] = empty( $field['placeholder'] ) ? '' : $field['placeholder'];
		$field['custom_attributes']['style']            = 'width:100%;';

		$this->output_select_html( $field );
	}

	/**
	 * Output a country select HTML.
	 *
	 * @param array $field Field data.
	 */
	protected function output_country_select_html( $field ) {

		if ( empty( $field['options'] ) ) {
			$field['options'] = WC()->countries->get_countries();
		}

		$this->output_enhanced_select_html( $field );

		if ( ! empty( $field['multiple'] ) ) {

			// Output tools.
			$buttons = array(
				array(
					'class' => 'button -select-all',
					'label' => __( 'Select all', 'woocommerce-product-price-based-on-countries' ),
				),
				array(
					'class' => 'button -select-none',
					'label' => __( 'Select none', 'woocommerce-product-price-based-on-countries' ),
				),
				array(
					'class' => 'button -select-eur',
					'label' => __( 'Select Eurozone', 'woocommerce-product-price-based-on-countries' ),
				),
				array(
					'class' => 'button -select-eur-none',
					'label' => __( 'Unselect Eurozone', 'woocommerce-product-price-based-on-countries' ),
				),
			);

			$defaults = array(
				'href'          => '#',
				'wrapper_start' => '',
				'wrapper_end'   => '',
			);

			echo '<div class="wcpbc-select-country-buttons-wrapper">';
			foreach ( $buttons as $button ) {
				$button = array_merge( $button, $defaults );

				$this->output_link_html( $button );
			}
			echo '</div>';
		}
	}

	/**
	 * Output a currency select HTML.
	 *
	 * @param array $field Field data.
	 */
	protected function output_currency_select_html( $field ) {
		$field['options'] = array();
		foreach ( get_woocommerce_currencies() as $code => $name ) {
			$field['options'][ $code ] = $name . ' (' . get_woocommerce_currency_symbol( $code ) . ')';
		}

		$this->output_enhanced_select_html( $field );
	}

	/**
	 * Output select options.
	 *
	 * @param array $options Options in array.
	 * @param array $values Values selected.
	 * @param array $disabled Disabled options disabled array.
	 */
	protected function output_options( $options, $values, $disabled = [] ) {
		if ( ! is_array( $options ) ) {
			return;
		}

		$values = is_array( $values ) ? $values : array( $values );

		foreach ( $options as $key => $option_value ) {
			if ( is_array( $option_value ) ) {
				echo '<optgroup label="' . esc_attr( $key ) . '">';
				self::output_options( $option_value, $values );
				echo '</optgroup>';
			} else {
				printf(
					'<option value="%s" %s %s>%s</option>',
					esc_attr( $key ),
					selected( in_array( $key, $values ), true, false ), // phpcs:ignore WordPress.PHP.StrictInArray
					disabled( in_array( $key, $disabled ), true, false ), // phpcs:ignore WordPress.PHP.StrictInArray
					esc_html( $option_value )
				);
			}
		}
	}

	/**
	 * Output a button link HTML.
	 *
	 * @param array $field Field data.
	 */
	protected function output_link_html( $field ) {
		$field = wp_parse_args(
			$field,
			array(
				'id'                => '',
				'class'             => '',
				'href'              => '',
				'custom_attributes' => array(),
				'wrapper_start'     => '<p>',
				'wrapper_end'       => '</p>',
			)
		);

		$attributes = $field['custom_attributes'];

		$attributes['class'] = $field['class'];
		$attributes['id']    = $field['id'];
		$attributes['href']  = empty( $field['href'] ) ? '' : $field['href'];

		$attributes = array_filter( $attributes );

		printf( '%s<a %s>%s</a>%s', $field['wrapper_start'], wc_implode_html_attributes( $attributes ), esc_html( $field['label'] ), $field['wrapper_end'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Output a submit buttton.
	 *
	 * @param array $field Field data.
	 */
	protected function output_submit_html( $field ) {
		echo '<p class="submit">';
		submit_button( $field['label'], 'primary', 'save', false );
		echo '</p>';
	}

	/**
	 * Output a control.
	 *
	 * @param array $field Field data.
	 */
	protected function output_field_html( $field ) {
		$field = wp_parse_args(
			$field,
			array(
				'type'              => 'text',
				'id'                => '',
				'name'              => false,
				'default'           => '',
				'class'             => '',
				'container_class'   => '',
				'custom_attributes' => array(),
				'placeholder'       => '',
			)
		);

		$field['name']  = empty( $field['name'] ) ? $field['id'] : $field['name'];
		$field['value'] = isset( $field['value'] ) ? $field['value'] : WC_Admin_Settings::get_option( $field['name'], $field['default'] );
		$field['class'] = $this->add_class( $field['class'], 'wcpbc-settings-input -input-' . $field['type'] . ' -' . $field['id'] );

		$container_classes = array(
			'wcpbc-input-container',
			'-container-' . $field['type'],
			'-container-' . $field['id'],
		);

		if ( ! empty( $field['container_class'] ) ) {
			$container_classes[] = $field['container_class'];
		}

		// Upgrade to Pro Ads.
		if ( ! empty( $field['is_pro'] ) && ! wcpbc_is_pro() ) {

			$container_classes[] = '-wcpbc-upgrade-pro';

			$field['custom_attributes']['disabled'] = 'disabled';
		}

		echo '<div class="' . esc_attr( implode( ' ', $container_classes ) ) . '"' . ( ! empty( $field['show-if'] ) ? ' data-show-if="' . esc_attr( wp_json_encode( $field['show-if'] ) ) . '"' : '' ) . '>';

		$type     = str_replace( '-', '_', esc_attr( $field['type'] ) );
		$callback = "output_{$type}_html";

		if ( is_callable( array( $this, $callback ) ) ) {
			$this->{$callback}( $field );
		} else {
			$this->output_label_html( $field );
			$this->output_input_html( $field );
		}

		if ( ! empty( $field['desc'] ) ) {
			printf( '<p class="wcpbc-input-help">%s</p>', wp_kses_post( $field['desc'] ) );
		}

		echo '</div>';

	}

	/**
	 * Output a section.
	 *
	 * @param array $data Section data.
	 */
	protected function output_controls_section_html( $data ) {
		$data = wp_parse_args(
			$data,
			array(
				'id'        => '',
				'title'     => '',
				'paragrahs' => array(),
				'fields'    => array(),
				'class'     => '',
			)
		);

		echo '<div class="wcpbc-settings-section-container -' . esc_html( $data['id'] ) . ( empty( $data['class'] ) ? '' : ' ' . esc_html( $data['class'] ) ) . '">';

		echo '<div class="wcpbc-settings-section-desc">';

		if ( ! empty( $data['title'] ) ) {
			printf( '<h2>%s</h2>', wp_kses_post( $data['title'] ) );
		}

		foreach ( $data['paragrahs'] as $paragrah ) {
			printf( '<p>%s</p>', wp_kses_post( $paragrah ) );
		}

		echo '</div>';

		if ( count( $data['fields'] ) ) {
			echo '<div class="wcpbc-settings-section-card-wrapper">';
			echo '<div class="wcpbc-settings-section-card' . ( 'submit' === $data['id'] ? '-submit' : '' ) . '">';

			foreach ( $data['fields'] as $field ) {
				$this->output_field_html( $field );
			}

			echo '</div></div>';
		}

		echo '</div>';
	}

	/**
	 * Output setting.
	 *
	 * @param array $data Data.
	 */
	protected function output_html( $data ) {
		$data = is_array( $data ) ? $data : array();

		echo '<div class="wcpbc-settings-panel">';

		foreach ( $data as $id => $section ) {
			$this->output_controls_section_html( $section );
		}

		echo '</div>';
	}
}
