<?php

class TranslatableEditorToolbarExtension extends DataExtension {
	
	function updateLinkForm(&$form) {
		$field = new LanguageDropdownField('Language', _t('CMSMain.LANGUAGEDROPDOWNLABEL', 'Language'));
		$field->setValue(Translatable::get_current_locale());
		$field->setForm($form);
		$form->Fields()->insertBefore($field, 'internal');
		Requirements::javascript('translatable/javascript/HtmlEditorField.Translatable.js');
	}
	
}
