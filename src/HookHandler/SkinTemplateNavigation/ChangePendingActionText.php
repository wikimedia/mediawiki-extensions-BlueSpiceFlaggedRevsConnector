<?php

namespace BlueSpice\FlaggedRevsConnector\HookHandler\SkinTemplateNavigation;

use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;

class ChangePendingActionText implements SkinTemplateNavigation__UniversalHook {

	/**
	 * // phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
	 * @inheritDoc
	 */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		if ( !isset( $links['views']['current'] ) ) {
			return;
		}

		$links['views']['current']['text']
			= $sktemplate->msg( "bs-flaggedrevsconnector-state-draft" )->plain();
	}
}
