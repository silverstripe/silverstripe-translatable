(function($) {
	$.entwine('ss', function($){
		$('form.htmleditorfield-linkform').entwine({
			redraw: function() {
				this._super();
				// show language field for internal links
				var linkType = this.find(':input[name=LinkType]:checked').val(), list = ['internal', 'external', 'file', 'email'];
				if(linkType == 'internal') {
					this.find('.field#Language').show();
				}
			},
			updateFromEditor: function() {
				var data = this.getCurrentLink();
				if(data && data.internal) {
					// fetch locale for given sitetree object
					var localeField = $(this).find('#Form_EditorToolbarLinkForm_Language');
					$.ajax({
						url: $.path.addSearchParams(localeField.data('localeUrl'), {'id': data.internal, 'class': 'SiteTree'}),
						success: function(data) {
							localeField.val(data);
							localeField.change();
							localeField.trigger('liszt:updated');
						}
					});
				}
				this._super();
			}
		});
		
		$('form.htmleditorfield-linkform #Form_EditorToolbarLinkForm_Language').entwine({
			onchange: function(e) {
				// reload tree with selected locale
				var treeDropdown = $(this).parents('form').find('#internal .treedropdown');
				treeDropdown.data('urlTree', $.path.addSearchParams(treeDropdown.data('urlTree').replace(/locale=[^&]*/, ''), 'locale='+$(this).val()));
				treeDropdown.loadTree();
			}
		});
	});
}(jQuery));