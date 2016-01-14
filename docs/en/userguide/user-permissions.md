# User Permissions

Permissions to view and create translations are managed through the CMS, based on security groups
defined in the "Security" section (`admin/security`). By default, all CMS users with rights to create and edit pages
can also create translations. This can be restricted by removing the "Translate into all available languages" permission,
and replacing it with language specific permissions.

You can further restrict viewing and editing rights on a specific language through the "Settings" section (`admin/settings`).
Each language has its own configuration "translation", and you can configure access to groups there.

Here's an example setup which allows content authors to write only English master content,
while translators can only write German translations, but still see readonly versions of the English master content.

Group: Administrator

 * Has "Full administrative rights" permission

Group: Content Author English

 * Has "View language dropdown" permission
 * Has "Translate into English" permission
 * Is part of "Who can edit pages?" in "Settings" for "English"
 * Is part of "Who can create pages?" in "Settings" for "English"

Group: Translator German

 * Has "View language dropdown" permission
 * Has "Translate into German" permission
 * Is part of "Who can edit pages?" in "Settings" for "German"
 * Is part of "Who can create pages?" in "Settings" for "German"