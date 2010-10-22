=== Plugin Name ===
Contributors: JohnPBloch
Donate Link: http://www.johnpbloch.com/
Tags: custom post type, custom permalink, permalink, permalinks, custom permalinks, custom post types, post permalinks, flexible permalinks, flexible permalink, post type permalink, post type, post type permalinks
Requires at least: 3.0
Tested up to: 3.0.1
Stable tag: 1.1.3

Custom Post Permalinks sets up permalinks for custom post types and gives you control over the permalink structure just like you have for blog post permalinks out of the box.

== Description ==

The plugin will set up custom post type permalinks for non-hierarchical permalinks which have the flexibility of blog post permalinks. So, for example, I could have a press release post type and set up permalinks like so:

* http://my-site.com/press-releases/2010/08/great-news/

which would also enable post type specific archives:

* http://my-site.com/press-releases/2010/08/
* http://my-site.com/press-releases/2010/

Special thanks goes out to Aaron Jorbin for helping me with reviewing the code and making the plugin better in general.

Props to Michael Fields too for his help as a beta tester.

If you find a bug or have a feature request, head on over to
http://bugs.johnpbloch.com and create a ticket.

== Installation ==

1. Use WordPress' built in plugin installer (or otherwise upload the custom-post-permalinks directory and its contents to your plugins directory)
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Go to your Permalinks settings page and modify the permalinks to your heart's content.

== Frequently Asked Questions ==

= The plugin added text to my permalinks! What's happening? =

The plugin will check your custom permalinks and compare them to the default permalinks for similar structures. If they're identical in structure, the plugin adds the `%post_type%` permalink tag to the beginning of the permalink structure to differentiate it from the blog posts' permalinks. Trust me, it's going to save you a lot of trouble.

== Screenshots ==

1. The permalinks settings screen with a few extra post types.

== Changelog ==

= 1.1.3 =
* Another stupid bug fix.

= 1.1.2 =
* Stupid stupid syntax error. I'm a bad person.

= 1.1.1 =
* Fixed a bug in custom taxonomy permalinks

= 1.1 =
* Fixed two major bugs in the way certain structures were handled
* Added support for hierarchical post types
* Added uninstall script to delete data and flush rewrite rules
* Added support for all taxonomies

= 1.0.3 =
* Fixed a major bug that would break permalinks under certain conditions

= 1.0.2 =
* Fixed code that improperly checked for categories

= 1.0.1 =
* Added category and author handling in permalinks.
* Restricted post types to non-hierarchical types.
* Add .pot file to lang directory

= 1.0 =
* First stable release

== Upgrade Notice ==

= 1.1.2 =
* Fixed a bug which broke permalinks for some post types with custom rewrite slugs.