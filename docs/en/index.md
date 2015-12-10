# Translation

## Introduction

This page introduces developers to using the CMS for creating content in multiple languages.

Please refer to the [`i18n` class](http://doc.silverstripe.org/framework/en/topics/i18n) 
in SilverStripe framework for a internationalization, globalization and 
localization support of built-in datatypes as well as translating templates and PHP code.

Translations can be enabled for all subclasses of [`DataObject`](http://doc.silverstripe.org/framework/en/topics/datamodel), 
so it can easily be implemented into existing code with minimal interference.

## Screenshots

![Main UI](https://raw.github.com/silverstripe/silverstripe-translatable/master/docs/en/_images/translatable4_small.png)

*Translated website*


![Translated website](https://raw.github.com/silverstripe/silverstripe-translatable/master/docs/en/_images/translatable1.png)

*CMS: Language dropdown*

![Language dropdown](https://raw.github.com/silverstripe/silverstripe-translatable/master/docs/en/_images/translatable2.png)

*CMS: Translatable field with original value*

![Translatable field with original value](https://raw.github.com/silverstripe/silverstripe-translatable/master/docs/en/_images/translatable3.png)

*CMS: Create a new translation*

## Installation

Please follow the standard [module installation documentation](http://doc.silverstripe.org/framework/en/topics/modules).
The module has to reside in a toplevel folder called `translatable/`.

## Caveats

There are several ways to model multilingual content in a relational database.
The Translatable module uses one of the presented approaches ("Storage in language rows"),
and most of the time you don't have to worry about the underlying datamodel.
But it is important to understand these differences in order to make an informed
decision about which one fits your content model best. 

### Storage in language columns

Each translated value is stored in a new database column
alongside its original record, e.g. a `Content` column gets extended to `Content_de` and `Content_fr`.
   
Advantages: Translation can be limited to certain columns.

Disadvantages: If applied to complex data like hierarchical pages, it only works if the content structure
is very similar between languages. It would be difficult to e.g. have a new page section just in one language, and still retain all the framework's features (e.g. permission checks).

### Storage in language rows

Each translated record gets copied to a new row in the same table,
retaining the original database column layout.
   
Advantages: Allows for flexible structures like page trees per language,
and permission checks per language. Works transparently with most other modules which modify queries
(e.g. the "subsites" module). 

### Storage in language tables

Similar to "Storage in language rows", but creates a new table
for each 

Disadvantages: All-or-nothing approach to column translation (including columns where translation
doesn't make much sense, like numeric values). More complex data model with relational tables.

### Module approach and Alternatives

The Translatable module uses the "Storage in language rows" approach.

Alternatives:

 * Tractorcow's [Fluent Module](https://github.com/tractorcow/silverstripe-fluent)
 * UncleCheese's [TranslatableDataObject](http://www.leftandmain.com/silverstripe-tips/2012/04/03/translatabledataobject-insanely-simple-translation/) (open source)
 * Kreationsbyran's [Multilingual Module 2.0](http://www.kreationsbyran.se/blogg/multilingual-module-2-0-for-silverstripe-cms-3-0/) (commercial)

## Usage

### Configuration

#### ThroughObject::add_extension()

Enabling Translatable through *add_extension()* in your *mysite/_config.php*:

	:::php
	SiteTree::add_extension('Translatable');
	SiteConfig::add_extension('Translatable'); // 2.4 or newer only


#### Through $extensions

	:::php
	class Page extends SiteTree {
	  private static $extensions = array(
	    "Translatable"
	  );
	}


Make sure to rebuild the database through /dev/build after enabling `[api:Translatable]`.
Use the correct set_default_locale() before building the database
for the first time, as this locale will be written on all new records.

#### Setting the default locale

<div class="notice" markdown='1'>
**Important:** If the "default language" of your site is not english (en_US), please ensure to set the appropriate default
language for your content before building the database with Translatable enabled
</div>

Example:

	:::php
	Translatable::set_default_locale(<locale>);
	// Important: Call add_extension() after setting the default locale
	SiteTree::add_extension('Translatable');


For the Translatable class, a "locale" consists of a language code plus a region code separated by an underscore, 
for example "de_AT" for German language ("de") in the region Austria ("AT").
See http://www.w3.org/International/articles/language-tags/ for a detailed description.

To ensure that your template declares the correct content language, please see [i18n](i18n#declaring_the_content_language_in_html).

### Usage

Getting a translation for an existing instance: 

	:::php
	$translatedObj = Translatable::get_one_by_locale('MyObject', 'de_DE');


Getting a translation for an existing instance: 

	:::php
	$obj = DataObject::get_by_id('MyObject', 99); // original language
	$translatedObj = $obj->getTranslation('de_DE');


Getting translations through Translatable::set_reading_locale().
This is *not* a recommended approach, but sometimes unavoidable (e.g. for `[api:Versioned]` methods).

	:::php
	$origLocale = Translatable::get_reading_locale();
	Translatable::set_reading_locale('de_DE');
	$obj = Versioned::get_one_by_stage('MyObject', "ID = 99");
	Translatable::set_reading_locale($origLocale);


Creating a translation: 

	:::php
	$obj = new MyObject();
	$translatedObj = $obj->createTranslation('de_DE');



### Usage for SiteTree

`[api:Translatable]` can be used for subclasses of SiteTree as well. 
If a child page translation is requested without the parent
page already having a translation in this language, the extension
will recursively create translations up the tree.

The `SiteTree.URLSegment` property is enforced to be unique across
languages by auto-appending the language code at the end.
It ensures that all pages can be reached through their URL without any additional setup.
This behaviour can be turned off through the `Translatable.enforce_global_unique_urls` configuration setting,
in which case its up to you to ensure the language context can be derived from the URL
(e.g. through a subdomain or a language path prefix like `/en/mypage`).

In either case, you'll need to take care that the appropriate "reading language" is set
before showing links to other pages on a website, for example through
a `locale` GET parameter (see `Translatable::choose_site_locale()`).

Note: You can't get Children() for a parent page in a different language
through set_reading_locale(). Get the translated parent first.

	:::php
	// wrong
	Translatable::set_reading_lang('de_DE');
	$englishParent->Children(); 
	// right
	$germanParent = $englishParent->getTranslation('de_DE');
	$germanParent->Children();

By default, the URLs generated for a page can only contain western characters ("ASCII").
You can configure this to accept the whole range of UTF8 characters as well.
This is a SilverStripe core feature, rather than anything specific to this module.
Refer to the `URLSegmentFilter.default_allow_multibyte` configuration setting.

### Translating custom properties

Keep in mind that the `[api:Translatable]` extension currently doesn't support the exclusion of properties from being
translated - all custom properties will automatically be fetched from their translated record on the database. This means
you don't have to explicitly mark any custom properties as being translatable.

The `[api:Translatable]` decorator applies only to the getCMSFields() method on DataObject or SiteTree and the getSettingsFields() 
on SiteTree, not to any fields added in overloaded getCMSFields() implementations. See Translatable->updateCMSFields() for details. 
By default, custom fields in the CMS won't show an original readonly value on a translated record, although they will save correctly. You can
attach this behaviour to custom fields by calling a helper function from your getCMSFields() and getSettingsFields() functions.

	:::php
	class Page extends SiteTree {
		
		private static $db = array(
			'AdditionalProperty' => 'Text', 
		);
		
		function getCMSFields() {
			$fields = parent::getCMSFields();
	
			// Add fields as usual
			$additionalField = new TextField('AdditionalProperty');
			$fields->addFieldToTab('Root.Main', $additionalField);
			
			// Apply Translatable modifications
			$this->applyTranslatableFieldsUpdate($fields, 'updateCMSFields');
	
			return $fields;
		}

		function getSettingsFields() {
			$fields = parent::getSettingsFields();
	
			// Add fields as usual
			$additionalField = new TextField('AdditionalProperty');
			$fields->addFieldToTab('Root.Main', $additionalField);
			
			// Apply Translatable modifications
			$this->applyTranslatableFieldsUpdate($fields, 'updateSettingsFields');
	
			return $fields;
		}
	}


### Translating the Homepage

Every homepage has a distinct URL, the default language is /home, a German translation by default would be /home-de_DE.
They can be accessed like any other translated page. If you want to access different homepages from the "root" without a
URL, add a "locale" GET parameter. The German homepage would also be accessible through /?locale=de_DE. 

For this to work, please ensure that the translated homepage is a direct translation of the default homepage, and not a
new page created through "Create page...".

### Translation groups

Each translation can have an associated "master" object in another language which it is based on,
as defined by the "MasterTranslationID" property. This relation is optional, meaning you can
create translations which have no representation in the "default language".
This "original" doesn't have to be in a default language, meaning
a french translation can have a german original, without either of them having a representation
in the default english language tree.
Caution: There is no versioning for translation groups,
meaning associating an object with a group will affect both stage and live records.

SiteTree database table (abbreviated)
 | ID | URLSegment | Title     | Locale | 
 | -- | ---------- | -----     | ------ | 
 | 1  | about-us   | About us  | en_US  | 
 | 2  | ueber-uns  | Über uns | de_DE  | 
 | 3  | contact    | Contact   | en_US  | 

SiteTree_translationgroups database table
 | TranslationGroupID | OriginalID | 
 | ------------------ | ---------- | 
 | 99                 | 1          | 
 | 99                 | 2          | 
 | 199                | 3          | 


### CharacterSets

<div class="warning" markdown='1'>
**Caution:** Does not apply any character-set conversion, it is assumed that all content
is stored and represented in UTF-8 (Unicode). Please make sure your database and
HTML-templates adjust to this.
</div>

### "Default" languages

<div class="warning" markdown='1'>
**Important:** If the "default language" of your site is not english (en_US), 
please ensure to set the appropriate default language for
your content before building the database with Translatable enabled
</div>

Example:

	:::php
	Translatable::set_default_locale(<locale>);



### Locales and language tags

For the `Translatable` class, a "locale" consists of a language code plus a region code separated by an underscore, 
for example "de_AT" for German language ("de") in the region Austria ("AT").
See [http://www.w3.org/International/articles/language-tags/](http://www.w3.org/International/articles/language-tags/)
for a detailed description. You can get the locale on any object through the `Locale` property,
e.g. `$myPage->Locale`. For page templates, use `$ContentLocale` instead (it defaults to the locale
of the currently displayed page, and falls back to `i18n::get_locale()`).

### Uninstalling/Disabling

Disabling Translatable after creating translations will lead to all
pages being shown in the default sitetree regardless of their language.
It is advised to start with a new database after uninstalling Translatable,
or manually filter out translated objects through their "Locale" property
in the database.

## Recipes


### Switching languages

A widget now exists to switch between languages, and is [available here](http://www.silverstripe.org/Language-Chooser-Widget/).
You can easily make your own switchers with the following basic tools. To stay friendly to  caches and search engines, each
translation of a page must have a unique URL.

By URL:

	:::php
	http://<mysite>/mypage/?locale=de_DE


By user preference (place this in your Page_Controller->init() method):

	:::php
	$member = Member::currentUser();
	if($member && $member->Locale) {
		Translatable::set_current_locale($member->Locale);
	}

### Templates

To declare the correct language, use the `lang` attribute on the `<html>` tag (see [W3C recommendation](http://www.w3.org/TR/2007/NOTE-i18n-html-tech-lang-20070412/#ri20060630.133619987)).
SilverStripe provides `$ContentLocale` for this purpose.

Declaring the language for HTML templates:

	:::ss
	<html lang="$ContentLocale">...</html>

Declaring the language for HTML templates:

	:::ss
	<html lang="$ContentLocale" xml:lang="$ContentLocale" xmlns= "http://www.w3.org/1999/xhtml">


As every page has its own unique URL, language selection mostly happens explicitly: A user requests a page, which always
has only one language. But how does a user coming to your English default language know that there's a Japanese version
of this page? 
By default, SilverStripe core doesn't provide any switching of languages through sessions or browser cookies. As a
SEO-friendly CMS, it contains all this information in the URL. Each page in SilverStripe is aware of its translations
through the *getTranslations()* method. We can use this method in our template to build a simple language switcher. It
shows all available translations in an unordered list with links to the same page in a different language. The example
below can be inserted in any of your templates, for example `themes/blackcandy/templates/Layout/Page.ss`.

	:::php
	<% if Translations %>
	<ul class="translations">
	<% loop Translations %>
	  <li class="$Locale.RFC1766">
	    <a href="$Link" hreflang="$Locale.RFC1766" 
	title="$Title">
	    <% sprintf(_t('SHOWINPAGE','Show page in %s'),$Locale.Nice) %>
	   </a>
	  </li>
	<% end_loop %>
	</ul>
	<% end_if %>


Keep in mind that this will only show you available translations for the current page. The $Locale.Nice casting will
just work if your locale value is registered in i18n::get_common_locales().

### Page-control

If you want to put static links in your template, which link to a site by their url, normally you can use the `<% loop
Page(page-url) %>`. For sites which use Translatable, this is not possible for more than one language, because the url's
of different pages differ.

For this case place the following function in your Page_Controller:

	:::php
	public function PageByLang($url, $lang) {
	    $SQL_url = Convert::raw2sql($url);
	    $SQL_lang = Convert::raw2sql($lang);
	
	    $page = Translatable::get_one_by_lang('SiteTree', $SQL_lang, "URLSegment = '$SQL_url'");
	
	    if ($page->Locale != Translatable::get_current_locale()) {
	        $page = $page->getTranslation(Translatable::get_current_locale());
	    }
	    return $page;
	}

So, for example if you have a german page "Kontakt", which should be translated to english as "Contact", you may use:

	<% loop PageByLang(Kontakt,de_DE) %>

The control displays the link in the right language, depending on the current locale.

Example:

	<% loop PageByLang(Kontakt,de_DE) %>
	 <h2><a href="$Link" title="$Title">$Title</a></h2>
	<% end_loop %>


### Enabling the _t() function in templates 

If you're looking to use [the _t() function](http://doc.silverstripe.com/doku.php?id=i18n#the_t_function) in template
files, you'll need to [set the i18n locale](/topics/translation#setting_the_i18n_locale) first. 

(The reasoning is as follows: Translatable doesn't set the i18n locale. Historically these were two separate systems,
but they're reasonably interchangeable for a front-end website. The distinction is mainly valid for the CMS, because you
want the CMS to be in English (`i18n`), but edit pages in different languages (`Translatable`).)

### Setting the i18n locale

You can set the `i18n` locale value which is used to format dates, currencies and other regionally different values to
the same as your current page locale. 

	:::php
	class Page_Controller extends ContentController {
		public function init() {
			parent::init();
		
			if($this->dataRecord->hasExtension('Translatable')) {
				i18n::set_locale($this->dataRecord->Locale);
			}
		}
	}


### Adding a new locale

The `i18n` logic has lookup tables for common locales in i18n::$common_locales, which is a subset of i18n::$all_locales.
If your locale is not present here, you can simply add it through `mysite/_config.php`:

	:::php
	i18n::$common_locales['de_AT'] = array('German (Austria)', 'Deutsch (Österreich)');

This should e.g. enable you to use `$Locale.Nice` in template code.


## Related

*  [translate.silverstripe.org](http://translate.silverstripe.org): Starting point for community-driven translation of the Silverstripe UI
*  [i18n](i18n): Developer-level documentation of Silverstripe's i18n capabilities
*  `[api:Translatable]`: DataObject-interface powering the website-content translations
*  ["Translatable ModelAdmin" module](http://silverstripe.org/translatablemodeladmin-module/): An extension which allows
translations of `[api:DataObject]`s inside `[api:ModelAdmin]`
