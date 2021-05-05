<?php

namespace BlueSpice\FlaggedRevsConnector\PageInfoElement;

use FlaggedRevision;
use Message;
use Html;
use IContextSource;
use Config;
use BlueSpice\IPageInfoElement;
use BlueSpice\FlaggedRevsConnector\Utils;

class PageStatusDropdown extends FlaggedPageElement {
	/** @var string  */
	public $state = 'undefined';
	/** @var bool  */
	public $needApproval = false;
	/** @var bool  */
	public $implicitDraft = false;

	/**
	 *
	 * @param IContextSource $context
	 * @param Config $config
	 * @param Utils $utils
	 */
	public static function factory( IContextSource $context, Config $config, Utils $utils = null ) {
		if ( !$utils ) {
			$utils = new Utils( $config );
		}
		return new static( $context, $config, $utils );
	}

	/**
	 *
	 * @return Message
	 */
	public function getLabelMessage() {
		// bs-flaggedrevsconnector-pageinfoelement-pagestatus-is-draft-text
		// bs-flaggedrevsconnector-pageinfoelement-pagestatus-is-first-draft-text
		// bs-flaggedrevsconnector-pageinfoelement-pagestatus-is-stable-text
		return $this->msg( 'bs-flaggedrevsconnector-pageinfoelement-pagestatus-is-' . $this->state . '-text' );
	}

	/**
	 *
	 * @return string
	 */
	public function getName() {
		return "bs-frc-page-status";
	}

	/**
	 *
	 * @return Message
	 */
	public function getTooltipMessage() {
		// bs-flaggedrevsconnector-pageinfoelement-pagestatus-is-draft-title
		// bs-flaggedrevsconnector-pageinfoelement-pagestatus-is-first-draft-title
		// bs-flaggedrevsconnector-pageinfoelement-pagestatus-is-stable-title
		return $this->msg( 'bs-flaggedrevsconnector-pageinfoelement-pagestatus-is-' . $this->state . '-title' );
	}

	/**
	 *
	 * @param IContextSource $context
	 * @return boolean
	 */
	public function shouldShow( $context ) {
		if ( !parent::shouldShow( $context ) ) {
			return false;
		}

		$hasStable = $this->utils->getFlaggableWikiPage( $context )->getStableRev() instanceof FlaggedRevision;
		$showingStable = $this->utils->isShowingStable( $context );
		$hasDrafts = $this->utils->getFlaggableWikiPage( $context )->getPendingRevCount() > 0;
		$inSync = $this->utils->getFlaggableWikiPage( $context )->stableVersionIsSynced();

		if ( $showingStable ) {
			$this->state = 'stable';
		} elseif ( !$hasStable ) {
			$this->state = 'first-draft';
			$this->needApproval = true;
		} elseif ( $hasDrafts || !$inSync ) {
			$this->state = 'draft';
			$this->needApproval = true;
			if ( !$inSync ) {
				$this->implicitDraft = true;
			}
		}

		return true;
	}

	/**
	 *
	 * @return string
	 */
	public function getItemClass() {
		if (  $this->state === 'stable') {
			return IPageInfoElement::ITEMCLASS_CONTRA;
		}

		if ( ( $this->state === 'draft' ) || ( $this->state === 'first-draft' ) ) {
			return IPageInfoElement::ITEMCLASS_CONTRA;
		}
	}

	/**
	 *
	 * @return string
	 */
	public function getHtmlClass() {
		return 'bs-frc-pageinfo-page-' . $this->state;
	}

	/**
	 *
	 * @return string
	 */
	public function getType() {
		if ( $this->needApproval ) {
			return IPageInfoElement::TYPE_MENU;
		} else {
			return IPageInfoElement::TYPE_TEXT;
		}
	}

	/**
	 *
	 * @return string
	 */
	public function getMenu() {
		if( $this->getType() === IPageInfoElement::TYPE_TEXT ) {
			return '';
		}

		if( !$this->needApproval ) {
			return '';
		}

		if ( !$this->context->getTitle()->userCan( 'review' ) ) {
			return '';
		}
		return $this->makeMenu();
	}

	/**
	 *
	 * @return string
	 */
	public function makeMenu() {
		$html = Html::openElement( 'ul' );

		$html .= Html::openElement( 'li' );
		$html .= $this->makeApproveLink();
		$html .= Html::closeElement( 'li' );

		$html .= Html::closeElement( 'ul' );

		return $html;
	}

	/**
	 *
	 * @return string
	 */
	protected function makeApproveLink() {
		$html = Html::openElement( 'a', [
			'href' => '#',
			'data-user-can-review' => $this->context->getTitle()->userCan( 'review' )
		] );

		$html .= Html::element(
				'span',
				[
					'class' => 'bs-frc-review'
				],
				$this->msg( 'bs-flaggedrevsconnector-pageinfoelement-pagestatus-accept' )->plain()
				);

		$html .= Html::closeElement( 'a' );

		return $html;
	}
}
