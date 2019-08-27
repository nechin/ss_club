<?php
$options = thrive_get_theme_options();
?>

<div class="<?php echo function_exists('_thrive_get_main_wrapper_class') ? _thrive_get_main_wrapper_class($options) : ''; ?>">
    <section class="<?php echo function_exists('_thrive_get_main_section_class') ? _thrive_get_main_section_class($options) : ''; ?>">

        <?php global $post, $wp_query;

        $saved_query = $wp_query;
        $saved_post = $post;

        $current_page = max( 1, get_query_var( 'paged' ) );

        $args = array(
            'post_type' => 'club_post',
            'paged' => $current_page
        );

        /* Если есть категории клуба, то выводим записи, не принадлежащие этой категории
        $terms = get_terms(array('club_category'));
        if ($terms) {
            foreach ($terms as $term) {
                $args['tax_query'][] = array(
                    'taxonomy'  => 'club_category',
                    'field'     => 'slug',
                    'terms'     => $term->slug,
                    'operator'  => 'NOT IN'
                );
            }
        }*/

        // Если записи скрыты, то юзеру нужно показать только общие записи и записи, доступ к которым он купил
        if (1 == get_option('club_notaccess_display', 1)) {
            // Если юзер не авторизован, то не показываем записи с доступом
            // "Зарегистрированным посетителям" и "Клиентам имеющим доступ:"
            if (!is_user_logged_in()) {
                $args['meta_query'][] = array(
                    'key'     => 'open_access',
                    'value'   => 1,
                    'compare' => '==',
                );
                $args['meta_query'][] = array(
                    'key' => 'close_access_list',
                    'value' => 0,
                    'compare' => '==',
                );
            }
            else {
                // Иначе не показываем только записи с доступом "Клиентам имеющим доступ:"
                // кроме тех, доступ к которым он купил
                $exclude_ids = $this->get_access_denied_post_ids();
                if (!empty($exclude_ids)) {
                    $args['post__not_in'] = $exclude_ids;
                }

                $args['meta_query'][] = array(
                    'key' => 'close_access_list',
                    'value' => 0,
                    'compare' => '==',
                );
            }
        }
        else {
            $args['meta_query'][] = array(
                'key' => 'close_access_list',
                'value' => 0,
                'compare' => '==',
            );
        }

        $wp_query = new WP_Query($args);
        if ($wp_query->have_posts()) {
            while ($wp_query->have_posts()) {
                $wp_query->the_post();
                $my_theme = wp_get_theme();

                if (
                    $my_theme->get('Name') == 'Rise'
                    || $my_theme->get('Name') == 'Storied'
                ) {
                    _thrive_render_post_content_template($options);
                }
                else if (
                    $my_theme->get('Name') == 'Focusblog'
                    || $my_theme->get('Name') == 'Performag'
                ) {
                    get_template_part('content', _thrive_get_post_content_template($options));
                }
                else if ($my_theme->get('Name') == 'Pressive') {
                    _thrive_render_post_content_template( $options );
                }
                else if ($my_theme->get('Name') == 'Voice') {
                    get_template_part( 'content' );
                }
                else {
                    get_template_part('content', get_post_format() );
                }
            }
        } ?>

    </section>
</div>
<div class="clear"></div>

<?php if ( $wp_query->max_num_pages > 1 ): ?>
    <div class="pgn clearfix">
        <?php thrive_pagination(); ?>
    </div>

    <div class="clear"></div>
<?php endif; ?>

<?php
$wp_query = $saved_query;
$post = $saved_post;
?>
