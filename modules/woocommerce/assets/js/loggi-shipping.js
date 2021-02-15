'use strict';

const { __, _x, _n, _nx } = wp.i18n;

jQuery(document).ready(function($) {
    // Update Environment Fields
    $('body').on('update-environment.slfw', function(event) {
        const environment = $('[name="woocommerce_loggi-shipping_environment"]').val();
        $('[data-slfw-environment-field]').each(function(index, el) {
            const visible = $(this).attr('data-slfw-environment-field') === environment;
            $(this).parents('tr').toggle(visible);
            $(this).prop('required', visible);
        });
    });

    $('[name="woocommerce_loggi-shipping_environment"]').on('change', function(event) {
        $('body').trigger('update-environment.slfw');
    });

    $('body').trigger('update-environment.slfw');

    // Request for API Key
    $('.slfw-request-api-key').on('click', function(event) {
        event.preventDefault();

        const input = $(this).parents('tr').find('input');

        const alert = (message, type = '') => {
            input.siblings('.description').find('.slfw-request-description').remove();

            if (message) {
                input.siblings('.description').prepend('<span class="slfw-request-description ' + type + '">' +  message + '</span>');
            }
        }

        // Reset
        alert('');
        input.removeClass('slfw-invalid-input');

        // Check password
        const password = input.val();
        if (! password) {
            alert( __( 'You should insert your Loggi password in "API Key" field.', 'shipping-loggi-for-woocommerce' ), 'error' )
            input.addClass('slfw-invalid-input');
            return;
        }

        // Check e-mail
        const email = $('[name="' + input.data('email-input') + '"]').val();
        if (! email) {
            alert( __( 'You should insert your Loggi e-mail in "E-mail" field.', 'shipping-loggi-for-woocommerce' ), 'error' )
            input.addClass('slfw-invalid-input');
            return;
        }

        alert( __( 'Loading...', 'shipping-loggi-for-woocommerce' ) );

        // Request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'slfw_request_api_key',
                email: email,
                password: password,
                environment: input.data('environment'),
                nonce: input.data('nonce')
            },
        })
        .done(response => {
            if (response.success && response.data) {
                alert( __( 'API Key found. Save the new settings.', 'shipping-loggi-for-woocommerce' ) );
                input.val(response.data);
                return;
            }

            if (response.data) {
                alert( response.data, 'error' );
                return;
            }

            alert( __( 'Error: please reload the page and try again.', 'shipping-loggi-for-woocommerce' ), 'error' );
        })
        .fail(() => {
            alert( __( 'Impossible to request your API Key. Please try again in few minutes.', 'shipping-loggi-for-woocommerce' ), 'error' );
        });

    });
});
