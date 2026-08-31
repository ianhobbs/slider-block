<?php

@include_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/classes/SliderBlock.php';

/**
 * Kirby Slider Block Plugin
 *
 * A custom layout block that renders a full-featured Swiper 14 carousel
 * with server-side image cropping via Kirby thumb presets.
 */

use Kirby\Cms\App as Kirby;
use IanHobbs\Slider\SliderBlock;

// 1.x namespace and class name. Templates and snippets in the wild type-hint
// `\IanHobbs\Swiper\SwiperBlock`, so the old name keeps resolving to the new
// class. Remove with the 1.x line.
class_alias(SliderBlock::class, 'IanHobbs\\Swiper\\SwiperBlock');

Kirby::plugin('ianhobbs/slider-block', [

    'version' => '2.0.0',

    // Set injectAssets to false to skip the automatic asset injection entirely
    // (e.g. when Swiper is already bundled/loaded globally by the site).
    //
    // The block ships one stylesheet and one script in assets/dist, each
    // bundling Swiper (v14.1.0, MIT) with the block's own code. Both are served
    // from the site's own origin, so a strict CSP needs no extra hosts.
    'options' => [
        'injectAssets' => true,

        // Image formats offered per slide, in <source> preference order. The
        // last enabled one is the fallback: it supplies <img srcset> and
        // <img src>. Set ['avif' => false] to emit a plain webp <img>, as
        // releases before 1.7.0 did.
        //
        // A map rather than a list because Kirby merges plugin defaults with
        // site config by key for associative arrays but APPENDS numeric lists,
        // so a list option could never be reduced by a site — only added to.
        'formats' => SliderBlock::DEFAULT_FORMATS,

        // The format on the <img> itself, read by clients that understand
        // neither <picture> nor srcset — so the widely decodable one, not the
        // most efficient. Ignored when switched off in `formats`, in which case
        // the last enabled format takes over.
        'fallbackFormat' => 'webp',

        // Native mode only: point a format at a srcset the site already
        // defines, e.g. ['avif' => 'avif'] reads thumbs.srcsets.avif. Keeps one
        // width ramp on the site instead of two. Empty = built-in ladder.
        // (Fixed-ratio modes need no option — they pick up
        // thumbs.srcsets.slider-horiz-avif and -vert-avif when those exist.)
        'srcsets' => [],

        // Replaces the whole `sizes` attribute for full-span blocks. The plugin
        // assumes a full-span block is 100vw wide; a site whose container is
        // narrower knows better and can say so. Verbatim — slidesPerView is not
        // divided into it, because a media-condition list cannot go in calc().
        'fullWidthSizes' => null,
    ],

    'icons' => [
        'slider-block' => file_get_contents(__DIR__ . '/assets/icons/slider-block.svg'),
    ],

    // ── Thumb presets ───────────────────────────────────────────────────────
    // NOTE: Kirby has no `thumbs` plugin-extension type, so a plugin cannot
    // register global thumb presets / srcsets. The snippet relies on named
    // srcsets (`slider-horiz`, `slider-vert`) and LQIP presets
    // (`slider-lqip-horiz`, `slider-lqip-vert`) that the consuming site must
    // define in its own site/config/config.php. See the README for the exact
    // block to copy.

    // ── Blueprints ───────────────────────────────────────────────────────────
    // Explicit registration required — auto-discovery fails when the plugin
    // is symlinked from outside site/plugins/ (rootRelativePath becomes null).
    //
    // `blocks/swiper` and `files/swiper-image` are the 1.x names, aliased to the
    // same files. A layout field on an existing site still lists `swiper` in its
    // fieldsets, and every uploaded slide image still carries
    // `template: swiper-image` in its content file — neither resolves without
    // these. Remove with the 1.x line.
    'blueprints' => [
        'blocks/slider'       => __DIR__ . '/blueprints/blocks/slider.yml',
        'files/slider-image'  => __DIR__ . '/blueprints/files/slider-image.yml',
        'blocks/swiper'       => __DIR__ . '/blueprints/blocks/slider.yml',
        'files/swiper-image'  => __DIR__ . '/blueprints/files/slider-image.yml',
    ],

    // ── Snippets ─────────────────────────────────────────────────────────────
    // Same reason — must be explicit when symlinked outside site/plugins/.
    'snippets' => [
        'blocks/slider' => __DIR__ . '/snippets/blocks/slider.php',
        'blocks/swiper' => __DIR__ . '/snippets/blocks/slider.php',
    ],

    // ── Block models ────────────────────────────────────────────────────────
    // Custom model centralises the block's computed values (JS config, aspect
    // CSS, thumb presets, responsive `sizes`) so the snippet stays markup-only.
    // Both types map to the one model — see SliderBlock::BLOCK_TYPES for why
    // `swiper` is still registered.
    'blockModels' => [
        'slider' => SliderBlock::class,
        'swiper' => SliderBlock::class,
    ],
]);
