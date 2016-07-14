<?php
require_once __DIR__ . '/locations.php';

wp_enqueue_style('araa');
wp_enqueue_script('araa-register');

$listName = get_option('araa_list_name');
$listUrl = get_option('araa_list_url');

$registered = false;
$errors = null;

$data = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'country' => 'AU',
    'address_1' => '',
    'address_2' => '',
    'suburb' => '',
    'state' => '',
    'state_au' => '',
    'postcode' => '',
    'password' => ''
];

if ($_POST) {
    foreach (array_keys($data) as $field) {
        if (isset($_POST["araa_$field"])) {
            $data[$field] = $_POST["araa_$field"];
        }
    }

    $errors = araa_registration_process($data);
    $registered = count($errors->errors) == 0;
}
?>
<div id="araa-register">
    <?php if ($registered) : ?>
        <p>
            Thankyou for submitting your registration. Once your registration is approved by the
            secretary you will be notified by email.
        </p>
    <?php else : ?>
        <form method="post" action="<?php the_permalink(); ?>">
            <?php wp_nonce_field('araa_register', 'araa_nonce'); ?>

            <?php if (is_wp_error($errors) && count($errors->errors) > 0) : ?>
                <ul class="errors">
                    <?php foreach ($errors->get_error_messages() as $message) : ?>
                        <li><?php esc_html_e($message); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <h3>Personal Details</h3>

            <p>
                <label for="first_name">First Name <span class="required">*</span></label>
                <input type="text" id="first_name" name="araa_first_name" value="<?php esc_attr_e($data['first_name']); ?>" />
            </p>

            <p>
                <label for="last_name">Last (Family) Name <span class="required">*</span></label>
                <input type="text" id="last_name" name="araa_last_name" value="<?php esc_attr_e($data['last_name']); ?>" />
            </p>

            <p>
                <label for="email">Email address <span class="required">*</span></label>
                <input type="email" id="email" name="araa_email" value="<?php esc_attr_e($data['email']); ?>" />
            </p>

            <p>
                <label for="phone">Phone number</label>
                <input type="tel" id="phone" name="araa_phone" value="<?php esc_attr_e($data['phone']); ?>" />
            </p>

            <h3>Postal Address</h3>

            <p>
                <label for="country">Country</label>
                <select id="country" name="araa_country">
                    <?php foreach ($araa_countries as $code => $name) : ?>
                        <option value="<?php esc_attr_e($code) ?>" <?php if ($data['country'] == $code) : ?>selected="selected"<?php endif; ?>>
                            <?php esc_html_e($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p>
                <label for="address-1">Address <span class="required">*</span></label>
                <input type="text" id="address-1" name="araa_address_1" value="<?php esc_attr_e($data['address_1']); ?>" />
                <input type="text" id="address-2" name="araa_address_2" value="<?php esc_attr_e($data['address_2']); ?>" />
            </p>

            <p>
                <label for="suburb">Suburb <span class="required">*</span></label>
                <input type="text" id="suburb" name="araa_suburb" value="<?php esc_attr_e($data['suburb']); ?>" />
            </p>

            <p id="state-au-container">
                <label for="state-au">State <span class="required">*</span></label>
                <select id="state-au" name="araa_state_au">
                    <option></option>
                    <?php foreach ($araa_au_states as $code => $name) : ?>
                        <option value="<?php esc_attr_e($code) ?>" <?php if ($data['state_au'] == $code) : ?>selected="selected"<?php endif; ?>>
                            <?php esc_html_e($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p id="state-container">
                <label for="state">State <span class="required">*</span></label>
                <input type="text" id="state" name="araa_state" value="<?php esc_attr_e($data['state']); ?>" />
            </p>

            <p>
                <label for="postcode">Postcode <span class="required">*</span></label>
                <input type="text" id="postcode" name="araa_postcode" value="<?php esc_attr_e($data['postcode']); ?>" />
            </p>

            <h3>Mailing List</h3>

            <p>
                The ARAA uses the <a href="<?php echo esc_url($listUrl); ?>" target="_blank">
                <?php esc_html_e($listName); ?></a> mailing list for communications. This list
                is for members of the ARAA to inform other members about activities in the field of
                robotics and automation in Australia and New Zealand. Mail is expected about subjects
                such as conferences, trade shows, job openings, student and post-doc positions, and
                significant news in the field.
            </p>

            <p>
                Your registration will be submitted for approval by the secretary. Once this is done,
                you will receive a notification and be subscribed to the mailing list. You will be
                sent an email asking you to confirm your registration before you will receive emails.
            </p>

            <p>
                <label for="password">Mailing list password</label>
                <input type="password" id="password" name="araa_password" value="<?php esc_attr_e($data['password']); ?>" />
                <label for="password" class="secondary">
                    If you do not enter a password, one will be generated and sent to you. Please
                    note this password is not securely transmitted, so it is recommended to use a
                    throwaway password.
                </label>
            </p>

            <p>
                <input type="submit" value="Register" />
            </p>
        </form>
    <?php endif; ?>
</div>
