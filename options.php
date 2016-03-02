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
    register_setting('araa_list_group', 'araa_list_name');
    register_setting('araa_list_group', 'araa_list_url');
    register_setting('araa_list_group', 'araa_list_request_email');

    register_setting('araa_gsheets_group', 'araa_access_token');
    register_setting('araa_gsheets_group', 'araa_spreadsheet_id');
    register_setting('araa_gsheets_group', 'araa_worksheet_id');

    add_settings_section(
        'araa_list_group',
        'Mailing List',
        'araa_list_settings_callback',
        'araa'
    );

    add_settings_field(
        'araa_list_name',
        'List Name',
        araa_text_setting_callback('araa_list_name'),
        'araa',
        'araa_list_group'
    );

    add_settings_field(
        'araa_list_url',
        'List URL',
        araa_text_setting_callback('araa_list_url'),
        'araa',
        'araa_list_group'
    );

    add_settings_field(
        'araa_list_request_email',
        'Requests Email',
        araa_text_setting_callback('araa_list_request_email'),
        'araa',
        'araa_list_group'
    );

    add_settings_section(
        'araa_gsheets_group',
        'Membership Google Sheet',
        'araa_gsheets_callback',
        'araa'
    );

    add_settings_field(
        'araa_access_token',
        'Access Token',
        'araa_access_token_callback',
        'araa',
        'araa_gsheets_group'
    );

    add_settings_field(
        'araa_spreadsheet_id',
        'Spreadsheet ID',
        araa_text_setting_callback('araa_spreadsheet_id'),
        'araa',
        'araa_gsheets_group'
    );

    add_settings_field(
        'araa_worksheet_id',
        'Worksheet ID',
        araa_text_setting_callback('araa_worksheet_id'),
        'araa',
        'araa_gsheets_group'
    );
});

function araa_options_page() {
    require __DIR__ . '/options-form.php';
}

function araa_list_settings_callback() {
    echo '<p>Approved members are automatically added to the mailing list.</p>';
}

function araa_gsheets_callback() {
    echo '<p>Pending members are added the specified Google sheet.</p>';
}

function araa_text_setting_callback($name) {
    return function() use ($name) {
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
