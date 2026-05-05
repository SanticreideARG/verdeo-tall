# Verdeo Filament Skin

Dark navy + verde Verdeo glassmorphism theme for [Filament v3](https://filamentphp.com) PHP dashboards.

## Files

- **`verdeo-filament.css`** — drop-in skin (overrides Filament's Tailwind tokens + chrome)
- **`Dashboard.html`** — live HTML preview using the skin
- **`verdeo-logo-crop.png`** — brand mark

## Install in a Filament v3 project

1. Copy `verdeo-filament.css` to:
   ```
   resources/css/filament/admin/verdeo.css
   ```

2. In your panel's theme entry (`resources/css/filament/admin/theme.css`) import it:
   ```css
   @import '/vendor/filament/filament/resources/css/index.css';
   @import 'verdeo.css';
   ```

3. Register the theme in your `AdminPanelProvider`:
   ```php
   ->viteTheme('resources/css/filament/admin/theme.css')
   ```

4. **Optional — particle background.** Register a render hook to inject the canvas:
   ```php
   use Filament\View\PanelsRenderHook;
   use Illuminate\Support\Facades\Blade;

   FilamentView::registerRenderHook(
     PanelsRenderHook::BODY_START,
     fn (): string => Blade::render('<canvas id="verdeo-bg"></canvas>')
   );
   ```
   Then add the particle JS from `Dashboard.html` to your panel's JS bundle.

5. Run `npm run build` and you're done.

## What's themed

- Sidebar with active-state glow
- Topbar + global search
- Stats widgets with hover lift
- Tables with row hover
- Buttons (primary gradient + ghost)
- Inputs / selects / textareas
- Modals + dropdowns (glass)
- Badges (success / warning / danger / info)
- Pagination, scrollbars, charts

## Brand tokens

```css
--verdeo-navy:    #0b1828;
--verdeo-green:   #3a7d44;
--verdeo-green-lt:#4e9e5a;
--verdeo-gold:    #c8a030;
```

Override any of these in your own CSS to retune the palette without touching the skin.
