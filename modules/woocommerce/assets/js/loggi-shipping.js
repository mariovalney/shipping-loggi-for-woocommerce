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

    // Working Days Input
    $('body').on('change', '.slfw-working-day-input input', function(event) {
        const wrapper = $(this).parents('.slfw-working-day-input');
        const input = wrapper.siblings('input');

        const is_active = wrapper.find('.input-active').prop('checked');
        const start_hour = wrapper.find('.input-start-time.input-hour');
        const start_minute = wrapper.find('.input-start-time.input-minute');
        const end_hour = wrapper.find('.input-end-time.input-hour');
        const end_minute = wrapper.find('.input-end-time.input-minute');

        const calculate_time = (input, max = 59, min = 0)  => {
            let value = parseInt( input.val().replace(/\D/g, '') );
            if (! value || value < min) {
                value = min;
            }

            if (value > max) {
                value = max;
            }

            value = value.toString().padStart(2, '0');
            input.val(value);

            return value;
        };

        let value = is_active ? '1' : '0';

        const output = `${value}|${calculate_time(start_hour, 23)}:${calculate_time(start_minute)}|${calculate_time(end_hour, 23)}:${calculate_time(end_minute)}`;
        input.val(output);

        start_hour.prop('disabled', ! is_active);
        start_minute.prop('disabled', ! is_active);
        end_hour.prop('disabled', ! is_active);
        end_minute.prop('disabled', ! is_active);
    });

    // Reload Shops
    $('body').on('change', '.loggi-api-input', function(event) {
        const environment = $('[name="woocommerce_loggi-shipping_environment"]').val();
        const api_email = $('[name="woocommerce_loggi-shipping_' + environment + '_api_email"]').val();
        const api_key = $('[name="woocommerce_loggi-shipping_' + environment + '_api_key"]').val();

        if ( ! api_email || ! api_key ) {
            return;
        }

        $('#slfw-reload-shops-description').show();

        $('#woocommerce_loggi-shipping_shop').find('option').remove();
        $('#woocommerce_loggi-shipping_shop').append('<option value="">' + __( 'Reload your stores.', 'shipping-loggi-for-woocommerce' ) + '</option>');
    });

    $('body').on('click', '#slfw-reload-shops-description a', function(event) {
        event.preventDefault();

        const select = $('#woocommerce_loggi-shipping_shop');

        select.find('option').remove();
        select.append('<option value="0">' + __( 'Loading...', 'shipping-loggi-for-woocommerce' ) + '</option>');

        const environment = $('[name="woocommerce_loggi-shipping_environment"]').val();
        const api_email = $('[name="woocommerce_loggi-shipping_' + environment + '_api_email"]').val();
        const api_key = $('[name="woocommerce_loggi-shipping_' + environment + '_api_key"]').val();

        // Request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'slfw_all_shops_as_options',
                environment: environment,
                api_email: api_email,
                api_key: api_key,
                nonce: select.data('nonce')
            },
        })
        .done(response => {
            select.find('option').remove();
            if (response.success && response.data) {
                for (var i = 0; i < response.data.length; i++) {
                    select.append('<option value="' + response.data[i].value + '">' + response.data[i].label + '</option>');
                }
                return;
            }

            select.append('<option value="0">' + __( 'You have any available shop.', 'shipping-loggi-for-woocommerce' ) + '</option>');
        })
        .fail(() => {
            select.find('option').remove();
            select.append('<option value="0">' + __( 'You have any available shop.', 'shipping-loggi-for-woocommerce' ) + '</option>');
        });
    });

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
