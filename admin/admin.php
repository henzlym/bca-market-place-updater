<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <form action="options.php" method="post">
        <?php
        $this->do_settings();
        submit_button('Save Settings'); // output save settings button
        ?>
    </form>
</div>