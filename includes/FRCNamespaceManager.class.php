<?php

class FRCNamespaceManager {

	public function onGetMetaFields( &$aMetaFields ) {
		$aMetaFields[] = array(
			'name' => 'flaggedrevs',
			'type' => 'boolean',
			'label' => wfMessage( 'bs-flaggedrevsconnector-label-flaggedrevs' )->plain(),
			'filter' => array(
				'type' => 'boolean'
			),
		);
		return true;
	}

	public function onGetNamespaceData( &$aResults ) {
		global $wgFlaggedRevsNamespaces;

		$iResults = count( $aResults );
		for ( $i = 0; $i < $iResults; $i++ ) {
			$aResults[ $i ][ 'flaggedrevs' ] = [
				'value' => in_array( $aResults[ $i ][ 'id' ], $wgFlaggedRevsNamespaces ),
				'disabled' => $aResults[ $i ]['isTalkNS']
			];
		}
		return true;
	}

	public function onEditNamespace( &$aNamespaceDefinitions, &$iNS, $aAdditionalSettings, $bUseInternalDefaults = false ) {
		if ( MWNamespace::isTalk( $iNS ) ) { //FlaggedRevs can not be activated for TALK namespaces!
			return true;
		}

		if ( !$bUseInternalDefaults && isset( $aAdditionalSettings['flaggedrevs'] ) ) {
			$aNamespaceDefinitions[$iNS][ 'flaggedrevs' ] = $aAdditionalSettings['flaggedrevs'];
		}
		else {
			$aNamespaceDefinitions[$iNS][ 'flaggedrevs' ] = false;
		}
		return true;
	}

	public function onWriteNamespaceConfiguration( &$sSaveContent, $sConstName, $iNsID, $aDefinition ) {
		global $wgFlaggedRevsNamespaces;

		if ( $iNsID=== null || MWNamespace::isTalk( $iNsID ) ) { //FlaggedRevs can not be activated for TALK namespaces!
			return true;
		}

		$bCurrentlyActivated = in_array($iNsID, $wgFlaggedRevsNamespaces);

		$bExplicitlyDeactivated = false;
		if ( isset( $aDefinition[ 'flaggedrevs' ] ) && $aDefinition[ 'flaggedrevs' ] === false ) {
			$bExplicitlyDeactivated = true;
		}

		$bExplicitlyActivated = false;
		if ( isset( $aDefinition[ 'flaggedrevs' ] ) && $aDefinition[ 'flaggedrevs' ] === true ) {
			$bExplicitlyActivated = true;
		}

		if( ($bCurrentlyActivated && !$bExplicitlyDeactivated) || $bExplicitlyActivated ) {
			$sSaveContent .= "\$GLOBALS['wgFlaggedRevsNamespaces'][] = {$sConstName};\n";
		}

		return true;
	}
}
