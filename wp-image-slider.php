<?php
/*
Plugin Name: WP Image Slider
Description: A standalone responsive image slider with shortcodes and GitHub release updates.
Version: 1.3.0
Author: Kaia Homes
Requires at least: 5.8
Requires PHP: 7.4
Update URI: https://wp-image-slider.invalid
Text Domain: wp-image-slider
*/

defined('ABSPATH') || exit;

define('WP_IMAGE_SLIDER_VERSION', '1.3.0');
define('WP_IMAGE_SLIDER_FILE', __FILE__);
define('WP_IMAGE_SLIDER_URL', plugin_dir_url(__FILE__));

require_once __DIR__ . '/includes/class-wp-image-slider-updater.php';
new WP_Image_Slider_Updater();

class WP_Image_Slider
{
    private static $assets_enqueued = false;

    public function __construct()
    {
        add_shortcode('wp_image_slider', array($this, 'render_shortcode'));
        add_shortcode('image_slider', array($this, 'render_shortcode'));
        add_shortcode('news_slider', array($this, 'render_shortcode'));
        // Keep the original shortcode working for existing pages.
        add_shortcode('kaia_news_slider', array($this, 'render_shortcode'));
    }

    public function render_shortcode($atts)
    {
        $atts = shortcode_atts(
            array(
                'ids' => '',
                'urls' => '',
                'limit' => '',
                'show_buttons' => 'yes',
                'show_arrows' => 'yes',
                'show_slideshow' => 'yes',
                'active_color' => '#e93a8b',
            ),
            $atts,
            'wp_image_slider'
        );

        $has_custom_sources = $this->has_custom_sources($atts);
        $limit = absint($atts['limit']);
        $limit = $limit > 0 ? min($limit, 12) : ($has_custom_sources ? 12 : 8);
        $slides = $this->get_slides($atts, $limit, $has_custom_sources);

        $show_buttons = $this->is_enabled($atts['show_buttons']);
        $show_arrows = $show_buttons && $this->is_enabled($atts['show_arrows']);
        $show_slideshow = $show_buttons && $this->is_enabled($atts['show_slideshow']);
        $active_color = sanitize_hex_color($atts['active_color']);
        $active_color = $active_color ? $active_color : '#e93a8b';

        if (empty($slides)) {
            return '';
        }

        $this->enqueue_assets();

        ob_start();
        ?>
        <section class="kaia-news-slider" data-kaia-news-slider style="<?php echo esc_attr('--kaia-news-slider-active-color: ' . $active_color . ';'); ?>" aria-label="<?php esc_attr_e('Featured news slideshow', 'wp-image-slider'); ?>">
            <div class="kaia-news-slider__stage">
                <div class="kaia-news-slider__track" data-kaia-news-slider-track>
                    <?php foreach ($slides as $index => $slide) : ?>
                        <figure class="kaia-news-slider__slide" data-kaia-news-slider-slide>
                            <img src="<?php echo esc_url($slide['url']); ?>" alt="<?php echo esc_attr($slide['alt']); ?>" loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>">
                        </figure>
                    <?php endforeach; ?>
                </div>

                <?php if (count($slides) > 1 && $show_arrows) : ?>
                    <button class="kaia-news-slider__nav kaia-news-slider__nav--prev" type="button" data-kaia-news-slider-prev aria-label="<?php esc_attr_e('Previous slide', 'wp-image-slider'); ?>">
                        <span aria-hidden="true">&lsaquo;</span>
                    </button>
                    <button class="kaia-news-slider__nav kaia-news-slider__nav--next" type="button" data-kaia-news-slider-next aria-label="<?php esc_attr_e('Next slide', 'wp-image-slider'); ?>">
                        <span aria-hidden="true">&rsaquo;</span>
                    </button>
                <?php endif; ?>

                <?php if ($show_slideshow) : ?>
                    <button class="kaia-news-slider__slideshow" type="button" data-kaia-news-slider-open>
                        <span class="kaia-news-slider__expand" aria-hidden="true"></span>
                        <?php esc_html_e('Tap to view slideshow', 'wp-image-slider'); ?>
                    </button>
                <?php endif; ?>
            </div>

            <div class="kaia-news-slider__footer">
                <p class="kaia-news-slider__counter">
                    <span data-kaia-news-slider-current>1</span> / <?php echo esc_html(count($slides)); ?>
                </p>

                <?php if (count($slides) > 1) : ?>
                    <div class="kaia-news-slider__dots" aria-label="<?php esc_attr_e('Slider pagination', 'wp-image-slider'); ?>">
                        <?php foreach ($slides as $index => $slide) : ?>
                            <button type="button" data-kaia-news-slider-dot="<?php echo esc_attr($index); ?>" aria-label="<?php echo esc_attr(sprintf(__('Go to slide %d', 'wp-image-slider'), $index + 1)); ?>"></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="kaia-news-slider__modal" data-kaia-news-slider-modal aria-hidden="true">
                <button class="kaia-news-slider__modal-close" type="button" data-kaia-news-slider-close aria-label="<?php esc_attr_e('Close slideshow', 'wp-image-slider'); ?>">Close</button>
                <button class="kaia-news-slider__modal-nav kaia-news-slider__modal-nav--prev" type="button" data-kaia-news-slider-modal-prev aria-label="<?php esc_attr_e('Previous slide', 'wp-image-slider'); ?>">
                    <span aria-hidden="true">&lsaquo;</span>
                </button>
                <img src="<?php echo esc_url($slides[0]['url']); ?>" alt="<?php echo esc_attr($slides[0]['alt']); ?>" data-kaia-news-slider-modal-image>
                <button class="kaia-news-slider__modal-nav kaia-news-slider__modal-nav--next" type="button" data-kaia-news-slider-modal-next aria-label="<?php esc_attr_e('Next slide', 'wp-image-slider'); ?>">
                    <span aria-hidden="true">&rsaquo;</span>
                </button>
            </div>
        </section>
        <?php

        return ob_get_clean();
    }

    private function enqueue_assets()
    {
        if (self::$assets_enqueued) {
            return;
        }

        wp_enqueue_style(
            'wp-image-slider',
            WP_IMAGE_SLIDER_URL . 'assets/css/kaia-news-slider.css',
            array(),
            WP_IMAGE_SLIDER_VERSION
        );

        wp_enqueue_script(
            'wp-image-slider',
            WP_IMAGE_SLIDER_URL . 'assets/js/kaia-news-slider.js',
            array(),
            WP_IMAGE_SLIDER_VERSION,
            true
        );

        self::$assets_enqueued = true;
    }

    private function get_slides($atts, $limit, $has_custom_sources)
    {
        $slides = array();
        $ids = $this->parse_csv($atts['ids']);
        $urls = $this->parse_csv($atts['urls']);

        foreach ($ids as $id) {
            $attachment_id = absint($id);
            $url = wp_get_attachment_image_url($attachment_id, 'large');

            if ($url) {
                $slides[] = array(
                    'url' => $url,
                    'alt' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
                );
            }

            if (count($slides) >= $limit) {
                return $slides;
            }
        }

        foreach ($urls as $url) {
            $safe_url = esc_url_raw($url, array('http', 'https'));
            if ($safe_url && filter_var($url, FILTER_VALIDATE_URL)) {
                $slides[] = array(
                    'url' => $safe_url,
                    'alt' => '',
                );
            }

            if (count($slides) >= $limit) {
                return $slides;
            }
        }

        if ($has_custom_sources) {
            return array_slice($slides, 0, $limit);
        }

        if (empty($slides)) {
            $slides = $this->get_recent_post_slides($limit);
        }

        foreach ($this->get_fallback_slides() as $fallback_slide) {
            if (count($slides) >= $limit) {
                break;
            }

            $slides[] = $fallback_slide;
        }

        return array_slice($slides, 0, $limit);
    }

    private function get_recent_post_slides($limit)
    {
        $slides = array();
        $query = new WP_Query(array(
            'post_type' => 'post',
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            'ignore_sticky_posts' => true,
        ));

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();

                if (has_post_thumbnail()) {
                    $thumbnail_id = get_post_thumbnail_id();
                    $slides[] = array(
                        'url' => get_the_post_thumbnail_url(get_the_ID(), 'large'),
                        'alt' => get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true) ?: get_the_title(),
                    );
                }
            }
        }

        wp_reset_postdata();

        return $slides;
    }

    private function get_fallback_slides()
    {
        // Site-specific defaults belong to the site, not this reusable plugin.
        $slides = apply_filters('wp_image_slider_fallback_slides', array());
        $valid_slides = array();
        foreach ((array) $slides as $slide) {
            if (is_array($slide) && ! empty($slide['url']) && esc_url_raw($slide['url'], array('http', 'https'))) {
                $valid_slides[] = array(
                    'url' => esc_url_raw($slide['url'], array('http', 'https')),
                    'alt' => isset($slide['alt']) ? (string) $slide['alt'] : '',
                );
            }
        }
        return $valid_slides;
    }

    private function parse_csv($value)
    {
        if (! is_string($value) || $value === '') {
            return array();
        }

        return array_filter(array_map('trim', explode(',', $value)));
    }

    private function has_custom_sources($atts)
    {
        return (isset($atts['ids']) && trim((string) $atts['ids']) !== '')
            || (isset($atts['urls']) && trim((string) $atts['urls']) !== '');
    }

    private function is_enabled($value)
    {
        return ! in_array(strtolower(trim((string) $value)), array('no', 'false', '0', 'off'), true);
    }
}

new WP_Image_Slider();
