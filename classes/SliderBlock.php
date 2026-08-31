<?php

namespace IanHobbs\Slider;

use Kirby\Cms\Block;
use Kirby\Cms\Structure;

/**
 * Slider block model
 *
 * Centralises everything the snippet would otherwise compute inline: the Swiper
 * JS config object, the aspect-ratio CSS, the per-orientation thumb presets and
 * the responsive `sizes` hint. Keeping it here makes the values unit-testable in
 * isolation and leaves the snippet focused on markup.
 *
 * Field accessors (e.g. `$this->effect()`) resolve to content fields via the
 * parent's `__call`; the methods below are named distinctly so they never shadow
 * a field of the same name.
 */
class SliderBlock extends Block
{
    /** Plugin id, and the prefix every option of ours hangs off. */
    public const PLUGIN_ID = 'ianhobbs.slider-block';

    /**
     * The 1.x option prefix. 2.0.0 renamed the package from
     * `ianhobbs/kirby-slider-block` to `ianhobbs/slider-block`, which moves the
     * option namespace with it — so a site's config.php keeps working unedited,
     * pluginOption() reads the old prefix too. Remove with the 1.x line.
     */
    public const LEGACY_PLUGIN_ID = 'ianhobbs.kirby-slider-block';

    /**
     * Block types this model answers to. 2.0.0 renamed the type from `swiper`
     * to `slider`, and the type is written into every saved content file, so
     * `swiper` stays registered as an alias — dropping it would blank every
     * block an existing site has already saved. Remove with the 1.x line.
     *
     * @var list<string>
     */
    public const BLOCK_TYPES = ['slider', 'swiper'];

    /**
     * One option read, old prefix first.
     *
     * Old-first, not new-first, because the new prefix carries this plugin's
     * registered defaults (see index.php) and so never reads back as null — a
     * site setting only the old key could never win a new-first test. The old
     * prefix registers no defaults, so a non-null value there is always
     * something the site asked for explicitly. A site that sets both should
     * drop the old key; until it does, the old key wins.
     */
    public static function pluginOption(string $key, mixed $default = null): mixed
    {
        $legacy = kirby()->option(self::LEGACY_PLUGIN_ID . '.' . $key);

        if ($legacy !== null) {
            return $legacy;
        }

        return kirby()->option(self::PLUGIN_ID . '.' . $key, $default);
    }

    /**
     * Font sizes offered in the Panel, as Tailwind utility class names. The class
     * is emitted verbatim so a Tailwind site styles it natively; slider-block.css
     * carries a zero-specificity `:where()` fallback for the same names, so the
     * scale also works on sites without Tailwind. Anything outside this list is
     * ignored — the value lands in a class attribute.
     */
    public const HEADING_SIZES = [
        'text-xs', 'text-sm', 'text-base', 'text-lg',
    ];

    public const SUBTEXT_SIZES = [
        'text-sm', 'text-base', 'text-lg', 'text-xl', 'text-2xl', 'text-3xl',
    ];

    /**
     * Caption font families offered in the Panel, as Tailwind utility class
     * names. Same contract as the sizes: a Tailwind site resolves them from its
     * own theme, and slider-block.css defines each one as a `:where()` fallback
     * pointing at a `--slider-block-font-*` custom property, so a site without
     * Tailwind can restyle the whole set by setting those properties.
     *
     * `font-body` is not a Tailwind stock utility — it is the conventional name
     * for a theme's body face, and is included because most Kirby themes define
     * one. On a site that doesn't, the CSS fallback keeps it rendering.
     */
    public const CAPTION_FONTS = [
        'font-sans', 'font-body', 'font-serif', 'font-mono',
    ];

    /**
     * Slide transition easing, as Panel value => CSS timing function.
     *
     * Swiper has no JS easing option — the slide transition is a plain CSS
     * transition on `.swiper-wrapper`, and Swiper 14 exposes
     * `--swiper-wrapper-transition-timing-function` for it, defaulting to
     * `initial` (i.e. `ease`). Setting that property on the block cascades to
     * the wrapper, so this is styling, not scripting.
     *
     * The value lands in a style attribute, so it is never taken from content:
     * the stored key selects one of these, and anything unrecognised falls back.
     */
    public const EASINGS = [
        'smooth'  => 'cubic-bezier(0.22, 1, 0.36, 1)',
        'gentle'  => 'cubic-bezier(0.65, 0, 0.35, 1)',
        'ease'    => 'ease',
        'linear'  => 'linear',
    ];

    /**
     * Whether the shared CDN + plugin assets have already been injected in this
     * request. Lives here, not as a `static` inside the snippet: Kirby renders
     * snippets through `F::loadIsolated()`, which `include`s the file afresh
     * every time, so a snippet-local static resets between blocks and a page
     * with several Slider blocks emitted the CDN tags once per block.
     */
    protected static bool $assetsInjected = false;

    /**
     * Claim the one asset injection for this request. Returns true exactly once
     * — the first caller renders the tags, every later block gets false.
     */
    public static function claimAssets(): bool
    {
        if (static::$assetsInjected === true) {
            return false;
        }

        return static::$assetsInjected = true;
    }

    /** Forget the claim — a fresh page/request. Used by the test suite. */
    public static function forgetAssets(): void
    {
        static::$assetsInjected = false;
    }

    /** The slides as a structure collection. */
    public function slidesData(): Structure
    {
        return $this->slides()->toStructure();
    }

    /** Transition effect value (slide|fade|creative|coverflow). */
    public function effectName(): string
    {
        return $this->effect()->or('slide')->value();
    }

    /** Whether the arrow navigation should render. */
    public function showNav(): bool
    {
        return $this->show_navigation()->isTrue();
    }

    /** Whether the pagination should render. */
    public function showPagination(): bool
    {
        return $this->show_pagination()->isTrue();
    }

    // ── Images ───────────────────────────────────────────────────────────────

    /** Orientation value (horizontal|vertical). */
    protected function orientationValue(): string
    {
        return $this->orientation()->or('horizontal')->value();
    }

    /**
     * Native mode: crop nothing — each image keeps its own ratio. Thumbs are
     * built width-only (inline, no site config) and the figure takes the
     * image's real aspect-ratio (set per slide in the snippet).
     */
    public function isNative(): bool
    {
        return $this->aspect_ratio()->or('native')->value() === 'native';
    }

    /**
     * Widths of the built-in native ladder, used when the site names no srcset
     * of its own. The old set jumped 900 -> 1400 (1.56x), and that gap straddled
     * the width a full-width block actually renders at, so most viewports
     * rounded up to 1400 and paid for the whole jump.
     */
    public const NATIVE_WIDTHS = [640, 900, 1200, 1600, 1920];

    /** Encoder quality per format for the built-in ladder. */
    public const FORMAT_QUALITY = ['avif' => 65, 'webp' => 80];

    /** Formats offered by default, in `<source>` preference order. */
    public const DEFAULT_FORMATS = ['avif' => true, 'webp' => true];

    /**
     * Image formats to offer, in `<source>` preference order — the browser takes
     * the first type it can decode and never looks further, so avif must precede
     * webp. The LAST enabled entry is the fallback: it supplies the plain
     * `<img srcset>` and the `<img src>`, which is what a client that
     * understands neither `<picture>` nor srcset ends up reading.
     *
     * The option is a MAP (`['avif' => false]`), not a list, on purpose: Kirby
     * merges plugin option defaults with the site's config through `A::merge`,
     * which merges associative arrays by key but *appends* numeric lists. A
     * list-valued option could therefore only ever be added to, never reduced.
     * A plain list is still accepted here, for a site that sets the option
     * before any default exists.
     *
     * @return list<string>
     */
    public function formats(): array
    {
        $option  = self::pluginOption('formats', self::DEFAULT_FORMATS);
        $enabled = [];

        foreach ((array) $option as $key => $value) {
            // List entry: the value is the format name. `??=` so a later
            // `['avif' => false]` from the site's config still wins over the
            // default list it was merged into.
            if (is_int($key)) {
                if (is_string($value)) {
                    $enabled[$value] ??= true;
                }
                continue;
            }

            $enabled[$key] = (bool) $value;
        }

        $formats = array_values(array_keys(array_filter($enabled)));

        return $formats ?: ['webp'];
    }

    /**
     * The format backing `<img src>` and `<img srcset>` — what a client that
     * reads neither `<picture>` nor srcset ends up with, so it must be the most
     * widely decodable format on offer, not the most efficient one.
     *
     * Named rather than inferred from position: option maps merge by key and
     * append unknown keys, so adding a format would otherwise silently promote
     * it to the fallback. Falls back to the last offered format when the named
     * one has been switched off.
     */
    public function fallbackFormat(): string
    {
        $formats = $this->formats();
        $named   = self::pluginOption('fallbackFormat', 'webp');

        if (is_string($named) && in_array($named, $formats, true)) {
            return $named;
        }

        return end($formats);
    }

    /**
     * Cap a ladder's descriptors at the master image's real width.
     *
     * Kirby writes each srcset descriptor from the array key verbatim
     * (`FileModifications::srcset()`) and never measures the thumb it just made,
     * while the darkroom refuses to upscale. So a 1200px master run through a
     * ladder ending at 1920 emits `...1400w, ...1920w` pointing at the same
     * 1200px file — two claims that can only make the browser pick a heavier
     * candidate than it needs, and two extra thumbs to generate and store.
     *
     * Steps at or under the master pass through. The first step past it is
     * re-labelled with the master's true width; the rest are dropped, since each
     * would be that same non-upscaled file under a wider claim.
     */
    public static function capLadder(array $steps, int $masterWidth): array
    {
        $out    = [];
        $capped = false;

        foreach ($steps as $options) {
            $width = is_array($options) ? ($options['width'] ?? null) : $options;

            if ($width === null) {
                continue;
            }

            if ($width <= $masterWidth) {
                $out[$width . 'w'] = $options;
                continue;
            }

            if ($capped === false) {
                // ['width' => $masterWidth] first: PHP's + keeps the LEFT
                // operand's keys, so the width is overridden while the format
                // and quality of the step being replaced survive.
                $out[$masterWidth . 'w'] = ['width' => $masterWidth] + (array) $options;
                $capped = true;
            }
        }

        return $out;
    }

    /**
     * The uncapped ladder for one format, as a Kirby srcset array.
     *
     * Native mode: a site can point each format at a ladder it already defines,
     * through the `srcsets` option (`['avif' => 'avif']` reads
     * `thumbs.srcsets.avif`), which keeps one ramp on the site instead of two.
     * Otherwise the built-in widths above are emitted in that format.
     *
     * Fixed-ratio modes: the cropped per-orientation srcset from site config.
     * `slider-horiz-avif` is used when the site defines it, so multiple formats
     * need no plugin option at all; when it doesn't, only the fallback format
     * resolves — to the plain `slider-horiz` — and the markup is what it was
     * before 1.7.0.
     */
    /**
     * The site-config name of a named srcset or thumb preset, old name included.
     *
     * 2.0.0 renamed the four names a consuming site defines — `swiper-horiz`,
     * `swiper-vert`, `swiper-lqip-horiz`, `swiper-lqip-vert` — to their
     * `slider-` equivalents. Those live in the SITE's config.php, not here, so
     * the rename cannot be applied for them: a site upgrading the plugin would
     * otherwise lose every cropped thumb until it edited its own config. The new
     * name wins whenever the site defines anything under it; the old one is used
     * only when nothing new is defined and something old is.
     *
     * @param list<string> $suffixes format suffixes to look for (`slider-horiz-avif`)
     */
    protected static function resolveThumbName(
        string $group,
        string $new,
        string $legacy,
        array $suffixes = []
    ): string {
        foreach ([$new, $legacy] as $candidate) {
            $names = [$candidate];

            foreach ($suffixes as $suffix) {
                $names[] = $candidate . '-' . $suffix;
            }

            foreach ($names as $name) {
                if (kirby()->option($group . '.' . $name) !== null) {
                    return $candidate;
                }
            }
        }

        return $new;
    }

    /** The per-orientation srcset base name from site config. */
    protected function srcsetBase(): string
    {
        return $this->orientationValue() === 'vertical'
            ? self::resolveThumbName('thumbs.srcsets', 'slider-vert', 'swiper-vert', $this->formats())
            : self::resolveThumbName('thumbs.srcsets', 'slider-horiz', 'swiper-horiz', $this->formats());
    }

    protected function ladder(string $format): array
    {
        if ($this->isNative()) {
            $named = self::pluginOption('srcsets', [])[$format] ?? null;

            if ($named !== null) {
                return kirby()->option('thumbs.srcsets.' . $named, []);
            }

            $ladder = [];

            foreach (self::NATIVE_WIDTHS as $width) {
                $ladder[$width . 'w'] = [
                    'width'   => $width,
                    'format'  => $format,
                    'quality' => self::FORMAT_QUALITY[$format] ?? 80,
                ];
            }

            return $ladder;
        }

        $base   = $this->srcsetBase();
        $ladder = kirby()->option('thumbs.srcsets.' . $base . '-' . $format, []);

        if ($ladder !== []) {
            return $ladder;
        }

        // No per-format ladder: only the fallback format may claim the plain
        // named srcset, or every <source> would serve the same file and the
        // browser would stop at the first one.
        return $format === $this->fallbackFormat()
            ? kirby()->option('thumbs.srcsets.' . $base, [])
            : [];
    }

    /**
     * `<source>` ladders keyed by MIME type, in preference order, each capped to
     * the master's width. The fallback format is excluded — it rides on the
     * `<img>` itself, so a `<source>` for it would only duplicate the fallback
     * and shadow it.
     *
     * @return array<string, array>
     */
    public function srcsets(int $masterWidth): array
    {
        $out      = [];
        $fallback = $this->fallbackFormat();

        foreach ($this->formats() as $format) {
            if ($format === $fallback) {
                continue;
            }

            $ladder = $this->ladder($format);

            if ($ladder !== []) {
                $out['image/' . $format] = self::capLadder($ladder, $masterWidth);
            }
        }

        return $out;
    }

    /**
     * The plain `<img srcset>` — the fallback format's ladder, capped. Null when
     * the site has defined no srcset for a fixed-ratio mode.
     */
    public function fallbackSrcset(int $masterWidth): array|null
    {
        $ladder = $this->ladder($this->fallbackFormat());

        return $ladder === [] ? null : self::capLadder($ladder, $masterWidth);
    }

    /**
     * The fixed-ratio srcset name, or the native ladder as an array.
     *
     * @deprecated 1.7.0 Use srcsets() and fallbackSrcset(), which cap the
     *             descriptors at the master width and carry every offered
     *             format. Kept so snippets written against 1.6 keep rendering.
     */
    public function srcsetName(): string|array
    {
        if ($this->isNative()) {
            return $this->ladder($this->fallbackFormat());
        }

        return $this->srcsetBase();
    }

    /** LQIP placeholder. Native uses a width-only tiny (keeps the image ratio). */
    public function lqipPreset(): string|array
    {
        if ($this->isNative()) {
            return ['width' => 48, 'blur' => 4, 'quality' => 30, 'format' => 'webp'];
        }
        return $this->orientationValue() === 'vertical'
            ? self::resolveThumbName('thumbs.presets', 'slider-lqip-vert', 'swiper-lqip-vert')
            : self::resolveThumbName('thumbs.presets', 'slider-lqip-horiz', 'swiper-lqip-horiz');
    }

    /**
     * The `<img src>` thumb — a MIDDLE rung of the fallback ladder, not the top.
     *
     * That attribute is what a client ignoring srcset downloads, so pointing it
     * at the 1920 step (as every release before 1.7.0 did) served the heaviest
     * file on the page to the least capable reader. The middle step is already
     * on the ladder, so it costs no extra thumb.
     *
     * Null when the site has defined no srcset for a fixed-ratio mode.
     */
    public function baseThumbOptions(int $masterWidth = PHP_INT_MAX): array|null
    {
        $ladder = $this->fallbackSrcset($masterWidth);

        if ($ladder === null || $ladder === []) {
            return null;
        }

        $steps = array_values($ladder);
        $step  = $steps[intdiv(count($steps) - 1, 2)];

        return is_array($step) ? $step : ['width' => $step];
    }

    /**
     * Responsive `sizes` hint. Each slide's rendered width is deterministic from
     * the column the block sits in (twelfths of the row) plus slidesPerView and
     * spaceBetween, so we tell the browser the real fraction it fills instead of
     * assuming the full viewport. 'auto' slidesPerView assumes 3 across — a safe
     * floor of at least three images per block width.
     *
     * A block in a part-width column emits two candidates, because layout rows
     * stack on small screens: the stacked (full-width) size below the breakpoint,
     * the column-fraction size above it.
     */
    public function imgSizes(): string
    {
        $spvRaw = $this->slides_per_view()->or('1')->value();
        $n      = $spvRaw === 'auto' ? 3 : max(1, (int) $spvRaw);
        $gap    = (int) $this->space_between()->or(0)->value();

        // The width of one slide, given the block's own width as a CSS length.
        $perSlide = function (string $blockWidth) use ($n, $gap): string {
            if ($n <= 1) {
                return $blockWidth;
            }
            if ($gap > 0) {
                return sprintf('calc((%s - %dpx) / %d)', $blockWidth, ($n - 1) * $gap, $n);
            }
            return sprintf('calc(%s / %d)', $blockWidth, $n);
        };

        $span = $this->columnSpan();

        if ($span >= 12) {
            // How wide a full-span block actually is depends on the host
            // layout's container, which the plugin cannot see. A site that has
            // measured its own can hand over a finished `sizes` string.
            $full = self::pluginOption('fullWidthSizes');

            return is_string($full) && $full !== '' ? $full : $perSlide('100vw');
        }

        // Below the host layout's stacking breakpoint the column is full width.
        $breakpoint = self::pluginOption('stackBreakpoint', '768px');
        $columnVw   = rtrim(rtrim(number_format($span / 12 * 100, 4, '.', ''), '0'), '.') . 'vw';

        return sprintf(
            '(max-width: %s) %s, %s',
            $breakpoint,
            $perSlide('100vw'),
            $perSlide($columnVw)
        );
    }

    // ── Layout column context ────────────────────────────────────────────────

    /**
     * Per-request cache of the layout scan, keyed by the parent field. Scanning
     * walks every layout, column and block, so it runs once per field however
     * many Slider blocks that field holds.
     *
     * @var array<int, array<string, array{span: int, firstInRow: bool}>>
     */
    protected static array $layoutScans = [];

    /**
     * Where this block sits in its layout field: the width of its column in
     * twelfths, and whether it is the first Slider block in its layout row.
     *
     * Kirby hands the block snippet nothing but the block, so we find the block
     * again from the other end — through the field it came from. A block in a
     * plain `blocks` field (or one built ad hoc, with no field) has no column,
     * and falls back to a full-width row of its own.
     *
     * @return array{span: int, firstInRow: bool}
     */
    protected function layoutContext(): array
    {
        $default = ['span' => 12, 'firstInRow' => true];
        $field   = $this->field();

        if ($field === null) {
            return $default;
        }

        $key = spl_object_id($field);

        if (isset(static::$layoutScans[$key]) === false) {
            $scan = [];

            foreach ($field->toLayouts() as $layout) {
                $rowHasSlider = false;

                foreach ($layout->columns() as $column) {
                    foreach ($column->blocks() as $block) {
                        if (in_array($block->type(), self::BLOCK_TYPES, true) === false) {
                            continue;
                        }

                        $scan[$block->id()] = [
                            'span'       => $column->span(),
                            'firstInRow' => $rowHasSlider === false,
                        ];

                        $rowHasSlider = true;
                    }
                }
            }

            static::$layoutScans[$key] = $scan;
        }

        return static::$layoutScans[$key][$this->id()] ?? $default;
    }

    /**
     * How much of the layout row the block fills, in twelfths. The Column Width
     * field wins when the editor sets it; `auto` (the default) reads the real
     * column from the layout field.
     */
    public function columnSpan(): int
    {
        $manual = $this->column_width()->or('auto')->value();

        if ($manual !== 'auto' && is_numeric($manual)) {
            return max(1, min(12, (int) $manual));
        }

        return $this->layoutContext()['span'];
    }

    /**
     * Whether this block is a second (or third…) Slider block in the same layout
     * row. One per row is the supported arrangement — several side by side fight
     * over the same drag/keyboard gestures and force each into a column too
     * narrow for the imagery. Blocks stacked down a page are fine and unlimited.
     */
    public function isRowDuplicate(): bool
    {
        return $this->layoutContext()['firstInRow'] === false;
    }

    /** Drop the cached layout scan — a fresh page/request. Used by the tests. */
    public static function forgetLayoutScans(): void
    {
        static::$layoutScans = [];
    }

    // ── Layout / wrapper ─────────────────────────────────────────────────────

    /** Inline style carrying the aspect-ratio (or fixed height) CSS custom props. */
    public function aspectStyle(): string
    {
        $styles = [];

        // Explicit container height (Fixed Height field) — decouples the block
        // height from the image box so text-only / empty slides don't collapse.
        // Emitted first; the stylesheet lets it take precedence over the ratio.
        if ($height = $this->fixedHeight()) {
            $styles[] = "--slider-block-fixed-height:{$height}";

            // Small-screen override, applied by a media query in the stylesheet.
            // Swiper itself can't carry this: `breakpoints` only accepts layout
            // params (slidesPerView, spaceBetween, grid.rows), and the `height`
            // option is documented as making Swiper non-responsive. So it stays
            // CSS — which also means no JS runs on resize to maintain it.
            if ($mobile = $this->mobileHeight()) {
                $styles[] = "--slider-block-mobile-height:{$mobile}";
            }
        }

        $aspect = $this->aspect_ratio()->or('native')->value();

        if ($aspect === 'custom') {
            $custom    = (int) $this->custom_height()->or(600)->value();
            $styles[]  = "--slider-block-height:{$custom}px";
        }
        // 'native' (default): no container ratio — each figure sets its own
        // aspect-ratio from the image's real dimensions (see the snippet).
        // Named ratios (16:9 etc) removed from the design plan.

        return implode(';', $styles);
    }

    /**
     * The raw Fixed Height content value, reading the v1 key when the v2 one is
     * absent. 2.0.0 renamed `slider_height` / `slider_height_unit` to `height` /
     * `height_unit`; content written by 1.x still carries the old keys, and a
     * content migration would mean rewriting every page on the site, so both are
     * read here instead. Drop the fallback once 1.x content is gone.
     *
     * @param 'height'|'height_unit' $key
     */
    protected function heightValue(string $key): ?string
    {
        $legacy = $key === 'height' ? 'slider_height' : 'slider_height_unit';
        $field  = $this->content()->get($key);

        if ($field->isEmpty()) {
            $field = $this->content()->get($legacy);
        }

        return $field->isEmpty() ? null : $field->value();
    }

    /**
     * Explicit container height as a CSS length (e.g. "600px", "80vh"), or null
     * when the editor left it at auto (0). Drives an explicit height on the
     * parent <div> so the block never collapses when slides have no image.
     */
    public function fixedHeight(): ?string
    {
        $value = (int) $this->heightValue('height');
        if ($value <= 0) {
            return null;
        }

        $unit = $this->heightValue('height_unit') ?? 'px';
        $unit = in_array($unit, ['px', 'vh', 'svh', 'dvh'], true) ? $unit : 'px';

        return "{$value}{$unit}";
    }

    /**
     * @deprecated 2.0.0 Renamed to fixedHeight(), matching the
     *             `--slider-block-fixed-height` custom property it feeds. Kept
     *             so snippets written against 1.x keep rendering.
     */
    public function sliderHeight(): ?string
    {
        return $this->fixedHeight();
    }

    /**
     * Small-screen height override as a CSS length, or null when it doesn't
     * apply. Three things must all hold, and the toggle alone isn't enough —
     * an editor can leave it on after clearing the number, or after switching
     * the unit away from px:
     *
     *  - the unit is px. vh/svh already track the viewport, so an override
     *    would only fight the thing that makes them work.
     *  - the toggle is on. Kirby's `when:` can't test "the field has a value",
     *    so the Panel needs an explicit trigger to reveal the number field.
     *  - the number is above zero. Zero means "no override" and falls through
     *    to the desktop height, matching how Fixed Height itself reads 0.
     *
     * Callers should only emit this when fixedHeight() is set — with no fixed
     * height there is nothing to override.
     */
    public function mobileHeight(): ?string
    {
        if (($this->heightValue('height_unit') ?? 'px') !== 'px') {
            return null;
        }

        if ($this->mobile_height_enable()->isTrue() === false) {
            return null;
        }

        $value = (int) $this->mobile_height()->or(0)->value();

        return $value > 0 ? "{$value}px" : null;
    }

    /**
     * The slide transition easing as a CSS custom property declaration, or ''
     * when the editor chose Swiper's own default and there is nothing to say.
     */
    public function easingStyle(): string
    {
        $key = $this->easing()->or('smooth')->value();

        if (isset(self::EASINGS[$key]) === false) {
            $key = 'smooth';
        }

        if ($key === 'ease') {
            return '';
        }

        return '--swiper-wrapper-transition-timing-function:' . self::EASINGS[$key];
    }

    /**
     * Everything the block needs on its inline `style` attribute: the height
     * custom properties plus the easing. Kept separate from aspectStyle() so
     * that method stays about layout only.
     */
    public function blockStyle(): string
    {
        return implode(';', array_filter([
            $this->aspectStyle(),
            $this->easingStyle(),
        ]));
    }

    /** Stable unique id so multiple blocks on a page don't collide. */
    public function uid(): string
    {
        return 'sb-' . substr(md5($this->id()), 0, 8);
    }

    // ── Caption typography & colour ──────────────────────────────────────────

    /** Tailwind size class for slide headings, validated against HEADING_SIZES. */
    public function headingSizeClass(): string
    {
        $value = $this->heading_size()->or('text-sm')->value();
        return in_array($value, self::HEADING_SIZES, true) ? $value : 'text-sm';
    }

    /** Tailwind size class for slide subtext, validated against SUBTEXT_SIZES. */
    public function subtextSizeClass(): string
    {
        $value = $this->subtext_size()->or('text-lg')->value();
        return in_array($value, self::SUBTEXT_SIZES, true) ? $value : 'text-lg';
    }

    /**
     * Tailwind font-family class for the whole caption. Emitted on the caption
     * wrapper, so the heading, subtext and CTA all inherit one face.
     */
    public function captionFontClass(): string
    {
        $value = $this->caption_font()->or('font-sans')->value();
        return in_array($value, self::CAPTION_FONTS, true) ? $value : 'font-sans';
    }

    /**
     * Vertical caption placement for a slide (top|middle|bottom). Only visible
     * when Fixed Height is set — that's the mode where the caption overlays a
     * full-height slide box; in auto mode it sits below the image in flow.
     */
    public static function verticalPosition(?string $value): string
    {
        return in_array($value, ['top', 'middle', 'bottom'], true) ? $value : 'middle';
    }

    /**
     * A per-slide caption colour, safe to interpolate into a style attribute, or
     * null when unset/unrecognised. The Panel's colour field stores hex, rgb() or
     * hsl(); anything else is dropped rather than passed through, so a hand-edited
     * content file can't inject arbitrary CSS (or close the attribute).
     */
    public static function cssColor(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $hex       = '/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i';
        $functional = '/^(?:rgb|hsl)a?\(\s*[0-9a-z%.,\s\/+-]+\)$/i';

        if (preg_match($hex, $value) || preg_match($functional, $value)) {
            return $value;
        }

        return null;
    }

    // ── JS config ────────────────────────────────────────────────────────────

    /**
     * The Swiper options object as a JSON string for the `data-slider-config`
     * attribute. Keys map 1:1 to Swiper options; context-prefixed keys
     * (autoplay*, pagination*, free*) are expanded into sub-objects by
     * slider-block.js.
     */
    public function jsConfig(): string
    {
        $spvRaw        = $this->slides_per_view()->or('1')->value();
        $slidesPerView = $spvRaw === 'auto' ? 'auto' : (int) $spvRaw;
        $effect        = $this->effectName();

        $config = [
            // Layout
            // Whether the block carries an explicit container height. Drives
            // autoHeight in slider-block.js: with a fixed height the slides are
            // height:100% of the wrapper, so measuring the active slide to size
            // that same wrapper is circular and oscillates on every observer
            // tick. Fixed height ⇒ autoHeight off.
            'fixedHeight'    => $this->fixedHeight() !== null,
            'direction'      => $this->direction()->or('horizontal')->value(),
            'slidesPerView'  => $slidesPerView,
            'slidesPerGroup' => (int) $this->slides_per_group()->or(1)->value(),
            'spaceBetween'   => (int) $this->space_between()->or(0)->value(),
            'centeredSlides' => $this->centered_slides()->isTrue(),
            'initialSlide'   => (int) $this->initial_slide()->or(0)->value(),

            // Animation
            'effect'               => $effect,
            'speed'                => (int) $this->speed()->or(600)->value(),
            'loop'                 => $this->loop()->isTrue(),
            'autoplay'             => $this->autoplay()->isTrue(),
            'autoplayDelay'        => (int) $this->autoplay_delay()->or(4000)->value(),
            'autoplayPauseOnHover' => $this->autoplay_pause_on_hover()->isTrue(),
            'freeMode'             => $this->free_mode()->isTrue(),
            'freeModeMomentum'     => $this->free_mode_momentum()->isTrue(),

            // Controls
            'showNavigation'  => $this->show_navigation()->isTrue(),
            'showPagination'  => $this->show_pagination()->isTrue(),
            'paginationType'  => $this->pagination_type()->or('bullets')->value(),
            'dynamicBullets'  => $this->dynamic_bullets()->isTrue(),
            'keyboardControl' => $this->keyboard_control()->isTrue(),
            'mousewheel'      => $this->mousewheel()->isTrue(),

            // Touch
            'grabCursor'    => $this->grab_cursor()->isTrue(),
            'simulateTouch' => $this->simulate_touch()->isTrue(),
            'threshold'     => (int) $this->threshold()->or(5)->value(),
            'longSwipes'    => $this->long_swipes()->isTrue(),
            'resistance'    => $this->resistance()->isTrue(),
        ];

        // Creative effect config — only included when needed
        if ($effect === 'creative') {
            $config['creativeEffect'] = [
                'prev' => ['shadow' => true, 'translate' => [0, 0, -400]],
                'next' => ['translate' => ['100%', 0, 0]],
            ];
        }

        return json_encode($config, JSON_HEX_QUOT | JSON_HEX_TAG);
    }
}
