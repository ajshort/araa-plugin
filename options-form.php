<div class="wrap">
    <h1>Australian Robotics and Automation Association</h1>

    <form method="post" action="options.php">
        <?php settings_fields('araa_list_group'); ?>
        <?php settings_fields('araa_membership_group'); ?>
        <?php do_settings_sections('araa'); ?>
        <?php submit_button(); ?>
    </form>
</div>
