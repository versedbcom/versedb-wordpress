# VerseDB for WordPress

Display your VerseDB profile, collection, wishlist, pull list, recent reads,
reading goal and public lists using native Gutenberg blocks. Pro accounts can
also display yearly reading statistics and a reading calendar.

## Setup

1. Install the plugin ZIP and activate VerseDB.
2. Open **Settings → VerseDB** and choose **Connect to VerseDB**. Approve the
   read-only connection on versedb.com. Your site needs HTTPS; localhost is
   supported for development.
3. Alternatively, create a personal access token in
   [My Apps](https://versedb.com/my/apps) with `read:public` and `read:showcase`,
   then use **Test and save connection**. Existing `read:user` tokens work but
   grant broader access. Token entry also requires HTTPS except on localhost.
4. Search for **VerseDB** in the block inserter and add a display to a page,
   post or block widget area. The first uncached display queues a background
   refresh. Use **Refresh preview** after it finishes.

Your theme supplies fonts and colors. Gutenberg controls offer text/background
colors, font size, line height, margins, padding and wide/full alignment.
Display controls include grid/list layout, columns, cover size, spacing and
item count. Profile controls include avatar size and shape, square-avatar corner radius, biography, banner and stats.

Profile, comic and list blocks include an “Enable links to VerseDB” toggle, on by default. Turn it off to display covers and names without links. Shortcodes accept enable_links="false". The optional display credit is controlled separately in Settings → VerseDB.

## Blocks and shortcodes

| Block | Shortcode |
| --- | --- |
| Profile | `[versedb_profile]` |
| Collection | `[versedb_collection]` |
| Wishlist | `[versedb_wishlist]` |
| Pull List | `[versedb_pull_list]` |
| Currently Reading | `[versedb_currently_reading]` |
| Recently Read | `[versedb_reading]` |
| Reading Goal | `[versedb_reading_goal]` |
| Public Lists | `[versedb_lists]` |
| List | `[versedb_list list_id="123"]` |
| Reading Stats (Pro) | `[versedb_reading_stats]` |
| Reading Calendar (Pro) | `[versedb_reading_calendar]` |

Shortcodes use the same renderer as blocks. Examples:

```text
[versedb_profile show_avatar="true" avatar_size="96" avatar_shape="square" avatar_radius="12" show_bio="true"]
[versedb_collection layout="grid" columns="4" limit="12" show_publisher="true"]
[versedb_collection layout="list" publisher_id="123" read_status="unread"]
[versedb_reading_goal year="2026"]
[versedb_list list_id="123" layout="list" limit="20"]
```

Collection filters include status, format, condition, graded copies, publisher
and series IDs, read status, review state and search. Sale/trade availability filters support both yes and no. Pro adds signed-copy,
grade-range, grading-company, genre, creator and character filters. Displays show up to 100 items from
VerseDB's first result page (50 for Currently Reading). A site can cache up to 100 distinct display queries.

The Recently Read block shows recorded reads. Currently Reading shows unfinished comics from the VerseDB reader and their completion percentages. The calendar uses the yearly API's daily
counts to avoid fetching every page of monthly reads.

## Enable or disable blocks

In Settings → VerseDB, use the Blocks cards to enable only the displays you need, then choose Save blocks. Search filters the cards; Enable all and Disable all apply to every block, including hidden search results. All eleven start enabled. Pro requirements still apply.

Disabled blocks are not registered, so their editor scripts do not load. Their shortcodes and public blocks render nothing, and their display queries are skipped during refresh. Saved page content and cached data remain; re-enable a block to restore it. Re-enable before editing pages that contain disabled blocks, which WordPress may show as unsupported. The shared display stylesheet is still needed when any enabled display renders, and account refresh continues.

## Block settings

All eleven blocks support text/background colors, font size, line height, margin,
padding and wide/full alignment when available in the active theme.

| Block | Display settings |
| --- | --- |
| Profile | Avatar visibility, size, circle/square shape, square corner radius (0–80px), biography, banner, level/XP/contributions |
| Collection | Heading, grid/list, desktop/mobile columns, item count, spacing, list cover size, covers, publishers, release dates, explicit-cover blur; filters and sorting described above |
| Wishlist | Heading, grid/list, desktop/mobile columns, item count, spacing, list cover size, covers, publishers, release dates, explicit-cover blur |
| Pull List | Same layout/cover settings as Wishlist; dates show the next issue release when available |
| Currently Reading | Same settings as Wishlist, plus progress-bar visibility; percentage remains visible |
| Recently Read | Same settings as Wishlist; dates are comic release dates |
| Reading Goal | Heading, year, progress-bar visibility; reading count remains visible |
| Public Lists | Heading, grid/list, desktop/mobile columns, item count, spacing, list cover size, covers |
| List | Public list ID plus the same settings as Wishlist; publisher/date fields appear when the list item provides them |
| Reading Stats (Pro) | Heading, year, desktop/mobile statistic columns, spacing, individual visibility for all six statistics |
| Reading Calendar (Pro) | Heading, year, year-summary visibility, day-cell size and spacing |

Grid-column controls appear in grid layout; cover size appears in list layout
with covers enabled. Mobile columns apply at 600px and below and cannot exceed
the desktop count. Cover-blur controls appear only with covers enabled.
Public Lists omits publisher and release-date controls because lists do not
provide those fields. An empty year uses the current year. Each new setting
also works in shortcodes using snake_case, for example `mobile_columns="1"`,
`show_progress="false"`, `show_active_days="false"` or `cell_size="16"`.

## Editing layouts

Every display starts with the standard arrangement of editable VerseDB Field
blocks. There is no layout mode to switch. Move or remove fields in List View, add fields with the
inserter, or use native Groups, Rows and Columns to arrange them. Select a field,
choose its **Content**, then open **Styles** for typography, colors, margins,
padding and borders. Image fields also have width, height and fit controls.

For a cover with only the series name, keep the Cover field and change Full item
name to **Series name and year**. It displays, for example, “Absolute Green Arrow
(2026)”. Issue number and issue title are separate optional fields. Years come
from the series start year, never the issue release date.

Comic and list templates repeat for each cached item. The editor previews all cached items up to your selected limit, using the
selected grid columns and spacing. Edit the first item to update every item. Profile and reading-summary layouts render once.
Native theme controls determine the available fonts and presets. Select a field and
open Styles for its typography, color and spacing. Statistic labels have separate
text controls. Select the parent display and open Styles to customize Heading text
and Display credit text. Defaults use 24px headings, 16px item names and 14px details;
custom styles take precedence. Alternatively, leave Heading empty and place a native
Heading block above the VerseDB block.

Existing blocks become directly editable when opened, retaining their current
arrangement and visibility choices. Previously customized layouts are preserved.
Adding and removing fields controls what appears. Content filters,
item count, grid columns, links and NSFW controls still apply. Layouts are saved in block content; shortcodes use the standard arrangement.

Older cached items refresh in the background to pick up separate series fields.
The last good data remains available during that refresh.

## Privacy and refreshes

Native display rendering never calls VerseDB. Hourly WP-Cron jobs refresh the account and
requested displays in small batches. Page and editor renders serve local cache
and queue stale refreshes. WP-Cron requires site traffic or a host-managed cron.

Only display fields are stored in the cache. Email, birth date, location,
purchase prices, notes, storage, loans and market values are excluded. Collections
show public copies only; individual lists must belong to the connected member,
be public and be published. Public Lists verifies publication through list details
in background batches of three. Existing unverified list caches are discarded
and rebuilt automatically. Explicit covers are blurred unless the owner enables
the block's override. Credit links are off by default and can be enabled in Settings → VerseDB.

Expired or revoked tokens and service outages preserve the last good cache.
Settings show reconnection status and warn before a known token expiry. Pasted-token expiry is unknown unless returned by the service. Pro output disappears
on the next successful account refresh after a subscription lapse.

Settings → VerseDB shows cache status, the last account refresh, the next scheduled refresh and counts of fresh, stale, missing and failed active display caches. Reload the page to update the status. Clear cache removes cached data and disabled query records, preserves your connection and preferences, and queues enabled displays for refresh while respecting API backoff. Public displays may be empty until rebuilding finishes.

Disconnect clears local credentials, cache and scheduled work. Delete the token
in My Apps to revoke it at VerseDB. Uninstall removes the plugin's settings too.

## Public link embeds

The plugin registers `https://versedb.com/oembed` for HTTPS title, series,
issue, event and creator links. Paste a link into WordPress's Embed
block or on its own line in the classic editor. Embeds use WordPress's own
oEmbed requests and cache, independently of the connected account; no account
token is sent. Cards load from VerseDB in an iframe, so their appearance is
supplied by VerseDB rather than your WordPress theme.


## Development

Requires Node 24.18+ and npm 11.16+. Run `npm ci`, then `npm run build`.
`npm start` watches blocks. `npm run package` creates `versedb.zip` containing
runtime files, block assets and the license. Run commands from this repository.

`npm run check` runs JavaScript/stylesheet linting, PHP syntax and PHP 7.4 grammar
checks, a production build, and integration checks on WordPress Playground with
WordPress 6.8 and PHP 7.4. Tests use real WordPress functions and synthetic HTTP
responses. Set `WP_VERSION` and `PHP_VERSION` to test another supported runtime.

`npm run dev:wordpress` opens a local WordPress environment without account data.
`npm run preview` creates a synthetic account and display page at port 9417 for
editor and responsive checks. Preview fixtures and test credentials are excluded
from the plugin ZIP. These checks do not prove live account connectivity.

## Requirements and support

WordPress 6.8+, PHP 7.4+. Support: hello@versedb.com. [WordPress](https://versedb.com/support/apps/wordpress).
Not yet released to the WordPress.org directory.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
