<?php

use MediaWiki\MediaWikiServices;

// phpcs:ignore MediaWiki.Files.ClassMatchesFilename.NotMatch
class FRCDiffView extends ContextSource {

	protected $out = null;
	protected $article = null;
	// ( array of templates, array of file)
	protected $oldRevIncludes = null;
	protected $isReviewableDiff = false;
	protected $isDiffFromStable = false;
	protected $isMultiPageDiff = false;
	protected $reviewNotice = '';
	protected $diffNoticeBox = '';
	protected $diffIncChangeBox = '';
	protected $reviewFormRev = false;
	protected $loaded = false;
	protected static $suppressParserOutput = false;
	protected static $instance = null;

	/**
	 *
	 * @param Article &$article
	 * @param bool &$outputDone
	 * @param bool &$pcache
	 * @return bool
	 */
	public static function onArticleViewHeader( &$article, &$outputDone, &$pcache ) {
		if ( $outputDone || !class_exists( 'FlaggablePageView', true ) ) {
			return true;
		}

		$view = self::singleton();
		$request = $view->getRequest();
		if ( !$request->getVal( 'oldid' ) || !$request->getVal( 'diff' ) ) {
			return true;
		}
		$view->addStableLink( $outputDone, $useParserCache );
		$view->setPageContent( $outputDone, $useParserCache );

		return true;
	}

	/**
	 *
	 * @param DifferenceEngine $diffEngine
	 * @param OutputPage $output
	 * @return bool
	 */
	public static function onArticleContentOnDiff( $diffEngine, $output ) {
		$outputDoneStub = false;
		$useParserCacheStub = false;

		$view = self::singleton();
		$view->addStableLink();
		$view->setPageContent( $outputDoneStub, $useParserCacheStub );
		self::$suppressParserOutput = true;
		return true;
	}

	/**
	 *
	 * @param OutputPage &$out
	 * @param string &$text
	 * @return bool
	 */
	public static function onOutputPageBeforeHTML( &$out, &$text ) {
		if ( self::$suppressParserOutput ) {
			$text = '';
		}
		return true;
	}

	/**
	 *
	 * @return FRCDiffView
	 */
	public static function singleton() {
		if ( self::$instance == null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	protected function __construct() {
	}

	protected function __clone() {
	}

	/**
	 * Clear the FlaggablePageView for this request.
	 * Only needed when page redirection changes the environment.
	 */
	public function clear() {
		self::$instance = null;
	}

	/**
	 * Load the global FlaggableWikiPage instance
	 */
	protected function load() {
		if ( !$this->loaded ) {
			$this->loaded = true;
			$this->article = self::globalArticleInstance();
			if ( $this->article == null ) {
				throw new MWException( 'FlaggablePageView has no context article!' );
			}
			// convenience
			$this->out = $this->getOutput();
		}
	}

	/**
	 * Get the FlaggableWikiPage instance associated with $wgTitle,
	 * or false if there isn't such a title
	 * @return FlaggableWikiPage|null
	 */
	public static function globalArticleInstance() {
		$title = RequestContext::getMain()->getTitle();
		if ( $title ) {
			return FlaggableWikiPage::getTitleInstance( $title );
		}
		return null;
	}

	/**
	 * Is this web response for a request to view a page where both:
	 * (a) no specific page version was requested via URL params
	 * (b) a stable version exists and is to be displayed
	 * This factors in site/page config, user preferences, and web request params.
	 * @return bool
	 */
	protected function showingStableAsDefault() {
		$request = $this->getRequest();
		$reqUser = $this->getUser();
		$this->load();
		# This only applies to viewing the default version of pages...
		if ( !$this->isDefaultPageView( $request ) ) {
			return false;
			# ...and the page must be reviewable and have a stable version
		} elseif ( !$this->article->getStableRev() ) {
			return false;
		}
		# Check user preferences ("show stable by default?")
		$pref = (int)$reqUser->getOption( 'flaggedrevsstable' );
		if ( $pref == FR_SHOW_STABLE_ALWAYS ) {
			return true;
		} elseif ( $pref == FR_SHOW_STABLE_NEVER ) {
			return false;
		}
		# Viewer may be in a group that sees the draft by default
		if ( $this->userViewsDraftByDefault( $reqUser ) ) {
			return false;
		}
		# Does the stable version override the draft?
		$config = $this->article->getStabilitySettings();
		return (bool)$config['override'];
	}

	/**
	 * Is this web response for a request to view a page where both:
	 * (a) the stable version of a page was requested (?stable=1)
	 * (b) the stable version exists and is to be displayed
	 * @return bool
	 */
	protected function showingStableByRequest() {
		$request = $this->getRequest();
		$this->load();
		# Are we explicity requesting the stable version?
		if ( $request->getIntOrNull( 'stable' ) === 1 ) {
			# This only applies to viewing a version of the page...
			if ( !$this->isPageView( $request ) ) {
				return false;
				# ...with no version parameters other than ?stable=1...
			} elseif ( $request->getVal( 'oldid' ) || $request->getVal( 'stableid' ) ) {
				// over-determined
				return false;
				# ...and the page must be reviewable and have a stable version
			} elseif ( !$this->article->getStableRev() ) {
				return false;
			}
			// show stable version
			return true;
		}
		return false;
	}

	/**
	 * Is this web response for a request to view a page
	 * where a stable version exists and is to be displayed
	 * @return bool
	 */
	public function showingStable() {
		return $this->showingStableByRequest() || $this->showingStableAsDefault();
	}

	/**
	 * Should this be using a simple icon-based UI?
	 * Check the user's preferences first, using the site settings as the default.
	 * @return bool
	 */
	public function useSimpleUI() {
		$reqUser = $this->getUser();
		return $reqUser->getOption( 'flaggedrevssimpleui' );
	}

	/**
	 * Should this user see the draft revision of pages by default?
	 * @param User $user
	 * @return bool
	 */
	protected function userViewsDraftByDefault( $user ) {
		$services = MediaWikiServices::getInstance();
		$config = $services->getMainConfig();
		$userGroupManager = $services->getUserGroupManager();
		# Check user preferences ("show stable by default?")
		if ( $user->getOption( 'flaggedrevsstable' ) ) {
			return false;
		}
		# Viewer sees current by default (editors, insiders, ect...) ?
		foreach ( $config->get( 'FlaggedRevsExceptions' ) as $group ) {
			if ( $group == 'user' ) {
				if ( $user->getId() ) {
					return true;
				}
			} elseif ( in_array( $group, $userGroupManager->getUserGroups( $user ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Is this a view page action (including diffs)?
	 * @param WebRequest $request
	 * @return bool
	 */
	protected function isPageViewOrDiff( WebRequest $request ) {
		$action = Action::getActionName( $this->getContext() );
		return self::isViewAction( $action );
	}

	/**
	 * Is this a view page action (not including diffs)?
	 * @param WebRequest $request
	 * @return bool
	 */
	protected function isPageView( WebRequest $request ) {
		return $this->isPageViewOrDiff( $request ) && $request->getVal( 'diff' ) === null;
	}

	/**
	 * Is this a web request to just *view* the *default* version of a page?
	 * @param WebRequest $request
	 * @return bool
	 */
	protected function isDefaultPageView( WebRequest $request ) {
		$action = Action::getActionName( $this->getContext() );
		return (
			self::isViewAction( $action ) && $request->getVal( 'oldid' ) === null
			&& $request->getVal( 'stable' ) === null && $request->getVal( 'stableid' ) === null
			&& $request->getVal( 'diff' ) === null
		);
	}

	/**
	 * Is this a view page action?
	 * @param string $action string from MediaWiki::getAction()
	 * @return bool
	 */
	protected static function isViewAction( $action ) {
		return ( $action == 'view' || $action == 'purge' || $action == 'render' );
	}

	/**
	 * Add a stable link when viewing old versions of an article that
	 * have been reviewed. (e.g. for &oldid=x urls)
	 * @return bool
	 */
	public function addStableLink() {
		$request = $this->getRequest();
		$this->load();
		if ( !$this->article->isReviewable() || !$request->getVal( 'oldid' ) ) {
			return true;
		}
		if ( !$this->out->isPrintable() ) {
			# We may have nav links like "direction=prev&oldid=x"
			$revID = $this->getOldIDFromRequest();
			$frev = FlaggedRevision::newFromTitle( $this->article->getTitle(), $revID );
			# Give a notice if this rev ID corresponds to a reviewed version...
			if ( $frev ) {
				$time = $this->getLanguage()->date( $frev->getTimestamp(), true );
				$flags = $frev->getTags();
				$quality = FlaggedRevs::isQuality( $flags );
				$msg = $quality ? 'revreview-quality-source' : 'revreview-basic-source';
				$tag = $this->msg( $msg, $frev->getRevId(), $time )->parse();
				# Hide clutter
				if ( !$this->useSimpleUI() && !empty( $flags ) ) {
					$tag .= FlaggedRevsXML::ratingToggle() .
							"<div id='mw-fr-revisiondetails'>" .
							$this->msg( 'revreview-oldrating' )->escaped() .
							FlaggedRevsXML::addTagRatings( $flags ) . '</div>';
				}
				$css = 'flaggedrevs_notice plainlinks noprint';
				$tag = "<div id='mw-fr-revisiontag-old' class='$css'>$tag</div>";
				$this->out->addHTML( $tag );
			}
		}
		return true;
	}

	/**
	 * @return mixed int/false/null
	 */
	protected function getRequestedStableId() {
		$request = $this->getRequest();
		$reqId = $request->getVal( 'oldid' );
		if ( $reqId === "best" ) {
			$reqId = $this->article->getBestFlaggedRevId();
		}
		return $reqId;
	}

	/**
	 * Replaces a page with the last stable version if possible
	 * Adds stable version status/info tags and notes
	 * Adds a quick review form on the bottom if needed
	 * @param bool &$outputDone
	 * @param bool &$useParserCache
	 * @return bool
	 */
	public function setPageContent( &$outputDone, &$useParserCache ) {
		$request = $this->getRequest();
		$this->load();
		# Only trigger for reviewable pages that exist
		if ( !$this->article->exists() || !$this->article->isReviewable() ) {
			return true;
		}
		// review tag box/bar message
		$tag = '';
		$old = $stable = false;
		# Check the newest stable version.
		$srev = $this->article->getStableRev();
		$stableId = $srev ? $srev->getRevId() : 0;
		// $frev is the revision we are looking at
		$frev = $srev;
		# Check for any explicitly requested reviewed version (stableid=X)...
		$reqId = $this->getRequestedStableId();
		if ( $reqId ) {
			if ( !$stableId ) {
				// must be invalid
				$reqId = false;
				# Treat requesting the stable version by ID as &stable=1
			} elseif ( $reqId != $stableId ) {
				// old reviewed version requested by ID
				$old = true;
				$frev = FlaggedRevision::newFromTitle( $this->article->getTitle(), $reqId );
				if ( !$frev ) {
					// invalid ID given
					$reqId = false;
				}
			} else {
				// stable version requested by ID
				$stable = true;
			}
		}
		// $reqId is null if nothing requested, false if invalid
		if ( $reqId === false ) {
			$this->out->addWikiText( $this->msg( 'revreview-invalid' )->text() );
			$this->out->returnToMain( false, $this->article->getTitle() );
			# Tell MW that parser output is done
			$outputDone = true;
			$useParserCache = false;
			return true;
		}
		// Is the page config altered?
		$prot = FlaggedRevsXML::lockStatusIcon( $this->article );
		if ( $frev ) {
			// has stable version?
			// Looking at some specific old stable revision ("&stableid=x")
			// set to override given the relevant conditions. If the user is
			// requesting the stable revision ("&stableid=x"), defer to override
			// behavior below, since it is the same as ("&stable=1").
			if ( $old ) {
				# Tell MW that parser output is done by setting $outputDone
				$outputDone = $this->showOldReviewedVersion( $frev, $tag, $prot );
				$useParserCache = false;
				// Stable version requested by ID or relevant conditions met to
				// to override page view with the stable version.
			} elseif ( $stable || $this->showingStable() ) {
				# Tell MW that parser output is done by setting $outputDone
				$outputDone = $this->showStableVersion( $srev, $tag, $prot );
				$useParserCache = false;
				// Looking at some specific old revision (&oldid=x) or if FlaggedRevs is not
				// set to override given the relevant conditions (like &stable=0).
			} else {
				$this->showDraftVersion( $srev, $tag, $prot );
			}
		} else {
			// Looking at a page with no stable version; add "no reviewed version" tag.
			$this->showUnreviewedPage( $tag, $prot );
		}

		return true;
	}

	/**
	 * @param string &$tag review box/bar info
	 * @param string $prot protection notice
	 * Tag output function must be called by caller
	 */
	protected function showUnreviewedPage( &$tag, $prot ) {
		if ( $this->out->isPrintable() ) {
			// all this function does is add notices; don't show them
			return;
		}
		$icon = FlaggedRevsXML::draftStatusIcon();
		// Simple icon-based UI
		if ( $this->useSimpleUI() ) {
			$tag .= $prot . $icon . $this->msg( 'revreview-quick-none' )->parse();
			// Standard UI
		} else {
			$tag .= $prot . $icon . $this->msg( 'revreview-noflagged' )->parse();
		}
	}

	/**
	 * Tag output function must be called by caller
	 * Parser cache control deferred to caller
	 * @param FlaggedRevision $srev stable version
	 * @param string &$tag review box/bar info
	 * @param string $prot protection notice icon
	 * @return void
	 */
	protected function showDraftVersion( FlaggedRevision $srev, &$tag, $prot ) {
		$request = $this->getRequest();
		$reqUser = $this->getUser();
		$this->load();
		if ( $this->out->isPrintable() ) {
			// all this function does is add notices; don't show them
			return;
		}
		$flags = $srev->getTags();
		$time = $this->getLanguage()->date( $srev->getTimestamp(), true );
		# Get quality level
		$quality = FlaggedRevs::isQuality( $flags );
		# Get stable version sync status
		$synced = $this->article->stableVersionIsSynced();
		if ( $synced ) {
			// draft == stable
			// no diff to show
			$diffToggle = '';
		} else {
			// draft != stable
			# The user may want the diff (via prefs)
			$diffToggle = $this->getTopDiffToggle( $srev, $quality );
			if ( $diffToggle != '' ) {
				$diffToggle = " $diffToggle";
			}
			# Make sure there is always a notice bar when viewing the draft.
			if ( $this->useSimpleUI() ) {
				// we already one for detailed UI
				$this->setPendingNotice( $srev, $diffToggle );
			}
		}
		$allowedReview = MediaWikiServices::getInstance()->getPermissionManager()->userHasRight(
			$reqUser,
			'review'
		);
		# Give a "your edit is pending" notice to newer users if
		# an unreviewed edit was completed...
		if ( $request->getVal( 'shownotice' )
			&& $this->article->getUserText( Revision::RAW ) == $reqUser->getName()
			&& $this->article->revsArePending() && !$allowedReview ) {
			$revsSince = $this->article->getPendingRevCount();
			$pending = $prot;
			if ( $this->showRatingIcon() ) {
				$pending .= FlaggedRevsXML::draftStatusIcon();
			}
			$pending .= $this->msg( 'revreview-edited', $srev->getRevId(), $revsSince )->parse();
			$anchor = $request->getVal( 'fromsection' );
			if ( $anchor != null ) {
				// Hack: reverse some of the Sanitizer::escapeId() encoding
				$section = urldecode( str_replace(
					// bug 35661
					[ ':', '.' ],
					[ '%3A', '%' ],
					$anchor
				) );
				// prettify
				$section = str_replace( '_', ' ', $section );
				$pending .= $this->msg( 'revreview-edited-section', $anchor, $section )->parseAsBlock();
			}
			# Notice should always use subtitle
			$this->reviewNotice = "<div id='mw-fr-reviewnotice' " .
					"class='flaggedrevs_preview plainlinks noprint'>$pending</div>";
			# Otherwise, construct some tagging info for non-printable outputs.
			# Also, if low profile UI is enabled and the page is synced, skip the tag.
			# Note: the "your edit is pending" notice has all this info, so we never add both.
		} elseif ( !( $this->article->lowProfileUI() && $synced ) ) {
			$revsSince = $this->article->getPendingRevCount();
			// Simple icon-based UI
			if ( $this->useSimpleUI() ) {
				if ( !$reqUser->getId() ) {
					// Anons just see simple icons
					$msgHTML = '';
				} elseif ( $synced ) {
					$msg = $quality ? 'revreview-quick-quality-same' : 'revreview-quick-basic-same';
					$msgHTML = $this->msg( $msg, $srev->getRevId(), $revsSince )->parse();
				} else {
					$msg = $quality ? 'revreview-quick-see-quality' : 'revreview-quick-see-basic';
					$msgHTML = $this->msg( $msg, $srev->getRevId(), $revsSince )->parse();
				}
				$icon = '';
				# For protection based configs, show lock only if it's not redundant.
				if ( $this->showRatingIcon() ) {
					$icon = $synced ? FlaggedRevsXML::stableStatusIcon( $quality ) : FlaggedRevsXML::draftStatusIcon();
				}
				$msgHTML = $prot . $icon . $msgHTML;
				$tag .= FlaggedRevsXML::prettyRatingBox( $srev, $msgHTML, $revsSince, 'draft', $synced, false );
				// Standard UI
			} else {
				if ( $synced ) {
					if ( $quality ) {
						$msg = 'revreview-quality-same';
					} else {
						$msg = 'revreview-basic-same';
					}
					$msgHTML = $this->msg( $msg, $srev->getRevId(), $time, $revsSince )->parse();
				} else {
					$msg = $quality ? 'revreview-newest-quality' : 'revreview-newest-basic';
					$msg .= ( $revsSince == 0 ) ? '-i' : '';
					$msgHTML = $this->msg( $msg, $srev->getRevId(), $time, $revsSince )->parse();
				}
				$icon = $synced ? FlaggedRevsXML::stableStatusIcon( $quality ) : FlaggedRevsXML::draftStatusIcon();
				$tag .= $prot . $icon . $msgHTML . $diffToggle;
			}
		}
	}

	/**
	 * Tag output function must be called by caller
	 * Parser cache control deferred to caller
	 * @param FlaggedRevision $frev selected flagged revision
	 * @param string &$tag review box/bar info
	 * @param string $prot protection notice icon
	 * @return ParserOutput
	 */
	protected function showOldReviewedVersion( FlaggedRevision $frev, &$tag, $prot ) {
		$reqUser = $this->getUser();
		$this->load();
		$flags = $frev->getTags();
		$time = $this->getLanguage()->date( $frev->getTimestamp(), true );
		# Set display revision ID
		$this->out->setRevisionId( $frev->getRevId() );
		# Get quality level
		$quality = FlaggedRevs::isQuality( $flags );

		# Construct some tagging for non-printable outputs. Note that the pending
		# notice has all this info already, so don't do this if we added that already.
		if ( !$this->out->isPrintable() ) {
			// Simple icon-based UI
			if ( $this->useSimpleUI() ) {
				$icon = '';
				# For protection based configs, show lock only if it's not redundant.
				if ( $this->showRatingIcon() ) {
					$icon = FlaggedRevsXML::stableStatusIcon( $quality );
				}
				$revsSince = $this->article->getPendingRevCount();
				if ( !$reqUser->getId() ) {
					// Anons just see simple icons
					$msgHTML = '';
				} else {
					$msg = $quality ? 'revreview-quick-quality-old' : 'revreview-quick-basic-old';
					$msgHTML = $this->msg( $msg, $frev->getRevId(), $revsSince )->parse();
				}
				$msgHTML = $prot . $icon . $msgHTML;
				$tag = FlaggedRevsXML::prettyRatingBox(
					$frev,
					$msgHTML,
					$revsSince,
					'oldstable',
					/* synced */
					false
				);
				// Standard UI
			} else {
				$icon = FlaggedRevsXML::stableStatusIcon( $quality );
				$msg = $quality ? 'revreview-quality-old' : 'revreview-basic-old';
				$tag = $prot . $icon;
				$tag .= $this->msg( $msg, $frev->getRevId(), $time )->parse();
				# Hide clutter
				if ( !empty( $flags ) ) {
					$tag .= FlaggedRevsXML::ratingToggle();
					$tag .= "<div id='mw-fr-revisiondetails'>" .
							$this->msg( 'revreview-oldrating' )->escaped() .
							FlaggedRevsXML::addTagRatings( $flags ) . '</div>';
				}
			}
		}

		$text = $frev->getRevText();
		# Get the new stable parser output...
		$pOpts = $this->article->makeParserOptions( $reqUser );
		$parserOut = FlaggedRevs::parseStableRevision( $frev, $pOpts );

		# Parse and output HTML
		$redirHtml = $this->getRedirectHtml( $text );
		if ( $redirHtml == '' ) {
			// page is not a redirect...
			# Add the stable output to the page view
			$this->out->addParserOutput( $parserOut );
		} else {
			// page is a redirect...
			$this->out->addHtml( $redirHtml );
			# Add output to set categories, displaytitle, etc.
			$this->out->addParserOutputNoText( $parserOut );
		}

		return $parserOut;
	}

	/**
	 * Tag output function must be called by caller
	 * Parser cache control deferred to caller
	 * @param \FlaggedRevision|\stable $srev stable version
	 * @param string &$tag review box/bar info
	 * @param string $prot protection notice
	 * @return ParserOutput
	 */
	protected function showStableVersion( FlaggedRevision $srev, &$tag, $prot ) {
		$reqUser = $this->getUser();
		$this->load();
		$flags = $srev->getTags();
		$time = $this->getLanguage()->date( $srev->getTimestamp(), true );
		# Set display revision ID
		$this->out->setRevisionId( $srev->getRevId() );
		# Get quality level
		$quality = FlaggedRevs::isQuality( $flags );

		$synced = $this->article->stableVersionIsSynced();
		# Construct some tagging
		if ( !$this->out->isPrintable() && !( $this->article->lowProfileUI() && $synced ) ) {
			$revsSince = $this->article->getPendingRevCount();
			// Simple icon-based UI
			if ( $this->useSimpleUI() ) {
				$icon = '';
				# For protection based configs, show lock only if it's not redundant.
				if ( $this->showRatingIcon() ) {
					$icon = FlaggedRevsXML::stableStatusIcon( $quality );
				}
				if ( !$reqUser->getId() ) {
					// Anons just see simple icons
					$msgHTML = '';
				} else {
					$msg = $quality ? 'revreview-quick-quality' : 'revreview-quick-basic';
					# Uses messages 'revreview-quick-quality-same', 'revreview-quick-basic-same'
					$msg = $synced ? "{$msg}-same" : $msg;
					$msgHTML = $this->msg( $msg, $srev->getRevId(), $revsSince )->parse();
				}
				$msgHTML = $prot . $icon . $msgHTML;
				$tag = FlaggedRevsXML::prettyRatingBox( $srev, $msgHTML, $revsSince, 'stable', $synced );
				// Standard UI
			} else {
				$icon = FlaggedRevsXML::stableStatusIcon( $quality );
				$msg = $quality ? 'revreview-quality' : 'revreview-basic';
				if ( $synced ) {
					# uses messages 'revreview-quality-same', 'revreview-basic-same'
					$msg .= '-same';
				} elseif ( $revsSince == 0 ) {
					# uses messages 'revreview-quality-i', 'revreview-basic-i'
					$msg .= '-i';
				}
				$tag = $prot . $icon;
				$tag .= $this->msg( $msg, $srev->getRevId(), $time, $revsSince )->parse();
				if ( !empty( $flags ) ) {
					$tag .= FlaggedRevsXML::ratingToggle();
					$tag .= "<div id='mw-fr-revisiondetails'>" .
							FlaggedRevsXML::addTagRatings( $flags ) . '</div>';
				}
			}
		}

		# Get parsed stable version and output HTML
		$pOpts = $this->article->makeParserOptions( $reqUser );
		$parserCache = FRParserCacheStable::singleton();
		$parserOut = $parserCache->get( $this->article, $pOpts );

		# Do not use the parser cache if it lacks mImageTimeKeys and there is a
		# chance that a review form will be added to this page (which requires the versions).
		$canReview = $this->article->getTitle()->userCan( 'review' );
		if ( $parserOut ) {
			# Cache hit. Note that redirects are not cached.
			$this->out->addParserOutput( $parserOut );
		} else {
			$text = $srev->getRevText();
			# Get the new stable parser output...
			$parserOut = FlaggedRevs::parseStableRevision( $srev, $pOpts );

			$redirHtml = $this->getRedirectHtml( $text );
			if ( $redirHtml == '' ) {
				// page is not a redirect...
				# Update the stable version cache
				$parserCache->save( $parserOut, $this->article, $pOpts );
				# Add the stable output to the page view
				$this->out->addParserOutput( $parserOut );
			} else {
				// page is a redirect...
				$this->out->addHtml( $redirHtml );
				# Add output to set categories, displaytitle, etc.
				$this->out->addParserOutputNoText( $parserOut );
			}
			# Update the stable version dependancies
			FlaggedRevs::updateStableOnlyDeps( $this->article, $parserOut );
		}

		# Update page sync status for tracking purposes.
		# NOTE: avoids master hits and doesn't have to be perfect for what it does
		if ( $this->article->syncedInTracking() != $synced ) {
			$loadBalancer = MediaWikiServices::getInstance()->getDBLoadBalancer();
			if ( $loadBalancer->safeGetLag( $loadBalancer->getConnectionRef( DB_REPLICA ) ) <= 5 ) {
				// avoid write-delay cycles
				$this->article->updateSyncStatus( $synced );
			}
		}

		return $parserOut;
	}

	/**
	 * Get fancy redirect arrow and link HTML
	 * @param string $text
	 * @return string
	 */
	protected function getRedirectHtml( $text ) {
		$title = $this->getTitle();
		$oContent = ContentHandler::makeContent( $text, $title );
		$rTargets = $oContent->getRedirectChain();
		if ( $rTargets ) {
			$article = new Article( $this->article->getTitle() );
			return $article->viewRedirect( $rTargets );
		}
		return '';
	}

	/**
	 * Show icons for draft/stable/old reviewed versions
	 * @return bool
	 */
	protected function showRatingIcon() {
		if ( FlaggedRevs::useOnlyIfProtected() ) {
			// If there is only one quality level and we have tabs to know
			// which version we are looking at, then just use the lock icon...
			return FlaggedRevs::qualityVersions();
		}
		return true;
	}

	/**
	 * Get collapsible diff-to-stable html to add to the review notice as needed
	 * @param FlaggedRevision $srev stable version
	 * @param bool $quality revision is quality
	 * @return string the html line (either "" or "<diff toggle><diff div>")
	 */
	protected function getTopDiffToggle( FlaggedRevision $srev, $quality ) {
		$reqUser = $this->getUser();
		$this->load();
		$userOptionsLookup = MediaWikiServices::getInstance()->getUserOptionsLookup();
		if ( !$userOptionsLookup->getBoolOption( $reqUser, 'flaggedrevsviewdiffs' ) ) {
			// nothing to do here
			return false;
		}
		# Diff should only show for the draft
		$oldid = $this->getOldIDFromRequest();
		$latest = $this->article->getLatest();
		if ( $oldid && $oldid != $latest ) {
			// not viewing the draft
			return false;
		}
		$revsSince = $this->article->getPendingRevCount();
		if ( !$revsSince ) {
			// no pending changes
			return false;
		}
		// convenience
		$title = $this->article->getTitle();
		# Review status of left diff revision...
		$leftNote = $quality ? 'revreview-hist-quality' : 'revreview-hist-basic';
		$lClass = FlaggedRevsXML::getQualityColor( (int)$quality );
		// @todo FIXME: i18n Hard coded brackets.
		$leftNote = "<span class='$lClass'>[" . $this->msg( $leftNote )->escaped() . "]</span>";
		# Review status of right diff revision...
		$rClass = FlaggedRevsXML::getQualityColor( false );
		// @todo FIXME: i18n Hard coded brackets.
		$rightNote = "<span class='$rClass'>[" .
				$this->msg( 'revreview-hist-pending' )->escaped() . "]</span>";
		# Get the actual body of the diff...
		$diffEngine = new DifferenceEngine( $title, $srev->getRevId(), $latest );
		$diffBody = $diffEngine->getDiffBody();
		if ( strlen( $diffBody ) > 0 ) {
			// full diff-to-stable, no need for query
			$nEdits = $revsSince - 1;
			if ( $nEdits ) {
				$limit = 100;
				$nUsers = $title->countAuthorsBetween( $srev->getRevId(), $latest, $limit );
				$multiNotice = DifferenceEngine::intermediateEditsMsg( $nEdits, $nUsers, $limit );
			} else {
				$multiNotice = '';
			}
			// add CSS
			$diffEngine->showDiffStyle();
			// alter default review form tags
			$this->isDiffFromStable = true;
			return FlaggedRevsXML::diffToggle() .
					"<div id='mw-fr-stablediff'>\n" .
					$this->getFormattedDiff( $diffBody, $multiNotice, $leftNote, $rightNote ) .
					"</div>\n";
		}
		return '';
	}

	/**
	 * $n number of in-between revs
	 * @param string $diffBody
	 * @param string $multiNotice
	 * @param string $leftStatus
	 * @param string $rightStatus
	 * @return string
	 */
	protected function getFormattedDiff(
	$diffBody, $multiNotice, $leftStatus, $rightStatus
	) {
		$tableClass = 'diff diff-contentalign-' .
				htmlspecialchars( $this->getTitle()->getPageLanguage()->alignStart() );
		if ( $multiNotice != '' ) {
			$multiNotice = "<tr><td colspan='4' align='center' class='diff-multi'>" .
					$multiNotice . "</td></tr>";
		}
		return "<table border='0' width='98%' cellpadding='0' cellspacing='4' class='$tableClass'>" .
				"<col class='diff-marker' />" .
				"<col class='diff-content' />" .
				"<col class='diff-marker' />" .
				"<col class='diff-content' />" .
				"<tr>" .
				"<td colspan='2' width='50%' align='center' class='diff-otitle'><b>" .
				$leftStatus . "</b></td>" .
				"<td colspan='2' width='50%' align='center' class='diff-ntitle'><b>" .
				$rightStatus . "</b></td>" .
				"</tr>" .
				$multiNotice .
				$diffBody .
				"</table>";
	}

	/**
	 *
	 * @return int
	 */
	protected function getOldIDFromRequest() {
		$article = new Article( $this->article->getTitle() );
		return $article->getOldIDFromRequest();
	}

	/**
	 * Adds a notice saying that this revision is pending review
	 * @param FlaggedRevision $srev The stable version
	 * @param string $diffToggle either "" or " <diff toggle><diff div>"
	 * @return void
	 */
	public function setPendingNotice( FlaggedRevision $srev, $diffToggle = '' ) {
		$this->load();
		$time = $this->getLanguage()->date( $srev->getTimestamp(), true );
		$revsSince = $this->article->getPendingRevCount();
		$msg = $srev->getQuality() ? 'revreview-newest-quality' : 'revreview-newest-basic';
		$msg .= ( $revsSince == 0 ) ? '-i' : '';
		# Add bar msg to the top of the page...
		$css = 'flaggedrevs_preview plainlinks';
		$msgHTML = $this->msg( $msg, $srev->getRevId(), $time, $revsSince )->parse();
		$this->reviewNotice .= "<div id='mw-fr-reviewnotice' class='$css'>" .
				"$msgHTML$diffToggle</div>";
	}

	/**
	 * Is a diff from $oldRev to $newRev a diff-to-stable?
	 * @param FlaggedRevision $srev
	 * @param Revision $oldRev
	 * @param Revision $newRev
	 * @return bool
	 */
	protected static function isDiffToStable( $srev, $oldRev, $newRev ) {
		return (
			// no multipage diffs
			$srev && $oldRev && $newRev && $oldRev->getPage() == $newRev->getPage()
			&& $oldRev->getId() == $srev->getRevId()
			// no backwards diffs
			&& $newRev->getTimestamp() >= $oldRev->getTimestamp()
		);
	}

}
