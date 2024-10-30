<div id="lh_rpbt-related_div" class="lh_rpbt-related_div">
<div class="lh_rpbt-related_post_div">
<h3><?php _e('Related', self::return_plugin_namespace()); ?></h3>
<ul class="lh_rpbt-related_post_list">
<?php 
$count = 0;
while ($the_query->have_posts()) { 
$the_query->the_post();

$the_thumbnail_id = apply_filters('lh_rpbt_thumbnail_id_fallback', get_post_thumbnail_id(), get_the_id());

if ( empty( $the_thumbnail_id)) {
     
$the_thumbnail_id = get_option( 'site_icon' );
     
}



?>
<li class="<?php echo 'item-'.$count; ?>">
<a href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link to <?php the_title_attribute(); ?>"><?php echo wp_get_attachment_image( $the_thumbnail_id, 'lh_rpbt_featured' ); ?></a>
 <h4><a href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link to <?php the_title_attribute(); ?>">
    <?php the_title(); ?></a></h4>
    
<?php    if (!empty(trim(apply_filters( 'the_excerpt', get_the_excerpt() )))){ ?>
<div class="summary">
<?php the_excerpt(); ?>
</div>
<?php } ?>
</li>
<?php 
$count++;
} ?>
</ul>
</div>
</div>