<?php

class TranslatableEditorToolbarExtension extends DataExtension {
	
	function updateLinkForm(&$form) {
		$field = new LanguageDropdownField('Language', _t('PageURLSelectionField.LANGUAGE', 'Language'));
		$field->setForm($form);
		$form->Fields()->insertBefore($field, 'internal');
		Requirements::javascript('translatable/javascript/HtmlEditorField.Translatable.js');
	}
	
}