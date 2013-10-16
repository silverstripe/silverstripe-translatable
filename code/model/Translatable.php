<?php
/**
 * The Translatable decorator allows your DataObjects to have versions in different languages,
 * defining which fields are can be translated. Translatable can be applied
 * to any {@link DataObject} subclass, but is mostly used with {@link SiteTree}.
 * Translatable is compatible with the {@link Versioned} extension.
 * To avoid cluttering up the database-schema of the 99% of sites without multiple languages,
 * the translation-feature is disabled by default.
 * 
 * Locales (e.g. 'en_US') are used in Translatable for identifying a record by language,
 * see section "Locales and Language Tags".
 * 
 * <h2>Configuration</h2>
 * 
 * The extension is automatically enabled for SiteTree and SiteConfig records,
 * if they can be found. Add the following to your config.yml in order to
 * register a custom class:
 * 
 * <code>
 * MyClass:
 *   extensions:
 *     Translatable
 * </code>
 * 
 * Make sure to rebuild the database through /dev/build after enabling translatable.
 * Use the correct {@link set_default_locale()} before building the database
 * for the first time, as this locale will be written on all new records.
 * 
 * <h3>"Default" locales</h3>
 * 
 * Important: If the "default language" of your site is not US-English (en_US), 
 * please ensure to set the appropriate default language for
 * your content before building the database with Translatable enabled:
 * <code>
 * Translatable::set_default_locale(<locale>); // e.g. 'de_DE' or 'fr_FR'
 * </code>
 * 
 * For the Translatable class, a "locale" consists of a language code plus a region 
 * code separated by an underscore, 
 * for example "de_AT" for German language ("de") in the region Austria ("AT").
 * See http://www.w3.org/International/articles/language-tags/ for a detailed description.
 * 
 * <h2>Usage</h2>
 *
 * Getting a translation for an existing instance: 
 * <code>
 * $translatedObj = Translatable::get_one_by_locale('MyObject', 'de_DE');
 * </code>
 * 
 * Getting a translation for an existing instance: 
 * <code>
 * $obj = DataObject::get_by_id('MyObject', 99); // original language
 * $translatedObj = $obj->getTranslation('de_DE');
 * </code>
 * 
 * Getting translations through {@link Translatable::set_current_locale()}.
 * This is *not* a recommended approach, but sometimes inavoidable (e.g. for {@link Versioned} methods).
 * <code>
 * $origLocale = Translatable::get_current_locale();
 * Translatable::set_current_locale('de_DE');
 * $obj = Versioned::get_one_by_stage('MyObject', "ID = 99");
 * Translatable::set_current_locale($origLocale);
 * </code>
 * 
 * Creating a translation: 
 * <code>
 * $obj = new MyObject();
 * $translatedObj = $obj->createTranslation('de_DE');
 * </code>
 *
 * <h2>Usage for SiteTree</h2>
 * 
 * Translatable can be used for subclasses of {@link SiteTree},
 * it is automatically configured if this class is foun.
 * 
 * If a child page translation is requested without the parent
 * page already having a translation in this language, the extension
 * will recursively create translations up the tree.
 * Caution: The "URLSegment" property is enforced to be unique across
 * languages by auto-appending the language code at the end.
 * You'll need to ensure that the appropriate "reading language" is set
 * before showing links to other pages on a website through $_GET['locale'].
 * Pages in different languages can have different publication states
 * through the {@link Versioned} extension.
 * 
 * Note: You can't get Children() for a parent page in a different language
 * through set_current_locale(). Get the translated parent first.
 * 
 * <code>
 * // wrong
 * Translatable::set_current_locale('de_DE');
 * $englishParent->Children(); 
 * // right
 * $germanParent = $englishParent->getTranslation('de_DE');
 * $germanParent->Children();
 * </code>
 *
 * <h2>Translation groups</h2>
 * 
 * Each translation can have one or more related pages in other languages. 
 * This relation is optional, meaning you can
 * create translations which have no representation in the "default language".
 * This means you can have a french translation with a german original, 
 * without either of them having a representation
 * in the default english language tree.
 * Caution: There is no versioning for translation groups,
 * meaning associating an object with a group will affect both stage and live records.
 * 
 * SiteTree database table (abbreviated)
 * ^ ID ^ URLSegment ^ Title ^ Locale ^
 * | 1 | about-us | About us | en_US |
 * | 2 | ueber-uns | Über uns | de_DE |
 * | 3 | contact | Contact | en_US |
 * 
 * SiteTree_translationgroups database table
 * ^ TranslationGroupID ^ OriginalID ^
 * | 99 | 1 |
 * | 99 | 2 |
 * | 199 | 3 |
 *
 * <h2>Character Sets</h2>
 * 
 * Caution: Does not apply any character-set conversion, it is assumed that all content
 * is stored and represented in UTF-8 (Unicode). Please make sure your database and
 * HTML-templates adjust to this.
 * 
 * <h2>Permissions</h2>
 * 
 * Authors without administrative access need special permissions to edit locales other than
 * the default locale.
 * 
 * - TRANSLATE_ALL: Translate into all locales
 * - Translate_<locale>: Translate a specific locale. Only available for all locales set in
 *   `Translatable::set_allowed_locales()`.
 * 
 * Note: If user-specific view permissions are required, please overload `SiteTree->canView()`.
 * 
 * <h2>Uninstalling/Disabling</h2>
 * 
 * Disabling Translatable after creating translations will lead to all
 * pages being shown in the default sitetree regardless of their language.
 * It is advised to start with a new database after uninstalling Translatable,
 * or manually filter out translated objects through their "Locale" property
 * in the database.
 * 
 * @see http://doc.silverstripe.org/doku.php?id=multilingualcontent
 *
 * @author Ingo Schommer <ingo (at) silverstripe (dot) com>
 * @author Michael Gall <michael (at) wakeless (dot) net>
 * @author Bernat Foj Capell <bernat@silverstripe.com>
 * 
 * @package translatable
 */
class Translatable extends DataExtension implements PermissionProvider {

	const QUERY_LOCALE_FILTER_ENABLED = 'Translatable.LocaleFilterEnabled';

	/**
	 * The 'default' language.
	 * @var string
	 */
	protected static $default_locale = 'en_US';
	
	/**
	 * The language in which we are reading dataobjects.
	 *
	 * @var string
	 */
	protected static $current_locale = null;
	
	/**
	 * A cached list of existing tables
	 *
	 * @var mixed
	 */
	protected static $tableList = null;

	/**
	 * An array of fields that can be translated.
	 * @var array
	 */
	protected $translatableFields = null;

	/**
	 * A map of the field values of the original (untranslated) DataObject record
	 * @var array
	 */
	protected $original_values = null;
	
	/**
	 * If this is set to TRUE then {@link augmentSQL()} will automatically add a filter
	 * clause to limit queries to the current {@link get_current_locale()}. This camn be
	 * disabled using {@link disable_locale_filter()}
	 *
	 * @var bool
	 */
	protected static $locale_filter_enabled = true;
	
	/**
	 * @var array All locales in which a translation can be created.
	 * This limits the choice in the CMS language dropdown in the
	 * "Translation" tab, as well as the language dropdown above
	 * the CMS tree. If not set, it will default to showing all
	 * common locales.
	 */
	protected static $allowed_locales = null;

	/**
	 * @var boolean Check other languages for URLSegment values (only applies to {@link SiteTree}).
	 * Turn this off to handle language setting yourself, e.g. through language-specific subdomains
	 * or URL path prefixes like "/en/mypage".
	 */
	private static $enforce_global_unique_urls = true;
		
	/**
	 * Reset static configuration variables to their default values
	 */
	static function reset() {
		self::enable_locale_filter();
		self::$default_locale = 'en_US';
		self::$current_locale = null;
		self::$allowed_locales = null;
	}
	
	/**
	 * Choose the language the site is currently on.
	 *
	 * If $_GET['locale'] is currently set, then that locale will be used. 
	 * Otherwise the member preference (if logged
	 * in) or default locale will be used.
	 * 
	 * @todo Re-implement cookie and member option
	 * 
	 * @param $langsAvailable array A numerical array of languages which are valid choices (optional)
	 * @return string Selected language (also saved in $current_locale).
	 */
	static function choose_site_locale($langsAvailable = array()) {
		if(self::$current_locale) {
			return self::$current_locale;
		}

		if(
			(isset($_REQUEST['locale']) && !$langsAvailable) 
			|| (isset($_REQUEST['locale']) 
			&& in_array($_REQUEST['locale'], $langsAvailable))
		) {
			// get from request parameter
			self::set_current_locale($_REQUEST['locale']);
		} else {
			self::set_current_locale(self::default_locale());
		}

		return self::$current_locale; 
	}
		
	/**
	 * Get the current reading language.
	 * This value has to be set before the schema is built with translatable enabled,
	 * any changes after this can cause unintended side-effects.
	 * 
	 * @return string
	 */
	static function default_locale() {
		return self::$default_locale;
	}
	
	/**
	 * Set default language. Please set this value *before* creating
	 * any database records (like pages), as this locale will be attached
	 * to all new records.
	 * 
	 * @param $locale String
	 */
	static function set_default_locale($locale) {
		if($locale && !i18n::validate_locale($locale)) {
			throw new InvalidArgumentException(sprintf('Invalid locale "%s"', $locale));
		}
		
		$localeList = i18n::config()->all_locales;
		if(isset($localeList[$locale])) {
			self::$default_locale = $locale;
		} else {
			user_error(
				"Translatable::set_default_locale(): '$locale' is not a valid locale.", 
				E_USER_WARNING
			);
		}
	}

	/**
	 * Get the current reading language.
	 * If its not chosen, call {@link choose_site_locale()}.
	 * 
	 * @return string
	 */
	static function get_current_locale() {
		return (self::$current_locale) ? self::$current_locale : self::choose_site_locale();
	}
		
	/**
	 * Set the reading language, either namespaced to 'site' (website content)
	 * or 'cms' (management backend). This value is used in {@link augmentSQL()}
	 * to "auto-filter" all SELECT queries by this language.
	 * See {@link disable_locale_filter()} on how to override this behaviour temporarily.
	 * 
	 * @param string $lang New reading language.
	 */
	static function set_current_locale($locale) {
		if($locale && !i18n::validate_locale($locale)) {
			throw new InvalidArgumentException(sprintf('Invalid locale "%s"', $locale));
		}
		
		self::$current_locale = $locale;
	}	
	
	/**
	 * Get a singleton instance of a class in the given language.
	 * @param string $class The name of the class.
	 * @param string $locale  The name of the language.
	 * @param string $filter A filter to be inserted into the WHERE clause.
	 * @param boolean $cache Use caching (default: false)
	 * @param string $orderby A sort expression to be inserted into the ORDER BY clause.
	 * @return DataObject
	 */
	static function get_one_by_locale($class, $locale, $filter = '', $cache = false, $orderby = "") {
		if($locale && !i18n::validate_locale($locale)) {
			throw new InvalidArgumentException(sprintf('Invalid locale "%s"', $locale));
		}
		
		$orig = Translatable::get_current_locale();
		Translatable::set_current_locale($locale);
		$do = $class::get()
			->where($filter)
			->where(sprintf('"Locale" = \'%s\'', Convert::raw2sql($locale)))
			->sort($orderby)
			->First();
		Translatable::set_current_locale($orig);
		return $do;
	}

	/**
	 * Get all the instances of the given class translated to the given language
	 *
	 * @param string $class The name of the class
	 * @param string $locale  The name of the language
	 * @param string $filter A filter to be inserted into the WHERE clause.
	 * @param string $sort A sort expression to be inserted into the ORDER BY clause.
	 * @param string $join A single join clause.  This can be used for filtering, only 1 
	 *               instance of each DataObject will be returned.
	 * @param string $limit A limit expression to be inserted into the LIMIT clause.
	 * @param string $containerClass The container class to return the results in.
	 * @param string $having A filter to be inserted into the HAVING clause.
	 * @return mixed The objects matching the conditions.
	 */
	static function get_by_locale($class, $locale, $filter = '', $sort = '', $join = "", $limit = "") {
		if($locale && !i18n::validate_locale($locale)) {
			throw new InvalidArgumentException(sprintf('Invalid locale "%s"', $locale));
		}
		
		$oldLang = self::get_current_locale();
		self::set_current_locale($locale);
		$result = $class::get();
		if($filter) $result = $result->where($filter);
		if($sort) $result = $result->sort($sort);
		if($join) $result = $result->leftJoin($join);
		if($limit) $result = $result->limit($limit);
		self::set_current_locale($oldLang);

		return $result;
	}
	
	/**
	 * @return bool
	 */
	public static function locale_filter_enabled() {
		return self::$locale_filter_enabled;
	}
	
	/**
	 * Enables automatic filtering by locale. This is normally called after is has been
	 * disabled using {@link disable_locale_filter()}.
	 *
	 * @param $enabled (default true), if false this call is a no-op - see {@link disable_locale_filter()}
	 */
	public static function enable_locale_filter($enabled = true) {
		if ($enabled) {
			self::$locale_filter_enabled = true;
		}
	}
	
	/**
	 * Disables automatic locale filtering in {@link augmentSQL()}. This can be re-enabled
	 * using {@link enable_locale_filter()}.
	 *
	 * Note that all places that disable the locale filter should generally re-enable it
	 * before returning from that block of code (function, etc). This is made easier by
	 * using the following pattern:
	 *
	 * <code>
	 * $enabled = Translatable::disable_locale_filter();
	 * // do some work here
	 * Translatable::enable_locale_filter($enabled);
	 * return $whateverYouNeedTO;
	 * </code>
	 *
	 * By using this pattern, the call to enable the filter will not re-enable it if it
	 * was not enabled initially.  That will keep code that called your function from
	 * breaking if it had already disabled the locale filter since it will not expect
	 * calling your function to change the global state by re-enabling the filter.
	 *
	 * @return boolean true if the locale filter was enabled, false if it was not
	 */
	public static function disable_locale_filter() {
		$enabled = self::$locale_filter_enabled;
		self::$locale_filter_enabled = false;
		return $enabled;
	}
	
	/**
	 * Gets all translations for this specific page.
	 * Doesn't include the language of the current record.
	 * 
	 * @return array Numeric array of all locales, sorted alphabetically.
	 */
	function getTranslatedLocales() {
		$langs = array();
		
		$baseDataClass = ClassInfo::baseDataClass($this->owner->class); //Base Class
		$translationGroupClass = $baseDataClass . "_translationgroups";
		if($this->owner->hasExtension("Versioned")  && Versioned::current_stage() == "Live") {
			$baseDataClass = $baseDataClass . "_Live";
		}
		
		$translationGroupID = $this->getTranslationGroup();
		if(is_numeric($translationGroupID)) {
			$query = new SQLQuery(
				'DISTINCT "Locale"',
				sprintf(
					'"%s" LEFT JOIN "%s" ON "%s"."OriginalID" = "%s"."ID"',
					$baseDataClass,
					$translationGroupClass,
					$translationGroupClass,
					$baseDataClass
				), // from
				sprintf(
					'"%s"."TranslationGroupID" = %d AND "%s"."Locale" != \'%s\'',
					$translationGroupClass,
					$translationGroupID,
					$baseDataClass,
					$this->owner->Locale
				) // where
			);
			$langs = $query->execute()->column();
		}
		if($langs) {
			$langCodes = array_values($langs);
			sort($langCodes);
			return $langCodes;
		} else {
			return array();
		};
	}
	
	/**
	 * Gets all locales that a member can access
	 * as defined by {@link $allowed_locales}
	 * and {@link canTranslate()}.
	 * If {@link $allowed_locales} is not set and
	 * the user has the `TRANSLATE_ALL` permission,
	 * the method will return all available locales in the system.
	 * 
	 * @param Member $member
	 * @return array Map of locales
	 */
	function getAllowedLocalesForMember($member) {
		$locales = self::get_allowed_locales();
		if(!$locales) $locales = i18n::get_common_locales();
		if($locales) foreach($locales as $k => $locale) {
			if(!$this->canTranslate($member, $locale)) unset($locales[$k]);
		}

		return $locales;
	}

	/**
	 * Get a list of languages in which a given element has been translated.
	 * 
	 * @deprecated 2.4 Use {@link getTranslations()}
	 *
	 * @param string $class Name of the class of the element
	 * @param int $id ID of the element
	 * @return array List of languages
	 */
	static function get_langs_by_id($class, $id) {
		$do = DataObject::get_by_id($class, $id);
		return ($do ? $do->getTranslatedLocales() : array());
	}

	/**
	 * Enables the multilingual feature
	 *
	 * @deprecated 2.4 Use SiteTree::add_extension('Translatable')
	 */
	static function enable() {
		if(class_exists('SiteTree')) SiteTree::add_extension('Translatable');
	}

	/**
	 * Disable the multilingual feature
	 *
	 * @deprecated 2.4 Use SiteTree::remove_extension('Translatable')
	 */
	static function disable() {
		if(class_exists('SiteTree')) SiteTree::remove_extension('Translatable');
	}
	
	/**
	 * Check whether multilingual support has been enabled
	 *
	 * @deprecated 2.4 Use SiteTree::has_extension('Translatable')
	 * @return boolean True if enabled
	 */
	static function is_enabled() {
		if(class_exists('SiteTree')){
			return SiteTree::has_extension('Translatable');
		}else{
			return false;
		}
	}
	
		
	/**
	 * Construct a new Translatable object.
	 * @var array $translatableFields The different fields of the object that can be translated.
	 * This is currently not implemented, all fields are marked translatable (see {@link setOwner()}).
	 */
	function __construct($translatableFields = null) {
		parent::__construct();

		// @todo Disabled selection of translatable fields - we're setting all fields as 
		// translatable in setOwner()
		/*
		if(!is_array($translatableFields)) {
			$translatableFields = func_get_args();
		}
		$this->translatableFields = $translatableFields;
		*/

		// workaround for extending a method on another decorator (Hierarchy):
		// split the method into two calls, and overwrite the wrapper AllChildrenIncludingDeleted()
		// Has to be executed even with Translatable disabled, as it overwrites the method with same name
		// on Hierarchy class, and routes through to Hierarchy->doAllChildrenIncludingDeleted() instead.
		// Caution: There's an additional method for augmentAllChildrenIncludingDeleted()
	
	}
	
	function setOwner($owner, $ownerBaseClass = null) {
		parent::setOwner($owner, $ownerBaseClass);

		// setting translatable fields by inspecting owner - this should really be done in the constructor
		if($this->owner && $this->translatableFields === null) {
			$this->translatableFields = array_merge(
				array_keys($this->owner->db()),
				array_keys($this->owner->has_many()),
				array_keys($this->owner->many_many()),
				array_keys($this->owner->has_one())
			);
		}
	}

	static function get_extra_config($class, $extensionClass, $args = null) {
		$config = array();
		$config['defaults'] = array(
			"Locale" => Translatable::default_locale() // as an overloaded getter as well: getLang()
		);
		$config['db'] = array(
			"Locale" => "DBLocale",
			//"TranslationMasterID" => "Int" // optional relation to a "translation master"
		);
		return $config;
	}

	/**
	 * Changes any SELECT query thats not filtering on an ID
	 * to limit by the current language defined in {@link get_current_locale()}.
	 * It falls back to "Locale='' OR Lang IS NULL" and assumes that
	 * this implies querying for the default language.
	 * 
	 * Use {@link disable_locale_filter()} to temporarily disable this "auto-filtering".
	 */
	function augmentSQL(SQLQuery &$query, DataQuery &$dataQuery = null) {
		// If the record is saved (and not a singleton), and has a locale,
		// limit the current call to its locale. This fixes a lot of problems
		// with other extensions like Versioned
		if($this->owner->ID && !empty($this->owner->Locale)) {
			$locale = $this->owner->Locale;
		} else {
			$locale = Translatable::get_current_locale();
		}
		
		$baseTable = ClassInfo::baseDataClass($this->owner->class);
		if(
			$locale
			// unless the filter has been temporarily disabled
			&& self::locale_filter_enabled()
			// or it was disabled when the DataQuery was created
			&& $dataQuery->getQueryParam(self::QUERY_LOCALE_FILTER_ENABLED)
			// DataObject::get_by_id() should work independently of language
			&& !$query->filtersOnID() 
			// the query contains this table
			// @todo Isn't this always the case?!
			&& array_search($baseTable, array_keys($query->getFrom())) !== false 
			// or we're already filtering by Lang (either from an earlier augmentSQL() 
			// call or through custom SQL filters)
			&& !preg_match('/("|\'|`)Locale("|\'|`)/', implode(' ', $query->getWhere()))
			//&& !$query->filtersOnFK()
		)  {
			$qry = sprintf('"%s"."Locale" = \'%s\'', $baseTable, Convert::raw2sql($locale));
			$query->addWhere($qry); 
		}
	}

	function augmentDataQueryCreation(SQLQuery &$sqlQuery, DataQuery &$dataQuery) {
		$enabled = self::locale_filter_enabled();
		$dataQuery->setQueryParam(self::QUERY_LOCALE_FILTER_ENABLED, $enabled);
	}
	
	/**
	 * Create <table>_translation database table to enable
	 * tracking of "translation groups" in which each related
	 * translation of an object acts as a sibling, rather than
	 * a parent->child relation.
	 */
	function augmentDatabase() {
		$baseDataClass = ClassInfo::baseDataClass($this->owner->class);
		if($this->owner->class != $baseDataClass) return;
		
		$fields = array(
			'OriginalID' => 'Int', 
			'TranslationGroupID' => 'Int', 
		);
		$indexes = array(
			'OriginalID' => true,
			'TranslationGroupID' => true
		);

		// Add new tables if required
		DB::requireTable("{$baseDataClass}_translationgroups", $fields, $indexes);
		
		// Remove 2.2 style tables
		DB::dontRequireTable("{$baseDataClass}_lang");
		if($this->owner->hasExtension('Versioned')) {
			DB::dontRequireTable("{$baseDataClass}_lang_Live");
			DB::dontRequireTable("{$baseDataClass}_lang_versions");
		}
	}
	
	/**
	 * @todo Find more appropriate place to hook into database building
	 */
	function requireDefaultRecords() {
		// @todo This relies on the Locale attribute being on the base data class, and not any subclasses
		if($this->owner->class != ClassInfo::baseDataClass($this->owner->class)) return false;
		
		// Permissions: If a group doesn't have any specific TRANSLATE_<locale> edit rights,
		// but has CMS_ACCESS_CMSMain (general CMS access), then assign TRANSLATE_ALL permissions as a default.
		// Auto-setting permissions based on these intransparent criteria is a bit hacky,
		// but unavoidable until we can determine when a certain permission code was made available first 
		// (see http://open.silverstripe.org/ticket/4940)
		$groups = Permission::get_groups_by_permission(array(
			'CMS_ACCESS_CMSMain',
			'CMS_ACCESS_LeftAndMain',
			'ADMIN'
		));
		if($groups) foreach($groups as $group) {
			$codes = $group->Permissions()->column('Code');
			$hasTranslationCode = false;
			foreach($codes as $code) {
				if(preg_match('/^TRANSLATE_/', $code)) $hasTranslationCode = true;
			}
			// Only add the code if no more restrictive code exists 
			if(!$hasTranslationCode) Permission::grant($group->ID, 'TRANSLATE_ALL');
		}
		
		// If the Translatable extension was added after the first records were already
		// created in the database, make sure to update the Locale property if
		// if wasn't set before
		$idsWithoutLocale = DB::query(sprintf(
			'SELECT "ID" FROM "%s" WHERE "Locale" IS NULL OR "Locale" = \'\'',
			ClassInfo::baseDataClass($this->owner->class)
		))->column();
		if(!$idsWithoutLocale) return;
		
			if(class_exists('SiteTree') && $this->owner->class == 'SiteTree') {
			foreach(array('Stage', 'Live') as $stage) {
				foreach($idsWithoutLocale as $id) {
					$obj = Versioned::get_one_by_stage(
						$this->owner->class, 
						$stage, 
						sprintf('"SiteTree"."ID" = %d', $id)
					);
					if(!$obj) continue;

					$obj->Locale = Translatable::default_locale();
					$obj->writeToStage($stage);
					$obj->addTranslationGroup($obj->ID);
					$obj->destroy();
					unset($obj);
				}
			}
		} else {
			foreach($idsWithoutLocale as $id) {
				$obj = DataObject::get_by_id($this->owner->class, $id);
				if(!$obj) continue;

				$obj->Locale = Translatable::default_locale();
				$obj->write();
				$obj->addTranslationGroup($obj->ID);
				$obj->destroy();
				unset($obj);
			}
		}
		DB::alteration_message(sprintf(
			"Added default locale '%s' to table %s","changed",
			Translatable::default_locale(),
			$this->owner->class
		));
	}
	
	/**
	 * Add a record to a "translation group",
	 * so its relationship to other translations
	 * based off the same object can be determined later on.
	 * See class header for further comments.
	 * 
	 * @param int $originalID Either the primary key of the record this new translation is based on,
	 *  or the primary key of this record, to create a new translation group
	 * @param boolean $overwrite
	 */
	public function addTranslationGroup($originalID, $overwrite = false) {
		if(!$this->owner->exists()) return false;
		
		$baseDataClass = ClassInfo::baseDataClass($this->owner->class);
		$existingGroupID = $this->getTranslationGroup($originalID);
		
		// Remove any existing groups if overwrite flag is set
		if($existingGroupID && $overwrite) {
			$sql = sprintf(
				'DELETE FROM "%s_translationgroups" WHERE "TranslationGroupID" = %d AND "OriginalID" = %d', 
				$baseDataClass, 
				$existingGroupID,
				$this->owner->ID
			);
			DB::query($sql);
			$existingGroupID = null;
		}
		
		// Add to group (only if not in existing group or $overwrite flag is set)
		if(!$existingGroupID) {
			$sql = sprintf(
				'INSERT INTO "%s_translationgroups" ("TranslationGroupID","OriginalID") VALUES (%d,%d)', 
				$baseDataClass, 
				$originalID, 
				$this->owner->ID
			);
			DB::query($sql);
		}
	}
	
	/**
	 * Gets the translation group for the current record.
	 * This ID might equal the record ID, but doesn't have to -
	 * it just points to one "original" record in the list.
	 * 
	 * @return int Numeric ID of the translationgroup in the <classname>_translationgroup table
	 */
	public function getTranslationGroup() {
		if(!$this->owner->exists()) return false;
		
		$baseDataClass = ClassInfo::baseDataClass($this->owner->class);
		return DB::query(
			sprintf(
				'SELECT "TranslationGroupID" FROM "%s_translationgroups" WHERE "OriginalID" = %d', 
				$baseDataClass, 
				$this->owner->ID
			)
		)->value();
	}
	
	/**
	 * Removes a record from the translation group lookup table.
	 * Makes no assumptions on other records in the group - meaning
	 * if this happens to be the last record assigned to the group,
	 * this group ceases to exist.
	 */
	public function removeTranslationGroup() {
		$baseDataClass = ClassInfo::baseDataClass($this->owner->class);
		DB::query(
			sprintf('DELETE FROM "%s_translationgroups" WHERE "OriginalID" = %d', $baseDataClass, $this->owner->ID)
		);
	}
	
	/**
	 * Determine if a table needs Versioned support
	 * This is called at db/build time
	 *
	 * @param string $table Table name
	 * @return boolean
	 */
	function isVersionedTable($table) {
		return false;
	}

	/**
	 * Note: The bulk of logic is in ModelAsController->getNestedController()
	 * and ContentController->handleRequest()
	 */
	function contentcontrollerInit($controller) {
		$controller->Locale = Translatable::choose_site_locale();
	}
	
	function modelascontrollerInit($controller) {
		//$this->contentcontrollerInit($controller);
	}
	
	function initgetEditForm($controller) {
		$this->contentcontrollerInit($controller);
	}

	/**
	 * Recursively creates translations for parent pages in this language
	 * if they aren't existing already. This is a necessity to make
	 * nested pages accessible in a translated CMS page tree.
	 * It would be more userfriendly to grey out untranslated pages,
	 * but this involves complicated special cases in AllChildrenIncludingDeleted().
	 * 
	 * {@link SiteTree->onBeforeWrite()} will ensure that each translation will get
	 * a unique URL across languages, by means of {@link SiteTree::get_by_link()}
	 * and {@link Translatable->alternateGetByURL()}.
	 */
	function onBeforeWrite() {
		// If language is not set explicitly, set it to current_locale.
		// This might be a bit overzealous in assuming the language
		// of the content, as a "single language" website might be expanded
		// later on. See {@link requireDefaultRecords()} for batch setting
		// of empty Locale columns on each dev/build call.
		if(!$this->owner->Locale) {
			$this->owner->Locale = Translatable::get_current_locale();
		}

		// Specific logic for SiteTree subclasses.
		// If page has untranslated parents, create (unpublished) translations
		// of those as well to avoid having inaccessible children in the sitetree.
		// Caution: This logic is very sensitve to infinite loops when translation status isn't determined properly
		// If a parent for the newly written translation was existing before this
		// onBeforeWrite() call, it will already have been linked correctly through createTranslation()
		if(
			class_exists('SiteTree') 
			&& $this->owner->hasField('ParentID') 
			&& $this->owner instanceof SiteTree
		) {
			if(
				!$this->owner->ID 
				&& $this->owner->ParentID 
				&& !$this->owner->Parent()->hasTranslation($this->owner->Locale)
			) {
				$parentTranslation = $this->owner->Parent()->createTranslation($this->owner->Locale);
				$this->owner->ParentID = $parentTranslation->ID;
			}
		}
		
		// Has to be limited to the default locale, the assumption is that the "page type"
		// dropdown is readonly on all translations.
		if($this->owner->ID && $this->owner->Locale == Translatable::default_locale()) {
			$changedFields = $this->owner->getChangedFields();
			$changed = isset($changedFields['ClassName']);

			if ($changed && $this->owner->hasExtension('Versioned')) {
				// this is required because when publishing a node the before/after
				// values of $changedFields['ClassName'] will be the same because
				// the record was already written to the stage/draft table and thus
				// the record was updated, and then publish('Stage', 'Live') is
				// called, which uses forceChange, which will make all the fields
				// act as though they are changed, although the before/after values
				// will be the same
				// So, we load one from the current stage and test against it
				// This is to prevent the overhead of writing all translations when
				// the class didn't actually change.
				$baseDataClass = ClassInfo::baseDataClass($this->owner->class);
				$currentStage = Versioned::current_stage();
				$fresh = Versioned::get_one_by_stage(
					$baseDataClass,
					Versioned::current_stage(),
					'"ID" = ' . $this->owner->ID,
					null
				);
				if ($fresh) {
					$changed = $changedFields['ClassName']['after'] != $fresh->ClassName;
				}
			}

			if($changed) {
				$this->owner->ClassName = $changedFields['ClassName']['before'];
				$translations = $this->owner->getTranslations();
				$this->owner->ClassName = $changedFields['ClassName']['after'];
				if($translations) foreach($translations as $translation) {
					$translation->setClassName($this->owner->ClassName);
					$translation = $translation->newClassInstance($translation->ClassName);
					$translation->populateDefaults();
					$translation->forceChange();
					$translation->write();
				}
			}
		}		
		
		// see onAfterWrite()
		if(!$this->owner->ID) {
			$this->owner->_TranslatableIsNewRecord = true;
		}
	}
	
	function onAfterWrite() {
		// hacky way to determine if the record was created in the database,
		// or just updated
		if($this->owner->_TranslatableIsNewRecord) {
			// this would kick in for all new records which are NOT
			// created through createTranslation(), meaning they don't
			// have the translation group automatically set.
			$translationGroupID = $this->getTranslationGroup();
			if(!$translationGroupID) {
				$this->addTranslationGroup(
					$this->owner->_TranslationGroupID ? $this->owner->_TranslationGroupID : $this->owner->ID
				);
			}
			unset($this->owner->_TranslatableIsNewRecord);
			unset($this->owner->_TranslationGroupID);
		}
		
	}
	
	/**
	 * Remove the record from the translation group mapping.
	 */
	function onBeforeDelete() {
		// @todo Coupling to Versioned, we need to avoid removing
		// translation groups if records are just deleted from a stage
		// (="unpublished"). Ideally the translation group tables would
		// be specific to different Versioned changes, making this restriction unnecessary.
		// This will produce orphaned translation group records for SiteTree subclasses.
		if(!$this->owner->hasExtension('Versioned')) {
			$this->removeTranslationGroup();
		}

		parent::onBeforeDelete();
	}
	
	/**
	 * Attempt to get the page for a link in the default language that has been translated.
	 *
	 * @param string $URLSegment
	 * @param int|null $parentID
	 * @return SiteTree
	 */
	public function alternateGetByLink($URLSegment, $parentID) {
		// If the parentID value has come from a translated page, 
		// then we need to find the corresponding parentID value
		// in the default Locale.
		if (
			is_int($parentID)
			&& $parentID > 0
			&& ($parent = DataObject::get_by_id('SiteTree', $parentID))
			&& ($parent->isTranslation())
		) {
			$parentID = $parent->getTranslationGroup();
		}
		
		// Find the locale language-independent of the page
		self::disable_locale_filter();
		$default = SiteTree::get()->where(sprintf (
			'"URLSegment" = \'%s\'%s',
			Convert::raw2sql($URLSegment),
			(is_int($parentID) ? " AND \"ParentID\" = $parentID" : null)
		))->First();
		self::enable_locale_filter();
		
		return $default;
	}
	
	//-----------------------------------------------------------------------------------------------//
	
	function applyTranslatableFieldsUpdate($fields, $type) {
		if (method_exists($this, $type)) {
			$this->$type($fields);
		} else {
			throw new InvalidArgumentException("Method $type does not exist on object of type ".  get_class($this));
		}
	}
	
	/**
	 * If the record is not shown in the default language, this method
	 * will try to autoselect a master language which is shown alongside
	 * the normal formfields as a readonly representation.
	 * This gives translators a powerful tool for their translation workflow
	 * without leaving the translated page interface.
	 * Translatable also adds a new tab "Translation" which shows existing
	 * translations, as well as a formaction to create new translations based
	 * on a dropdown with available languages.
	 *
	 * This method can be called multiple times on the same FieldList
	 * because it checks which fields have already been added or modified.
	 * 
	 * @todo This is specific to SiteTree and CMSMain
	 * @todo Implement a special "translation mode" which triggers display of the
	 * readonly fields, so you can translation INTO the "default language" while
	 * seeing readonly fields as well.
	 */
	function updateCMSFields(FieldList $fields) {
		$this->addTranslatableFields($fields);
		
		// Show a dropdown to create a new translation.
		// This action is possible both when showing the "default language"
		// and a translation. Include the current locale (record might not be saved yet).
		$alreadyTranslatedLocales = $this->getTranslatedLocales();
		$alreadyTranslatedLocales[$this->owner->Locale] = $this->owner->Locale;
		$alreadyTranslatedLocales = array_combine($alreadyTranslatedLocales, $alreadyTranslatedLocales);

		// Check if fields exist already to avoid adding them twice on repeat invocations
		$tab = $fields->findOrMakeTab('Root.Translations', _t('Translatable.TRANSLATIONS', 'Translations'));
		if(!$tab->fieldByName('CreateTransHeader')) {
			$tab->push(new HeaderField(
				'CreateTransHeader', 
				_t('Translatable.CREATE', 'Create new translation'), 
				2
			));
		}
		if(!$tab->fieldByName('NewTransLang') && !$tab->fieldByName('AllTransCreated')) {
			$langDropdown = LanguageDropdownField::create(
				"NewTransLang", 
				_t('Translatable.NEWLANGUAGE', 'New language'), 
				$alreadyTranslatedLocales,
				'SiteTree',
				'Locale-English',
				$this->owner
			)->addExtraClass('languageDropdown no-change-track');
			$tab->push($langDropdown);
			$canAddLocale = (count($langDropdown->getSource()) > 0);

			if($canAddLocale) {
				// Only add create button if new languages are available
				$tab->push(
					$createButton = InlineFormAction::create(
						'createtranslation',
						_t('Translatable.CREATEBUTTON', 'Create')
					)->addExtraClass('createTranslationButton')
				);
				$createButton->includeDefaultJS(false); // not fluent API...
			} else {
				$tab->removeByName('NewTransLang');
				$tab->push(new LiteralField(
					'AllTransCreated', 
					_t('Translatable.ALLCREATED', 'All allowed translations have been created.')
				));
			}
		}
		if($alreadyTranslatedLocales) {
			if(!$tab->fieldByName('ExistingTransHeader')) {
				$tab->push(new HeaderField(
					'ExistingTransHeader', 
					_t('Translatable.EXISTING', 'Existing translations'), 
					3
				));
				if (!$tab->fieldByName('existingtrans')) {
					$existingTransHTML = '<ul>';
					if ($existingTranslations = $this->getTranslations()) {
						foreach ($existingTranslations as $existingTranslation) {
							if ($existingTranslation && $existingTranslation->hasMethod('CMSEditLink')) {
								$existingTransHTML .= sprintf(
									'<li><a href="%s">%s</a></li>',
									Controller::join_links(
										$existingTranslation->CMSEditLink(),
										'?locale=' . $existingTranslation->Locale
									),
									i18n::get_locale_name($existingTranslation->Locale)
								);
							}
						}
					}
					$existingTransHTML .= '</ul>';
					$tab->push(new LiteralField('existingtrans', $existingTransHTML));
				}
			}
		}		
	}
	
	function updateSettingsFields(&$fields) {
		$this->addTranslatableFields($fields);
	}

	public function updateRelativeLink(&$base, &$action) {
		// Prevent home pages for non-default locales having their urlsegments
		// reduced to the site root.
		if($base === null && $this->owner->Locale != self::default_locale()){ 
			$base = $this->owner->URLSegment; 
		}
	}

	/**
	 * This method can be called multiple times on the same FieldList
	 * because it checks which fields have already been added or modified.
	 */
	protected function addTranslatableFields(&$fields) {
		// used in LeftAndMain->init() to set language state when reading/writing record
		$fields->push(new HiddenField("Locale", "Locale", $this->owner->Locale));
		
		// Don't apply these modifications for normal DataObjects - they rely on CMSMain logic
		if(!class_exists('SiteTree')) return;
		if(!($this->owner instanceof SiteTree)) return;
		
		// Don't allow translation of virtual pages because of data inconsistencies (see #5000)
		if(class_exists('VirtualPage')){
			$excludedPageTypes = array('VirtualPage');
			foreach($excludedPageTypes as $excludedPageType) {
				if(is_a($this->owner, $excludedPageType)) return;
			}
		}
		
		// TODO Remove hardcoding for SiteTree properties
		$excludeFields = array(
			'ViewerGroups',
			'EditorGroups',
			'CanViewType',
			'CanEditType',
			'NewTransLang',
			'createtranslation'
		);

		// if a language other than default language is used, we're in "translation mode",
		// hence have to modify the original fields
		$baseClass = $this->owner->class;
		while( ($p = get_parent_class($baseClass)) != "DataObject") $baseClass = $p;

		// try to get the record in "default language"
		$originalRecord = $this->owner->getTranslation(Translatable::default_locale());
		// if no translation in "default language", fall back to first translation
		if(!$originalRecord) {
			$translations = $this->owner->getTranslations();
			$originalRecord = ($translations) ? $translations->First() : null;
		}
		
		$isTranslationMode = $this->owner->Locale != Translatable::default_locale();
		
		if($originalRecord && $isTranslationMode) {
			// Remove parent page dropdown
			$fields->removeByName("ParentType");
			$fields->removeByName("ParentID");
			
			$translatableFieldNames = $this->getTranslatableFields();
			$allDataFields = $fields->dataFields();
			
			$transformation = new Translatable_Transformation($originalRecord);
			
			// iterate through sequential list of all datafields in fieldset
			// (fields are object references, so we can replace them with the translatable CompositeField)
			foreach($allDataFields as $dataField) {
				// Transformation is a visual helper for CMS authors, so ignore hidden fields
				if($dataField instanceof HiddenField) continue;
				// Some fields are explicitly excluded from transformation
				if(in_array($dataField->getName(), $excludeFields)) continue;
				// Readonly field which has been added previously
				if(preg_match('/_original$/', $dataField->getName())) continue;
				// Field already has been transformed
				if(isset($allDataFields[$dataField->getName() . '_original'])) continue;
				// CheckboxField which is already transformed
				if(preg_match('/class=\"originalvalue\"/', $dataField->Title())) continue;
				
				if(in_array($dataField->getName(), $translatableFieldNames)) {
					// if the field is translatable, perform transformation
					$fields->replaceField($dataField->getName(), $transformation->transformFormField($dataField));
				} elseif(!$dataField->isReadonly()) {
					// else field shouldn't be editable in translation-mode, make readonly
					$fields->replaceField($dataField->getName(), $dataField->performReadonlyTransformation());
				}
			}
			
		} elseif($this->owner->isNew()) {
			$fields->addFieldsToTab(
				'Root',
				new Tab(_t('Translatable.TRANSLATIONS', 'Translations'),
					new LiteralField('SaveBeforeCreatingTranslationNote',
						sprintf('<p class="message">%s</p>',
							_t('Translatable.NOTICENEWPAGE', 'Please save this page before creating a translation')
						)
					)
				)
			);
		}
	}
		
	/**
	 * Get the names of all translatable fields on this class as a numeric array.
	 * @todo Integrate with blacklist once branches/translatable is merged back.
	 * 
	 * @return array
	 */
	function getTranslatableFields() {
		return $this->translatableFields;
	}
		
	/**
	 * Return the base table - the class that directly extends DataObject.
	 * @return string
	 */
	function baseTable($stage = null) {
		$tableClasses = ClassInfo::dataClassesFor($this->owner->class);
		$baseClass = array_shift($tableClasses);
		return (!$stage || $stage == $this->defaultStage) ? $baseClass : $baseClass . "_$stage";		
	}
	
	function extendWithSuffix($table) { 
		return $table; 
	}
		
	/**
	 * Gets all related translations for the current object,
	 * excluding itself. See {@link getTranslation()} to retrieve
	 * a single translated object.
	 * 
	 * Getter with $stage parameter is specific to {@link Versioned} extension,
	 * mostly used for {@link SiteTree} subclasses.
	 * 
	 * @param string $locale
	 * @param string $stage 
	 * @return DataObjectSet
	 */
	function getTranslations($locale = null, $stage = null) {
		if($locale && !i18n::validate_locale($locale)) {
			throw new InvalidArgumentException(sprintf('Invalid locale "%s"', $locale));
		}
		
		if(!$this->owner->exists()) return new ArrayList();

		// HACK need to disable language filtering in augmentSQL(), 
		// as we purposely want to get different language
		// also save state of locale-filter, revert to this state at the
		// end of this method
		$localeFilterEnabled = false;
		if(self::locale_filter_enabled()) {
			self::disable_locale_filter();
			$localeFilterEnabled = true;
		}

		$translationGroupID = $this->getTranslationGroup();
		
		$baseDataClass = ClassInfo::baseDataClass($this->owner->class);
		$filter = sprintf('"%s_translationgroups"."TranslationGroupID" = %d', $baseDataClass, $translationGroupID);
		if($locale) {
			$filter .= sprintf(' AND "%s"."Locale" = \'%s\'', $baseDataClass, Convert::raw2sql($locale));
		} else {
			// exclude the language of the current owner
			$filter .= sprintf(' AND "%s"."Locale" != \'%s\'', $baseDataClass, $this->owner->Locale);
		}
		$currentStage = Versioned::current_stage();
		$joinOnClause = sprintf('"%s_translationgroups"."OriginalID" = "%s"."ID"', $baseDataClass, $baseDataClass);
		if($this->owner->hasExtension("Versioned")) {
			if($stage) Versioned::reading_stage($stage);
			$translations = Versioned::get_by_stage(
				$baseDataClass,
				Versioned::current_stage(), 
				$filter, 
				null
			)->leftJoin("{$baseDataClass}_translationgroups", $joinOnClause);
			if($stage) Versioned::reading_stage($currentStage);
		} else {
			$class = $this->owner->class;
			$translations = $baseDataClass::get()
				->where($filter)
				->leftJoin("{$baseDataClass}_translationgroups", $joinOnClause);
		}

		// only re-enable locale-filter if it was enabled at the beginning of this method
		if($localeFilterEnabled) {
			self::enable_locale_filter();
		}

		return $translations;
	}
	
	/**
	 * Gets an existing translation based on the language code.
	 * Use {@link hasTranslation()} as a quicker alternative to check
	 * for an existing translation without getting the actual object.
	 * 
	 * @param String $locale
	 * @return DataObject Translated object
	 */
	function getTranslation($locale, $stage = null) {
		if($locale && !i18n::validate_locale($locale)) {
			throw new InvalidArgumentException(sprintf('Invalid locale "%s"', $locale));
		}
		
		$translations = $this->getTranslations($locale, $stage);
		return ($translations) ? $translations->First() : null;
	}
	
	/**
	 * When the SiteConfig object is automatically instantiated, we should ensure that
	 * 1. All SiteConfig objects belong to the same group
	 * 2. Defaults are correctly initiated from the base object
	 * 3. The creation mechanism uses the createTranslation function in order to be consistent
	 * This function ensures that any already created "vanilla" SiteConfig object is populated 
	 * correctly with translated values.
	 * This function DOES populate the ID field with the newly created object ID
	 * @see SiteConfig
	 */
	protected function populateSiteConfigDefaults() {
		
		// Work-around for population of defaults during database initialisation.
		// When the database is being setup singleton('SiteConfig') is called.
		if(!DB::getConn()->hasTable($this->owner->class)) return;
		if(!DB::getConn()->hasField($this->owner->class, 'Locale')) return;
		if(DB::getConn()->isSchemaUpdating()) return;
		
		// Find the best base translation for SiteConfig
		$enabled = Translatable::locale_filter_enabled();
		Translatable::disable_locale_filter();
		$existingConfig = SiteConfig::get()->filter(array(
			'Locale' => Translatable::default_locale()
		))->first();
		if(!$existingConfig) $existingConfig = SiteConfig::get()->first();
		if ($enabled) {
			Translatable::enable_locale_filter();
		}

		// Stage this SiteConfig and copy into the current object
		if(
			$existingConfig
			// Double-up of SiteConfig in the same locale can be ignored. Often caused by singleton(SiteConfig)	
			&& !$existingConfig->getTranslation(Translatable::get_current_locale())
			// If translation is not allowed by the current user then do not 
			// allow this code to attempt any behind the scenes translation.
			&& $existingConfig->canTranslate(null, Translatable::get_current_locale())
		) {
			// Create an unsaved "staging" translated object using the correct createTranslation mechanism
			$stagingConfig = $existingConfig->createTranslation(Translatable::get_current_locale(), false);
			$this->owner->update($stagingConfig->toMap());
		}
		
		// Maintain single translation group for SiteConfig
		if($existingConfig) {
			$this->owner->_TranslationGroupID = $existingConfig->getTranslationGroup();
		}
		
		$this->owner->Locale = Translatable::get_current_locale();
	}
	
	/**
	 * Enables automatic population of SiteConfig fields using createTranslation if
	 * created outside of the Translatable module
	 * @var boolean
	 */
	public static $enable_siteconfig_generation = true;

	/**
	 * Hooks into the DataObject::populateDefaults() method 
	 */
	public function populateDefaults() {
		if (
			empty($this->owner->ID) 
			&& ($this->owner instanceof SiteConfig) 
			&& self::$enable_siteconfig_generation
		) {
			// Use enable_siteconfig_generation to prevent infinite loop during object creation
			self::$enable_siteconfig_generation = false;
			$this->populateSiteConfigDefaults();
			self::$enable_siteconfig_generation = true;
		}
	}
	
	/**
	 * Creates a new translation for the owner object of this decorator.
	 * Checks {@link getTranslation()} to return an existing translation
	 * instead of creating a duplicate. Writes the record to the database before
	 * returning it. Use this method if you want the "translation group"
	 * mechanism to work, meaning that an object knows which group of translations
	 * it belongs to. For "original records" which are not created through this
	 * method, the "translation group" is set in {@link onAfterWrite()}.
	 * 
	 * @param string $locale Target locale to translate this object into
	 * @param boolean $saveTranslation Flag indicating whether the new record 
	 * should be saved to the database.
	 * @return DataObject The translated object
	 */
	function createTranslation($locale, $saveTranslation = true) {
		if($locale && !i18n::validate_locale($locale)) {
			throw new InvalidArgumentException(sprintf('Invalid locale "%s"', $locale));
		}
		
		if(!$this->owner->exists()) {
			user_error(
				'Translatable::createTranslation(): Please save your record before creating a translation', 
				E_USER_ERROR
			);
		}
		
		// permission check
		if(!$this->owner->canTranslate(null, $locale)) {
			throw new Exception(sprintf(
				'Creating a new translation in locale "%s" is not allowed for this user',
				$locale
			));
			return;
		}
		
		$existingTranslation = $this->getTranslation($locale);
		if($existingTranslation) return $existingTranslation;
		
		$class = $this->owner->class;
		$newTranslation = new $class;
		
		// copy all fields from owner (apart from ID)
		$newTranslation->update($this->owner->toMap());
		
		// If the object has Hierarchy extension,
		// check for existing translated parents and assign
		// their ParentID (and overwrite any existing ParentID relations
		// to parents in other language). If no parent translations exist,
		// they are automatically created in onBeforeWrite()
		if($newTranslation->hasField('ParentID')) {
			$origParent = $this->owner->Parent();
			$newTranslationParent = $origParent->getTranslation($locale);
			if($newTranslationParent) $newTranslation->ParentID = $newTranslationParent->ID;
		}
		
		$newTranslation->ID = 0;
		$newTranslation->Locale = $locale;
		
		$originalPage = $this->getTranslation(self::default_locale());
		if ($originalPage) {
			$urlSegment = $originalPage->URLSegment;
		} else {
			$urlSegment = $newTranslation->URLSegment;
		}

		// Only make segment unique if it should be enforced
		if(Config::inst()->get('Translatable', 'enforce_global_unique_urls')) {
			$newTranslation->URLSegment = $urlSegment . '-' . i18n::convert_rfc1766($locale);	
		}
		
		// hacky way to set an existing translation group in onAfterWrite()
		$translationGroupID = $this->getTranslationGroup();
		$newTranslation->_TranslationGroupID = $translationGroupID ? $translationGroupID : $this->owner->ID;
		if($saveTranslation) $newTranslation->write();
		
		// run callback on page for translation related hooks
		$newTranslation->invokeWithExtensions('onTranslatableCreate', $saveTranslation);
		
		return $newTranslation;
	}
	
	/**
	 * Caution: Does not consider the {@link canEdit()} permissions.
	 * 
	 * @param DataObject|int $member
	 * @param string $locale
	 * @return boolean
	 */
	function canTranslate($member = null, $locale) {
		if($locale && !i18n::validate_locale($locale)) {
			throw new InvalidArgumentException(sprintf('Invalid locale "%s"', $locale));
		}
		
		if(!$member || !(is_a($member, 'Member')) || is_numeric($member)) $member = Member::currentUser();

		// check for locale
		$allowedLocale = (
			!is_array(self::get_allowed_locales()) 
			|| in_array($locale, self::get_allowed_locales())
		);

		if(!$allowedLocale) return false;
		
		// By default, anyone who can edit a page can edit the default locale
		if($locale == self::default_locale()) return true;
		
		// check for generic translation permission
		if(Permission::checkMember($member, 'TRANSLATE_ALL')) return true;
		
		// check for locale specific translate permission
		if(!Permission::checkMember($member, 'TRANSLATE_' . $locale)) return false;
		
		return true;
	}
	
	/**
	 * @return boolean
	 */
	function canEdit($member) {
		if(!$this->owner->Locale) return null;
		return $this->owner->canTranslate($member, $this->owner->Locale) ? null : false;
	}
	
	/**
	 * Returns TRUE if the current record has a translation in this language.
	 * Use {@link getTranslation()} to get the actual translated record from
	 * the database.
	 * 
	 * @param string $locale
	 * @return boolean
	 */
	function hasTranslation($locale) {
		if($locale && !i18n::validate_locale($locale)) {
			throw new InvalidArgumentException(sprintf('Invalid locale "%s"', $locale));
		}
		
		return (
			$this->owner->Locale == $locale
			|| array_search($locale, $this->getTranslatedLocales()) !== false
		);
	}
	
	function AllChildrenIncludingDeleted($context = null) {
		$children = $this->owner->doAllChildrenIncludingDeleted($context);
		
		return $children;
	}
	
	/**
	 * Returns <link rel="alternate"> markup for insertion into
	 * a HTML4/XHTML compliant <head> section, listing all available translations
	 * of a page.
	 * 
	 * @see http://www.w3.org/TR/html4/struct/links.html#edef-LINK
	 * @see http://www.w3.org/International/articles/language-tags/
	 * 
	 * @return string HTML
	 */
	function MetaTags(&$tags) {
		$template = '<link rel="alternate" type="text/html" title="%s" hreflang="%s" href="%s" />' . "\n";
		$translations = $this->owner->getTranslations();
		if($translations) {
			$translations = $translations->toArray();
			$translations[] = $this->owner;
			
		foreach($translations as $translation) {
			$tags .= sprintf($template,
				Convert::raw2xml($translation->Title),
				i18n::convert_rfc1766($translation->Locale),
				$translation->AbsoluteLink()
				);
			}
		}
	}
	
	function providePermissions() {
		if(!SiteTree::has_extension('Translatable') || !class_exists('SiteTree')) return false;
		
		$locales = self::get_allowed_locales();
		
		// Fall back to any locales used in existing translations (see #4939)
		if(!$locales) {
			$locales = DB::query('SELECT "Locale" FROM "SiteTree" GROUP BY "Locale"')->column();
		}
		
		$permissions = array();
		if($locales) foreach($locales as $locale) {
			$localeName = i18n::get_locale_name($locale);
			$permissions['TRANSLATE_' . $locale] = sprintf(
				_t(
					'Translatable.TRANSLATEPERMISSION', 
					'Translate %s', 
					'Translate pages into a language'
				),
				$localeName
			);
		}
		
		$permissions['TRANSLATE_ALL'] = _t(
			'Translatable.TRANSLATEALLPERMISSION', 
			'Translate into all available languages'
		);
		
		$permissions['VIEW_LANGS'] = _t(
			'Translatable.TRANSLATEVIEWLANGS', 
			'View language dropdown'
		);
		
		return $permissions;
	}
	
	/**
	 * Get a list of languages with at least one element translated in (including the default language)
	 *
	 * @param string $className Look for languages in elements of this class
	 * @param string $where Optional SQL WHERE statement
	 * @return array Map of languages in the form locale => langName
	 */
	static function get_existing_content_languages($className = 'SiteTree', $where = '') {
		$baseTable = ClassInfo::baseDataClass($className);
		$query = new SQLQuery("Distinct \"Locale\"","\"$baseTable\"",$where, '', "\"Locale\"");
		$dbLangs = $query->execute()->column();
		$langlist = array_merge((array)Translatable::default_locale(), (array)$dbLangs);
		$returnMap = array();
		$allCodes = array_merge(
			Config::inst()->get('i18n', 'all_locales'), 
			Config::inst()->get('i18n', 'common_locales')
		);
		foreach ($langlist as $langCode) {
			if($langCode && isset($allCodes[$langCode])) {
				if(is_array($allCodes[$langCode])) {
					$returnMap[$langCode] = $allCodes[$langCode]['name'];
				} else {
					$returnMap[$langCode] = $allCodes[$langCode];
				}
			}
		}
		return $returnMap;
	}
	
	/**
	 * Get the RelativeLink value for a home page in another locale. This is found by searching for the default home
	 * page in the default language, then returning the link to the translated version (if one exists).
	 *
	 * @return string
	 */
	public static function get_homepage_link_by_locale($locale) {
		$originalLocale = self::get_current_locale();

		self::set_current_locale(self::default_locale());
		$original = SiteTree::get_by_link(RootURLController::config()->default_homepage_link);
		self::set_current_locale($originalLocale);

		if($original) {
			if($translation = $original->getTranslation($locale)) return trim($translation->RelativeLink(true), '/');
		}
	}
	
	
	/**
	 * @deprecated 2.4 Use {@link Translatable::get_homepage_link_by_locale()}
	 */
	static function get_homepage_urlsegment_by_locale($locale) {
		user_error (
			'Translatable::get_homepage_urlsegment_by_locale() is deprecated, please use get_homepage_link_by_locale()',
			E_USER_NOTICE
		);
		
		return self::get_homepage_link_by_locale($locale);
	}
	
	/**
	 * Define all locales which in which a new translation is allowed.
	 * Checked in {@link canTranslate()}.
	 *
	 * @param array List of allowed locale codes (see {@link i18n::$all_locales}).
	 *  Example: array('de_DE','ja_JP')
	 */
	static function set_allowed_locales($locales) {
		self::$allowed_locales = $locales;
	}
	
	/**
	 * Get all locales which are generally permitted to be translated.
	 * Use {@link canTranslate()} to check if a specific member has permission
	 * to translate a record.
	 * 
	 * @return array
	 */
	static function get_allowed_locales() {
		return self::$allowed_locales;
	}
	
	/**
	 * @deprecated 2.4 Use get_homepage_urlsegment_by_locale()
	 */
	static function get_homepage_urlsegment_by_language($locale) {
		return self::get_homepage_urlsegment_by_locale($locale);
	}
	
	/**
	 * @deprecated 2.4 Use custom check: self::$default_locale == self::get_current_locale()
	 */
	static function is_default_lang() {
		return (self::$default_locale == self::get_current_locale());
	}
	
	/**
	 * @deprecated 2.4 Use set_default_locale()
	 */
	static function set_default_lang($lang) {
		self::set_default_locale(i18n::get_locale_from_lang($lang));
	}
	
	/**
	 * @deprecated 2.4 Use get_default_locale()
	 */
	static function get_default_lang() {
		return i18n::get_lang_from_locale(self::default_locale());
	}
	
	/**
	 * @deprecated 2.4 Use get_current_locale()
	 */
	static function current_lang() {
		return i18n::get_lang_from_locale(self::get_current_locale());
	}
	
	/**
	 * @deprecated 2.4 Use set_current_locale()
	 */
	static function set_reading_lang($lang) {
		self::set_current_locale(i18n::get_locale_from_lang($lang));
	}
	
	/**
	 * @deprecated 2.4 Use get_reading_locale()
	 */
	static function get_reading_lang() {
		return i18n::get_lang_from_locale(self::get_reading_locale());
	}
	
	/**
	 * @deprecated 2.4 Use default_locale()
	 */
	static function default_lang() {
		return i18n::get_lang_from_locale(self::default_locale());
	}
	
	/**
	 * @deprecated 2.4 Use get_by_locale()
	 */
	static function get_by_lang($class, $lang, $filter = '', $sort = '', 
		$join = "", $limit = "", $containerClass = "DataObjectSet", $having = ""
	) {
		return self::get_by_locale(
			$class, i18n::get_locale_from_lang($lang), $filter, 
			$sort, $join, $limit, $containerClass, $having
		);
	}
	
	/**
	 * @deprecated 2.4 Use get_one_by_locale()
	 */
	static function get_one_by_lang($class, $lang, $filter = '', $cache = false, $orderby = "") {
		return self::get_one_by_locale($class, i18n::get_locale_from_lang($lang), $filter, $cache, $orderby);
	}
	
	/**
	 * Determines if the record has a locale,
	 * and if this locale is different from the "default locale"
	 * set in {@link Translatable::default_locale()}.
	 * Does not look at translation groups to see if the record
	 * is based on another record.
	 * 
	 * @return boolean
	 * @deprecated 2.4
	 */
	function isTranslation() { 
		return ($this->owner->Locale && ($this->owner->Locale != Translatable::default_locale())); 
	}
	
	/**
	 * @deprecated 2.4 Use choose_site_locale()
	 */
	static function choose_site_lang($langsAvail=null) {
		return self::choose_site_locale($langsAvail);
	}
	
	/**
	 * @deprecated 2.4 Use getTranslatedLocales()
	 */
	function getTranslatedLangs() {
		return $this->getTranslatedLocales();
	}

	/**
	 * Return a piece of text to keep DataObject cache keys appropriately specific
	 */
	function cacheKeyComponent() {
		return 'locale-'.self::get_current_locale();
	}
	
	/**
	 * Extends the SiteTree::validURLSegment() method, to do checks appropriate
	 * to Translatable
	 * 
	 * @return bool
     */
	public function augmentValidURLSegment() {
		$reEnableFilter = false;
		if(!Config::inst()->get('Translatable', 'enforce_global_unique_urls')) {
			self::enable_locale_filter();
		} elseif(self::locale_filter_enabled()) {
			self::disable_locale_filter();
			$reEnableFilter = true;
		}

		$IDFilter = ($this->owner->ID) ? "AND \"SiteTree\".\"ID\" <> {$this->owner->ID}" :  null;
		$parentFilter = null;

			if($this->owner->ParentID) {
				$parentFilter = " AND \"SiteTree\".\"ParentID\" = {$this->owner->ParentID}";
			} else {
				$parentFilter = ' AND "SiteTree"."ParentID" = 0';
			}

		$existingPage = SiteTree::get()
			// disable get_one cache, as this otherwise may pick up results from when locale_filter was on
			->where("\"URLSegment\" = '{$this->owner->URLSegment}' $IDFilter $parentFilter")->First();
		if($reEnableFilter) self::enable_locale_filter();
		
		// By returning TRUE or FALSE, we overrule the base SiteTree->validateURLSegment() logic
		return !$existingPage;
	}
		
}

/**
 * Transform a formfield to a "translatable" representation,
 * consisting of the original formfield plus a readonly-version
 * of the original value, wrapped in a CompositeField.
 * 
 * @param DataObject $original Needs the original record as we populate 
 *                   the readonly formfield with the original value
 * 
 * @package translatable
 * @subpackage misc
 */
class Translatable_Transformation extends FormTransformation {
	
	/**
	 * @var DataObject
	 */
	private $original = null;
	
	function __construct(DataObject $original) {
		$this->original = $original;
		parent::__construct();
	}
	
	/**
	 * Returns the original DataObject attached to the Transformation
	 *
	 * @return DataObject
	 */
	function getOriginal() {
		return $this->original;
	}
	
	public function transformFormField(FormField $field) {
		$newfield = $field->performReadOnlyTransformation();
		$fn = 'transform' . $field->class;
		return $this->hasMethod($fn) ? $this->$fn($newfield, $field) : $this->baseTransform($newfield, $field);
	}
	
	/**
	 * Transform a translatable CheckboxField to show the field value from the default language
	 * in the label.
	 * 
	 * @param FormField $nonEditableField The readonly field to contain the original value
	 * @param FormField $originalField The original editable field containing the translated value
	 * @return CheckboxField The field with a modified label
	 */
	protected function transformCheckboxField(CheckboxField $nonEditableField, CheckboxField $originalField) {
		$label = $originalField->Title();
		$fieldName = $originalField->getName();
		$value = ($this->original->$fieldName) 
			? _t('Translatable_Transform.CheckboxValueYes', 'Yes') 
			: _t('Translatable_Transform.CheckboxValueNo', 'No');
		$originalLabel = _t(
			'Translatable_Transform.OriginalCheckboxLabel', 
			'Original: {value}',
			'Addition to a checkbox field label showing the original value of the translatable field.',
			array('value'=>$value)
		);
		$originalField->setTitle($label . ' <span class="originalvalue">(' . $originalLabel . ')</span>');
		return $originalField;
	}
	
	/**
	 * Transform a translatable field to show the field value from the default language
	 * DataObject below the translated field.
	 * 
	 * This is a fallback function which handles field types that aren't transformed by
	 * $this->transform{FieldType} functions.
	 * 
	 * @param FormField $nonEditableField The readonly field to contain the original value
	 * @param FormField $originalField The original editable field containing the translated value
	 * @return \CompositeField The transformed field
	 */
	protected function baseTransform($nonEditableField, $originalField) {
		$fieldname = $originalField->getName();
		
		$nonEditableField_holder = new CompositeField($nonEditableField);
		$nonEditableField_holder->setName($fieldname.'_holder');
		$nonEditableField_holder->addExtraClass('originallang_holder');
		$nonEditableField->setValue($this->original->$fieldname);
		$nonEditableField->setName($fieldname.'_original');
		$nonEditableField->addExtraClass('originallang');
		$nonEditableField->setTitle(_t(
			'Translatable_Transform.OriginalFieldLabel', 
			'Original {title}', 
			'Label for the original value of the translatable field.',
			array('title'=>$originalField->Title())
		));
		
		$nonEditableField_holder->insertBefore($originalField, $fieldname.'_original');
		return $nonEditableField_holder;
	}
		
}
