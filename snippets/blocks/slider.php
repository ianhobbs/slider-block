<?php
/**
 * Slider Block Snippet
 *
 * Renders a Swiper 14 carousel from a Kirby layout block. All computed values
 * (JS config, aspect CSS, thumb presets, responsive `sizes`) come from the
 * SwiperBlock model — see classes/SwiperBlock.php — so this snippet stays
 * markup-only. CDN scripts and plugin CSS are injected automatically on first
 * use; no manual template changes are required.
 *
 * @var \IanHobbs\Swiper\SwiperBlock $block
 */

// ── Bail if no slides ────────────────────────────────────────────────────────
$slides = $block->slidesData();
if ($slides->count() === 0) return;

// ── One Slider block per layout row ──────────────────────────────────────────
// Side-by-side blocks fight over the same drag and keyboard gestures and leave
// each other too little width for the imagery, so only the first in a row
// renders. Blocks stacked down a page are unaffected.
if ($block->isRowDuplicate()) {
    echo '<!-- kirby-slider-block: skipped — a layout row can hold only one Slider block -->' . "\n";

    if (kirby()->option('debug', false) === true) {
        echo '<p class="swiper-block-warning">Only one Slider block per layout row. '
           . 'Move this one to a row of its own.</p>' . "\n";
    }

    return;
}

// ── Inject CDN + plugin assets once per page ─────────────────────────────────
// The claim lives on the model, not in a static here: Kirby `include`s a snippet
// afresh for every render, so a snippet-local static resets between blocks and a
// page with several Slider blocks repeated the CDN tags once per block.
// Honour the injectAssets option — sites that load Swiper themselves can disable it.
if (kirby()->option('ianhobbs.kirby-slider-block.injectAssets', true) && \IanHobbs\Swiper\SwiperBlock::claimAssets()) {
    $plugin = kirby()->plugin('ianhobbs/kirby-slider-block');

    // Sites running a strict-dynamic CSP (e.g. akibeo/kirby-csp) ignore host
    // allowlists on script-src — only a matching nonce trusts a <script>. Note
    // strict-dynamic drops 'self' too, so the nonce is required even for the
    // same-origin files below, not just for the CDN. function_exists keeps this
    // plugin working without that dependency; sites without a CSP just get no
    // nonce attribute.
    $nonce = function_exists('cspNonce') ? ' nonce="' . cspNonce() . '"' : '';

    // One stylesheet and one script: Swiper and the block's own code are built
    // together into assets/dist (see package.json → build:frontend), carrying
    // only the Swiper modules the block can use. Both come from the site's own
    // origin, so a strict CSP needs no extra hosts — the typical style-src is
    // `'self' 'unsafe-inline'`, with no nonce or strict-dynamic to rescue an
    // off-origin stylesheet the way script-src has for the JS.
    echo '<link rel="stylesheet" href="' . $plugin->asset('dist/swiper-block.css')->url() . '">' . "\n";
    echo '<script src="' . $plugin->asset('dist/swiper-block.js')->url() . '" defer' . $nonce . '></script>' . "\n";
}

// Per-slide image params — resolved once; identical for every slide in the block.
$effect     = $block->effectName();
$lqipPreset = $block->lqipPreset();
$imgSizes   = $block->imgSizes();

// Caption typography — block-level, so every slide shares one scale. Emitted as
// Tailwind class names; swiper-block.css carries a :where() fallback for sites
// that don't run Tailwind (see "Caption typography" there).
$headingSize      = $block->headingSizeClass();
$subtextSize      = $block->subtextSizeClass();
$captionFont      = $block->captionFontClass();
?>

<div
  class="swiper swiper-block<?= $effect !== 'slide' ? ' swiper-block--' . $effect : '' ?>"
  id="<?= $block->uid() ?>"
  style="<?= $block->blockStyle() ?>"
  data-swiper-config="<?= htmlspecialchars($block->jsConfig(), ENT_QUOTES, 'UTF-8') ?>"
  aria-roledescription="carousel"
  aria-label="<?= $slides->count() ?> slides"
>

  <?php if ($block->showNav()) : ?>
  <button class="swiper-button-prev" aria-label="Previous slide"></button>
  <button class="swiper-button-next" aria-label="Next slide"></button>
  <?php endif ?>

  <?php if ($block->showPagination()) : ?>
  <div class="swiper-pagination" role="tablist" aria-label="Slide navigation"></div>
  <?php endif ?>

  <div class="swiper-wrapper">

    <?php $index = 0; foreach ($slides as $slide) :
      $index++;

      // ── Image & thumb variants ────────────────────────────────────────────
      $imageField = $slide->image()->toFiles()->first();

      if ($imageField) {
          // Every ladder is capped to this master's real width — see
          // SwiperBlock::capLadder(). Kirby writes each srcset descriptor from
          // the array key and never measures the thumb it made, so an uncapped
          // ladder promises widths the file cannot deliver.
          $master   = $imageField->width();
          $sources  = $block->srcsets($master);
          $ladder   = $block->fallbackSrcset($master);
          $srcset   = $ladder ? $imageField->srcset($ladder) : null;
          $baseOpts = $block->baseThumbOptions($master);
          $base     = $baseOpts ? $imageField->thumb($baseOpts) : $imageField;
          $lqip     = $imageField->thumb($lqipPreset);
          $altText  = $imageField->alt()->or($slide->heading())->value();
      }

      // Only the first slide is on screen at load; the rest are translated out
      // of the viewport, so they defer. This governs the LQIP as much as the
      // sharp image — an eager placeholder per slide is still a request per
      // slide, which is what Lighthouse counts.
      $isFirst = $index === 1;

      $position     = $slide->content_position()->or('center')->value();
      $positionY    = \IanHobbs\Swiper\SwiperBlock::verticalPosition($slide->content_position_y()->value());
      $captionColor = \IanHobbs\Swiper\SwiperBlock::cssColor($slide->caption_color()->value());
    ?>

    <div
      class="swiper-slide"
      role="group"
      aria-roledescription="slide"
      aria-label="Slide <?= $index ?> of <?= $slides->count() ?>"
    >

      <?php if ($imageField) : ?>
      <figure class="swiper-slide-media" aria-hidden="true"<?php if ($block->isNative()) : ?> style="aspect-ratio:<?= $imageField->width() ?>/<?= $imageField->height() ?>"<?php endif ?>>

        <img
          class="swiper-slide__lqip"
          src="<?= $lqip->url() ?>"
          width="<?= $lqip->width() ?>"
          height="<?= $lqip->height() ?>"
          alt=""
          aria-hidden="true"
          loading="<?= $isFirst ? 'eager' : 'lazy' ?>"
          decoding="async"
        >

        <?php /* Source order IS preference order: the browser takes the first
                 <source> whose type it decodes and never looks at the rest, so
                 the formats are emitted most-wanted first. Each carries its own
                 sizes — a <source> ignores the sizes on the <img> below it. */ ?>
        <picture>
          <?php foreach ($sources as $type => $sourceLadder) : ?>
          <source
            type="<?= $type ?>"
            srcset="<?= $imageField->srcset($sourceLadder) ?>"
            sizes="<?= $imgSizes ?>"
          >
          <?php endforeach ?>
          <img
            class="swiper-slide__img"
            src="<?= $base->url() ?>"
            <?php if ($srcset) : ?>srcset="<?= $srcset ?>"
            sizes="<?= $imgSizes ?>"
            <?php endif ?>width="<?= $base->width() ?>"
            height="<?= $base->height() ?>"
            alt="<?= htmlspecialchars($altText, ENT_QUOTES, 'UTF-8') ?>"
            loading="<?= $isFirst ? 'eager' : 'lazy' ?>"
            decoding="<?= $isFirst ? 'sync' : 'async' ?>"
            fetchpriority="<?= $isFirst ? 'high' : 'auto' ?>"
          >
        </picture>

      </figure>
      <?php endif ?>

      <?php if ($slide->heading()->isNotEmpty() || $slide->subtext()->isNotEmpty() || $slide->link()->isNotEmpty()) : ?>
      <div
        class="swiper-slide-caption swiper-slide-caption--<?= $position ?> swiper-slide-caption--<?= $positionY ?> <?= $captionFont ?>"
        <?php if ($captionColor) : ?>style="color:<?= htmlspecialchars($captionColor, ENT_QUOTES, 'UTF-8') ?>"<?php endif ?>
      >

        <?php if ($slide->heading()->isNotEmpty()) : ?>
        <p class="swiper-slide-heading <?= $headingSize ?>"><?= $slide->heading()->html() ?></p>
        <?php endif ?>
        <?php if ($slide->subtext()->isNotEmpty()) : ?>
        <p class="swiper-slide-subtext <?= $subtextSize ?>"><?= $slide->subtext()->html() ?></p>
        <?php endif ?>
        <?php if ($slide->link()->isNotEmpty()) : ?>
        <a
          class="swiper-slide__cta"
          href="<?= $slide->link()->url() ?>"
        ><?= $slide->link_text()->or('Learn more')->html() ?></a>
        <?php endif ?>

      </div>
      <?php endif ?>

    </div><!-- /.swiper-slide -->

    <?php endforeach ?>
  </div><!-- /.swiper-wrapper -->

</div><!-- /.swiper-block -->
