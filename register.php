<?php if ($registered) : ?>
<?php else : ?>
    <form method="post" action="<?php the_permalink(); ?>">
    </form>
<?php endif; ?>
