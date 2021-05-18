<?php

namespace BlueSpice\FlaggedRevsConnector\Renderer\PageHeader;

use Config;
use Revision;
use WikiPage;
use IContextSource;
use RequestContext;
use QuickTemplate;
use FlaggableWikiPage;
use MediaWiki\Linker\LinkRenderer;
use BlueSpice\Services;
use BlueSpice\UtilityFactory;
use BlueSpice\Renderer;
use BlueSpice\Renderer\Params;
use BlueSpice\FlaggedRevsConnector\Utils;
use BlueSpice\Calumma\Renderer\PageHeader\LastEdit as CalummaLastEdit;

class LastEdit extends CalummaLastEdit {

	/**
	 *
	 * @var Utils
	 */
	public $frcUtils = null;

	/**
	 * LastEdit constructor.
	 * @param Config $config
	 * @param Params $params
	 * @param LinkRenderer|null $linkRenderer
	 * @param IContextSource|null $context
	 * @param string $name
	 * @param QuickTemplate|null $skinTemplate
	 * @param UtilityFactory|null $util
	 * @param Utils|null $frcUtils
	 */
	protected function __construct( Config $config, Params $params,
		LinkRenderer $linkRenderer = null, IContextSource $context = null,
		$name = '', QuickTemplate $skinTemplate = null, UtilityFactory $util = null,
		Utils $frcUtils = null ) {
		parent::__construct(
			$config,
			$params,
			$linkRenderer,
			$context,
			$name,
			$skinTemplate,
			$util
		);

		$this->frcUtils = $frcUtils;
	}

	/**
	 *
	 * @param string $name
	 * @param Services $services
	 * @param Config $config
	 * @param Params $params
	 * @param IContextSource|null $context
	 * @param LinkRenderer|null $linkRenderer
	 * @param QuickTemplate|null $skinTemplate
	 * @param UtilityFactory|null $util
	 * @param Utils|null $frcUtils
	 * @return Renderer
	 */
	public static function factory( $name, Services $services, Config $config, Params $params,
		IContextSource $context = null, LinkRenderer $linkRenderer = null,
		QuickTemplate $skinTemplate = null, UtilityFactory $util = null, Utils $frcUtils = null ) {
		if ( !$context ) {
			$context = $params->get(
				static::PARAM_CONTEXT,
				false
			);
			if ( !$context instanceof IContextSource ) {
				$context = RequestContext::getMain();
			}
		}
		if ( !$linkRenderer ) {
			$linkRenderer = $services->getLinkRenderer();
		}
		if ( !$util ) {
			$util = $services->getService( 'BSUtilityFactory' );
		}
		if ( !$skinTemplate ) {
			$skinTemplate = $params->get( static::SKIN_TEMPLATE, null );
		}
		if ( !$skinTemplate ) {
			throw new Exception(
				'Param "' . static::SKIN_TEMPLATE . '" must be an instance of '
				. QuickTemplate::class
			);
		}
		if ( !$frcUtils ) {
			$frcUtils = new Utils( $config );
		}

		return new static(
			$config,
			$params,
			$linkRenderer,
			$context,
			$name,
			$skinTemplate,
			$util,
			$frcUtils
		);
	}

	/**
	 * @param WikiPage $wikiPage
	 * @return Revision|null
	 */
	protected function getCurrentRevision( WikiPage $wikiPage ) {
		$title = $wikiPage->getTitle();
		$currentRevision = null;

		if ( $this->getOldId() ) {
			$currentRevision = Revision::newFromId( $this->getOldId() );
		} elseif ( $this->frcUtils->isFlaggableNamespace( $title ) && $this->isStable() ) {
			$flaggableWikiPage = FlaggableWikiPage::getTitleInstance( $title );
			$currentRevision = Revision::newFromId( $flaggableWikiPage->getStable() );
		}

		$currentRevision = $currentRevision ? $currentRevision : $wikiPage->getRevision();

		return $currentRevision;
	}

	/**
	 * @return bool
	 */
	private function isStable() {
		$stable = $this->getContext()->getRequest()->getVal( 'stable' );

		if ( $stable === null ) {
			return true;
		}

		return (bool)$stable;
	}

	/**
	 * @return int|null
	 */
	private function getOldId() {
		return $this->getContext()->getRequest()->getIntOrNull( 'oldid' );
	}
}
