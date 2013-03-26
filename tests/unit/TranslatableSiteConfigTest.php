<?php
/**
 * @package translatable
 */
class TranslatableSiteConfigTest extends SapphireTest {
	
	protected static $fixture_file = 'translatable/tests/unit/TranslatableSiteConfigTest.yml';
	
	protected $requiredExtensions = array(
		'SiteTree' => array('Translatable'),
		'SiteConfig' => array('Translatable'),
	);
	
	protected $illegalExtensions = array(
		'SiteTree' => array('SiteTreeSubsites')
	);
	
	private $origLocale;

	function setUp() {
		parent::setUp();
				
		$this->origLocale = Translatable::default_locale();
		Translatable::set_default_locale("en_US");
	}
	
	function tearDown() {
		Translatable::set_default_locale($this->origLocale);
		Translatable::set_current_locale($this->origLocale);

		parent::tearDown();
	}
	
	function testCurrentCreatesDefaultForLocale() {
		Translatable::set_current_locale(Translatable::default_locale());
		$configEn = SiteConfig::current_site_config();
		Translatable::set_current_locale('fr_FR');
		$configFr = SiteConfig::current_site_config();
		Translatable::set_current_locale(Translatable::default_locale());
		
		$this->assertInstanceOf('SiteConfig', $configFr);
		$this->assertEquals($configFr->Locale, 'fr_FR');
		$this->assertEquals($configFr->Title, $configEn->Title, 'Copies title from existing config');
		$this->assertEquals(
			$configFr->getTranslationGroup(), 
			$configEn->getTranslationGroup(), 
			'Created in the same translation group'
		);
	}
	
	function testCanEditTranslatedRootPages() {
		$configEn = $this->objFromFixture('SiteConfig', 'en_US');
		$configDe = $this->objFromFixture('SiteConfig', 'de_DE');
		
		$pageEn = $this->objFromFixture('Page', 'root_en');
		$pageDe = $pageEn->createTranslation('de_DE');
		
		$translatorDe = $this->objFromFixture('Member', 'translator_de');
		$translatorEn = $this->objFromFixture('Member', 'translator_en');
		
		$this->assertFalse($pageEn->canEdit($translatorDe));
		$this->assertTrue($pageEn->canEdit($translatorEn));
	}
}