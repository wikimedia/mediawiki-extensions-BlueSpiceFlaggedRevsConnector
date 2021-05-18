<?php

namespace BlueSpice\FlaggedRevsConnector\Privacy;

use BlueSpice\Privacy\IPrivacyHandler;
use BlueSpice\Privacy\Module\Transparency;

class Handler implements IPrivacyHandler {
	protected $db;
	protected $language;

	/**
	 *
	 * @param \Database $db
	 */
	public function __construct( \Database $db ) {
		$this->db = $db;
		$this->language = \RequestContext::getMain()->getLanguage();
	}

	/**
	 *
	 * @param string $oldUsername
	 * @param string $newUsername
	 * @return \Status
	 */
	public function anonymize( $oldUsername, $newUsername ) {
		return \Status::newGood();
	}

	/**
	 *
	 * @param \User $userToDelete
	 * @param \User $deletedUser
	 * @return \Status
	 */
	public function delete( \User $userToDelete, \User $deletedUser ) {
		$this->db->update(
			'flaggedrevs',
			[ 'fr_user' => $deletedUser->getId() ],
			[ 'fr_user' => $userToDelete->getId() ],
			__METHOD__
		);

		return \Status::newGood();
	}

	/**
	 *
	 * @param array $types
	 * @param string $format
	 * @param \User $user
	 * @return \Status
	 */
	public function exportData( array $types, $format, \User $user ) {
		if ( !in_array( Transparency::DATA_TYPE_WORKING, $types ) ) {
			return \Status::newGood( [] );
		}

		$res = $this->db->select(
			'flaggedrevs',
			'*',
			[ 'fr_user' => $user->getId() ],
			__METHOD__
		);

		$data = [];
		foreach ( $res as $row ) {
			$title = \Title::newFromID( $row->fr_page_id );
			if ( !$title ) {
				continue;
			}

			$timestamp = $this->language->userTimeAndDate(
				$row->fr_timestamp,
				$user
			);

			$tags = explode( "\n", $row->fr_tags );
			$messagizedTags = [];
			foreach ( $tags as $tag ) {
				if ( empty( $tag ) ) {
					continue;
				}
				$bits = explode( ':', $tag );
				$stepMessage = wfMessage( "revreview-{$bits[0]}" )->plain();
				$flagMessage = wfMessage( "revreview-{$bits[0]}-{$bits[1]}" )->plain();
				$messagizedTags[] = $stepMessage . ':' . $flagMessage;
			}
			$messagizedTags = implode( ', ', $messagizedTags );

			$data[] = wfMessage(
				'bs-flaggedrevsconnector-privacy-transparency-working-flagging',
				$title->getPrefixedText(),
				$row->fr_rev_id,
				$timestamp,
				$messagizedTags
			)->plain();
		}

		return \Status::newGood( [
			Transparency::DATA_TYPE_WORKING => $data
		] );
	}
}
