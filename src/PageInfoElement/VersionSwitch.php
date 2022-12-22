<?php

namespace BlueSpice\FlaggedRevsConnector\PageInfoElement;

use BlueSpice\FlaggedRevsConnector\Utils;
use Config;
use FlaggableWikiPage;
use FlaggedRevision;
use Html;
use IContextSource;
use MediaWiki\MediaWikiServices;
use Message;
use PageHeader\IPageInfo;

class VersionSwitch extends FlaggedPageElement {

	/** @var bool */
	public $hasSwitchToDraft = false;
	/** @var bool */
	public $hasSwitchToStable = false;
	/** @var bool */
	protected $hasImplicitDraft = false;

	/**
	 *
	 * @var Utils
	 */
	public $utils = null;

	/**
	 *
	 * @param IContextSource $context
	 * @param Config $config
	 * @param Utils|null $utils
	 * @return FlaggedPageElement
	 */
	public static function factory( IContextSource $context, Config $config, Utils $utils = null ) {
		if ( !$utils ) {
			// we need instance of bsg config. since 4.0.1 this $config is main config
			$utils = new Utils(
				MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'bsg' )
			);
		}
		return new static( $context, $config, $utils );
	}

	/**
	 *
	 * @return Message
	 */
	public function getLabelMessage() {
		if ( $this->hasSwitchToDraft ) {
			$label = $this->context->msg(
				'bs-flaggedrevsconnector-pageinfoelement-versionswitch-has-draft-text'
			);
		} elseif ( $this->hasSwitchToStable ) {
			$label = $this->context->msg(
				'bs-flaggedrevsconnector-pageinfoelement-versionswitch-has-stable-text'
			);
		}

		return $label;
	}

	/**
	 *
	 * @return Message
	 */
	public function getTooltipMessage() {
		if ( $this->hasSwitchToDraft ) {
			return $this->context->msg(
				'bs-flaggedrevsconnector-pageinfoelement-versionswitch-has-draft-title'
			);
		} elseif ( $this->hasSwitchToStable ) {
			return $this->context->msg(
				'bs-flaggedrevsconnector-pageinfoelement-versionswitch-has-stable-title'
			);
		}

		return '';
	}

	/**
	 *
	 * @return string
	 */
	public function getName() {
		return "bs-frc-version-switch";
	}

	/**
	 *
	 * @return string
	 */
	public function getUrl() {
		if ( $this->hasSwitchToDraft ) {
			return $this->context->getTitle()->getFullUrl( 'stable=0' );
		} elseif ( $this->hasSwitchToStable ) {
			return $this->context->getTitle()->getFullUrl( 'stable=1' );
		}

		return '';
	}

	/**
	 *
	 * @return string
	 */
	public function getHtmlClass() {
		if ( $this->hasSwitchToDraft ) {
			return 'bs-frc-pageinfo-page-draft';
		} elseif ( $this->hasSwitchToStable ) {
			return 'bs-frc-pageinfo-page-stable';
		}

		return '';
	}

	/**
	 *
	 * @return int
	 */
	public function getPosition() {
		return 1;
	}

	/**
	 * @return string Can be one of IPageInfo::TYPE_*
	 */
	public function getType() {
		return IPageInfo::TYPE_MENU;
	}

	/**
	 *
	 * @param IContextSource $context
	 * @return bool
	 */
	public function shouldShow( $context ) {
		if ( !parent::shouldShow( $context ) ) {
			return false;
		}

		$hasStable = $this->utils->getFlaggableWikiPage( $context )->getStableRev() instanceof FlaggedRevision;
		$showingStable = $this->utils->isShowingStable( $context );
		$hasDrafts = $this->utils->getFlaggableWikiPage( $context )->getPendingRevCount() > 0;
		$inSync = $this->utils->getFlaggableWikiPage( $context )->stableVersionIsSynced();

		if ( $showingStable && ( $hasDrafts || !$inSync ) ) {
			$this->hasSwitchToDraft = true;
			if ( !$inSync ) {
				$this->hasImplicitDraft = true;
			}
		} elseif ( ( !$showingStable && $hasStable ) && ( $hasDrafts || !$inSync ) ) {
			$this->hasSwitchToStable = true;
			if ( !$inSync ) {
				$this->hasImplicitDraft = true;
			}
		} else {
			return false;
		}

		return true;
	}

	/**
	 * @return string Can be one of IPageInfoElement::ITEMCLASS_*
	 */
	public function getItemClass() {
		return IPageInfo::ITEMCLASS_PRO;
	}

	/**
	 *
	 * @return string
	 */
	public function getMenu() {
		// We cannot show diff view if draft is only
		// caused by a change to resources (same rev)
		if ( !$this->hasImplicitDraft ) {
			return $this->makeMenu();
		}

		return '';
	}

	/**
	 *
	 * @return string
	 */
	protected function makeMenu() {
		$html = Html::openElement( 'ul' );

		$html .= Html::openElement( 'li' );
		$html .= $this->makeDiffLink();
		$html .= Html::closeElement( 'li' );

		$html .= Html::closeElement( 'ul' );

		return $html;
	}

	/**
	 *
	 * @return string
	 */
	protected function makeDiffLink() {
		$flaggableWikiPage = FlaggableWikiPage::getTitleInstance( $this->context->getTitle() );
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();

		return $linkRenderer->makeLink(
			$this->context->getTitle(),
			$this->context->msg( 'bs-flaggedrevsconnector-pageinfoelement-versionswitch-show-diff-label' ),
			[
				'title' => $this->context->msg(
					'bs-flaggedrevsconnector-pageinfoelement-versionswitch-show-diff-tooltip'
				)
			],
			[
				'oldid' => $flaggableWikiPage->getStable(),
				'diff' => $flaggableWikiPage->getLatest()
			]
		);
	}
}
