![Packagist Version](https://img.shields.io/packagist/v/ianhobbs/slider-block)
![Kirby 5](https://img.shields.io/badge/Kirby-5-black)
![License MIT](https://img.shields.io/badge/license-MIT-green)

# Kirby Slider Block

A [Kirby CMS](https://getkirby.com). layout block plugin that renders a full-featured [Swiper 14](https://swiperjs.com/) carousel with a Panel editor, responsive WebP images, lazy loading, and LQIP blur-up placeholders.

Swiper ships pre-bundled with the plugin — no CDN, no npm, and no build step required to use it. No site config required either: images are handled entirely by the plugin.

**Current release:** 2.0.0 · **Requires:** Kirby 5 · PHP 8.3+

> **2.0.0 renamed the package**; the 1.x line receives no further releases. Upgrading is a
> one-line Composer change — see [Upgrading from 1.x](#upgrading-from-1x).

---

## Installation

### Via Composer (recommended)

```bash
composer require ianhobbs/slider-block
```

This installs to `site/plugins/slider-block/` — not `vendor/` — via
[`getkirby/composer-installer`](https://github.com/getkirby/composer-installer), so Kirby
auto-loads it.

### Manual

Clone into your site's `site/plugins/` directory:

```bash
git clone https://github.com/ianhobbs/slider-block site/plugins/slider-block
```

---

## Upgrading from 1.x

2.0.0 renamed the package. Swap it in your `composer.json` — the 1.x package receives no
further releases:

```bash
composer remove ianhobbs/kirby-slider-block
composer require ianhobbs/slider-block
```

Then delete the old `site/plugins/` folder it left behind. Everything a 1.x site has already
**saved** is read under both names, so nothing else has to change for the block to keep
rendering:

| Renamed | 1.x name | 2.x name |
|---|---|---|
| Block type | `swiper` | `slider` |
| File blueprint | `swiper-image` | `slider-image` |
| Named srcsets | `swiper-horiz` / `swiper-vert` | `slider-horiz` / `slider-vert` |
| LQIP presets | `swiper-lqip-horiz` / `-vert` | `slider-lqip-horiz` / `-vert` |
| Content keys | `slider_height` / `slider_height_unit` | `height` / `height_unit` |
| Model class | `IanHobbs\Swiper\SwiperBlock` | `IanHobbs\Slider\SliderBlock` |
| Model method | `sliderHeight()` | `fixedHeight()` |

Those fallbacks are **not permanent** — they go when the 1.x line is retired, so move your
content and preset names over when convenient.

### What does not fall back

**Option keys.** The option prefix follows the package name, so anything you set under the 1.x
prefix in `site/config/config.php` must move to `ianhobbs.slider-block.*`. Keys left under the
old prefix are ignored and the option silently returns to its default.

**Asset URLs.** Plugin assets are served from `/media/plugins/{plugin-id}/`, so they now live
at `/media/plugins/ianhobbs/slider-block/`. The block resolves its own asset URLs, so this only
matters if you hardcoded the old path in a template — see
[Manual asset loading](#manual-asset-loading) for the way that survives a rename.

**CSS class names and custom properties.** `.swiper-block`, `.swiper-slide-caption`,
`.swiper-slide__img`, `--swiper-block-fixed-height` and the rest are now `.slider-block`,
`.slider-slide-caption`, `.slider-slide__img`, `--slider-block-fixed-height`. A site with its
own overrides for those has to rename its selectors. Swiper's own classes — `.swiper`,
`.swiper-slide`, `.swiper-wrapper`, `.swiper-button-*`, `.swiper-pagination`, and every
`--swiper-*` property that is not `--swiper-block-*` — belong to the library and are unchanged.

The bundled JavaScript does still initialise 1.x markup (`.swiper-block` /
`data-swiper-config`), and `window.initSwiperBlocks` still works, for sites running a forked
copy of the old snippet.

---

## Zero-config setup

No template changes needed. When a page contains a Slider block, the snippet automatically injects Swiper and the plugin CSS **once per page load** — the first block to render claims the injection, so a page with several Slider blocks down it still loads Swiper once. Everything is self-contained.

The whole frontend is **two same-origin files** — `assets/dist/slider-block.css` and `assets/dist/slider-block.js` — each bundling Swiper 14.1.0 (MIT) with the block's own code. No CDN dependency, and **nothing to allow in a Content-Security-Policy**. See [Content-Security-Policy](#content-security-policy).

Only the Swiper modules the block can actually use are compiled in; the rest (cube/flip/cards effects, thumbs, zoom, parallax, scrollbar, grid, virtual, hash and history navigation) are dropped at build time — roughly a quarter off both the JavaScript and the CSS, gzipped.

### Browser support

Swiper 14 targets the last couple of years of evergreen browsers: **Chrome/Edge 110+,
Safari 16.4+ (iOS 16.4+), Firefox 110+**. Sites that still need older browsers should stay on
the 1.4.x releases, which bundle Swiper 12.

If you prefer to control asset placement (e.g. move them to `<head>` for performance), see [Manual asset loading](#manual-asset-loading) below.

---

## Usage in the Panel

Add the `slider` block type to any blocks or layout field in your blueprint:

```yaml
fields:
  content:
    type: layout
    fieldsets:
      - slider
```

> **One Slider block per layout row.** Several **down** a page are fine and fully
> independent; two in the *same* row are not — the second is skipped. See
> [Using the block in a layout field](#using-the-block-in-a-layout-field).

The block editor opens with **5 tabs** covering all configuration options:

### Tab 1 — Slides (per-slide settings)

| Field | Description |
|---|---|
| Image | Single image — min. 1920 px wide recommended. JPG, PNG or WebP |
| Caption Heading | Slide title |
| Subtext / Caption | Optional body text |
| CTA Link + Label | Optional call-to-action button |
| Content Position | Left / Centre / Right |
| Vertical Position | Top / Middle / Bottom — see [Caption colour, placement & type](#caption-colour-placement--type) |
| Caption Colour | Colour picker (with alpha) for this slide's heading, subtext and CTA. Empty inherits the page |

Uploads use the plugin's `slider-image` file blueprint, which adds an **Alt text** field. Alt text falls back to the slide heading when left empty.

### Tab 2 — Layout

| Field | Default | Description |
|---|---|---|
| Column Width | Auto | How much of the layout row the block fills. Auto reads the real column. See [Column-aware image sizes](#column-aware-image-sizes) |
| Fixed Height | 0 (auto) | Explicit container height — `0` means each slide keeps its image's own ratio. See [Fixed height & avoiding collapse](#fixed-height--avoiding-collapse) |
| Height Unit | px | Unit for Fixed Height — px / vh / svh |
| Separate Mobile Height | Off | Reveals the mobile height field. Shown only when Height Unit is `px` |
| Mobile Fixed Height | 0 | Height in px below 768px viewport width. `0` falls back to Fixed Height |
| Slide Direction | Horizontal | Scroll direction — Horizontal or Vertical |
| Slides Visible | 1 | 1 / 2 / 3 / 4 / Auto (by width) |
| Advance Per Click | 1 | Slides to jump per navigation action |
| Gap Between Slides | 0 px | Spacing between slides |
| Centre Active Slide | Off | Keeps the active slide centred |
| Starting Slide | 0 | Zero-based index of the first visible slide |
| Caption Font | `font-sans` | Font family for the whole caption — a Tailwind class name. See [Caption colour, placement & type](#caption-colour-placement--type) |
| Heading Size | `text-sm` | Caption heading size, shared by every slide — a Tailwind class name |
| Subtext Size | `text-lg` | Caption subtext size, shared by every slide |

### Tab 3 — Animation

| Field | Default | Description |
|---|---|---|
| Transition Effect | Slide | Slide / Fade / Creative (zoom) / Coverflow |
| Transition Speed | 600 ms | 100–3000 ms |
| Easing | Smooth | Shape of the slide transition — Smooth / Gentle / Swiper default / Linear |
| Loop | On | Infinite loop |
| Autoplay | Off | Auto-advances slides |
| Autoplay Delay | 4000 ms | Delay between slides (when Autoplay is On) |
| Pause on Hover | On | Pauses autoplay on mouse enter (when Autoplay is On) |
| Free Mode | Off | Slides move freely without snapping |
| Free Mode Momentum | On | Momentum-based deceleration (when Free Mode is On) |

Fade and Creative effects require **Slides Visible = 1**.

### Tab 4 — Controls

| Field | Default | Description |
|---|---|---|
| Arrow Buttons | Visible | Previous / Next navigation arrows |
| Pagination | Visible | Pagination indicator |
| Pagination Style | Bullets | Bullets / Fraction (2/5) / Progress Bar |
| Dynamic Bullets | On | Active bullet enlarges relative to neighbours |
| Keyboard Navigation | On | Arrow keys navigate slides when in viewport |
| Mousewheel Control | Off | Scroll wheel advances slides |

### Tab 5 — Touch & Input

| Field | Default | Description |
|---|---|---|
| Grab Cursor | On | Shows a hand cursor when dragging on desktop |
| Touch on Desktop | On | Allows mouse drag to simulate touch |
| Swipe Threshold | 5 px | Minimum drag distance to register a swipe |
| Long Swipes | On | Long swipe gestures advance slides |
| Edge Resistance | On | Drag resistance at the first and last slide |

---

## How images are handled

**No `thumbs` configuration is required.** Earlier versions asked you to copy named
`thumbs.presets` / `thumbs.srcsets` into `site/config/config.php`. That is no longer the
case — the block builds every thumb inline, so it works on a stock Kirby install.

In **auto** height mode images are **never cropped**. Each slide keeps its source image's own
aspect ratio: the snippet reads the image's real dimensions and sets them as an inline
`aspect-ratio` on the slide's `<figure>`, so a portrait and a landscape image can sit in the
same block without either being re-framed. Set a **Fixed Height** (or a custom fixed image
height) and the box stops following the image — the image then fills that box and is
centre-cropped on whichever axis overflows.

What the block generates per slide:

| Purpose | Output |
|---|---|
| `<source type="image/avif">` | Width-only variants at 640 / 900 / 1200 / 1600 / 1920 px, quality 65 |
| `<img srcset>` | The same widths in **WebP**, quality 80 |
| `<img src>` fallback | The middle rung of the WebP ladder |
| LQIP placeholder | 48 px wide, blurred, quality 30, WebP |

Each slide is a `<picture>`. Source order is preference order — the browser takes the first
`<source>` whose type it can decode and never looks at the rest — so AVIF precedes WebP, and
WebP rides on the `<img>` itself rather than as a `<source>` that would shadow it.

**Every ladder is capped at the master image's real width.** Kirby writes each `srcset`
descriptor from the array key verbatim and never measures the thumb it just made, while the
darkroom refuses to upscale. So on a 1000 px master an uncapped ladder would emit `1200w`,
`1600w` and `1920w` URLs all serving the same 1000 px file — three extra thumbs, and three
claims that can only push the browser towards a heavier candidate than it needs. The block
instead re-labels the first oversized step with the master's true width and drops the rest.

The `<img src>` is a **middle** rung, not the top one. That attribute is what a client
ignoring `srcset` downloads, so pointing it at the 1920 px step served the heaviest file on
the page to the least capable reader. The middle step is already on the ladder, so it costs
no extra thumb.

Further behaviour worth knowing:

- The `sizes` attribute is computed per block from the **layout column** the block sits in,
  plus **Slides Visible** (`slidesPerView`) and **Gap Between Slides** (`spaceBetween`), so the
  browser downloads an image matched to the slot it actually fills — not the whole viewport.
  See [Column-aware image sizes](#column-aware-image-sizes).
- The sharp image is `object-fit: cover`, so it always fills the slide box — full width and
  the full designated height — with no letterbox bars; the overflowing axis is centre-cropped.
  The blurred LQIP sits behind it, also `object-fit: cover`, so the placeholder and the final
  image are framed identically through the fade-in.
- The first slide loads with `loading="eager"` / `decoding="sync"` / `fetchpriority="high"`;
  every later slide is `lazy` / `async` / `auto`. **The LQIP placeholder is deferred with it** —
  it is a request of its own, so an eager placeholder per slide meant one offscreen image per
  slide even when the sharp image was already deferred. In `loop` mode Swiper clones slides at
  runtime; the clones inherit whatever attributes their originals carry.

Thumbs are generated on demand by Kirby's media manager and cached under `/media`.

---

## Using the block in a layout field

The block's normal home is a **layout field**, in a column of some fraction width. Two things
follow from that.

### One Slider block per layout row

**Only the first Slider block in a layout row renders.** A second one in the same row — in
another column, or stacked in the same column — is skipped, leaving an HTML comment in its
place. With `debug` on it also renders a visible note on the page, so the block doesn't just
silently vanish while you're building.

Side-by-side blocks compete for the same drag and keyboard gestures, and each ends up in a
column too narrow to show its imagery. Put each one in **a row of its own**; as many rows
down a page as you like, all fully independent.

### Column-aware image sizes

The `sizes` hint tells the browser how wide the image will actually be, so a block in a
one-third column doesn't download a full-viewport image. The block finds its own layout column
and sizes accordingly:

| Column | Slides Visible | `sizes` |
| --- | --- | --- |
| Full row | 1 | `100vw` |
| Full row | 3, 16 px gap | `calc((100vw - 32px) / 3)` |
| 1/2 | 1 | `(max-width: 768px) 100vw, 50vw` |
| 1/3 | 2 | `(max-width: 768px) calc(100vw / 2), calc(33.3333vw / 2)` |

Part-width columns emit **two candidates**, because layout rows stack on small screens: the
full-width size below the breakpoint, the column fraction above it. The breakpoint defaults to
`768px` — set it to whatever your layout CSS actually uses:

```php
return [
    'ianhobbs.slider-block.stackBreakpoint' => '60rem',
];
```

Write plugin options with the **dotted key**, as above. Kirby stores plugin option defaults
under their flat dotted name, and the nested form (`'ianhobbs' => ['slider-block' => …]`)
never reaches them — it is silently ignored, defaults intact. This applies to every option
below.

**Column Width** (Layout tab) overrides the detection. Leave it on **Auto** unless the block
sits inside a wrapper of your own that is narrower than its column — Auto can only see the
column, not your CSS. It affects the `sizes` hint only, never the rendered width: the block is
always `width: 100%` of whatever contains it.

Outside a layout field — in a plain `blocks` field, say — there is no column to detect and the
block assumes a full-width row.

---

## Caption colour, placement & type

**Colour is per slide, type is per block.** Each slide sits over a different image and needs
its own text colour; the type scale should stay consistent down the block, so it is set once.

- **Caption Colour** (Slides tab) — a Panel colour picker with alpha. The value is written as
  an inline `color` on the caption wrapper, and the heading, subtext and CTA all inherit it.
  Left empty, nothing is emitted and the caption inherits your page's text colour. Only hex
  and `rgb()` / `hsl()` values are accepted; anything else in the content file is dropped.
- The caption area carries **`1rem` of padding** (border-box), so text never sits flush against
  the slide edge and the padding stays inside whatever layout column the block is dropped into.
- **Content Position** + **Vertical Position** (Slides tab) — horizontal and vertical placement,
  giving nine zones. Vertical placement only bites when the caption has room to move inside —
  i.e. when **Fixed Height** is set and the caption overlays the media. In auto height the
  caption sits below the image in normal flow, so Top / Middle / Bottom look the same.

### Font sizes and families are Tailwind class names

**Heading Size** and **Subtext Size** (Layout tab) emit the Tailwind utility of the same name
onto the element, and **Caption Font** emits one onto the caption wrapper, so the heading,
subtext and CTA all share a single face:

```html
<div class="slider-slide-caption slider-slide-caption--center slider-slide-caption--middle font-sans">
  <p class="slider-slide-heading text-sm">…</p>
  <p class="slider-slide-subtext text-lg">…</p>
</div>
```

On a Tailwind site those classes are already yours — Tailwind styles them, and the plugin
stays out of the way. 
If you are not using TW **Tailwind is not required.** `slider-block.css` ships a fallback table
covering the same scale at Tailwind's own values:

```css
:where(.slider-slide-caption .text-sm) { font-size: 0.875rem; line-height: 1.25rem; }

```

The `:where()` wrapper gives those rules **zero specificity**, so a real Tailwind utility — or
any rule of your own targeting `.slider-slide-heading` — always wins, whatever order the
stylesheets load in. The fallback only applies when nothing else has an opinion.

#### Overriding the caption fonts

The four font classes resolve a custom property before falling back to a stock stack, so you
can point them at your own faces without writing a selector or fighting specificity:

```css
:root {
  --slider-block-font-sans:  "Your Sans Face", system-ui, sans-serif;
  --slider-block-font-body:  "Your Body Face", Georgia, serif;
  --slider-block-font-serif: "Your Serif", Georgia, serif;
  --slider-block-font-mono:  "Your Mono", ui-monospace, monospace;
}
```

`font-body` has no Tailwind stock definition — it is the conventional name for a theme's body
face. Its fallback is `inherit`, so a site that defines neither the utility nor the custom
property simply keeps the page's own font.

If you use Tailwind with a content scan, the class names come from this plugin's PHP rather
than your own templates, so add the plugin to your `content` / `@source` paths (or safelist
`text-xs` through `text-3xl` plus `font-sans` / `font-body` / `font-serif` / `font-mono`) to
stop them being purged.

---

## Options

Every option goes in `site/config/config.php` under its **dotted key** — see the note under
[Column-aware image sizes](#column-aware-image-sizes) for why the nested form does not work.

| Option | Default | What it does |
|---|---|---|
| `injectAssets` | `true` | Inject the CSS/JS tags at render time. See [Manual asset loading](#manual-asset-loading). |
| `stackBreakpoint` | `'768px'` | Where your layout rows stack, for the `sizes` hint. |
| `formats` | `['avif' => true, 'webp' => true]` | Formats offered per slide, in `<source>` preference order. |
| `fallbackFormat` | `'webp'` | The format on the `<img>` itself. |
| `srcsets` | `[]` | Native mode: point a format at a `thumbs.srcsets` ladder you already define. |
| `fullWidthSizes` | `null` | Replaces `sizes` for full-span blocks. |

### A tuned site, in two lines

Defaults work with no config at all. The two options worth setting on a site that already has
its own thumb ladders and a content container narrower than the viewport:

```php
return [
    'ianhobbs.slider-block.srcsets' => ['avif' => 'avif', 'webp' => 'webp'],
    'ianhobbs.slider-block.fullWidthSizes'
        => '(min-width: 780px) calc(816px + (100vw - 816px) * 0.3), calc(100vw - 2.5rem)',
];
```

The first points each format at a `thumbs.srcsets` ladder you already define — one width ramp
on the site instead of two. The second corrects the `sizes` hint for full-span blocks, which
otherwise assume `100vw`.

**Both values are site-specific — read them, don't copy them.** The `srcsets` values are the
names of *your* ladders. The `fullWidthSizes` string above is derived from one particular
layout: an 816px content measure (a 48rem measure plus two 24px gutters) in a container that
reaches 30% of the remaining distance to the viewport edge above 780px, and is a plain framed
child below it. Measure your own box in the browser and write the equivalent. Getting it wrong
in the *narrow* direction is cheap; overstating it buys a rung too high on every slide, which
is the whole cost the option exists to avoid.

Both are explained in full below: [Reusing ladders you already have](#reusing-ladders-you-already-have)
and [A `sizes` the plugin cannot work out](#a-sizes-the-plugin-cannot-work-out).

### Turning AVIF off

```php
return [
    'ianhobbs.slider-block.formats' => ['avif' => false],
];
```

`formats` is a **map**, not a list, and deliberately so. Kirby merges plugin option defaults
with your config through `A::merge`, which merges associative arrays by key but *appends*
numeric lists — a list-valued option could only ever be added to, never reduced. For the same
reason `fallbackFormat` is named rather than inferred from position: adding a format to the
map would otherwise silently promote it to the `<img>`, which is the one place the most
widely decodable format belongs.

### Reusing ladders you already have

In native mode, rather than emitting its own widths the block can read ladders the site
already defines — one width ramp across the whole site instead of two:

```php
return [
    'thumbs' => [
        'srcsets' => [
            'avif' => [ /* '400w' => ['width' => 400, 'format' => 'avif', 'quality' => 75], … */ ],
            'webp' => [ /* … */ ],
        ],
    ],
    'ianhobbs.slider-block.srcsets' => ['avif' => 'avif', 'webp' => 'webp'],
];
```

Whatever ladder is used, the block still caps it at each master's real width.

In **fixed-ratio** modes the block reads the cropped per-orientation srcsets
`slider-horiz` / `slider-vert`. Define `slider-horiz-avif` and `slider-vert-avif` and it picks
them up with no option at all; leave them undefined and only the fallback format resolves, to
the plain named srcset, producing a single-format `<img>`.

### A `sizes` the plugin cannot work out

The block assumes a full-span block is `100vw` wide. If your layout container is narrower than
the viewport, tell it so — the string replaces the whole attribute:

```php
return [
    'ianhobbs.slider-block.fullWidthSizes'
        => '(min-width: 780px) calc(816px + (100vw - 816px) * 0.3), calc(100vw - 2.5rem)',
];
```

Verbatim, with **Slides Visible** *not* divided into it — a media-condition list cannot go
inside `calc()`, so if you show several slides at once, account for that in the string
yourself. Part-width columns are unaffected: their `sizes` is already derived from the column
fraction.

---

## Manual asset loading

By default the snippet injects the asset tags at the point the block is rendered in the page body. For performance-sensitive sites you may want to place them in `<head>` instead. Add this to your head snippet:

```php
<link rel="stylesheet" href="<?= $kirby->plugin('ianhobbs/slider-block')->asset('dist/slider-block.css')->url() ?>">
```

And before `</body>`:

```php
<script src="<?= $kirby->plugin('ianhobbs/slider-block')->asset('dist/slider-block.js')->url() ?>" defer></script>
```

Then suppress auto-injection in `site/config/config.php`:

```php
return [
    'ianhobbs.slider-block.injectAssets' => false,
];
```

### Easing

Swiper has **no JavaScript easing option** — the slide transition is an ordinary CSS
transition on `.swiper-wrapper`, and Swiper 14 exposes a custom property for its timing
function. The **Easing** field sets that property on the block, which cascades down:

| Option | Timing function | |
|---|---|---|
| Smooth | `cubic-bezier(0.22, 1, 0.36, 1)` | Default. Decelerates hard into place |
| Gentle | `cubic-bezier(0.65, 0, 0.35, 1)` | Symmetric ease-in-out |
| Swiper default | *(none emitted)* | Swiper's own `ease` — stops more abruptly |
| Linear | `linear` | Constant speed |

Easing shapes the motion; **Transition Speed** sets its duration. If a slide still feels
abrupt on Smooth, raise the speed — the curve can only distribute the time it's given.

To use a curve that isn't offered, override the property yourself — it's Swiper's own, so
nothing in this plugin needs to know:

```css
.slider-block {
  --swiper-wrapper-transition-timing-function: cubic-bezier(0.87, 0, 0.13, 1);
}
```

---

## Content-Security-Policy

The plugin is designed to pass a strict CSP with **no extra hosts and no policy changes**.
Both files it loads — Swiper included, since it's compiled in — come from `/media/plugins/`,
which `'self'` already covers.

Two details are worth knowing if you use a strict-CSP plugin such as
[`akibeo/kirby-csp`](https://github.com/wdebusschere/kirby-csp), whose defaults are:

```
script-src 'self' 'nonce-{nonce}' 'strict-dynamic' https: 'unsafe-inline'
style-src  'self' 'unsafe-inline'
```

**Scripts get a nonce.** When `cspNonce()` exists, the injected `<script>` tags carry
`nonce="…"` automatically. This is required even though the files are same-origin:
`'strict-dynamic'` tells the browser to ignore host expressions *including* `'self'`, so a
nonce is the only thing that trusts a script under that policy. Sites without a CSP plugin
get no nonce attribute and are unaffected.

**Stylesheets can't use one.** There is no nonce or `'strict-dynamic'` in `style-src`, so an
off-origin stylesheet has nothing to fall back on — which is why loading Swiper's CSS from a
CDN reported violations, and why it is bundled locally now. Inline `style` attributes (the
block emits a few, carrying its height and caption colour) are covered by the
`'unsafe-inline'` that policy already includes.

---

## Fixed height & avoiding collapse

Swiper containers have **no intrinsic height** — a block whose slides have nothing to give
them height collapses to zero. The block handles that in two ways, both applied automatically
to the `.slider-block` parent `<div>` via an inline CSS custom property (no template or
layout-class changes required):

- **Auto** (`Fixed Height = 0`, the default) — each slide's height comes from its **image**,
  at the image's own aspect ratio. Because this is image-driven, a **text-only or empty slide
  has no height and collapses.**
- **Fixed Height** — sets an **explicit height on the container** (in `px`, `vh`, or `svh`),
  decoupled from the images. Use it for text-only slides, mixed-content blocks, or
  fixed-height heroes. When set, the media fills the slide box and the caption overlays it.

> You don't need to add custom classes to your layout field to give the block a height — set
> **Fixed Height** in the Layout tab instead. The plugin's CSS reads the value from the parent
> `.slider-block` element, so per-block height control lives entirely in the Panel.

### A different height on phones

A pixel height chosen for a desktop hero is usually far too tall on a phone. Switch
**Separate Mobile Height** on and set **Mobile Fixed Height**; below `768px` the block uses
that instead.

Both fields appear only when **Height Unit** is `px` — `vh` and `svh` already track the
viewport, so an override would just fight them. The toggle exists because Kirby's `when:`
conditions match a field's *value*, not whether it has one, so revealing the number field
needs an explicit trigger. All three must hold (px unit, toggle on, non-zero number) before
anything is emitted.

This is a CSS media query, not a Swiper setting. Swiper's `breakpoints` only accepts layout
parameters — `slidesPerView`, `slidesPerGroup`, `spaceBetween`, `grid.rows` — and its
standalone `height` option is documented as making the instance non-responsive. Nothing runs
on resize to maintain it.

`768px` matches the default `stackBreakpoint`. If you've moved that option, realign the rule
at your own breakpoint — the custom property is readable either way:

```css
@media (max-width: 60rem) {
  .slider-block[style*="--slider-block-mobile-height"] {
    height: var(--slider-block-mobile-height);
  }
}
```

---

## For plugin developers

The repo root is the plugin only. The whole development environment — Kirby install and Pest
suite — lives in `dev/`, so the plugin's own `composer.json` carries no dev dependencies or
scripts:

```bash
cd dev
composer install     # Kirby + Pest, into dev/
composer test        # 53 tests
```

See [DEVELOPMENT.md](DEVELOPMENT.md) for the full guide, including how to add an optional
visual test site inside `dev/`.

Node is only required if you are modifying the **Panel editor** component
(`src/SliderBlock.vue`). The frontend JS (`assets/js/slider-block.js`) is plain hand-authored
JavaScript — no build step needed.

### Panel build

```bash
npm install
npm run build      # compiles src/ → index.js + index.css (plugin root)
npm run dev        # kirbyup dev server with hot reload
```

The bundle is built with [kirbyup](https://github.com/johannschopplich/kirbyup), Kirby's official Panel plugin bundler — it compiles against the Panel's own Vue 2.7 runtime, and Kirby auto-loads `index.js` / `index.css` from the plugin root.

### Deployment

Commit the compiled Panel output alongside your source changes:

```bash
git add index.js index.css assets/
git commit -m "Build: update panel and assets"
```

Kirby will never see `.vue` files on the live server — only the pre-compiled `index.js`.
Development material (`dev/`, `src/`, npm config) is stripped from the released package via
`.gitattributes` `export-ignore`, so `composer require` pulls only the runtime files.

---

## License

MIT © [Ian Hobbs](https://ianhobbsmedia.com.au)

This plugin is published as free, open-source software under the MIT License. It is provided "as is", without warranty of any kind, express or implied. You are free to use, copy, modify, merge, publish, and distribute it in personal and commercial projects. If you find it useful in a commercial context, please consider supporting Kirby by purchasing a license at [getkirby.com/buy](https://getkirby.com/buy) — this plugin is not affiliated with or endorsed by the Kirby team.

## Credit 

The final credit should go to the original developer of the source code.
Copyright (c) 2019 Vladimir Kharlampidi - thanks for a well considered slider kit.!!

Ian Hobbs