<?php

namespace BlueSpice\FlaggedRevsConnector\Data;

class Record extends \MWStake\MediaWiki\Component\DataStore\Record {
	public const PAGE_ID = 'page_id';
	public const PAGE_NAMESPACE = 'page_namespace';
	public const PAGE_TITLE = 'page_title';
	public const PAGE_LINK = 'page_link';
	public const REVISION_STATE = 'revision_state';
	public const REVISION_STATE_RAW = 'revision_state_raw';
	public const REVISIONS_SINCE_STABLE = 'revs_since_stable';
	public const PAGE_CATEGORIES = 'page_categories';
	public const PAGE_CATEGORIES_LINKS = 'page_categories_links';
}
