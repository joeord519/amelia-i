<?php
/* Template Name: Booking Form */
get_header(); ?>

<div class="booking-form-container">
    <?php 
        // Include the booking form you created (from the booking-form.php file)
        include get_template_directory() . '/booking-form.php'; 
    ?>
</div>

<?php get_footer(); ?>
