<?php

namespace MediaWiki\Extension\DynamicPageList4\Lister;

use MediaWiki\Extension\DynamicPageList4\Article;
use MediaWiki\Registration\ExtensionRegistry;
use PageImages\PageImages;
use function trim;
use const NS_FILE;

class GalleryList extends Lister {

	protected int $style = parent::LIST_GALLERY;

	protected string $listStart = '<gallery%s>';
	protected string $listEnd = '</gallery>';

	protected string $itemStart = "\n";
	protected string $itemEnd = '|';

	protected function formatItem( Article $article, ?string $pageText ): string {
		$item = $article->mTitle->getPrefixedText();
		$this->listAttributes = '';

		$imageWidth = trim( $this->parameters->getParameter( 'imagewidth' ) ?? '' );
		if ( $imageWidth !== '' ) {
			$this->listAttributes .= " widths=$imageWidth";
		}

		$imageHeight = trim( $this->parameters->getParameter( 'imageheight' ) ?? '' );
		if ( $imageHeight !== '' ) {
			$this->listAttributes .= " heights=$imageHeight";
		}

		$galleryMode = trim( $this->parameters->getParameter( 'gallerymode' ) ?? '' );
		if ( $galleryMode !== '' ) {
			$this->listAttributes .= " mode=$galleryMode";
		}

		// If PageImages is loaded and this is not a file, attempt to assemble a gallery of PageImages
		if ( $article->mNamespace !== NS_FILE && ExtensionRegistry::getInstance()->isLoaded( 'PageImages' ) ) {
			$pageImage = PageImages::getPageImage( $article->mTitle );
			if ( $pageImage && $pageImage->exists() ) {
				// Successfully got a page image, wrapping it.
				$item = $this->getItemStart() . $pageImage->getName() . $this->itemEnd .
					"[[$item]]{$this->itemEnd}link=$item";
			} else {
				// Failed to get a page image.
				$item = $this->getItemStart() . $item . $this->itemEnd . "[[$item]]";
			}

			return $this->replaceTagParameters( $item, $article );
		}

		if ( $pageText !== null ) {
			// Include parsed/processed wiki markup content
			// after each item before the closing tag.
			$item .= $pageText;
		}

		$item = $this->getItemStart() . $item . $this->itemEnd;
		return $this->replaceTagParameters( $item, $article );
	}
}
