=== VerseDB ===
Tags: comics, collection, reading, blocks
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display your VerseDB comics, profile and reading progress on your personal WordPress site with Gutenberg blocks.

== Description ==

Connect one VerseDB account and display its profile, public collection copies,
wishlist, pull list, recent reads, reading goal and public lists. VerseDB Pro
accounts also have yearly reading statistics, a reading heatmap and additional
collection filters through the VerseDB service.

Each display is a native dynamic Gutenberg block with a matching shortcode for
classic editors and page builders. Blocks also work in block widget areas.
Styling inherits your theme. Use Gutenberg's color, typography, spacing and
alignment controls, with grid/list, desktop/mobile columns and cover controls
where applicable. Reading displays offer progress visibility, individual statistic
visibility and calendar cell sizing.

Your WordPress server refreshes data in the background and stores a display-safe
cache. Visitors never wait for a VerseDB API request. Cached displays remain up
when a token expires or the service is unavailable. Credit links are off by default and can be enabled in Settings → VerseDB. Explicit covers are blurred by default.

This plugin is for personal blogs. It does not provide shop, inventory, publisher,
event, market-value or multi-account features.

Support: hello@versedb.com.
WordPress: https://versedb.com/support/apps/wordpress
Source and build instructions: https://github.com/versedbcom/versedb-wordpress

= External services =

This plugin connects to VerseDB at https://versedb.com. Account API requests begin
only when a site administrator connects an account.

Connect to VerseDB sends the site name, return URL, a random state and PKCE
challenge to the VerseDB consent screen. After approval, the WordPress server
exchanges the authorization code and verifier for a read-only token. The server
sends this token to the User API at https://versedb.com/api/v1 to retrieve the
member's profile, selected comic displays, public lists and reading progress.

API requests run during connection validation and scheduled refreshes, not public
page rendering. Readers' browsers load cover and profile images from the URLs
provided by VerseDB, normally https://media.versedb.com. Following a comic or
profile link opens its VerseDB page. Native block scripts and styles are local.

Public link embeds use WordPress's oEmbed requests and cache. WordPress sends the
pasted public URL and requested dimensions to https://versedb.com/oembed without
an account token. Visitors load the card in a VerseDB iframe. These embeds work
independently of the account connection.

Private account fields, purchase prices, notes, storage, loans and collection
value are excluded from the display cache and public output. Encrypted credentials
are stored server-side. Disconnect removes local credentials and data; revoke the
token in VerseDB's My Apps page to remove its service access.

Terms: https://versedb.com/policy/terms
Privacy: https://versedb.com/policy/privacy

== Installation ==

1. Upload the VerseDB plugin ZIP and activate it.
2. Open Settings → VerseDB and choose Connect to VerseDB.
3. Approve the read-only connection on VerseDB. HTTPS is required except on localhost.
4. Search for VerseDB in the block inserter, add a display and publish your page.
5. Allow WP-Cron to refresh new displays, then use Refresh preview in the editor.

As a fallback, create a token in https://versedb.com/my/apps with read:public and
read:showcase permissions. Paste it into the settings page and choose Test and
save connection.

Token entry also requires HTTPS, except on localhost.

== Frequently Asked Questions ==

= Can I paste a VerseDB link to embed a card? =

Yes. Title, series, issue, event and creator links become cards in WordPress's
Embed block and classic-editor auto-embeds. Other VerseDB links stay links.
No account token is used for public link embeds.

= Which shortcodes are available? =

[versedb_profile], [versedb_collection], [versedb_wishlist], [versedb_pull_list],
[versedb_reading], [versedb_currently_reading], [versedb_reading_goal], [versedb_lists], [versedb_list],
[versedb_reading_stats] and [versedb_reading_calendar].

Use list_id="123" for one public list, year="2026" for a reading display,
layout="list" or columns="4" for comic displays, and limit="12" for item count.
Profile options include show_avatar, avatar_size, show_bio, show_banner and show_stats.

Profile, comic and list blocks include an “Enable links to VerseDB” toggle, on by default. Turn it off to display covers and names without links. Shortcodes accept enable_links="false". The optional display credit is controlled separately in Settings → VerseDB.

= Can I rearrange and style individual fields? =

Every block starts with the standard arrangement of editable fields. Move,
remove and add VerseDB Field blocks, or arrange them with Groups, Rows and Columns. Each field has its own
Styles controls for typography, colors, spacing and borders. Image fields include
width, height and fit. For covers with only series names, keep Cover and choose
Series name and year for the text field, such as “Absolute Green Arrow (2026)”.

The template repeats for every comic or list item. Profile and reading summaries
render once. Existing layouts become directly editable when opened, retaining the current
arrangement. Shortcodes use the standard layout.

= Why is a block empty? =

Connect your account first. A new or changed display needs a background refresh.
Check token permissions, filters and public-list visibility in Settings → VerseDB.
An empty public block renders no content. Pro displays require an active Pro
account. WP-Cron runs when the site receives traffic; quiet sites may need a
scheduled cron from their host.

= How often does the cache refresh? =

Hourly by default, in small batches. Refresh now queues a background refresh.
Rate-limit backoff is respected. Item displays show up to 100 items (50 for
Currently Reading), and the site can cache up to 100 distinct display queries.
Public Lists verifies each list's publication status in background batches of
three. Existing unverified list caches are removed and rebuilt automatically.

= Will private data appear? =

No private account fields, prices, notes, storage, loans or market values appear.
Collections exclude copies marked private. Lists must be public and published.
Placing a block publishes that selected display on your site.

= What does VerseDB Pro add? =

The VerseDB service provides yearly statistics, calendar counts and collection
filters for signed copies, grade ranges, grading companies, genres, creators
and characters. The plugin follows
the account's current service eligibility. Local styling works for every account.

= Does Recently Read show what I am currently reading? =

Recently Read shows recorded reads. Currently Reading shows unfinished comics
from the VerseDB reader with their completion percentages.

= What happens when my token expires? =

Cached content remains available. Reconnect from Settings → VerseDB. OAuth tokens
include expiry metadata; a pasted token's expiry may be unknown. The token manager
on VerseDB shows its expiry and lets you revoke it.

== Screenshots ==

1. Collection and profile blocks on a personal blog.
2. Block controls and a collection preview in the editor.
3. Account connection and refresh controls.
4. Reading goal, progress and calendar displays.

== Changelog ==

= 1.0.0 =
* Initial release.
* Connect a VerseDB account with Connect to VerseDB or a personal access token.
* Blocks and shortcodes for profile, collection, wishlist, pull list, recently read, currently reading, reading goal and public lists.
* Pro reading statistics, reading calendar and collection filters.
* Hourly background refreshes with a cache that stays up during outages.
* Embed cards for VerseDB title, series, issue, event and creator links.
