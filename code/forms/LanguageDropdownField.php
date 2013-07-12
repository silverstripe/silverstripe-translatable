<?php
/**
 * An extension to dropdown field, pre-configured to list languages.
 * The languages already used in the site will be on top.
 * 
 * @package translatable
 */
class LanguageDropdownField extends GroupedDropdownField {

	private static $allowed_actions = array(
		'getLocaleForObject'
	);
	
	/**
	 * Create a new LanguageDropdownField
	 * @param string $name
	 * @param string $title
	 * @param array $excludeLocales List of locales that won't be included
	 * @param string $translatingClass Name of the class with translated instances 
	 *               where to look for used languages
	 * @param string $list Indicates the source language list. 
	 *               Can be either Common-English, Common-Native, Locale-English, Locale-Native
	 */
	function __construct($name, $title, $excludeLocales = array(), 
		$translatingClass = 'SiteTree', $list = 'Common-English', $instance = null
	) {
		$usedLocalesWithTitle = Translatable::get_existing_content_languages($translatingClass);
		$usedLocalesWithTitle = array_diff_key($usedLocalesWithTitle, $excludeLocales);

		if('Common-English' == $list) $allLocalesWithTitle = i18n::get_common_languages();
		else if('Common-Native' == $list) $allLocalesWithTitle = i18n::get_common_languages(true);
		else if('Locale-English' == $list) $allLocalesWithTitle = i18n::get_common_locales();
		else if('Locale-Native' == $list) $allLocalesWithTitle = i18n::get_common_locales(true);
		else $allLocalesWithTitle = i18n::config()->all_locales;

		if(isset($allLocales[Translatable::default_locale()])) {
			unset($allLocales[Translatable::default_locale()]);
		}
		
		// Limit to allowed locales if defined
		// Check for canTranslate() if an $instance is given
		$allowedLocales = Translatable::get_allowed_locales();
		foreach($allLocalesWithTitle as $locale => $localeTitle) {
			if(
				($allowedLocales && !in_array($locale, $allowedLocales))
				|| ($excludeLocales && in_array($locale, $excludeLocales))
				|| ($usedLocalesWithTitle && array_key_exists($locale, $usedLocalesWithTitle))
			) {
				unset($allLocalesWithTitle[$locale]);
			}
		}
		// instance specific permissions
		foreach($allLocalesWithTitle as $locale => $localeTitle) {
			if($instance && !$instance->canTranslate(null, $locale)) {
				unset($allLocalesWithTitle[$locale]);
			}
		}
		foreach($usedLocalesWithTitle as $locale => $localeTitle) {
			if($instance && !$instance->canTranslate(null, $locale)) {
				unset($usedLocalesWithTitle[$locale]);
			}
		}

		// Sort by title (array value)
		asort($allLocalesWithTitle);
		
		if(count($usedLocalesWithTitle)) {
			asort($usedLocalesWithTitle);
			$source = array(
				_t('Form.LANGAVAIL', "Available languages") => $usedLocalesWithTitle,
				_t('Form.LANGAOTHER', "Other languages") => $allLocalesWithTitle
			);
		} else {
			$source = $allLocalesWithTitle;
		}

		parent::__construct($name, $title, $source);
	}

	function Type() {
		return 'languagedropdown dropdown';
	}
	
	public function getAttributes() {
		return array_merge(
			parent::getAttributes(),
			array('data-locale-url' => $this->Link('getLocaleForObject'))
		);
	}
	
	/**
	 * Get the locale for an object that has the Translatable extension.
	 * 
	 * @return locale
	 */
	function getLocaleForObject() {
		$id = (int)$this->getRequest()->requestVar('id');
		$class = Convert::raw2sql($this->getRequest()->requestVar('class'));
		$locale = Translatable::get_current_locale();
		if ($id && $class && class_exists($class) && $class::has_extension('Translatable')) {
			// temporarily disable locale filter so that we won't filter out the object 
			Translatable::disable_locale_filter();
			$object = DataObject::get_by_id($class, $id);
			Translatable::enable_locale_filter();
			if ($object) {
				$locale = $object->Locale;
			}
		}
		return $locale;
	}
	
}
