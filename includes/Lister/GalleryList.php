<?php

namespace MediaWiki\Extension\DynamicPageList3\Lister;

use MediaWiki\Extension\DynamicPageList3\Article;
use PageImages\PageImages;
use ExtensionRegistry;
use MediaWiki\MediaWikiServices;

class GalleryList extends Lister {
	/**
	 * Listing style for this class.
	 *
	 * @var int
	 */
	public $style = parent::LIST_GALLERY;

	/**
	 * List(Section) Start
	 *
	 * @var string
	 */
	public $listStart = '<gallery%s>';

	/**
	 * List(Section) End
	 *
	 * @var string
	 */
	public $listEnd = '</gallery>';

	/**
	 * Item Start
	 *
	 * @var string
	 */
	public $itemStart = "\n";

	/**
	 * Item End
	 *
	 * @var string
	 */
	public $itemEnd = '|';
	
	/**
	 * Return $this->listStart with attributes replaced.
	 *
	 * @return string
	 */
	public function getListStart() {
		// add gallery parameters
		$galleryparams = $this->getParameters()->getParameter( 'galleryparameters' );

		return sprintf( $this->listStart, $this->listAttributes . ' ' . $galleryparams );
	}

	/**
	 * Format an item.
	 *
	 * @param Article $article
	 * @param string|null $pageText
	 * @return string
	 */
	public function formatItem( Article $article, $pageText = null ) {
		$item = $article->mTitle;
		if ( $article->mNamespace != NS_FILE && ExtensionRegistry::getInstance()->isLoaded( 'PageImages' ) ) {
			$pageImage = self::getPageImage( $article->mID ) ?: false;
			if ( $pageImage ) {
				$item = $pageImage;
			}
		}

		if ( $pageText !== null ) {
			// Include parsed/processed wiki markup content after each item before the closing tag.
			$item .= $pageText;
		}

		if ( $this->getParameters()->getParameter( 'gallerycaptions' ) ) {
			$item = $this->getItemStart() . $item . $this->itemEnd . $article->mTitle;
		}
		else
		{
			$item = $this->getItemStart() . $item . $this->itemEnd;
		}

		$item = $this->replaceTagParameters( $item, $article );

		return $item;
	}


	public function getPageImage( int $pageID ) {

		$dbl = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $dbl->getConnection( DB_REPLICA );
		//in the future, a check could be made for the page_image prop too, but page_image_free is the default, should do for now
		$propValue = $dbr->selectField( 'page_props', // table to use
					'pp_value', // Field to select
					[ 'pp_page' => $pageID, 'pp_propname' => "page_image_free" ], // where conditions 
					__METHOD__
		);
		if ( $propValue === false ) {
					// No prop stored for this page
					return false;
		}

		return $propValue;
	}
}
