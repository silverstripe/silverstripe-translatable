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
			
				// whenever a new value is selected, reload the whole CMS in the new locale
				this.find(':input[name=Locale]').bind('change', function(e) {
					var url = document.location.href;
					url += (url.indexOf('?') != -1) ? '&' : '?';
					// TODO Replace existing locale GET params
					url += 'locale=' + $(e.target).val();
					document.location = url;
					return false;
				});
			
				this._super();
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
		$('.CMSMain :input[name=action_createtranslation]').entwine({
			
			onclick: function() {
				var form = this.parents('form'), locale = form.find(':input[name=NewTransLang]').val();
				var params = {
					'ID': form.find(':input[name=ID]').val(), 
					'newlang': locale,
					'locale': locale,
					'SecurityID': form.find(':input[name=SecurityID]').val()
				};
				// redirect to new URL
				// TODO This should really be a POST request
				// TODO Fix hardcode URL
				document.location.href = $('base').attr('href') + 'admin/createtranslation?' + $.param(params);

				return false;
			}
		});
	});
}(jQuery));