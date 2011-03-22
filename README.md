# Translatable module for SilverStripe CMS #

## Introduction ##

Allows translation of DataObject and SiteTree records into multiple languages.
See `/docs/en/index.md` for details.

## Requirements ##

 * SilverStripe 3.0 (both cms and sapphire modules)

## Maintainers ##

 * Ingo Schommer <ingo at silverstripe dot com>

## TODO ##

This module was originally part of the SilverStripe CMS core codebase.
While the bulk of the logic has been separated out into this module,
there are still many places across SilverStripe CMS which this modules relies on:

* CMSBatchActionHandler->handleAction()
* ContentController->handleRequest()
* ContentController->ContentLocale()
* ErrorPage::response_for()
* LeftAndMain->init()
* ModelAsController->getNestedController()
* RootURLController::get_homepage_link()
* SearchForm
* SiteConfig
* SiteTree->RelativeLink()
* SiteTree->getSiteConfig()

These APIs mostly require either hooks for an Extension subclass,
or refactoring to allow better customization.