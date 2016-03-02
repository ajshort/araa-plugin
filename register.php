<?php

add_shortcode('araa-register-form', function() {
    ob_start();
    require __DIR__ . '/register-form.php';
    return ob_get_clean();
});

function araa_registration_process($data) {
    require_once __DIR__ . '/vendor/google-api-php-client/src/Google/autoload.php';

    global $araa_au_states;
    global $araa_countries;
    global $wpdb;

    // Check the submission is valid.
    $nonce = isset($_POST['araa_nonce']) ? $_POST['araa_nonce'] : null;

    if (!wp_verify_nonce($nonce, 'araa_register')) {
        return new WP_Error('nonce', 'Your session has expired, please submit the form again');
    }

    if (empty($data['name'])) {
        return new WP_Error('name', 'You must provide a name');
    }

    if (empty($data['email']) || !is_email($data['email'])) {
        return new WP_Error('email', 'You must provide a valid email address');
    }

    if (empty($data['country']) || !array_key_exists($data['country'], $araa_countries)) {
        return new WP_Error('country', 'You must select a country');
    }

    if (empty($data['address_1']) || empty($data['suburb'])) {
        return new WP_Error('address', 'You must provide your address');
    }

    if ($data['country'] == 'AU') {
        if (empty($data['state_au']) || !array_key_exists($data['state_au'], $araa_au_states)) {
            return new WP_Error('state', 'You must select a state');
        }
    } else {
        if (empty($data['state'])) {
            return new WP_Error('state', 'You must provide your state');
        }
    }

    if (empty($data['postcode'])) {
        return new WP_Error('postcode', 'You must provide your postcode');
    }

    if (araa_registration_is_spam($data)) {
        return new WP_Error('spam', 'Your registration submission has been classified as spam');
    }

    $client = new Google_Client();
    $client->setClientId(get_option('araa_client_id'));
    $client->setClientSecret(get_option('araa_client_secret'));
    $client->setAccessToken(get_option('araa_access_token'));

    if (araa_registration_is_duplicate($client, $data['email'])) {
        return new WP_Error('duplicate', 'A registration with this email address has already been submitted');
    }

    // The registraion is valid, so write it to the sheet.
    $body = <<<EOS
<entry xmlns="http://www.w3.org/2005/Atom" xmlns:gsx="http://schemas.google.com/spreadsheets/2006/extended">
    <gsx:name>%s</gsx:name>
    <gsx:date>%s</gsx:date>
    <gsx:email>%s</gsx:email>
    <gsx:phone>%s</gsx:phone>
    <gsx:address_1>%s</gsx:address_1>
    <gsx:address_2>%s</gsx:address_2>
    <gsx:suburb>%s</gsx:suburb>
    <gsx:state>%s</gsx:state>
    <gsx:postcode>%s</gsx:postcode>
    <gsx:country>%s</gsx:country>
</entry>
EOS;

    $body = sprintf(
        $body,
        esc_html($data['name']),
        esc_html(date('c')),
        esc_html($data['email']),
        esc_html($data['phone']),
        esc_html('')
    );

    // Create and sign the request.
    $url = sprintf(
        'https://spreadsheets.google.com/feeds/list/%s/%s/private/full',
        urlencode(get_option('araa_spreadsheet_id')),
        urlencode(get_option('araa_worksheet_id'))
    );

    $request = new Google_Http_Request($url, 'POST');
    $request->setRequestHeaders(array(
        'GData-Version' => '3.0',
        'Content-Type' => 'application/atom+xml'
    ));
    $request->setPostBody($body);

    try {
        $response = $client->getAuth()->authenticatedRequest($request);
    } catch (Google_Auth_Exception $e) {
        return new WP_Error('sheets', 'Could not open the members spreadsheet for writing');
    }

    if ($response->getResponseHttpCode() != 201) {
        return new WP_Error('sheets', 'Could not write your details to the member spreadsheet');
    }

    // Send an email to the admin with the details.
    araa_registration_send_emails($data);

    return new WP_Error();
}

/**
 * Sends registration emails to the site admin and mailing list.
 */
function araa_registration_send_emails($data) {
    // Send an email to the admin.
    $to = get_option('admin_email');
    $subject = 'New ARAA Individual Registration';

    $body = <<<EOS
<p>
A new member has registered through the ARAA website. They have been added to the
`Pending` sheet...
</p>
<dl>
    <dt>Name</dt><dd>%1$s</dd>
    <dt>Email address</dt><dd><a href="mailto:%2$s">%2$s</a></dd>
    <dt>Phone number</dt><dd>%3$s</dd>
</dl>
EOS;
    $body = sprintf(
        $body,
        esc_html($reg['name']),
        esc_attr($reg['email']),
        esc_html($reg['phone'])
    );

    if (!wp_mail($to, $subject, $body, array('Content-Type: text/html; charset=UTF-8'))) {
        return new WP_Error('email', 'There was an error notifying the administrator of your registration.');
    }

    // Subscribe the user to the mailing list.
    $to = get_option('araa_mailing_list_requests');
    $command = array('subscribe');

    if (!empty($reg->password)) {
        $command[] = '"' . str_replace('"', '"', $reg->password) . '"';
    }

    $command[] = 'nodigest';
    $command[] = "address=$reg->email";
    $command = implode(' ', $command);

    if (!wp_mail($to, '', $command)) {
        return new WP_Error('subscribe', 'There was an error subscribing to the mailing list.');
    }

    return new WP_Error();
}

function araa_registration_is_spam($data) {
    // Is Akismet enabled?
    if (!class_exists('Akismet')) {
        return false;
    }

    $query = http_build_query(array(
        'comment_type' => 'signup',
        'comment_author' => $data['name'],
        'comment_author_email' => $data['email'],
        'user_ip' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'],
        'referrer' => $_SERVER['HTTP_REFERER'],
        'blog' => get_site_url(),
        'blog_lang' => get_locale()
    ));

    $response = Akismet::http_post($query, 'comment-check');
    $spam = is_array($response) && isset($response[1]) ? $response[1] : false;

    return $spam;
}

function araa_registration_is_duplicate($client, $email) {
    // Check if the user has already submitted a registration.
    $url = sprintf(
        'https://spreadsheets.google.com/feeds/list/%s/%s/private/full?sq=%s',
        urlencode(get_option('araa_spreadsheet_id')),
        urlencode(get_option('araa_worksheet_id')),
        urlencode(sprintf('email="%s"', addslashes($email)))
    );

    $request = new Google_Http_Request($url);
    $request->setRequestHeaders(array('Content-Type' => 'application/atom+xml'));

    try {
        $response = $client->getAuth()->authenticatedRequest($request);
        $existing = simplexml_load_string($response->getResponseBody());

        if ($existing) {
            $ns = 'http://a9.com/-/spec/opensearchrss/1.0/';
            $total = intval($existing->children($ns)->totalResults);

            return $total > 0;
        }
    } catch (Google_Auth_Exception $e) {
        // Don't worry about checking duplicates.
    }

    return false;
}
