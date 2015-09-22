<?php

add_action('admin_menu', function() {
    add_options_page(
        'Australian Robotics and Automation Association',
        'ARAA',
        'manage_options',
        'araa',
        'araa_options_page'
    );
});

add_action('admin_init', function() {
    register_setting('araa_registration_group', 'araa_mailing_list_requests');
    register_setting('araa_registration_group', 'araa_client_id');
    register_setting('araa_registration_group', 'araa_client_secret');
    register_setting('araa_registration_group', 'araa_access_token');
    register_setting('araa_registration_group', 'araa_spreadsheet_id');
    register_setting('araa_registration_group', 'araa_worksheet_id');

    add_settings_section(
        'araa_registration_group',
        'Registration',
        'araa_registration_settings_callback',
        'araa'
    );

    add_settings_field(
        'araa_mailing_list_requests',
        'Mailing List Requests Email',
        araa_text_setting_callback('araa_mailing_list_requests'),
        'araa',
        'araa_registration_group'
    );

    add_settings_field(
        'araa_client_id',
        'Google Sheets Client ID',
        araa_text_setting_callback('araa_client_id'),
        'araa',
        'araa_registration_group'
    );

    add_settings_field(
        'araa_client_secret',
        'Client Secret',
        araa_text_setting_callback('araa_client_secret'),
        'araa',
        'araa_registration_group'
    );

    add_settings_field(
        'araa_access_token',
        'Access Token',
        'araa_access_token_callback',
        'araa',
        'araa_registration_group'
    );

    add_settings_field(
        'araa_spreadsheet_id',
        'Spreadsheet ID',
        araa_text_setting_callback('araa_spreadsheet_id'),
        'araa',
        'araa_registration_group'
    );

    add_settings_field(
        'araa_worksheet_id',
        'Worksheet ID',
        araa_text_setting_callback('araa_worksheet_id'),
        'araa',
        'araa_registration_group'
    );
});

add_action('admin_init', function() {
    // Are we generating an Oauth token for the spreadsheet access?
    if (!isset($_GET['araa-generate-token'])) {
        return;
    }

    require_once __DIR__ . '/vendor/google-api-php-client/src/Google/autoload.php';

    $client = new Google_Client();
    $client->setClientId(get_option('araa_client_id'));
    $client->setClientSecret(get_option('araa_client_secret'));
    $client->addScope('https://spreadsheets.google.com/feeds');
    $client->setRedirectUri(admin_url('options-general.php?page=araa&araa-generate-token'));
    $client->setApprovalPrompt('force');
    $client->setAccessType('offline');

    // Callback with a code.
    if (isset($_GET['code'])) {
        $client->authenticate($_GET['code']);
        update_option('araa_access_token', $client->getAccessToken());

        wp_redirect('options-general.php?page=araa');
        exit;
    }

    wp_redirect($client->createAuthUrl());
    exit;
});

function araa_options_page() {
    require __DIR__ . '/options.php';
}

function araa_registration_settings_callback() {
    echo '<p>Member registrations are saved to a Google Sheets spreadsheet.</p>';
}

function araa_text_setting_callback($name) {
    return function() use($name) {
        printf(
            '<input type="text" name="%1$s" id="%1$s" value="%2$s" />',
            esc_attr($name),
            esc_attr(get_option($name))
        );
    };
}

function araa_access_token_callback() {
    $tok = get_option('araa_access_token');
    $generate = admin_url('options-general.php?page=araa&araa-generate-token');

    printf('<input type="text" name="araa_access_token" id="araa_access_token" value="%s" />', esc_attr($tok));
    printf('<a href="%s" class="button">Generate</a>', esc_url($generate));
}
