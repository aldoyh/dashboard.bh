# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Quick Commands

### Development & Build
- **Dev environment setup**: `composer install && npm install && npm run dev`
- **Watch CSS/JS changes**: `npm run watch`
- **Production build**: `npm run prod`
- **Code formatting**: `npm run format` (Prettier)

### Laravel & Database
- **Generate app key**: `php artisan key:generate`
- **Run migrations**: `php artisan migrate --seed`
- **List routes**: `php artisan route:list`
- **Run single command**: `php artisan command:name`
- **Check scheduler**: `php artisan schedule:list`

### Queue & Real-time
- **Test queue worker**: `php artisan queue:work --once`
- **Queue failed jobs**: `php artisan queue:failed`
- **Broadcast test**: Check WebSocket connection in browser console

### Testing & Validation
- **Run PHPUnit tests** (if configured): `php artisan test`
- **Check asset compilation**: Open `/public/css/app.css` and verify it contains Tailwind output

---

## Architecture Overview

This is a **wall-mounted team dashboard** for real-time office display. It combines two architectures:

### 1. Main Dashboard (Laravel + Livewire)
**Purpose**: Display team status, tasks, schedules, metrics on a wall-mounted TV

**Data Flow**:
```
External APIs → Scheduled Commands → TileStore (DB) → Livewire Components → Blade Views → Browser
```

**Key Layers**:

| Layer | Location | Purpose |
|-------|----------|---------|
| **Commands** | `app/Console/Commands/` | Fetch data from APIs on a schedule |
| **Stores** | `app/Tiles/{Tile}Store.php` | Persist tile data to `dashboard_tiles` table |
| **Components** | `app/Livewire/` | React to data changes, pass to views |
| **Views** | `resources/views/components/tiles/` | Render tile HTML with Blade |
| **Routes** | `routes/web.php` | Single entry: `GET /` requires `AccessToken` |

**Critical Pattern - Tile Anatomy**:

Every dashboard widget follows this structure:
1. **Command** (e.g., `FetchGitHubTotalsCommand`) - runs on schedule, fetches fresh data
2. **Store** (e.g., `StatisticsStore`) - wraps `Spatie\Dashboard\Models\Tile` DB model, provides typed getters/setters
3. **Component** (e.g., `StatisticsTileComponent extends Livewire\Component`) - receives position, calls render()
4. **View** (e.g., `statistics-tile.blade.php`) - receives data, renders HTML wrapped in `<x-dashboard-tile>`

Example flow for GitHub stats:
```php
// Command: FetchGitHubTotalsCommand->handle()
$data = $github->getStats(); // API call
StatisticsStore::make()->setGitHubTotals($data); // Persists to DB

// Component: StatisticsTileComponent->render()
return view('components.tiles.statistics-tile', [
    'stats' => StatisticsStore::make()->getGitHubTotals(),
]);

// View: statistics-tile.blade.php
<x-dashboard-tile :position="$position">
    <div>{{ $stats['stars'] }} ⭐</div>
</x-dashboard-tile>
```

### 2. Grid Layout System

Dashboard uses a custom **2D positioning system** (not CSS Grid):

```
Columns: A, B, C, D, E
Rows: 1-20
Format: "a1:a18" means column A, rows 1-18
```

CSS implementation in `resources/css/components/grid.css`:
- Tiles use `position="a1:a18"` attribute in Blade
- Custom Tailwind component `<x-dashboard-tile :position="$position">` calculates grid placement
- Grid based on `grid-column-start`, `grid-row-start`, `grid-row-end`

### 3. Scheduler Integration

**Critical**: Dashboard data is updated via Laravel's task scheduler running **every 1 minute**:

```bash
# Set up cron job (usually in /etc/cron.d or crontab)
* * * * * cd /path/to/dashboard && php artisan schedule:run >> /dev/null 2>&1
```

**Scheduler runs these commands**:
- `FetchBelgianTrainsCommand` - Every minute
- `FetchCalendarEventsCommand` - Every minute
- `FetchSlackStatusCommand` - Every 10 minutes
- `FetchGitHubTotalsCommand` - Every 30 minutes
- `FetchPackagistTotalsCommand` - Hourly
- (Others per configured tiles)

Each command fetches fresh data and stores via `TileStore::make()->set...()`.

### 4. Real-time Updates

**Livewire** provides reactive updates:
- Livewire components poll server or receive WebSocket broadcasts
- When `dashboard_tiles` table updates (via commands), components re-render
- Minimal DOM diffs sent to browser (efficient for wall display)

**Pusher WebSockets** (optional):
- If `PUSHER_*` env vars configured, Livewire broadcasts updates
- Allows real-time push instead of polling

---

## Data Model

### Core Table: `dashboard_tiles`

```sql
CREATE TABLE dashboard_tiles (
  id INTEGER PRIMARY KEY,
  name VARCHAR(255) UNIQUE,
  data JSON,
  created_at TIMESTAMP,
  updated_at TIMESTAMP
);
```

**Tile names follow pattern**: `{tile_type}` or `{tile_type}_{variant}`

Examples:
- `statistics` - GitHub stats (global)
- `member_freek` - Freek's status (per member)
- `fathom_GSENXMLW` - Fathom analytics for Mailcoach site
- `belgian_trains` - Train statuses

**Data column**: JSON blob with tile-specific schema. Access via `TileStore::make('tile_name')->data`.

### Other Tables

- `users` - Auth (usually one user per `.env` `BASIC_AUTH`)
- `coffees` - Coffee tile tracking (custom data)

---

## Configuration

### Environment Variables (`.env`)

**Required**:
```env
APP_NAME=Dashboard
APP_KEY=<generated via php artisan key:generate>
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
BASIC_AUTH_USERNAME=admin
BASIC_AUTH_PASSWORD=password
```

**Optional (per integrated services)**:
```env
GITHUB_TOKEN=                    # GitHub API
SLACK_TOKEN=                     # Slack status
PUSHER_APP_ID=                   # Real-time WebSockets
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=
GOOGLE_CALENDAR_ID=              # Google Calendar
GOOGLE_API_KEY=
OPENWEATHERMAP_KEY=              # Weather
TWITTER_API_KEY=                 # Twitter stream (if enabled)
FATHOM_API_KEY=                  # Website analytics
PACKAGIST_USERNAME=              # Packagist stats
APPLE_MUSIC_PRIVATE_KEY=         # Music playback
LAST_FM_API_KEY=
```

### Dashboard Config

`config/dashboard.php` defines which tiles are loaded and their properties:

```php
'tiles' => [
    \Spatie\Dashboard\Tiles\StatisticsTile::class,
    // ... more tiles
],
```

### Livewire Registration

`app/Providers/LivewireComponentsServiceProvider` registers custom components:

```php
Livewire::component('team-member-tile', TeamMemberTileComponent::class);
```

---

## Adding a New Tile (Workflow)

1. **Create Command**: `app/Console/Commands/Fetch{TileName}Command.php`
   - Extends `Command`
   - In `handle()`: fetch data, call `TileStore::make('tile_name')->set{Data}($result)`

2. **Create Store**: `app/Tiles/{TileName}Store.php`
   - Extends base Tile store pattern
   - Implement `get{Data}()` and `set{Data}()` methods

3. **Create Component**: `app/Livewire/{TileName}Component.php`
   - Extends `Livewire\Component`
   - Mount method receives `position` prop
   - Render method calls store and passes to view

4. **Create View**: `resources/views/components/tiles/{tile-name}.blade.php`
   - Wraps content in `<x-dashboard-tile :position="$position">`
   - Receives data from component's `render()` array

5. **Register in Scheduler**: `app/Console/Kernel.php`
   - Add `$this->command('fetch:{tile-name}')->...()->withoutOverlapping();`

6. **Register Component**: `app/Providers/LivewireComponentsServiceProvider`
   - Add `Livewire::component('tile-name', {TileName}Component::class);`

7. **Add to Dashboard**: `resources/views/dashboard.blade.php`
   - Add `<livewire:tile-name position="a1:a10" />`

---

## CSS & Styling

### Tailwind CSS Setup
- **Source**: `resources/css/app.css`
- **Build**: `webpack.mix.js` → PostCSS (PostCSS Easy Import + Tailwind) → `public/css/app.css`
- **Watch**: `npm run watch` during development
- **Build**: `npm run prod` before deployment

### Custom Grid System
- **File**: `resources/css/components/grid.css`
- **Pattern**: Converts position string (e.g., `a1:a18`) to CSS Grid values
- **Implementation**: Custom Tailwind component; modify if layout changes

### Typography & Colors
- **Font**: Inter UI (system fonts fallback)
- **Icons**: Twemoji (emoji rendering)
- **Dark theme**: Base classes in `app.css`; tiles can override with color classes

### Component Hierarchy
```
resources/css/
├── app.css                    # Entry point
├── components/
│   ├── grid.css              # 2D positioning
│   ├── fonts.css             # Font definitions
│   ├── filter.css            # CSS filters
│   └── markup.css            # Typography defaults
└── fonts/
    ├── inter-ui/             # Font files
    └── twemoji/              # Emoji fonts
```

---

## Common Development Tasks

### Add a new data source to existing tile

1. In the **Command**: Add API call, update store call
2. In the **Store**: Add getter/setter for new field
3. In the **View**: Add HTML to render new data

Example: Add Slack status to TeamMemberTile:
```php
// In FetchSlackStatusCommand:
$status = $slack->getStatus($member);
MemberStore::make($member)->setSlackStatus($status);

// In MemberStore:
public function setSlackStatus($status) { ... }
public function getSlackStatus() { ... }

// In team-member.blade.php:
<div>{{ $member->getSlackStatus() }}</div>
```

### Modify grid layout

Edit `resources/views/dashboard.blade.php` - change `position` attributes:
```blade
<livewire:statistics-tile position="d12:d20" />  <!-- Change this -->
```

### Update tile appearance

Edit the tile's Blade view:
```blade
{{-- resources/views/components/tiles/statistics-tile.blade.php --}}
<x-dashboard-tile :position="$position">
    {{-- Modify HTML here --}}
</x-dashboard-tile>
```

### Change scheduler frequency

Edit `app/Console/Kernel.php`:
```php
$this->command('fetch:github-totals')
    ->everyThirtyMinutes()  // Change frequency here
    ->withoutOverlapping();
```

---

## Daily Dashboard (Subproject)

Located in `/daily-dashboard/` - **Separate from main dashboard**

**Purpose**: Individual developer metrics dashboard (GitHub stats, commit history, etc.)

**Key files**:
- `index.php` (1,176 lines) - Single-file PHP app with no framework
- Provides JSON endpoint: `/metrics?scope=full`
- Frontend loads async, caches results with TTL

**Not used in main wall dashboard** - it's a standalone tool for individual developers.

---

## Performance Considerations

1. **Scheduler overhead**: Commands fetch from external APIs; ensure rate limits aren't hit
   - Use API caching (TTL in stores) to reduce requests
   - Offset commands if they overlap (`.withoutOverlapping()`)

2. **Database queries**: Each tile render calls `TileStore::make()->get...()` which queries DB
   - Stores use JSON columns; minimal overhead
   - No N+1 queries by design (tile data is flat JSON)

3. **WebSocket broadcasts**: Only enabled if Pusher configured; otherwise polling (every 0.5-1s by default)
   - Wall display doesn't need sub-second updates; adjust Livewire polling in config

4. **Asset size**: Tailwind CSS is purged in production (`npm run prod`)
   - No unused CSS in compiled output
   - Icons are inline SVG or emoji (Twemoji)

---

## Debugging

### View scheduled commands
```bash
php artisan schedule:list
```

### Run a single command manually
```bash
php artisan fetch:github-totals
```

### Check database tile data
```bash
php artisan tinker
>>> Spatie\Dashboard\Models\Tile::all();  # View all tiles
>>> Spatie\Dashboard\Models\Tile::where('name', 'statistics')->first();
```

### Monitor Livewire updates
- Open browser DevTools → Network tab
- Filter for XHR requests to `livewire`
- Watch for component re-renders (POST requests)

### Test WebSocket connection (if Pusher enabled)
```js
// Browser console
console.log(window.Echo);  // Check if Echo client loaded
```

---

## Deployment

1. **Environment**:
   - Copy `.env.example` → `.env`
   - Update API credentials, database path, Pusher config

2. **Install dependencies**:
   ```bash
   composer install --no-dev
   npm install --production
   ```

3. **Build assets**:
   ```bash
   npm run prod
   ```

4. **Setup database**:
   ```bash
   php artisan migrate --seed
   ```

5. **Scheduler cron**:
   ```bash
   # Add to crontab or system cron
   * * * * * cd /path/to/dashboard && php artisan schedule:run >> /dev/null 2>&1
   ```

6. **Queue worker** (optional, if using async jobs):
   ```bash
   php artisan queue:work --daemon
   ```

7. **Serve application**:
   - Nginx/Apache with Laravel config
   - Or: `php artisan serve` (dev only)

---

## Key Files to Know

| File | Purpose |
|------|---------|
| `routes/web.php` | Single route: GET / with AccessToken middleware |
| `resources/views/dashboard.blade.php` | Tile layout and positioning |
| `app/Console/Kernel.php` | Scheduler: define command frequencies |
| `app/Providers/LivewireComponentsServiceProvider` | Register Livewire components |
| `config/dashboard.php` | Spatie dashboard tile configuration |
| `resources/css/app.css` | Tailwind source CSS |
| `resources/css/components/grid.css` | Custom grid layout CSS |
| `webpack.mix.js` | Asset compilation configuration |
