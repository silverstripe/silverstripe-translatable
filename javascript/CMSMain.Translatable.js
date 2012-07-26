/**
 * File: CMSMain.Translatable.js
 */
(function($) {
	$.entwine('ss', function($){
	
		/**
		 * Class: .CMSMain #Form_LangForm
		 * 
		 * Dropdown with languages above CMS tree, causing a redirect upon translation
		 */
		$('.CMSMain #Form_LangForm').entwine({
			/**
			 * Constructor: onmatch
			 */
			onmatch: function() {
				var self = this;
			
				// monitor form loading for any locale changes
				$('#Form_EditForm').bind('loadnewpage', function(e) {
					var newLocale = $(this).find(':input[name=Locale]').val();
					if(newLocale) self.val(newLocale);
				});
			
				this._super();
			}
		});

		/**
		 * whenever a new value is selected, reload the whole CMS in the new locale
		 */
		$('.CMSMain #Form_LangForm :input[name=Locale]').entwine({
			onchange: function(e) {
				var url = $.path.addSearchParams(
					document.location.href.replace(/locale=[^&]*/, ''),
					{locale: $(e.target).val()}
				);
				$('.cms-container').loadPanel(url);
				return false;
			}
		});
	
		/**
		 * Class: .CMSMain .createTranslation
		 * 
		 * Loads /admin/createtranslation, which will create the new record,
		 * and redirect to an edit form.
		 * 
		 * Dropdown in "Translation" tab in CMS forms, with button to 
		 * trigger translating the currently loaded record.
		 * 
		 * Requires:
		 *  jquery.metadata
		 */
		$('.LeftAndMain :input[name=action_createtranslation]').entwine({
			
			onclick: function(e) {
				this.parents('form').trigger('submit', [this]);
				e.preventDefault();
				return false;
			}
		});
	});
}(jQuery));