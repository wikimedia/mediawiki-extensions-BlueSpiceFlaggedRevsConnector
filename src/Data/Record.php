<?php

namespace BlueSpice\FlaggedRevsConnector\Data;

class Record extends \BlueSpice\Data\Record {
	const PAGE_ID = 'page_id';
	const PAGE_NAMESPACE = 'page_namespace';
	const PAGE_TITLE = 'page_title';
	const PAGE_LINK = 'page_link';
	const REVISION_STATE = 'revision_state';
	const REVISION_STATE_RAW = 'revision_state_raw';
	const REVISIONS_SINCE_STABLE = 'revs_since_stable';
	const PAGE_CATEGORIES = 'page_categories';
	const PAGE_CATEGORIES_LINKS = 'page_categories_links';
}
