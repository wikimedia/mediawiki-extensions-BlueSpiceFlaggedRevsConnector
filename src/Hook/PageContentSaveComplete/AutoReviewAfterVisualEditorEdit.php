<?php

namespace BlueSpice\FlaggedRevsConnector\Hook\PageContentSaveComplete;

use BlueSpice\Hook\PageContentSaveComplete;
use BlueSpice\FlaggedRevsConnector\PermissionLessReviewForm;
use FlaggedRevs;
use FRInclusionCache;
use RevisionReviewForm;
use FlaggableWikiPage;
use User;
use Title;
use Revision;

class AutoReviewAfterVisualEditorEdit extends PageContentSaveComplete {

	/**
	 *
	 * @return bool
	 */
	protected function skipProcessing() {
		if ( !$this->getContext()->getRequest()->getCheck( 'bswpReviewEdit' ) ) {
			return true;
		}
		return !$this->wikipage->getTitle()->userCan( 'review', $this->user );
	}

	/**
	 *
	 * @return bool
	 */
	protected function doProcess() {
		$res = $this->doOwnWorkingReview(
			$this->user,
			$this->wikipage->getTitle(),
			$this->revision,
			$this->summary
		);
		return true;
	}

	/**
	 * @param User $user
	 * @param Title $title
	 * @param Revision $revision
	 * @param string $comment
	 * @return bool|string
	 */
	protected function doOwnWorkingReview( User $user, Title $title,
		Revision $revision, $comment = '' ) {

		// Construct submit form...
		$form = new PermissionLessReviewForm( $user );
		$form->setPage( $title );
		$form->setOldId( $revision->getId() );
		$form->setApprove( true );
		$form->setUnapprove( false );
		$form->setComment( $comment );

		// The flagging parameters have the form 'flag_$name'.
		// Extract them and put the values into $form->dims
		foreach ( FlaggedRevs::getTags() as $tag ) {
			if ( FlaggedRevs::binaryFlagging() ) {
				$form->setDim( $tag, 1 );
			}
		}

		$article = new FlaggableWikiPage( $title );
		// Get the file version used for File: pages
		$file = $article->getFile();
		if ( $file ) {
			$fileVer = array( 'time' => $file->getTimestamp(), 'sha1' => $file->getSha1() );
		} else {
			$fileVer = null;
		}

		// Now get the template and image parameters needed
		list( $templateIds, $fileTimeKeys ) =
			FRInclusionCache::getRevIncludes( $article, $revision, $user );
		// Get version parameters for review submission (flat strings)
		list( $templateParams, $imageParams, $fileParam ) =
			RevisionReviewForm::getIncludeParams( $templateIds, $fileTimeKeys, $fileVer );
		// Set the version parameters...
		$form->setTemplateParams( $templateParams );
		$form->setFileParams( $imageParams );
		$form->setFileVersion( $fileParam );
		// always OK; uses current templates/files
		$form->bypassValidationKey();

		// all params set
		$form->ready();

		# Try to do the actual review
		$status = $form->submit();

		return $status;
	}

}
