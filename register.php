<?php

namespace Araa;

use Akismet;
use Google_Auth_Exception;
use Google_Client;
use Google_Http_Request;
use WP_Error;

// A struct for registration submission data.
class Registration {
    public $name;
    public $email;
    public $phone;
    public $address;
    public $password;
}

// Setup the form.
wp_enqueue_style('araa');

// Initialise form variables.
$reg = new Registration;
$errors = new WP_Error;
$registered = false;

foreach (array('name', 'email', 'phone', 'address', 'password') as $input) {
    $reg->$input = isset($_POST["araa-$input"]) ? $_POST["araa-$input"] : null;
}

// Process form submissions.
if ($_POST) {
    if (registration_process($reg, $errors)) {
        $registered = true;
    }
}

// Processes a registration and returns true if it succeeds.
function registration_process(Registration $reg, $errors) {
    // Field validation.
    $nonce = isset($_POST['araa-nonce']) ? $_POST['araa-nonce'] : null;

    if (!wp_verify_nonce($nonce, 'araa_register')) {
        $errors->add('nonce', 'Your session has expired, please submit the form again');
        return false;
    }

    if (empty($reg->name)) {
        $errors->add('name', 'You must provide a name');
        return false;
    }

    if (empty($reg->email) || !is_email($reg->email)) {
        $errors->add('email', 'The email address provided is not valid');
        return false;
    }

    // Anti-spam through Akismet.
    if (registration_is_spam($reg)) {
        $errors->add('spam', 'Your registration submission has been classified as spam.');
        return false;
    }

    // Attempt to apply the registration.
    if (!registration_save($reg, $errors)) {
        return false;
    }

    return true;
}

// Checks if a registration is classified as spam by Akisment.
function registration_is_spam(Registration $reg) {
    // Is Akismet enabled?
    if (!class_exists('Akismet')) {
        return false;
    }

    $query = http_build_query(array(
        'comment_type' => 'signup',
        'comment_author' => $reg->name,
        'comment_author_email' => $reg->email,
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

// Writes a registration to the spreadsheet and mailing list, and sends an email to the admin.
function registration_save(Registration $reg, $errors) {
    if (!registration_save_sheet($reg, $errors)) {
        return false;
    }

    // Send an email to the admin.
    $to = get_option('admin_email');
    $subject = 'New ARAA Individual Registration';

    $body = <<<EOS
<p>
    A new member has registered through the ARAA website. They have been added to the
    `Pending` sheet...
</p>
<dl>
    <dt>Name</dt><dd>%s</dd>
    <dt>Email address</dt><dd><a href="mailto:%s">%s</a></dd>
    <dt>Phone number</dt><dd>%s</dd>
    <dt>Postal address</dt><dd>%s</dd>
</dl>
EOS;

    $body = sprintf(
        $body,
        esc_html($reg->name),
        esc_attr($reg->email),
        esc_html($reg->email),
        esc_html($reg->phone),
        nl2br(esc_html($reg->address))
    );

    if (!wp_mail($to, $subject, $body, array('Content-Type: text/html; charset=UTF-8'))) {
        $errors->add('email', 'There was an error notifying the administrator of your registration.');
        return false;
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
        $errors->add('subscribe', 'There was an error subscribing to the mailing list.');
        return false;
    }

    return true;
}

function registration_save_sheet(Registration $reg, $errors) {
    require_once __DIR__ . '/vendor/google-api-php-client/src/Google/autoload.php';

    $client = new Google_Client();
    $client->setClientId(get_option('araa_client_id'));
    $client->setClientSecret(get_option('araa_client_secret'));
    $client->setAccessToken(get_option('araa_access_token'));

    // Check if the user has already submitted a registration.
    $url = sprintf(
        'https://spreadsheets.google.com/feeds/list/%s/%s/private/full?sq=%s',
        urlencode(get_option('araa_spreadsheet_id')),
        urlencode(get_option('araa_worksheet_id')),
        urlencode(sprintf('email="%s"', addslashes($reg->email)))
    );

    $request = new Google_Http_Request($url);
    $request->setRequestHeaders(array('Content-Type' => 'application/atom+xml'));

    try {
        $response = $client->getAuth()->authenticatedRequest($request);
        $existing = simplexml_load_string($response->getResponseBody());

        if ($existing) {
            $ns = 'http://a9.com/-/spec/opensearchrss/1.0/';
            $total = intval($existing->children($ns)->totalResults);

            if ($total > 0) {
                $errors->add('duplicate', 'A registration request for this email address has already been submitted');
                return false;
            }
        }
    } catch (Google_Auth_Exception $e) {
        // Don't worry about checking duplicates.
    }

    // The row data to add.
    $body = <<<EOS
<entry xmlns="http://www.w3.org/2005/Atom" xmlns:gsx="http://schemas.google.com/spreadsheets/2006/extended">
    <gsx:name>%s</gsx:name>
    <gsx:date>%s</gsx:date>
    <gsx:email>%s</gsx:email>
    <gsx:phone>%s</gsx:phone>
    <gsx:address>%s</gsx:address>
</entry>
EOS;

    $body = sprintf(
        $body,
        esc_html($reg->name),
        esc_html(date('c')),
        esc_html($reg->email),
        esc_html($reg->phone),
        esc_html($reg->address)
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
        $errors->add('sheets', 'Could not open the members spreadsheet for writing');
        return false;
    }

    if ($response->getResponseHttpCode() != 201) {
        $errors->add('sheets', 'Could not write your details to the member spreadsheet');
        return false;
    }

    return true;
}
?>

<div id="araa-register">
    <?php if ($registered) : ?>
        <p>
            Your registration has been succesfully submitted. Once it has been approved you will
            receive a notification email that you have been subscribed to the
            <a href="https://lists.csiro.au/mailman/listinfo/robotics-australia-nz-list" target="_blank">robotics-australia-nz-list</a>
            mailing list. Thankyou!
        </p>
    <?php else : ?>
        <form method="post" action="<?php the_permalink(); ?>">
            <?php wp_nonce_field('araa_register', 'araa-nonce'); ?>

            <?php if ($errors->errors) : ?>
                <ul class="errors">
                    <?php foreach ($errors->get_error_messages() as $message) : ?>
                        <li><?php esc_html_e($message); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <p>
                <label for="name">Your name <span class="required">*</span></label>
                <input type="text" id="name" name="araa-name" value="<?php esc_attr_e($reg->name); ?>" required="required" />
            </p>

            <p>
                <label for="email">Email address <span class="required">*</span></label>
                <input type="email" id="email" name="araa-email" value="<?php esc_attr_e($reg->email); ?>" required="required" />
            </p>

            <p>
                <label for="phone">Phone number</label>
                <input type="tel" id="phone" name="araa-phone" value="<?php esc_attr_e($reg->phone); ?>" />
            </p>

            <p>
                <label for="address">Postal address</label>
                <textarea id="address" name="araa-address" rows="3"><?php echo esc_textarea($reg->address); ?></textarea>
            </p>

            <p>
                The ARAA uses the <a href="https://lists.csiro.au/mailman/listinfo/robotics-australia-nz-list" target="_blank">robotics-australia-nz-list</a>
                mailing list for communications. This list is for members of the ARAA to inform other
                members about activities in the field of robotics and automation in Australia and New
                Zealand. Mail is expected about subjects such as conferences, trade shows, job openings,
                student and post-doc positions, and significant news in the field.
            </p>

            <p>
                Once you submit your registration, you will be automatically subscribed to the mailing
                list. You will receive an email asking you to confirm your registration before you
                will receive emails.
            </p>

            <p>
                <label for="password">Mailing list password</label>
                <input type="password" id="password" name="araa-password" value="<?php esc_attr_e($reg->password); ?>" />
                <label for="password" class="secondary">
                    If you do not enter a password, one will be generated and sent to you.
                </label>
            </p>

            <p>
                <input type="submit" class="submit" value="Register" />
            </p>
        </form>
    <?php endif; ?>
</div>
