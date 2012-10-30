=== SD Meeting Tool ===
Tags: sd, meeting, tool, checkin, checkout, speakers, speaker list
Requires at least: 3.2
Tested up to: 3.2
Stable tag: trunk
Contributors: Sverigedemokraterna IT
Control meetings and conferences by handling checkin, checkout, speaker lists, printing, etc.
Donate Link: https://it.sverigedemokraterna.se/donera/
License: GPL v3


== Description ==

SD Meeting Tool can be used to administer meetings and conferences. It was originally written by the Sweden Democrat party for internal use.

The plugin should be able to handle organizational and governmental meetings and conferences, by providing participant, voting, checkin, checkout, printing services, etc.

= Requirements =

* At least one technically-minded person to administer the internals of the tool: creation of participants, lists, actions, registrations, etc.
* A Wordpress installation.
* Javascript for the administrators and, optionally, for visitors that want automatic refreshes.

Some features, such as automatic updating of shortcodes for visitor pages, will require powerful hardware. The reason for this is that most shortcodes are completely dynamically generated without caching possibility. Having several hundred or thousand visitors requesting AJAX-updates of speaker lists or agendas will bog down Wordpress (being a generally slow CMS to start off with).

Keeping the amount of visitors to a minimum, or even better: keeping the site internal and private, is recommended until further notice.

= Documentation =

The documentation is available in the apidoc.7z file of the plugin. It meant to be read by a technically-able person who will then configure and administer the meeting tool.

== Frequently Asked Questions ==

No FAQ yet. 

== Installation ==

1. Unzip and copy the zip contents (including directory) into the `/wp-content/plugins/` directory
1. Activate the plugin sitewide through the 'Plugins' menu in WordPress.

== Screenshots ==

1. The Meeting Tool in the admin panel.
1. Registrations overview.
1. Agendas overview with a filled-in agenda.
1. Display format overview. Several display formats are already configured.
1. Displays overview. Displays display lists to the visitors.
1. Elections overview. One manual and one anonymous election is visible.
1. List sort overviews. The first two sort priorities per list sort are shown.
1. Lists overviews. Lists are central to the meeting tool.
1. Participant overview. Participants can be easily imported from a spreadsheet.
1. Printing overview. En kallelse två, ett deltagarkuvert och en namnskyltmall är inställda.
1. Talarlistan för agendan "Första dagen".
1. Temat Landsdagarna 2011 visar talarlistan för dagen.

== Upgrade Notice ==

= 1.0 =

No upgrade necessary.

== Changelog ==

= 1.1 2012-10-30 =

Base:

- Added: Settings tab.

Elections:

- Added participant query function

Lists:

- New: sd_mt_add_list_participants function
- New: Particpants are shown in the participant edit textarea
- Fix: Lists are properly cloned

Participants:

- New: Clear participants function.

Printing:

- New: Wizard, 24 stickers
- New: Page size and orientation can be chosen
- New: Block and field clone functions

UI Text Searches:

- Change: UUID is now an MD5, not a SHA512. It appears that inputs can't have names that long.
- Change: Selected text row is shown instead of ID number when registration is successful.

Speakers:

- New: Restop: updates the time the user stopped speaking to the current time.
- New: Delete button follows current speaker.
- New: Added first setting: default speaker time.
- New: Able to change current agenda item using a button.
- New: Cursor keys can be used to select agenda item (after first selecting the select box).
- Fix: Speaker list can be editing by several people simultaneously.
- Fix: Size of management panel should be maximized without becoming too big.
- Fix: Speaker log shortcode no longer returns description of log.
- Fix: Prevent speaker from expanding when dragging.

= 1.0 =

- Initial public release

