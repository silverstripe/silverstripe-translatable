<?php
/**
 * @todo Test Versioned getters
 * 
 * @package translatable
 */
class TranslatableTest extends FunctionalTest {
	
	protected static $fixture_file = 'translatable/tests/unit/TranslatableTest.yml';

	protected $extraDataObjects = array(
		'TranslatableTest_DataObject',
		'TranslatableTest_OneByLocaleDataObject',
		'TranslatableTest_Page',
	);
	
	protected $requiredExtensions = array(
		'SiteTree' => array('Translatable', 'Versioned', 'EveryoneCanPublish'),
		'SiteConfig' => array('Translatable'),
		'TranslatableTest_DataObject' => array('Translatable'),
		'TranslatableTest_OneByLocaleDataObject' => array('Translatable'),
	);
	
	private $origLocale;
	
	protected $autoFollowRedirection = false;

	function setUp() {
		parent::setUp();
		
		// whenever a translation is created, canTranslate() is checked
		$cmseditor = $this->objFromFixture('Member', 'cmseditor');
		$cmseditor->logIn();
		
		$this->origLocale = Translatable::default_locale();
		Translatable::set_default_locale("en_US");
	}
	
	function tearDown() {
		Translatable::set_default_locale($this->origLocale);
		Translatable::set_current_locale($this->origLocale);

		parent::tearDown();
	}

	function assertArrayEqualsAfterSort($expected, $actual, $message = null) {
		sort($expected);
		sort($actual);
		return $this->assertEquals($expected, $actual, $message);
	}

	function testGetOneByLocale() {
		Translatable::disable_locale_filter();
		$this->assertEquals(
			0, 
			TranslatableTest_OneByLocaleDataObject::get()->count(), 
			'should not be any test objects yet'
		);
		Translatable::enable_locale_filter();

		$obj = new TranslatableTest_OneByLocaleDataObject();
		$obj->TranslatableProperty = 'test - en';
		$obj->write();

		Translatable::disable_locale_filter();
		$this->assertEquals(
			1, 
			TranslatableTest_OneByLocaleDataObject::get()->count(), 
			'should not be any test objects yet'
		);
		Translatable::enable_locale_filter();

		$found = Translatable::get_one_by_locale('TranslatableTest_OneByLocaleDataObject', $obj->Locale);
		$this->assertNotNull($found, 'should have found one for ' . $obj->Locale);
		$this->assertEquals($obj->ID, $found->ID);

		$translated = $obj->createTranslation('de_DE');
		$translated->write();

		Translatable::disable_locale_filter();
		$this->assertEquals(
			2, 
			TranslatableTest_OneByLocaleDataObject::get()->count(), 
			'should not be any test objects yet'
		);
		Translatable::enable_locale_filter();

		$found = Translatable::get_one_by_locale(
			'TranslatableTest_OneByLocaleDataObject', 
			$translated->Locale
		);
		$this->assertNotNull($found, 'should have found one for ' . $translated->Locale);
		$this->assertEquals($translated->ID, $found->ID);

		// test again to make sure that get_one_by_locale works when locale filter disabled
		Translatable::disable_locale_filter();
		$found = Translatable::get_one_by_locale(
			'TranslatableTest_OneByLocaleDataObject', 
			$translated->Locale
		);
		$this->assertEquals($translated->ID, $found->ID);
		Translatable::enable_locale_filter();
	}

	function testLocaleFilteringEnabledAndDisabled() {
		$this->assertTrue(Translatable::locale_filter_enabled());

		// get our base page to use for testing
		$origPage = $this->objFromFixture('Page', 'testpage_en');
		$origPage->MenuTitle = 'unique-key-used-in-my-query';
		$origPage->write();
		$origPage->publish('Stage', 'Live');

		// create a translation of it so that we can see if translations are filtered
		$translatedPage = $origPage->createTranslation('de_DE');
		$translatedPage->MenuTitle = $origPage->MenuTitle;
		$translatedPage->write();
		$translatedPage->publish('Stage', 'Live');

		$where = sprintf("\"MenuTitle\" = '%s'", Convert::raw2sql($origPage->MenuTitle));

		// make sure that our query was filtered
		$this->assertEquals(1, Page::get()->where($where)->count());

		// test no filtering with disabled locale filter
		Translatable::disable_locale_filter();
		$this->assertEquals(2, Page::get()->where($where)->count());
		Translatable::enable_locale_filter();

		// make sure that our query was filtered after re-enabling the filter
		$this->assertEquals(1, Page::get()->where($where)->count());

		// test effectiveness of disabling locale filter with 3.x delayed querying
		// see https://github.com/silverstripe/silverstripe-translatable/issues/113
		Translatable::disable_locale_filter();
		// create the DataList while the locale filter is disabled
		$dataList = Page::get()->where($where);
		Translatable::enable_locale_filter();
		// but don't use it until later - after the filter is re-enabled
		$this->assertEquals(2, $dataList->count());
	}
	
	function testLocaleGetParamRedirectsToTranslation() {
		$origPage = $this->objFromFixture('Page', 'testpage_en');
		$origPage->publish('Stage', 'Live');
		$translatedPage = $origPage->createTranslation('de_DE');
		$translatedPage->URLSegment = 'ueber-uns';
		$translatedPage->write();
		$translatedPage->publish('Stage', 'Live');
		
		// Need to log out, otherwise pages redirect to CMS views
		$this->session()->inst_set('loggedInAs', null);
		
		$response = $this->get($origPage->URLSegment);
		$this->assertEquals(200, $response->getStatusCode(), 'Page request without Locale GET param doesnt redirect');
		
		$response = $this->get(Controller::join_links($origPage->URLSegment, '?locale=de_DE'));
		$this->assertEquals(301, $response->getStatusCode(), 'Locale GET param causes redirect if it exists');
		$this->assertContains($translatedPage->URLSegment, $response->getHeader('Location'));
		
		$response = $this->get(Controller::join_links($origPage->URLSegment, '?locale=fr_FR'));
		$this->assertEquals(200, $response->getStatusCode(), 
			'Locale GET param without existing translation shows original page'
		);
	}
	
	function testTranslationGroups() {
		// first in french
		$frPage = new SiteTree();
		$frPage->Locale = 'fr_FR';
		$frPage->write();
		
		// second in english (from french "original")
		$enPage = $frPage->createTranslation('en_US');
		
		// third in spanish (from the english translation)
		$esPage = $enPage->createTranslation('es_ES');
		
		// test french
		
		$this->assertArrayEqualsAfterSort(
			array('en_US','es_ES'),
			$frPage->getTranslations()->column('Locale')
		);
		$this->assertNotNull($frPage->getTranslation('en_US'));
		$this->assertEquals(
			$frPage->getTranslation('en_US')->ID,
			$enPage->ID
		);
		$this->assertNotNull($frPage->getTranslation('es_ES'));
		$this->assertEquals(
			$frPage->getTranslation('es_ES')->ID,
			$esPage->ID
		);
		
		// test english
		$this->assertArrayEqualsAfterSort(
			array('es_ES', 'fr_FR'),
			$enPage->getTranslations()->column('Locale')
		);
		$this->assertNotNull($frPage->getTranslation('fr_FR'));
		$this->assertEquals(
			$enPage->getTranslation('fr_FR')->ID,
			$frPage->ID
		);
		$this->assertNotNull($frPage->getTranslation('es_ES'));
		$this->assertEquals(
			$enPage->getTranslation('es_ES')->ID,
			$esPage->ID
		);
		
		// test spanish
		$this->assertArrayEqualsAfterSort(
			array('en_US', 'fr_FR'),
			$esPage->getTranslations()->column('Locale')
		);
		$this->assertNotNull($esPage->getTranslation('fr_FR'));
		$this->assertEquals(
			$esPage->getTranslation('fr_FR')->ID,
			$frPage->ID
		);
		$this->assertNotNull($esPage->getTranslation('en_US'));
		$this->assertEquals(
			$esPage->getTranslation('en_US')->ID,
			$enPage->ID
		);
	}

	function assertClass($class, $node) {
		$this->assertNotNull($node);
		$this->assertEquals($class, $node->ClassName);
		$this->assertEquals($class, get_class($node));
	}

	function testChangingClassOfDefaultLocaleTranslationChangesOthers() {
		// see https://github.com/silverstripe/silverstripe-translatable/issues/97
		// create an English SiteTree
		$enST = new SiteTree();
		$enST->Locale = 'en_US';
		$enST->write();

		// create French and Spanish translations
		$frST = $enST->createTranslation('fr_FR');
		$esST = $enST->createTranslation('es_ES');

		// change the class name of the default locale's translation (as CMS admin would)
		$enST->setClassName('Page');
		$enST->write();

		// reload them all to get fresh instances
		$enPg = DataObject::get_by_id('Page', $enST->ID, $cache = false);
		$frPg = DataObject::get_by_id('Page', $frST->ID, $cache = false);
		$esPg = DataObject::get_by_id('Page', $esST->ID, $cache = false);

		// make sure they are all the right class
		$this->assertClass('Page', $enPg);
		$this->assertClass('Page', $frPg);
		$this->assertClass('Page', $esPg);

		// test that we get the right translations back from each instance
		$this->assertArrayEqualsAfterSort(
			array('fr_FR', 'es_ES'),
			$enPg->getTranslations()->column('Locale')
		);
		$this->assertArrayEqualsAfterSort(
			array('en_US', 'es_ES'),
			$frPg->getTranslations()->column('Locale')
		);
		$this->assertArrayEqualsAfterSort(
			array('en_US', 'fr_FR'),
			$esPg->getTranslations()->column('Locale')
		);
	}

	function testChangingClassOfDefaultLocaleTranslationChangesOthersWhenPublished() {
		// create an English SiteTree
		$enST = new SiteTree();
		$enST->Locale = 'en_US';
		$enST->write();
		$enST->doPublish();

		// create and publish French and Spanish translations
		$frST = $enST->createTranslation('fr_FR');
		$this->assertTrue($frST->doPublish(), 'should have been able to publish French translation');
		$esST = $enST->createTranslation('es_ES');
		$this->assertTrue($esST->doPublish(), 'should have been able to publish Spanish translation');

		// change the class name of the default locale's translation (as CMS admin would)
		// and publish the change - we should see both versions of English change class
		$enST->setClassName('Page');
		$enST->doPublish();
		$this->assertClass('Page', Versioned::get_one_by_stage('SiteTree', 'Stage', '"ID" = ' . $enST->ID));
		$this->assertClass('Page', Versioned::get_one_by_stage('SiteTree', 'Live', '"ID" = ' . $enST->ID));

		// and all of the draft versions of translations:
		$this->assertClass('Page', Versioned::get_one_by_stage('SiteTree', 'Stage', '"ID" = ' . $frST->ID));
		$this->assertClass('Page', Versioned::get_one_by_stage('SiteTree', 'Stage', '"ID" = ' . $esST->ID));

		// and all of the live versions of translations as well:
		$this->assertClass('Page', Versioned::get_one_by_stage('SiteTree', 'Live', '"ID" = ' . $frST->ID));
		$this->assertClass('Page', Versioned::get_one_by_stage('SiteTree', 'Live', '"ID" = ' . $esST->ID));

	}
	
	function testTranslationGroupsWhenTranslationIsSubclass() {
		// create an English SiteTree
		$enST = new SiteTree();
		$enST->Locale = 'en_US';
		$enST->write();

		// create French and Spanish translations
		$frST = $enST->createTranslation('fr_FR');
		$esST = $enST->createTranslation('es_ES');

		// test that we get the right translations back from each instance
		$this->assertArrayEqualsAfterSort(
			array('fr_FR', 'es_ES'),
			$enST->getTranslations()->column('Locale')
		);
		$this->assertArrayEqualsAfterSort(
			array('en_US', 'es_ES'),
			$frST->getTranslations()->column('Locale')
		);
		$this->assertArrayEqualsAfterSort(
			array('en_US', 'fr_FR'),
			$esST->getTranslations()->column('Locale')
		);

		// this should be considered an edge-case, but on some sites translations
		// may be allowed to be a subclass of the default locale's translation of
		// the same page.  In this case, we need to support getTranslations returning
		// all of the translations, even if one of the translations is a different
		// class from others
		$esST->setClassName('Page');
		$esST->write();
		$esPg = DataObject::get_by_id('Page', $esST->ID, $cache = false);

		// make sure we successfully changed the class
		$this->assertClass('Page', $esPg);

		// and make sure that the class of the others did not change
		$frST = DataObject::get_by_id('SiteTree', $frST->ID, $cache = false);
		$this->assertClass('SiteTree', $frST);
		$enST = DataObject::get_by_id('SiteTree', $enST->ID, $cache = false);
		$this->assertClass('SiteTree', $enST);

		// now that we know our edge case is successfully configured, we need to
		// make sure that we get the right translations back from everything
		$this->assertArrayEqualsAfterSort(
			array('fr_FR', 'es_ES'),
			$enST->getTranslations()->column('Locale')
		);
		$this->assertArrayEqualsAfterSort(
			array('en_US', 'es_ES'),
			$frST->getTranslations()->column('Locale')
		);
		$this->assertArrayEqualsAfterSort(
			array('en_US', 'fr_FR'),
			$esPg->getTranslations()->column('Locale')
		);
		$this->assertEquals($enST->ID, $esPg->getTranslation('en_US')->ID);
		$this->assertEquals($frST->ID, $esPg->getTranslation('fr_FR')->ID);
		$this->assertEquals($esPg->ID, $enST->getTranslation('es_ES')->ID);
		$this->assertEquals($esPg->ID, $frST->getTranslation('es_ES')->ID);
	}

	function testTranslationGroupNotRemovedWhenSiteTreeUnpublished() {
		$enPage = new Page();
		$enPage->Locale = 'en_US';
		$enPage->write();
		$enPage->publish('Stage', 'Live');
		$enTranslationGroup = $enPage->getTranslationGroup();
		
		$frPage = $enPage->createTranslation('fr_FR');
		$frPage->write();
		$frPage->publish('Stage', 'Live');
		$frTranslationGroup = $frPage->getTranslationGroup();
		
		$enPage->doUnpublish();
		$this->assertEquals($enPage->getTranslationGroup(), $enTranslationGroup);
		
		$frPage->doUnpublish();
		$this->assertEquals($frPage->getTranslationGroup(), $frTranslationGroup);
	}
	
	function testGetTranslationOnSiteTree() {
		$origPage = $this->objFromFixture('Page', 'testpage_en');
		
		$translatedPage = $origPage->createTranslation('fr_FR');
		$getTranslationPage = $origPage->getTranslation('fr_FR');
	
		$this->assertNotNull($getTranslationPage);
		$this->assertEquals($getTranslationPage->ID, $translatedPage->ID);
	}
	
	function testGetTranslatedLanguages() {
		$origPage = $this->objFromFixture('Page', 'testpage_en');
		
		// through createTranslation()
		$translationAf = $origPage->createTranslation('af_ZA');
		
		// create a new language on an unrelated page which shouldnt be returned from $origPage
		$otherPage = new Page();
		$otherPage->write();
		$otherTranslationEs = $otherPage->createTranslation('es_ES');
		
		$this->assertEquals(
			$origPage->getTranslatedLangs(),
			array(
				'af_ZA',
				//'en_US', // default language is not included
			),
			'Language codes are returned specifically for the queried page through getTranslatedLangs()'
		);
		
		$pageWithoutTranslations = new Page();
		$pageWithoutTranslations->write();
		$this->assertEquals(
			$pageWithoutTranslations->getTranslatedLangs(),
			array(),
			'A page without translations returns empty array through getTranslatedLangs(), ' . 
			'even if translations for other pages exist in the database'
		);
		
		// manual creation of page without an original link
		$translationDeWithoutOriginal = new Page();
		$translationDeWithoutOriginal->Locale = 'de_DE';
		$translationDeWithoutOriginal->write();
		$this->assertEquals(
			$translationDeWithoutOriginal->getTranslatedLangs(),
			array(),
			'A translated page without an original doesn\'t return anything through getTranslatedLang()'
		);
	}
	
	function testTranslationCantHaveSameURLSegmentAcrossLanguages() {
		$origPage = $this->objFromFixture('Page', 'testpage_en');
		$translatedPage = $origPage->createTranslation('de_DE');
		$this->assertEquals($translatedPage->URLSegment, 'testpage-de-de');
	
		$translatedPage->URLSegment = 'testpage'; // de_DE clashes with en_US
		$translatedPage->write();
		$this->assertNotEquals($origPage->URLSegment, $translatedPage->URLSegment);

		Translatable::set_current_locale('de_DE');
		Config::inst()->update('Translatable', 'enforce_global_unique_urls', false);
		$translatedPage->URLSegment = 'testpage'; // de_DE clashes with en_US
		$translatedPage->write();
		$this->assertEquals('testpage', $translatedPage->URLSegment);
		Config::inst()->update('Translatable', 'enforce_global_unique_urls', true);
		Translatable::set_current_locale('en_US');
	}
	
	function testUpdateCMSFieldsOnSiteTree() {
		$pageOrigLang = new TranslatableTest_Page();
		$pageOrigLang->write();
		
		// first test with default language
		$fields = $pageOrigLang->getCMSFields();
		$this->assertInstanceOf(
			'TextField', 
			$fields->dataFieldByName('Title'),
			'Translatable doesnt modify fields if called in default language (e.g. "non-translation mode")'
		);
		$this->assertNull( 
			$fields->dataFieldByName('Title_original'),
			'Translatable doesnt modify fields if called in default language (e.g. "non-translation mode")'
		);
		$this->assertInstanceOf(
			'TextField', 
			$fields->dataFieldByName('TranslatableProperty'),
			'Has custom field'
		);
		
		// then in "translation mode"
		$pageTranslated = $pageOrigLang->createTranslation('fr_FR');
		$fields = $pageTranslated->getCMSFields();
		$this->assertInstanceOf(
			'TextField', 
			$fields->dataFieldByName('Title'),
			'Translatable leaves original formfield intact in "translation mode"'
		);
		$readonlyField = $fields->dataFieldByName('Title')->performReadonlyTransformation();
		$this->assertInstanceOf(
			$readonlyField->class, 
			$fields->dataFieldByName('Title_original'),
			'Translatable adds the original value as a ReadonlyField in "translation mode"'
		);
		$this->assertInstanceOf(
			'ReadonlyField', 
			$fields->dataFieldByName('TranslatableProperty_original'),
			'Retains original custom field'
		);
		$this->assertInstanceOf(
			'TextField', 
			$fields->dataFieldByName('TranslatableProperty'),
			'Adds custom fields as ReadonlyField'
		);
		
	}
	
	function testDataObjectGetWithReadingLanguage() {
		$origTestPage = $this->objFromFixture('Page', 'testpage_en');
		$otherTestPage = $this->objFromFixture('Page', 'othertestpage_en');
		$translatedPage = $origTestPage->createTranslation('de_DE');
		
		// test in default language
		$resultPagesDefaultLang = DataObject::get(
			'Page',
			sprintf("\"SiteTree\".\"MenuTitle\" = '%s'", 'A Testpage')
		);
		$resultPagesDefaultLangIDs = $resultPagesDefaultLang->column('ID');
		foreach($resultPagesDefaultLangIDs as $key => $val)
			$resultPagesDefaultLangIDs[$key] = intval($val);
		$this->assertEquals($resultPagesDefaultLang->Count(), 2);
		$this->assertContains((int)$origTestPage->ID, $resultPagesDefaultLangIDs);
		$this->assertContains((int)$otherTestPage->ID, $resultPagesDefaultLangIDs);
		$this->assertNotContains((int)$translatedPage->ID, $resultPagesDefaultLangIDs);
		
		// test in custom language
		Translatable::set_current_locale('de_DE');
		$resultPagesCustomLang = DataObject::get(
			'Page',
			sprintf("\"SiteTree\".\"MenuTitle\" = '%s'", 'A Testpage')
		);
		$resultPagesCustomLangIDs = $resultPagesCustomLang->column('ID');
		foreach($resultPagesCustomLangIDs as $key => $val)
			$resultPagesCustomLangIDs[$key] = intval($val);
		$this->assertEquals($resultPagesCustomLang->Count(), 1);
		$this->assertNotContains((int)$origTestPage->ID, $resultPagesCustomLangIDs);
		$this->assertNotContains((int)$otherTestPage->ID, $resultPagesCustomLangIDs);
		$this->assertContains((int)$translatedPage->ID, $resultPagesCustomLangIDs);
		
		Translatable::set_current_locale('en_US');
	}
	
	function testDataObjectGetByIdWithReadingLanguage() {
		$origPage = $this->objFromFixture('Page', 'testpage_en');
		$translatedPage = $origPage->createTranslation('de_DE');
		$compareOrigPage = DataObject::get_by_id('Page', $origPage->ID);
		
		$this->assertEquals(
			$origPage->ID, 
			$compareOrigPage->ID,
			'DataObject::get_by_id() should work independently of the reading language'
		);
	}
	
	function testDataObjectGetOneWithReadingLanguage() {
		$origPage = $this->objFromFixture('Page', 'testpage_en');
		$translatedPage = $origPage->createTranslation('de_DE');
		
		// running the same query twice with different 
		Translatable::set_current_locale('de_DE');
		$compareTranslatedPage = DataObject::get_one(
			'Page', 
			sprintf("\"SiteTree\".\"Title\" = '%s'", $translatedPage->Title)
		);
		$this->assertNotNull($compareTranslatedPage);
		$this->assertEquals(
			$translatedPage->ID, 
			$compareTranslatedPage->ID,
			"Translated page is found through get_one() when reading lang is not the default language"
		);
		
		// reset language to default
		Translatable::set_current_locale('en_US');
	}
	
	function testModifyTranslationWithDefaultReadingLang() {
		$origPage = $this->objFromFixture('Page', 'testpage_en');
		$translatedPage = $origPage->createTranslation('de_DE');
		
		Translatable::set_current_locale('en_US');
		$translatedPage->Title = 'De Modified';
		$translatedPage->write();
		$savedTranslatedPage = $origPage->getTranslation('de_DE');
		$this->assertEquals(
			$savedTranslatedPage->Title, 
			'De Modified',
			'Modifying a record in language which is not the reading language should still write the record correctly'
		);
		$this->assertEquals(
			$origPage->Title, 
			'Home',
			'Modifying a record in language which is not the reading language does not modify the original record'
		);
	}
	
	function testSiteTreePublication() {
		$origPage = $this->objFromFixture('Page', 'testpage_en');
		$translatedPage = $origPage->createTranslation('de_DE');
		
		Translatable::set_current_locale('en_US');
		$origPage->Title = 'En Modified';
		$origPage->write();
		// modifying a record in language which is not the reading language should still write the record correctly
		$translatedPage->Title = 'De Modified';
		$translatedPage->write();
		$origPage->publish('Stage', 'Live');
		$liveOrigPage = Versioned::get_one_by_stage('Page', 'Live', "\"SiteTree\".\"ID\" = {$origPage->ID}");
		$this->assertEquals(
			$liveOrigPage->Title, 
			'En Modified',
			'Publishing a record in its original language publshes correct properties'
		);
	}
	
	function testDeletingTranslationKeepsOriginal() {
		$origPage = $this->objFromFixture('Page', 'testpage_en');
		$translatedPage = $origPage->createTranslation('de_DE');
		$translatedPageID = $translatedPage->ID;
		$translatedPage->delete();
		
		$translatedPage->flushCache();
		$origPage->flushCache();
	
		$this->assertNull($origPage->getTranslation('de_DE'));
		$this->assertNotNull(DataObject::get_by_id('Page', $origPage->ID));
	}
	
	function testHierarchyChildren() {
		$parentPage = $this->objFromFixture('Page', 'parent');
		$child1Page = $this->objFromFixture('Page', 'child1');
		$child2Page = $this->objFromFixture('Page', 'child2');
		$child3Page = $this->objFromFixture('Page', 'child3');
		$grandchildPage = $this->objFromFixture('Page', 'grandchild1');
		
		$parentPageTranslated = $parentPage->createTranslation('de_DE');
		$child4PageTranslated = new SiteTree();
		$child4PageTranslated->Locale = 'de_DE';
		$child4PageTranslated->ParentID = $parentPageTranslated->ID;
		$child4PageTranslated->write();
		
		Translatable::set_current_locale('en_US');
		$this->assertArrayEqualsAfterSort(
			array(
				$child1Page->ID,
				$child2Page->ID,
				$child3Page->ID
			),
			$parentPage->Children()->column('ID'),
			"Showing Children() in default language doesnt show children in other languages"
		);
		
		Translatable::set_current_locale('de_DE');
		$parentPage->flushCache();
		$this->assertEquals(
			$parentPageTranslated->Children()->column('ID'),
			array($child4PageTranslated->ID),
			"Showing Children() in translation mode doesnt show children in default languages"
		);
		
		// reset language
		Translatable::set_current_locale('en_US');
	}
	
	function testHierarchyLiveStageChildren() {
		$parentPage = $this->objFromFixture('Page', 'parent');
		$child1Page = $this->objFromFixture('Page', 'child1');
		$child1Page->publish('Stage', 'Live');
		$child2Page = $this->objFromFixture('Page', 'child2');
		$child3Page = $this->objFromFixture('Page', 'child3');
		$grandchildPage = $this->objFromFixture('Page', 'grandchild1');
		
		$parentPageTranslated = $parentPage->createTranslation('de_DE');
		
		$child4PageTranslated = new SiteTree();
		$child4PageTranslated->Locale = 'de_DE';
		$child4PageTranslated->ParentID = $parentPageTranslated->ID;
		$child4PageTranslated->write();
		$child4PageTranslated->publish('Stage', 'Live');
		
		$child5PageTranslated = new SiteTree();
		$child5PageTranslated->Locale = 'de_DE';
		$child5PageTranslated->ParentID = $parentPageTranslated->ID;
		$child5PageTranslated->write();
		
		Translatable::set_current_locale('en_US');
		$this->assertNotNull($parentPage->liveChildren());
		$this->assertEquals(
			$parentPage->liveChildren()->column('ID'),
			array(
				$child1Page->ID
			),
			"Showing liveChildren() in default language doesnt show children in other languages"
		);
		$this->assertNotNull($parentPage->stageChildren());
		$this->assertArrayEqualsAfterSort(
			array(
				$child1Page->ID,
				$child2Page->ID,
				$child3Page->ID
			),
			$parentPage->stageChildren()->column('ID'),
			"Showing stageChildren() in default language doesnt show children in other languages"
		);
		
		Translatable::set_current_locale('de_DE');
		$parentPage->flushCache();
		$this->assertNotNull($parentPageTranslated->liveChildren());
		$this->assertEquals(
			$parentPageTranslated->liveChildren()->column('ID'),
			array($child4PageTranslated->ID),
			"Showing liveChildren() in translation mode doesnt show children in default languages"
		);
		$this->assertNotNull($parentPageTranslated->stageChildren());
		$this->assertEquals(
			$parentPageTranslated->stageChildren()->column('ID'),
			array(
				$child4PageTranslated->ID,
				$child5PageTranslated->ID,
			),
			"Showing stageChildren() in translation mode doesnt show children in default languages"
		);
		
		// reset language
		Translatable::set_current_locale('en_US');
	}
	
	function testTranslatablePropertiesOnSiteTree() {
		$origObj = $this->objFromFixture('TranslatableTest_Page', 'testpage_en');
		
		$translatedObj = $origObj->createTranslation('fr_FR');
		$translatedObj->TranslatableProperty = 'fr_FR';
		$translatedObj->write();
		
		$this->assertEquals(
			$origObj->TranslatableProperty,
			'en_US',
			'Creating a translation doesnt affect database field on original object'
		);
		$this->assertEquals(
			$translatedObj->TranslatableProperty,
			'fr_FR',
			'Translated object saves database field independently of original object'
		);
	}
	
	function testCreateTranslationOnSiteTree() {
		$origPage = $this->objFromFixture('Page', 'testpage_en');
		$translatedPage = $origPage->createTranslation('de_DE');
	
		$this->assertEquals($translatedPage->Locale, 'de_DE');
		$this->assertNotEquals($translatedPage->ID, $origPage->ID);
	
		$subsequentTranslatedPage = $origPage->createTranslation('de_DE');
		$this->assertEquals(
			$translatedPage->ID,
			$subsequentTranslatedPage->ID,
			'Subsequent calls to createTranslation() dont cause new records in database'
		);
	}
	
	function testTranslatablePropertiesOnDataObject() {
		$origObj = $this->objFromFixture('TranslatableTest_DataObject', 'testobject_en');
		$translatedObj = $origObj->createTranslation('fr_FR');
		$translatedObj->TranslatableProperty = 'fr_FR';
		$translatedObj->TranslatableDecoratedProperty = 'fr_FR';
		$translatedObj->write();
		
		$this->assertEquals(
			$origObj->TranslatableProperty,
			'en_US',
			'Creating a translation doesnt affect database field on original object'
		);
		$this->assertEquals(
			$origObj->TranslatableDecoratedProperty,
			'en_US',
			'Creating a translation doesnt affect decorated database field on original object'
		);
		$this->assertEquals(
			$translatedObj->TranslatableProperty,
			'fr_FR',
			'Translated object saves database field independently of original object'
		);
		$this->assertEquals(
			$translatedObj->TranslatableDecoratedProperty,
			'fr_FR',
			'Translated object saves decorated database field independently of original object'
		);
	}
	
	function testCreateTranslationWithoutOriginal() {
		$origParentPage = $this->objFromFixture('Page', 'testpage_en');
		$translatedParentPage = $origParentPage->createTranslation('de_DE');
	
		$translatedPageWithoutOriginal = new SiteTree();
		$translatedPageWithoutOriginal->ParentID = $translatedParentPage->ID;
		$translatedPageWithoutOriginal->Locale = 'de_DE';
		$translatedPageWithoutOriginal->write();
	
		Translatable::set_current_locale('de_DE');
		$this->assertEquals(
			$translatedParentPage->stageChildren()->column('ID'),
			array(
				$translatedPageWithoutOriginal->ID
			),
			"Children() still works on a translated page even if no translation group is set"
		);
		
		Translatable::set_current_locale('en_US');
	}
	
	function testCreateTranslationTranslatesUntranslatedParents() {
		$parentPage = $this->objFromFixture('Page', 'parent');
		$child1Page = $this->objFromFixture('Page', 'child1');
		$child1PageOrigID = $child1Page->ID;
		$grandChild1Page = $this->objFromFixture('Page', 'grandchild1');
		$grandChild2Page = $this->objFromFixture('Page', 'grandchild2');
	
		$this->assertFalse($grandChild1Page->hasTranslation('de_DE'));
		$this->assertFalse($child1Page->hasTranslation('de_DE'));
		$this->assertFalse($parentPage->hasTranslation('de_DE'));
	
		$translatedGrandChild1Page = $grandChild1Page->createTranslation('de_DE');
		$translatedGrandChild2Page = $grandChild2Page->createTranslation('de_DE');
		$translatedChildPage = $child1Page->getTranslation('de_DE');
		$translatedParentPage = $parentPage->getTranslation('de_DE');
	
		$this->assertTrue($grandChild1Page->hasTranslation('de_DE'));
		$this->assertEquals($translatedGrandChild1Page->ParentID, $translatedChildPage->ID);
	
		$this->assertTrue($grandChild2Page->hasTranslation('de_DE'));
		$this->assertEquals($translatedGrandChild2Page->ParentID, $translatedChildPage->ID);
	
		$this->assertTrue($child1Page->hasTranslation('de_DE'));
		$this->assertEquals($translatedChildPage->ParentID, $translatedParentPage->ID);
	
		$this->assertTrue($parentPage->hasTranslation('de_DE'));
	}
	
	function testHierarchyAllChildrenIncludingDeleted() {
		// Original tree in 'en_US':
		//   parent
		//    child1 (Live only, deleted from stage)
		//    child2 (Stage only, never published)
		//    child3 (Stage only, never published, untranslated)
		// Translated tree in 'de_DE':
		//   parent
		//    child1 (Live only, deleted from stage)
		//    child2 (Stage only)
		
		// Create parent
		$parentPage = $this->objFromFixture('Page', 'parent');
		$parentPageID = $parentPage->ID;
		
		// Create parent translation
		$translatedParentPage = $parentPage->createTranslation('de_DE');
		$translatedParentPageID = $translatedParentPage->ID;
		
		// Create child1
		$child1Page = $this->objFromFixture('Page', 'child1');
		$child1PageID = $child1Page->ID;
		$child1Page->publish('Stage', 'Live');
		
		// Create child1 translation
		$child1PageTranslated = $child1Page->createTranslation('de_DE');
		$child1PageTranslatedID = $child1PageTranslated->ID;
		$child1PageTranslated->publish('Stage', 'Live');
		$child1PageTranslated->deleteFromStage('Stage'); // deleted from stage only, record still exists on live
		$child1Page->deleteFromStage('Stage'); // deleted from stage only, record still exists on live
		
		// Create child2
		$child2Page = $this->objFromFixture('Page', 'child2');
		$child2PageID = $child2Page->ID;
		
		// Create child2 translation
		$child2PageTranslated = $child2Page->createTranslation('de_DE');
		$child2PageTranslatedID = $child2PageTranslated->ID;
		
		// Create child3
		$child3Page = $this->objFromFixture('Page', 'child3');
		$child3PageID = $child3Page->ID;
		
		// on original parent in default language
		Translatable::set_current_locale('en_US');
		SiteTree::flush_and_destroy_cache();
		$parentPage = $this->objFromFixture('Page', 'parent');
		$children = $parentPage->AllChildrenIncludingDeleted();
		$this->assertArrayEqualsAfterSort(
			array(
				$child2PageID,
				$child3PageID,
				$child1PageID // $child1Page was deleted from stage, so the original record doesn't have the ID set
			),
			$parentPage->AllChildrenIncludingDeleted()->column('ID'),
			"Showing AllChildrenIncludingDeleted() in default language doesnt show deleted children in other languages"
		);
	
		// on original parent in translation mode
		Translatable::set_current_locale('de_DE');
		SiteTree::flush_and_destroy_cache();
		$parentPage = $this->objFromFixture('Page', 'parent');
		$this->assertEquals(
			$translatedParentPage->AllChildrenIncludingDeleted()->column('ID'),
			array(
				$child2PageTranslatedID,
				// $child1PageTranslated was deleted from stage, so the original record doesn't have the ID set
				$child1PageTranslatedID 
			),
			"Showing AllChildrenIncludingDeleted() in translation mode with parent page in " .
			"translated language shows children in translated language"
		);
		
		Translatable::set_current_locale('de_DE');
		SiteTree::flush_and_destroy_cache();
		$parentPage = $this->objFromFixture('Page', 'parent');
		$this->assertEquals(
			$parentPage->AllChildrenIncludingDeleted()->column('ID'),
			array(),
			"Showing AllChildrenIncludingDeleted() in translation mode with parent page in " .
			"translated language shows children in default language"
		);
		
		// reset language
		Translatable::set_current_locale('en_US');
	}
	
	function testRootUrlDefaultsToTranslatedLink() {
		$origPage = $this->objFromFixture('Page', 'homepage_en');
		$origPage->publish('Stage', 'Live');
		$translationDe = $origPage->createTranslation('de_DE');
		$translationDe->URLSegment = 'heim';
		$translationDe->write();
		$translationDe->publish('Stage', 'Live');
		
		// test with translatable
		Translatable::set_current_locale('de_DE');		
		$this->assertEquals(
			RootURLController::get_homepage_link(), 
			'heim', 
			'Homepage with different URLSegment in non-default language is found'
		);
		
		// @todo Fix add/remove extension
		// test with translatable disabled
		// Object::remove_extension('Page', 'Translatable');
		// 		$_SERVER['HTTP_HOST'] = '/';
		// 		$this->assertEquals(
		// 			RootURLController::get_homepage_urlsegment(), 
		// 			'home', 
		// 			'Homepage is showing in default language if ?lang GET variable is left out'
		// 		);
		// 		Object::add_extension('Page', 'Translatable');
		
		// setting back to default
		Translatable::set_current_locale('en_US');
	}
	
	function testSiteTreeChangePageTypeInMaster() {
		// create original
		$origPage = new SiteTree();
		$origPage->Locale = 'en_US';
		$origPage->write();
		$origPageID = $origPage->ID;
		
		// create translation
		$translatedPage = $origPage->createTranslation('de_DE');
		$translatedPageID = $translatedPage->ID;
		
		// change page type
		$newPage = $origPage->newClassInstance('RedirectorPage');
		$newPage->write();
		
		// re-fetch original page with new instance
		$origPageChanged = DataObject::get_by_id('RedirectorPage', $origPageID);
		$this->assertEquals($origPageChanged->ClassName, 'RedirectorPage',
			'A ClassName change to an original page doesnt change original classname'
		);
		
		// re-fetch the translation with new instance
		Translatable::set_current_locale('de_DE');
		$translatedPageChanged = DataObject::get_by_id('RedirectorPage', $translatedPageID);
		$translatedPageChanged = $origPageChanged->getTranslation('de_DE');
		$this->assertEquals($translatedPageChanged->ClassName, 'RedirectorPage',
			'ClassName change on an original page also changes ClassName attribute of translation'
		);
	}
	
	function testGetTranslationByStage() {
		$publishedPage = new SiteTree();
		$publishedPage->Locale = 'en_US';
		$publishedPage->Title = 'Published';
		$publishedPage->write();
		$publishedPage->publish('Stage', 'Live');
		$publishedPage->Title = 'Unpublished';
		$publishedPage->write();
		
		$publishedTranslatedPage = $publishedPage->createTranslation('de_DE');
		$publishedTranslatedPage->Title = 'Publiziert';
		$publishedTranslatedPage->write();
		$publishedTranslatedPage->publish('Stage', 'Live');
		$publishedTranslatedPage->Title = 'Unpubliziert';
		$publishedTranslatedPage->write();
		
		$compareStage = $publishedPage->getTranslation('de_DE', 'Stage');
		$this->assertNotNull($compareStage);
		$this->assertEquals($compareStage->Title, 'Unpubliziert');
		
		$compareLive = $publishedPage->getTranslation('de_DE', 'Live');
		$this->assertNotNull($compareLive);
		$this->assertEquals($compareLive->Title, 'Publiziert');
	}
	
	function testCanTranslateAllowedLocales() {
		$origAllowedLocales = Translatable::get_allowed_locales();
		
		$cmseditor = $this->objFromFixture('Member', 'cmseditor');
		
		$testPage = $this->objFromFixture('Page', 'testpage_en');
		$this->assertTrue(
			$testPage->canTranslate($cmseditor, 'de_DE'),
			"Users with canEdit() and TRANSLATE_ALL permission can create a new translation if locales are not limited"
		);
		
		Translatable::set_allowed_locales(array('ja_JP'));
		$this->assertTrue(
			$testPage->canTranslate($cmseditor, 'ja_JP'),
			"Users with canEdit() and TRANSLATE_ALL permission can create a new translation " .
			"if locale is in Translatable::get_allowed_locales()"
		);
		$this->assertFalse(
			$testPage->canTranslate($cmseditor, 'de_DE'),
			"Users with canEdit() and TRANSLATE_ALL permission can't create a new translation if " .
			"locale is not in Translatable::get_allowed_locales()"
		);
		
		$this->assertInstanceOf(
			'Page',
			$testPage->createTranslation('ja_JP')
		);
		try {
			$testPage->createTranslation('de_DE');
			$this->setExpectedException("Exception");
		} catch(Exception $e) {}
		
		Translatable::set_allowed_locales($origAllowedLocales);
	}
	
	function testCanTranslatePermissionCodes() {
		$origAllowedLocales = Translatable::get_allowed_locales();
		
		Translatable::set_allowed_locales(array('ja_JP','de_DE'));
		
		$cmseditor = $this->objFromFixture('Member', 'cmseditor');
		
		$testPage = $this->objFromFixture('Page', 'testpage_en');
		$this->assertTrue(
			$testPage->canTranslate($cmseditor, 'de_DE'),
			"Users with TRANSLATE_ALL permission can create a new translation"
		);
		
		$translator = $this->objFromFixture('Member', 'germantranslator');
		
		$testPage = $this->objFromFixture('Page', 'testpage_en');
		$this->assertTrue(
			$testPage->canTranslate($translator, 'de_DE'),
			"Users with TRANSLATE_<locale> permission can create a new translation"
		);
		
		$this->assertFalse(
			$testPage->canTranslate($translator, 'ja_JP'),
			"Users without TRANSLATE_<locale> permission can create a new translation"
		);
		
		Translatable::set_allowed_locales($origAllowedLocales);
	}
	
	function testLocalesForMember() {
		$origAllowedLocales = Translatable::get_allowed_locales();
		Translatable::set_allowed_locales(array('de_DE', 'ja_JP'));
		
		$cmseditor = $this->objFromFixture('Member', 'cmseditor');
		$translator = $this->objFromFixture('Member', 'germantranslator');
		
		$this->assertEquals(
			array('de_DE', 'ja_JP'), 
			singleton('SiteTree')->getAllowedLocalesForMember($cmseditor),
			'Members with TRANSLATE_ALL permission can edit all locales'
		);
		
		$this->assertEquals(
			array('de_DE'), 
			singleton('SiteTree')->getAllowedLocalesForMember($translator),
			'Members with TRANSLATE_<locale> permission cant edit all locales'
		);
		
		Translatable::set_allowed_locales($origAllowedLocales);
	}
	
	function testSavePageInCMS() {
		$adminUser = $this->objFromFixture('Member', 'admin');
		$enPage = $this->objFromFixture('Page', 'testpage_en');
		
		$group = new Group();
		$group->Title = 'Example Group';
		$group->write();
		
		$frPage = $enPage->createTranslation('fr_FR');
		$frPage->write();
		
		$adminUser->logIn();
		
		$cmsMain = new CMSPageEditController();
		
		$origLocale = Translatable::get_current_locale();
		Translatable::set_current_locale('fr_FR');
		
		$form = $cmsMain->getEditForm($frPage->ID);
		$form->loadDataFrom(array(
			'Title' => 'Translated', // $db field
		));
		$form->saveInto($frPage);
		$frPage->write();
	
		$this->assertEquals('Translated', $frPage->Title);
		
		$adminUser->logOut();
		Translatable::set_current_locale($origLocale);
	}
	
	public function testAlternateGetByLink() {
		$parent     = $this->objFromFixture('Page', 'parent');
		$child      = $this->objFromFixture('Page', 'child1');
		$grandchild = $this->objFromFixture('Page', 'grandchild1');
		
		$parentTranslation = $parent->createTranslation('en_AU');
		$parentTranslation->write();
		
		$childTranslation = $child->createTranslation('en_AU');
		$childTranslation->write();
		
		$grandchildTranslation = $grandchild->createTranslation('en_AU');
		$grandchildTranslation->write();
		
		Translatable::set_current_locale('en_AU');
		
		$this->assertEquals (
			$parentTranslation->ID,
			Sitetree::get_by_link($parentTranslation->Link())->ID,
			'Top level pages can be found.'
		);
		
		$this->assertEquals (
			$childTranslation->ID,
			SiteTree::get_by_link($childTranslation->Link())->ID,
			'Child pages can be found.'
		);
		
		$this->assertEquals (
			$grandchildTranslation->ID,
			SiteTree::get_by_link($grandchildTranslation->Link())->ID,
			'Grandchild pages can be found.'
		);
	
		// TODO Re-enable test after clarifying with ajshort (see r88503).
		// Its unclear if this is valid behaviour, and/or necessary for translated nested URLs
		// to work properly
		// 
		// $this->assertEquals (
		// 	$child->ID,
		// 	SiteTree::get_by_link($parentTranslation->Link($child->URLSegment))->ID,
		// 	'Links can be made up of multiple languages'
		// );
	}
	
	public function testSiteTreeGetByLinkFindsTranslationWithoutLocale() {
		$parent = $this->objFromFixture('Page', 'parent');
		
		$parentTranslation = $parent->createTranslation('en_AU');
		$parentTranslation->URLSegment = 'parent-en-AU';
		$parentTranslation->write();
		
		$match = Sitetree::get_by_link($parentTranslation->URLSegment);
		$this->assertNotNull(
			$match,
			'SiteTree::get_by_link() doesnt need a locale setting to find translated pages'
		);
		$this->assertEquals(
			$parentTranslation->ID,
			$match->ID,
			'SiteTree::get_by_link() doesnt need a locale setting to find translated pages'
		);
	}
}

class TranslatableTest_OneByLocaleDataObject extends DataObject implements TestOnly {
	private static $db = array(
		'TranslatableProperty' => 'Text'
	);
}

class TranslatableTest_DataObject extends DataObject implements TestOnly {
	// add_extension() used to add decorator at end of file
	
	private static $db = array(
		'TranslatableProperty' => 'Text'
	);
}

class TranslatableTest_Extension extends DataExtension implements TestOnly {
	
	private static $db = array(
		'TranslatableDecoratedProperty' => 'Text'
	);
	
}

class TranslatableTest_Page extends Page implements TestOnly {
	// static $extensions is inherited from SiteTree,
	// we don't need to explicitly specify the fields
	
	private static $db = array(
		'TranslatableProperty' => 'Text'
	);

	function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->addFieldToTab(
			'Root.Main',
			new TextField('TranslatableProperty')
		);

		$this->applyTranslatableFieldsUpdate($fields, 'updateCMSFields');

		return $fields;
	}
}


class EveryoneCanPublish extends DataExtension {

	function canPublish($member = null) {
		return true;
	}
}

TranslatableTest_DataObject::add_extension('TranslatableTest_Extension');
